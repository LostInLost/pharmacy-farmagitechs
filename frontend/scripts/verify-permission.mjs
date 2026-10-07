/**
 * Verifikasi tampilan izin di browser: baris yang `can_update = false` harus
 * menyembunyikan aksi Ubah (sel aksi kosong, tanpa badge apa pun), dan
 * membuka form editnya harus diblokir dengan pesan + tautan kembali (bukan
 * form yang bisa disimpan).
 *
 * Pemakaian:
 *   node scripts/verify-permission.mjs <chromePath> <sessionCookie> <ownId> <otherId>
 */
const [, , chromePath, sessionCookie, ownId, otherId] = process.argv

const APP = "http://localhost:4321"
const CDP_PORT = 9337

import { spawn } from "node:child_process"
import { mkdtempSync } from "node:fs"
import { tmpdir } from "node:os"
import { join } from "node:path"

const profile = mkdtempSync(join(tmpdir(), "cdp-perm-"))

const chrome = spawn(
  chromePath,
  [
    "--headless=new",
    "--disable-gpu",
    "--no-first-run",
    "--no-default-browser-check",
    `--remote-debugging-port=${CDP_PORT}`,
    `--user-data-dir=${profile}`,
    "about:blank",
  ],
  { stdio: "ignore" }
)

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms))

async function cdpUrl() {
  for (let attempt = 0; attempt < 40; attempt += 1) {
    try {
      const response = await fetch(`http://127.0.0.1:${CDP_PORT}/json/list`)
      const targets = await response.json()
      const page = targets.find((target) => target.type === "page")

      if (page?.webSocketDebuggerUrl) return page.webSocketDebuggerUrl
    } catch {
      // belum siap
    }

    await sleep(250)
  }

  throw new Error("Chrome DevTools tidak siap")
}

function connect(url) {
  const socket = new WebSocket(url)
  const pending = new Map()
  let nextId = 1

  const ready = new Promise((resolve, reject) => {
    socket.addEventListener("open", () => resolve())
    socket.addEventListener("error", () => reject(new Error("WebSocket gagal")))
  })

  socket.addEventListener("message", (event) => {
    const message = JSON.parse(event.data)

    if (message.id === undefined) return

    const entry = pending.get(message.id)

    if (entry) {
      pending.delete(message.id)
      entry(message)
    }
  })

  const send = (method, params = {}) =>
    new Promise((resolve, reject) => {
      const id = nextId
      nextId += 1
      pending.set(id, (message) =>
        message.error
          ? reject(new Error(`${method}: ${message.error.message}`))
          : resolve(message.result)
      )
      socket.send(JSON.stringify({ id, method, params }))
    })

  return { ready, send, close: () => socket.close() }
}

const report = { ownId: Number(ownId), otherId: Number(otherId) }

try {
  const client = connect(await cdpUrl())
  await client.ready

  await client.send("Network.enable")
  await client.send("Page.enable")
  await client.send("Runtime.enable")
  await client.send("Network.setCookie", {
    name: "ci_session",
    value: sessionCookie,
    domain: "localhost",
    path: "/",
    httpOnly: true,
  })

  const evaluate = async (expression) => {
    const { result } = await client.send("Runtime.evaluate", {
      expression,
      returnByValue: true,
      awaitPromise: true,
    })

    return result.value
  }

  // Daftar: baris milik orang lain harus tanpa tombol Ubah dan tanpa badge.
  await client.send("Page.navigate", { url: `${APP}/receptions` })
  await sleep(5500)

  report.list = JSON.parse(
    await evaluate(`
      (() => {
        const actionCellText = (id) => {
          const link = [...document.querySelectorAll('a')]
            .find((anchor) => anchor.getAttribute('href') === '/receptions/' + id + '/edit');
          const row = link && link.closest('tr');
          return row ? row.lastElementChild.textContent.trim() : null;
        };

        return JSON.stringify({
          ubahButtons: [...document.querySelectorAll('a')]
            .filter((anchor) => anchor.textContent.trim() === 'Ubah')
            .map((anchor) => anchor.getAttribute('href')),
          ownActionCell: actionCellText(${ownId}),
          otherActionCell: actionCellText(${otherId}),
          notAllowedText: document.body.innerText.includes('tidak berhak'),
        });
      })()
    `)
  )

  // Form edit milik orang lain: diblokir, tanpa form yang bisa disimpan.
  await client.send("Page.navigate", { url: `${APP}/receptions/${otherId}/edit` })
  await sleep(5000)

  report.otherForm = JSON.parse(
    await evaluate(`
      JSON.stringify({
        blockedMessage: document.body.innerText.includes('Anda tidak berhak mengubah penerimaan ini.'),
        hasSubmit: [...document.querySelectorAll('button[type="submit"]')]
          .some((button) => button.textContent.includes('Simpan')),
        backLink: document.body.innerText.includes('Kembali ke daftar'),
      })
    `)
  )

  // Form edit milik sendiri: form lengkap tersedia.
  await client.send("Page.navigate", { url: `${APP}/receptions/${ownId}/edit` })
  await sleep(5000)

  report.ownForm = JSON.parse(
    await evaluate(`
      JSON.stringify({
        hasSubmit: [...document.querySelectorAll('button[type="submit"]')]
          .some((button) => button.textContent.includes('Simpan')),
        itemRows: [...document.querySelectorAll('tbody tr')]
          .filter((row) => row.querySelector('input[type="date"]')).length,
      })
    `)
  )

  client.close()
} finally {
  chrome.kill()
}

// Petugas hanya berhak atas penerimaan buatannya sendiri: tidak boleh ada
// tombol "Ubah" yang menunjuk ke dokumen milik orang lain, dan baris itu
// tampil tanpa aksi apa pun (tanpa badge "tidak berhak").
report.pass =
  report.list.ubahButtons.includes(`/receptions/${ownId}/edit`) &&
  !report.list.ubahButtons.includes(`/receptions/${otherId}/edit`) &&
  report.list.ownActionCell === 'Ubah' &&
  report.list.otherActionCell === '' &&
  !report.list.notAllowedText &&
  report.otherForm.blockedMessage &&
  !report.otherForm.hasSubmit &&
  report.ownForm.hasSubmit &&
  report.ownForm.itemRows >= 1

console.log(JSON.stringify(report, null, 2))

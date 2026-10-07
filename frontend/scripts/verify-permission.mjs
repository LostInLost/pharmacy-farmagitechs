/**
 * Verifikasi tampilan izin di browser (akun petugas): baris yang
 * `can_update = false` menyembunyikan tombol Ubah, detailnya tetap bisa
 * dibuka lewat sheet lihat (`?view=<id>`) dengan catatan "hanya melihat",
 * dan membuka sheet ubahnya (`?edit=<id>`) diblokir dengan pesan + tombol
 * pindah ke mode lihat.
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

  // Daftar: baris milik orang lain tanpa tombol Ubah; tombol header
  // "Tambah Penerimaan" tetap ada karena petugas punya receipt.create.
  await client.send("Page.navigate", { url: `${APP}/receptions` })
  await sleep(5500)

  report.list = JSON.parse(
    await evaluate(`
      (() => {
        const rowFor = (id) => {
          const link = [...document.querySelectorAll('a')]
            .find((anchor) => anchor.getAttribute('href') === '/receptions?view=' + id);
          return link && link.closest('tr');
        };

        const rowHasUbah = (id) => {
          const row = rowFor(id);
          return row === null
            ? null
            : [...row.querySelectorAll('button')].some((button) => button.textContent.trim() === 'Ubah');
        };

        return JSON.stringify({
          ownRowUbah: rowHasUbah(${ownId}),
          otherRowUbah: rowHasUbah(${otherId}),
          viewLinks: [...document.querySelectorAll('a')]
            .map((anchor) => anchor.getAttribute('href'))
            .filter((href) => href && href.startsWith('/receptions?view=')),
          addButton: [...document.querySelectorAll('button')]
            .some((button) => button.textContent.includes('Tambah Penerimaan')),
          notAllowedText: document.body.innerText.includes('tidak berhak'),
        });
      })()
    `)
  )

  // Detail milik orang lain: sheet lihat terbuka, tanpa tombol Ubah di dalam
  // sheet (tombol di baris tabel tidak dihitung — itu milik baris lain).
  await client.send("Page.navigate", { url: `${APP}/receptions?view=${otherId}` })
  await sleep(5500)

  report.otherView = JSON.parse(
    await evaluate(`
      (() => {
        const sheet = document.querySelector('[data-slot="sheet-content"]');

        return JSON.stringify({
          sheetOpen: sheet !== null,
          onlyReadNote: document.body.innerText.includes('Anda hanya dapat melihat penerimaan ini.'),
          ubahButtonInSheet: sheet === null
            ? null
            : [...sheet.querySelectorAll('button')].some((button) => button.textContent.trim() === 'Ubah'),
          auditVisible: document.body.innerText.includes('Riwayat Aksi'),
        });
      })()
    `)
  )

  // Sheet ubah milik orang lain: diblokir, tanpa form yang bisa disimpan,
  // tetapi ada jalan ke mode lihat.
  await client.send("Page.navigate", { url: `${APP}/receptions?edit=${otherId}` })
  await sleep(5500)

  report.otherForm = JSON.parse(
    await evaluate(`
      JSON.stringify({
        blockedMessage: document.body.innerText.includes('Anda tidak berhak mengubah penerimaan ini.'),
        hasSubmit: [...document.querySelectorAll('button[type="submit"]')]
          .some((button) => button.textContent.includes('Simpan')),
        viewDetailButton: [...document.querySelectorAll('button')]
          .some((button) => button.textContent.trim() === 'Lihat detail'),
      })
    `)
  )

  // Sheet ubah milik sendiri: form lengkap tersedia.
  await client.send("Page.navigate", { url: `${APP}/receptions?edit=${ownId}` })
  await sleep(5500)

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

// Petugas hanya berhak mengubah penerimaan buatannya sendiri: baris miliknya
// punya tombol Ubah, baris orang lain tanpa aksi apa pun, detail orang lain
// tetap bisa dilihat (tanpa tombol Ubah di dalam sheet), dan sheet ubahnya
// diblokir dengan jalan pintas ke mode lihat.
report.pass =
  report.list.ownRowUbah === true &&
  report.list.otherRowUbah === false &&
  report.list.addButton &&
  report.list.viewLinks.includes(`/receptions?view=${ownId}`) &&
  report.list.viewLinks.includes(`/receptions?view=${otherId}`) &&
  !report.list.notAllowedText &&
  report.otherView.sheetOpen &&
  report.otherView.onlyReadNote &&
  report.otherView.ubahButtonInSheet === false &&
  !report.otherView.auditVisible &&
  report.otherForm.blockedMessage &&
  !report.otherForm.hasSubmit &&
  report.otherForm.viewDetailButton &&
  report.ownForm.hasSubmit &&
  report.ownForm.itemRows >= 1

console.log(JSON.stringify(report, null, 2))

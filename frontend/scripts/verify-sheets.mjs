/**
 * Verifikasi alur sheet di halaman penerimaan (akun supervisor):
 *
 * 1. Tombol header "Tambah Penerimaan" membuka sheet dan URL menjadi `?new=1`.
 * 2. Deep link `?view=<id>` SSR langsung membuka sheet detail (tanpa riwayat
 *    aksi, dengan tombol Ubah).
 * 3. Tombol "Ubah" di dalam sheet detail berpindah ke sheet ubah (`?edit=<id>`)
 *    dan formnya terisi.
 * 4. Sheet cukup lebar untuk tabel item (bukan `sm:max-w-sm` bawaan).
 * 5. Menutup sheet membersihkan query string.
 *
 * Pemakaian:
 *   node scripts/verify-sheets.mjs <chromePath> <sessionCookie> <receptionId>
 */
const [, , chromePath, sessionCookie, receptionId] = process.argv

const APP = "http://localhost:4321"
const CDP_PORT = 9338

import { spawn } from "node:child_process"
import { mkdtempSync } from "node:fs"
import { tmpdir } from "node:os"
import { join } from "node:path"

const profile = mkdtempSync(join(tmpdir(), "cdp-sheets-"))

const chrome = spawn(
  chromePath,
  [
    "--headless=new",
    "--disable-gpu",
    "--no-first-run",
    "--no-default-browser-check",
    `--remote-debugging-port=${CDP_PORT}`,
    `--user-data-dir=${profile}`,
    "--window-size=1280,900",
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
  const listeners = []
  let nextId = 1

  const ready = new Promise((resolve, reject) => {
    socket.addEventListener("open", () => resolve())
    socket.addEventListener("error", () => reject(new Error("WebSocket gagal")))
  })

  socket.addEventListener("message", (event) => {
    const message = JSON.parse(event.data)

    if (message.id === undefined) {
      for (const listener of listeners) listener(message)

      return
    }

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

  return {
    ready,
    send,
    onEvent: (listener) => listeners.push(listener),
    close: () => socket.close(),
  }
}

const report = { receptionId: Number(receptionId) }
const consoleErrors = []

try {
  const client = connect(await cdpUrl())
  await client.ready

  await client.send("Network.enable")
  await client.send("Page.enable")
  await client.send("Runtime.enable")

  client.onEvent((message) => {
    if (message.method === "Runtime.exceptionThrown") {
      consoleErrors.push(
        message.params?.exceptionDetails?.exception?.description ??
          message.params?.exceptionDetails?.text ??
          "exception"
      )
    }
  })

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

  // 1. Tombol header membuka sheet tambah dan menyinkronkan URL.
  await client.send("Page.navigate", { url: `${APP}/receptions` })
  await sleep(6000)

  report.create = JSON.parse(
    await evaluate(`
      (async function () {
        const button = [...document.querySelectorAll('button')]
          .find((candidate) => candidate.textContent.includes('Tambah Penerimaan'))

        if (!button) return JSON.stringify({ buttonFound: false })

        button.click()
        await new Promise((resolve) => setTimeout(resolve, 900))

        return JSON.stringify({
          buttonFound: true,
          sheetOpen: document.querySelector('[data-slot="sheet-content"]') !== null,
          title: document.body.innerText.includes('Penerimaan Baru'),
          url: location.pathname + location.search,
          width: Math.round(
            document.querySelector('[data-slot="sheet-content"]')?.getBoundingClientRect().width ?? 0
          ),
        })
      })()
    `)
  )

  // 2. Tutup sheet: URL kembali bersih.
  report.closed = JSON.parse(
    await evaluate(`
      (async function () {
        const close = document.querySelector('[data-slot="sheet-content"] [data-slot="sheet-close"]')
          ?? [...document.querySelectorAll('button')]
            .find((candidate) => candidate.textContent.trim() === 'Batal')

        if (!close) return JSON.stringify({ closeFound: false })

        close.click()
        await new Promise((resolve) => setTimeout(resolve, 900))

        return JSON.stringify({
          closeFound: true,
          sheetClosed: document.querySelector('[data-slot="sheet-content"]') === null,
          url: location.pathname + location.search,
        })
      })()
    `)
  )

  // 3. Deep link detail: SSR langsung membuka sheet, tanpa riwayat aksi,
  //    dengan tombol Ubah (supervisor punya receipt.update-any).
  await client.send("Page.navigate", {
    url: `${APP}/receptions?view=${receptionId}`,
  })
  await sleep(6000)

  report.view = JSON.parse(
    await evaluate(`
      JSON.stringify({
        sheetOpen: document.querySelector('[data-slot="sheet-content"]') !== null,
        hasItems: document.querySelectorAll('[data-slot="sheet-content"] tbody tr').length > 0,
        auditVisible: document.body.innerText.includes('Riwayat Aksi'),
        ubahButton: [...document.querySelectorAll('[data-slot="sheet-content"] button')]
          .some((button) => button.textContent.trim() === 'Ubah'),
      })
    `)
  )

  // 4. Pindah ke sheet ubah dari dalam sheet detail.
  report.edit = JSON.parse(
    await evaluate(`
      (async function () {
        const button = [...document.querySelectorAll('[data-slot="sheet-content"] button')]
          .find((candidate) => candidate.textContent.trim() === 'Ubah')

        if (!button) return JSON.stringify({ buttonFound: false })

        button.click()
        await new Promise((resolve) => setTimeout(resolve, 1200))

        return JSON.stringify({
          buttonFound: true,
          title: document.body.innerText.includes('Ubah Penerimaan'),
          referenceFilled: (document.querySelector('#reference_no')?.value ?? '') !== '',
          itemRows: [...document.querySelectorAll('tbody tr')]
            .filter((row) => row.querySelector('input[type="date"]')).length,
          url: location.pathname + location.search,
        })
      })()
    `)
  )

  report.consoleErrors = consoleErrors.slice(0, 5)

  client.close()
} finally {
  chrome.kill()
}

// Lebar sheet harus lega untuk tabel item (viewport 1280 → ±768px), bukan
// `sm:max-w-sm` (384px) bawaan.
report.pass =
  report.create.buttonFound &&
  report.create.sheetOpen &&
  report.create.title &&
  report.create.url === '/receptions?new=1' &&
  report.create.width >= 600 &&
  report.closed.closeFound &&
  report.closed.sheetClosed &&
  report.closed.url === '/receptions' &&
  report.view.sheetOpen &&
  report.view.hasItems &&
  !report.view.auditVisible &&
  report.view.ubahButton &&
  report.edit.buttonFound &&
  report.edit.title &&
  report.edit.referenceFilled &&
  report.edit.itemRows >= 1 &&
  report.edit.url === `/receptions?edit=${receptionId}` &&
  report.consoleErrors.length === 0

console.log(JSON.stringify(report, null, 2))

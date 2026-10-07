/**
 * Verifikasi alur ubah (PUT) dari browser: buka sheet Ubah penerimaan lewat
 * deep link `?edit=<id>`, ubah jumlah item, simpan, lalu pastikan backend
 * menerima perubahannya.
 *
 * PUT belum pernah dipakai frontend sebelum ini, jadi jalur CSRF-nya
 * (bootstrap token -> kirim -> retry sekali bila 403) wajib dibuktikan.
 * Sekaligus memastikan Riwayat Aksi tidak lagi dirender di dalam form.
 *
 * Pemakaian:
 *   node scripts/verify-update.mjs <chromePath> <sessionCookie> <receptionId>
 */
const [, , chromePath, sessionCookie, receptionId] = process.argv

const APP = "http://localhost:4321"
const API = "http://localhost:8080"
const CDP_PORT = 9335
const NEW_QUANTITY = 33

import { spawn, execFileSync } from "node:child_process"
import { mkdtempSync } from "node:fs"
import { tmpdir } from "node:os"
import { join } from "node:path"

const profile = mkdtempSync(join(tmpdir(), "cdp-update-"))

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

const report = { receptionId: Number(receptionId), newQuantity: NEW_QUANTITY }
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

  await client.send("Page.navigate", {
    url: `${APP}/receptions?edit=${receptionId}`,
  })
  await sleep(6000)

  const evaluate = async (expression) => {
    const { result } = await client.send("Runtime.evaluate", {
      expression,
      returnByValue: true,
      awaitPromise: true,
    })

    return result.value
  }

  // Bukti sheet ubah terisi dari detail: header + baris item, dan Riwayat
  // Aksi tidak dirender di mana pun lagi.
  report.loaded = await evaluate(`
    JSON.stringify({
      sheetOpen: document.querySelector('[data-slot="sheet-content"]') !== null,
      title: document.body.innerText.includes('Ubah Penerimaan'),
      reference: document.querySelector('#reference_no')?.value ?? null,
      supplier: document.querySelector('#supplier_id')?.textContent?.trim() ?? null,
      receivedAt: document.querySelector('#received_at')?.value ?? null,
      itemRows: [...document.querySelectorAll('tbody tr')]
        .filter((row) => row.querySelector('input[type="date"]')).length,
      auditVisible: document.body.innerText.includes('Riwayat Aksi'),
    })
  `)

  report.beforeQuantity = await evaluate(`
    (function () {
      const row = [...document.querySelectorAll('tbody tr')]
        .find((candidate) => candidate.querySelector('input[type="number"]'))

      return row ? row.querySelector('input[type="number"]').value : null
    })()
  `)

  // Ubah jumlah lalu simpan. Sheet tertutup setelah sukses (tanpa navigasi).
  report.changed = await evaluate(`
    (function () {
      const row = [...document.querySelectorAll('tbody tr')]
        .find((candidate) => candidate.querySelector('input[type="number"]'))

      if (!row) return 'no-row'

      const qty = row.querySelector('input[type="number"]')
      const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set

      setter.call(qty, ${JSON.stringify(String(NEW_QUANTITY))})
      qty.dispatchEvent(new Event('input', { bubbles: true }))
      qty.dispatchEvent(new Event('change', { bubbles: true }))

      document.querySelector('#reception-form').requestSubmit()

      return 'submitted'
    })()
  `)

  const snapshots = []

  for (const wait of [1500, 1500, 2000, 3000]) {
    await sleep(wait)

    try {
      snapshots.push(
        JSON.parse(
          await evaluate(`
            JSON.stringify({
              path: location.pathname + location.search,
              sheetClosed: document.querySelector('[data-slot="sheet-content"]') === null,
              success: document.body.innerText.includes('Penerimaan diperbarui.'),
              alerts: [...document.querySelectorAll('[role="alert"]')].map((node) => node.innerText),
            })
          `)
        )
      )
    } catch {
      snapshots.push({ navigated: true })
    }
  }

  report.saved = snapshots
  report.finalPath = snapshots[snapshots.length - 1]?.path ?? null
  report.sheetClosed = snapshots[snapshots.length - 1]?.sheetClosed ?? null
  report.consoleErrors = consoleErrors.slice(0, 5)

  client.close()
} finally {
  chrome.kill()
}

// Konfirmasi lewat API: kuantitas item dan jumlah entri audit.
const jar = process.env.VERIFY_JAR

if (jar) {
  const detail = JSON.parse(
    execFileSync("curl.exe", ["-s", "-b", jar, `${API}/api/receipts/${receptionId}`], {
      encoding: "utf8",
    })
  )

  report.apiQuantity = detail.data.items[0]?.quantity ?? null
  report.apiSupplier = detail.data.supplier_name
  report.apiActions = detail.data.logs.map((log) => log.action)
}

console.log(JSON.stringify(report, null, 2))

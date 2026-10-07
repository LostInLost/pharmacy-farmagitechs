/**
 * Verifikasi alur tulis dari browser sungguhan: isi form penerimaan, simpan,
 * lalu pastikan backend benar-benar menerima datanya.
 *
 * Ini menguji jalur yang paling berisiko: bootstrap token CSRF, POST lintas
 * origin dengan cookie sesi, dan validasi Zod sebelum kirim.
 *
 * Pemakaian:
 *   node scripts/verify-write.mjs <chromePath> <sessionCookie>
 */
const [, , chromePath, sessionCookie] = process.argv

const APP = "http://localhost:4321"
const API = "http://localhost:8080"
const CDP_PORT = 9334
const REFERENCE = `BROWSER-E2E-${Date.now()}`

import { spawn } from "node:child_process"
import { mkdtempSync } from "node:fs"
import { tmpdir } from "node:os"
import { join } from "node:path"

const profile = mkdtempSync(join(tmpdir(), "cdp-write-"))

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

const report = { reference: REFERENCE }
const consoleErrors = []

try {
  const client = connect(await cdpUrl())
  await client.ready

  await client.send("Network.enable")
  await client.send("Page.enable")
  await client.send("Runtime.enable")

  // Rekam error runtime agar kegagalan tidak tersembunyi.
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

  await client.send("Page.navigate", { url: `${APP}/receptions/new` })
  await sleep(5000)

  const evaluate = async (expression) => {
    const { result } = await client.send("Runtime.evaluate", {
      expression,
      returnByValue: true,
      awaitPromise: true,
    })

    return result.value
  }

  // Isi header. React mengendalikan input, jadi nilai diset lewat setter
  // native lalu event input dipicu agar state ikut berubah.
  report.filled = await evaluate(`
    (function () {
      const setValue = (element, value) => {
        const proto = element instanceof HTMLInputElement
          ? HTMLInputElement.prototype
          : HTMLSelectElement.prototype
        const setter = Object.getOwnPropertyDescriptor(proto, 'value').set

        setter.call(element, value)
        element.dispatchEvent(new Event('input', { bubbles: true }))
        element.dispatchEvent(new Event('change', { bubbles: true }))
      }

      const reference = document.querySelector('#reference_no')
      const received = document.querySelector('#received_at')

      if (!reference || !received) return 'form-not-found'

      setValue(reference, ${JSON.stringify(REFERENCE)})
      setValue(received, '2026-10-07T11:00')

      return 'ok'
    })()
  `)

  // Pilih pemasok & obat lewat klik Radix Select (bukan elemen native).
  report.supplier = await evaluate(`
    (async function () {
      const trigger = document.querySelector('#supplier_id')
      if (!trigger) return 'no-trigger'

      trigger.click()
      await new Promise((resolve) => setTimeout(resolve, 600))

      const options = [...document.querySelectorAll('[role="option"]')]
      const target = options.find((option) => option.textContent.includes('Farma Nusantara'))
        ?? options[0]

      if (!target) return 'no-option'

      target.click()
      await new Promise((resolve) => setTimeout(resolve, 400))

      return trigger.textContent.trim()
    })()
  `)

  // Isi baris item pertama.
  report.item = await evaluate(`
    (async function () {
      const rows = [...document.querySelectorAll('tbody tr')]
      const row = rows.find((candidate) => candidate.querySelector('input[type="date"]'))
      if (!row) return 'no-row'

      const batch = row.querySelector('input:not([type="date"]):not([type="number"])')
      const date = row.querySelector('input[type="date"]')
      const qty = row.querySelector('input[type="number"]')

      const setValue = (element, value) => {
        const setter = Object.getOwnPropertyDescriptor(
          HTMLInputElement.prototype,
          'value'
        ).set

        setter.call(element, value)
        element.dispatchEvent(new Event('input', { bubbles: true }))
        element.dispatchEvent(new Event('change', { bubbles: true }))
      }

      setValue(batch, 'PCT-E2E-01')
      setValue(date, '2030-05-31')
      setValue(qty, '12')

      const trigger = row.querySelector('button[role="combobox"]')
      if (!trigger) return 'no-medicine-trigger'

      trigger.click()
      await new Promise((resolve) => setTimeout(resolve, 600))

      const options = [...document.querySelectorAll('[role="option"]')]
      const target = options.find((option) => option.textContent.includes('Paracetamol'))
        ?? options[0]

      if (!target) return 'no-medicine-option'

      target.click()
      await new Promise((resolve) => setTimeout(resolve, 400))

      return 'ok'
    })()
  `)

  // Simpan. Pengalihan ke /receptions akan mematikan konteks evaluasi, jadi
  // status dipantau dari sisi Node (bukan di dalam satu evaluate panjang).
  const submit = await evaluate(`
    (function () {
      const form = document.querySelector('form')
      if (!form) return 'no-form'

      form.requestSubmit()

      return 'submitted'
    })()
  `)

  report.submit = submit

  const snapshots = []

  for (const wait of [1500, 1500, 2000, 3000]) {
    await sleep(wait)

    try {
      const snapshot = await evaluate(`
        JSON.stringify({
          path: location.pathname,
          success: document.body.innerText.includes('Tersimpan'),
          alerts: [...document.querySelectorAll('[role="alert"]')].map((node) => node.innerText),
        })
      `)

      snapshots.push(JSON.parse(snapshot))
    } catch {
      // Konteks hilang karena navigasi; baca ulang di percobaan berikutnya.
      snapshots.push({ navigated: true })
    }
  }

  report.saved = snapshots
  report.finalPath = snapshots[snapshots.length - 1]?.path ?? null

  report.consoleErrors = consoleErrors.slice(0, 5)

  client.close()
} finally {
  chrome.kill()
}

// Konfirmasi langsung ke API: sumber kebenaran, bukan tampilan.
const jar = process.env.VERIFY_JAR

if (jar) {
  const { execFileSync } = await import("node:child_process")
  const list = JSON.parse(
    execFileSync("curl.exe", ["-s", "-b", jar, `${API}/api/receipts`], {
      encoding: "utf8",
    })
  )

  const created = list.data.find((row) => row.reference_no === REFERENCE)

  report.apiConfirmed = created
    ? { id: created.id, supplier: created.supplier_name, can_update: created.can_update }
    : null
}

console.log(JSON.stringify(report, null, 2))

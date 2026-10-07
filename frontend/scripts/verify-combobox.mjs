/**
 * Verifikasi combobox pemasok & obat di form penerimaan (Chrome headless via CDP).
 *
 * Yang dibuktikan:
 *  1. Trigger pemasok & obat membuka daftar yang bisa dicari, dan mengetik
 *     menyaring opsi (bukan sekadar menampilkan seluruh daftar).
 *  2. Memilih opsi mengisi trigger dengan labelnya, lalu simpan benar-benar
 *     mengirim id yang dipilih — dikonfirmasi ulang lewat API.
 *
 * Pemakaian:
 *   node scripts/verify-combobox.mjs <chromePath> <sessionCookie>
 *
 * `sessionCookie` diambil dari login backend, mis.:
 *   curl -s -c jar.txt -X POST http://localhost:8080/api/login \
 *     -H "Content-Type: application/json" \
 *     -d '{"username":"supervisor","password":"supervisor123"}'
 */
const [, , chromePath, sessionCookie] = process.argv

const APP = "http://localhost:4321"
const API = "http://localhost:8080"
const CDP_PORT = 9341
const REFERENCE = `COMBOBOX-E2E-${Date.now()}`

import { spawn } from "node:child_process"
import { mkdtempSync } from "node:fs"
import { tmpdir } from "node:os"
import { join } from "node:path"

const profile = mkdtempSync(join(tmpdir(), "cdp-combobox-"))

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

const report = {
  reference: REFERENCE,
  /** Id yang dipilih di combobox; dibandingkan dengan hasil API di akhir. */
  picked: {},
}
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

  await client.send("Page.navigate", { url: `${APP}/receptions?new=1` })
  await sleep(6000)

  const evaluate = async (expression) => {
    const { result } = await client.send("Runtime.evaluate", {
      expression,
      returnByValue: true,
      awaitPromise: true,
    })

    return result.value
  }

  // Isi kolom teks header lewat setter native (React mengendalikan nilainya).
  report.filled = await evaluate(`
    (function () {
      const setValue = (element, value) => {
        const setter = Object.getOwnPropertyDescriptor(
          HTMLInputElement.prototype,
          'value'
        ).set

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

  // --- 1. Pemasok: buka, cari, saring, pilih --------------------------
  report.supplier = await evaluate(`
    (async function () {
      const trigger = document.querySelector('#supplier_id')
      if (!trigger) return { step: 'no-trigger' }

      trigger.click()
      await new Promise((resolve) => setTimeout(resolve, 700))

      const search = document.querySelector('[data-slot="combobox-input"]')
      if (!search) return { step: 'no-search-input' }

      const allBefore = document.querySelectorAll('[data-slot="combobox-item"]').length

      const setter = Object.getOwnPropertyDescriptor(
        HTMLInputElement.prototype,
        'value'
      ).set
      setter.call(search, 'nusantara')
      search.dispatchEvent(new Event('input', { bubbles: true }))
      await new Promise((resolve) => setTimeout(resolve, 400))

      const items = [...document.querySelectorAll('[data-slot="combobox-item"]')]
      const labels = items.map((item) => item.textContent.trim())
      const target = items[0]

      if (!target) return { step: 'filtered-to-nothing', allBefore }

      // Nilai opsi dibaca dari id item (id list + '-' + nilai), agar bisa
      // dibandingkan dengan hasil API di akhir.
      const listId = document.querySelector('[data-slot="combobox-list"]').id
      const pickedValue = target.id.slice(listId.length + 1)

      target.click()
      await new Promise((resolve) => setTimeout(resolve, 500))

      return {
        step: 'ok',
        allBefore,
        filteredCount: items.length,
        labels,
        pickedValue,
        triggerText: trigger.textContent.trim(),
        closed: document.querySelector('[data-slot="combobox-input"]') === null,
      }
    })()
  `)

  // --- 2. Obat: buka, cari, pilih, dan pastikan satuan tampil ---------
  report.medicine = await evaluate(`
    (async function () {
      const row = [...document.querySelectorAll('tbody tr')]
        .find((candidate) => candidate.querySelector('input[type="date"]'))
      if (!row) return { step: 'no-row' }

      const trigger = row.querySelector('button[role="combobox"]')
      if (!trigger) return { step: 'no-trigger' }

      trigger.click()
      await new Promise((resolve) => setTimeout(resolve, 700))

      const search = document.querySelector('[data-slot="combobox-input"]')
      if (!search) return { step: 'no-search-input' }

      const allBefore = document.querySelectorAll('[data-slot="combobox-item"]').length

      const setter = Object.getOwnPropertyDescriptor(
        HTMLInputElement.prototype,
        'value'
      ).set
      setter.call(search, 'paracetamol')
      search.dispatchEvent(new Event('input', { bubbles: true }))
      await new Promise((resolve) => setTimeout(resolve, 400))

      const items = [...document.querySelectorAll('[data-slot="combobox-item"]')]
      const labels = items.map((item) => item.textContent.trim())
      const target = items[0]

      if (!target) return { step: 'filtered-to-nothing', allBefore }

      const listId = document.querySelector('[data-slot="combobox-list"]').id
      const pickedValue = target.id.slice(listId.length + 1)

      target.click()
      await new Promise((resolve) => setTimeout(resolve, 500))

      return {
        step: 'ok',
        allBefore,
        filteredCount: items.length,
        labels,
        pickedValue,
        triggerText: trigger.textContent.trim(),
      }
    })()
  `)

  // Isi batch, kedaluwarsa, dan jumlah pada baris yang sama.
  report.itemFields = await evaluate(`
    (function () {
      const row = [...document.querySelectorAll('tbody tr')]
        .find((candidate) => candidate.querySelector('input[type="date"]'))
      if (!row) return 'no-row'

      const batch = row.querySelector('input:not([type="date"]):not([type="number"])')
      const date = row.querySelector('input[type="date"]')
      const qty = row.querySelector('input[type="number"]')

      const setter = Object.getOwnPropertyDescriptor(
        HTMLInputElement.prototype,
        'value'
      ).set

      const setValue = (element, value) => {
        setter.call(element, value)
        element.dispatchEvent(new Event('input', { bubbles: true }))
        element.dispatchEvent(new Event('change', { bubbles: true }))
      }

      setValue(batch, 'PCT-CB-01')
      setValue(date, '2030-05-31')
      setValue(qty, '7')

      return 'ok'
    })()
  `)

  report.submit = await evaluate(`
    (function () {
      const form = document.querySelector('#reception-form')
      if (!form) return 'no-form'

      form.requestSubmit()

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
              success: document.body.innerText.includes('Penerimaan dibuat.'),
              sheetClosed: document.querySelector('[data-slot="sheet-content"]') === null,
              alerts: [...document.querySelectorAll('[role="alert"]')].map((node) => node.innerText),
            })
          `)
        )
      )
    } catch {
      snapshots.push({ navigated: true })
    }
  }

  report.picked = {
    supplier: report.supplier?.pickedValue ?? null,
    medicine: report.medicine?.pickedValue ?? null,
  }
  report.saved = snapshots
  report.consoleErrors = consoleErrors.slice(0, 5)

  client.close()
} finally {
  chrome.kill()
}

// Konfirmasi lewat API: sumber kebenaran, bukan tampilan. Label yang dipilih
// di combobox harus benar-benar tersimpan sebagai id yang benar.
if (sessionCookie) {
  const headers = { cookie: `ci_session=${sessionCookie}` }
  const list = await (await fetch(`${API}/api/receipts`, { headers })).json()
  const created = list.data.find((row) => row.reference_no === REFERENCE)

  if (created) {
    const detail = await (
      await fetch(`${API}/api/receipts/${created.id}`, { headers })
    ).json()

    report.apiConfirmed = {
      id: created.id,
      supplier_id: detail.data.supplier_id,
      supplier_name: detail.data.supplier_name,
      items: detail.data.items.map((item) => ({
        medicine_id: item.medicine_id,
        medicine_name: item.medicine_name,
        unit: item.unit,
        quantity: item.quantity,
      })),
    }
    report.matches = {
      supplier:
        report.apiConfirmed.supplier_id === Number(report.picked.supplier),
      medicine:
        report.apiConfirmed.items[0]?.medicine_id ===
        Number(report.picked.medicine),
    }
  } else {
    report.apiConfirmed = null
  }
}

console.log(JSON.stringify(report, null, 2))

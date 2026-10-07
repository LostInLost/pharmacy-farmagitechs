/**
 * Verifikasi laporan stok di browser: angka dari server, filter tanggal
 * mengubah status kedaluwarsa, dan filter status hanya menyaring tampilan.
 *
 * Pemakaian:
 *   node scripts/verify-stocks.mjs <chromePath> <sessionCookie>
 */
const [, , chromePath, sessionCookie] = process.argv

const APP = "http://localhost:4321"
const API = "http://localhost:8080"
const CDP_PORT = 9336

import { spawn, execFileSync } from "node:child_process"
import { mkdtempSync } from "node:fs"
import { tmpdir } from "node:os"
import { join } from "node:path"

const profile = mkdtempSync(join(tmpdir(), "cdp-stocks-"))

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

/** Baca angka yang benar-benar tampil di tabel (bukan dari state React). */
const READ_ROWS = `
  (function () {
    const rows = [...document.querySelectorAll('tbody tr')]
      .filter((row) => row.querySelectorAll('td').length >= 7)

    const cells = (row) => [...row.querySelectorAll('td')].map((cell) => cell.innerText.trim())

    return JSON.stringify({
      count: rows.length,
      rows: rows.slice(0, 6).map((row) => {
        const values = cells(row)

        return {
          code: values[0],
          physical: values[3],
          available: values[4],
          expired: values[5],
          batches: values[6].replace(/\\s+/g, ' ').slice(0, 40),
        }
      }),
    })
  })()
`

const report = {}
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

  await client.send("Page.navigate", { url: `${APP}/stocks` })
  await sleep(5500)

  const evaluate = async (expression) => {
    const { result } = await client.send("Runtime.evaluate", {
      expression,
      returnByValue: true,
      awaitPromise: true,
    })

    return result.value
  }

  report.initial = JSON.parse(await evaluate(READ_ROWS))
  report.initialDate = await evaluate(`document.querySelector('#on_date').value`)

  const pickStatus = (label) => `
    (async function () {
      const trigger = document.querySelector('#status_filter')

      trigger.click()
      await new Promise((resolve) => setTimeout(resolve, 600))

      const options = [...document.querySelectorAll('[role="option"]')]
      const target = options.find((option) => option.textContent.includes(${JSON.stringify(label)}))

      if (!target) return 'no-option'

      target.click()
      await new Promise((resolve) => setTimeout(resolve, 1000))

      return 'ok'
    })()
  `

  // Filter "kedaluwarsa" pada tanggal hari ini: harus menyisakan obat yang
  // benar-benar punya batch kedaluwarsa (bukan nol).
  report.pickedExpired = await evaluate(pickStatus("kedaluwarsa"))
  await sleep(1000)
  report.expiredFilterToday = JSON.parse(await evaluate(READ_ROWS))
  report.expiredFilterEmptyText = await evaluate(`
    document.body.innerText.includes('Tidak ada obat yang cocok dengan status ini.')
  `)

  // Kembali ke "semua" supaya jumlah baris kembali penuh.
  report.pickedAll = await evaluate(pickStatus("Semua status"))
  await sleep(1000)
  report.allFilterToday = JSON.parse(await evaluate(READ_ROWS))

  // Tanggal mundur jauh: tidak ada batch yang dianggap kedaluwarsa lagi.
  report.pastDate = "2020-01-01"

  await evaluate(`
    (async function () {
      const input = document.querySelector('#on_date')
      const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set

      setter.call(input, ${JSON.stringify("2020-01-01")})
      input.dispatchEvent(new Event('input', { bubbles: true }))
      input.dispatchEvent(new Event('change', { bubbles: true }))

      await new Promise((resolve) => setTimeout(resolve, 300))

      document.querySelector('form').requestSubmit()

      return 'submitted'
    })()
  `)

  await sleep(4000)

  report.afterPastDate = JSON.parse(await evaluate(READ_ROWS))

  report.consoleErrors = consoleErrors.slice(0, 5)
  client.close()
} finally {
  chrome.kill()
}

// Bandingkan dengan angka server langsung.
const jar = process.env.VERIFY_JAR

if (jar) {
  const read = (date) => {
    const url = date ? `${API}/api/stocks?on_date=${date}` : `${API}/api/stocks`

    return JSON.parse(execFileSync("curl.exe", ["-s", "-b", jar, url], { encoding: "utf8" }))
  }

  const today = read(null)
  const past = read(report.pastDate)

  const totals = (payload) => ({
    medicines: payload.medicines.length,
    available: payload.medicines.reduce((sum, item) => sum + item.available_quantity, 0),
    expired: payload.medicines.reduce((sum, item) => sum + item.expired_quantity, 0),
  })

  report.apiToday = { onDate: today.on_date, ...totals(today) }
  report.apiPast = { onDate: past.on_date, ...totals(past) }

  // Assertion: "Semua status" harus menampilkan seluruh obat yang dikirim
  // server, termasuk yang belum punya batch (perilaku halaman CI4).
  const expected = report.apiToday.medicines
  const expiredExpected = today.medicines.filter(
    (item) => item.expired_batches.length > 0
  ).length

  report.assertions = {
    allRowsMatchApi: report.allFilterToday?.count === expected,
    allRows: report.allFilterToday?.count,
    expectedAllRows: expected,
    expiredRowsMatchApi: report.expiredFilterToday?.count === expiredExpected,
    expiredRows: report.expiredFilterToday?.count,
    expectedExpiredRows: expiredExpected,
  }

  report.pass =
    report.assertions.allRowsMatchApi &&
    report.assertions.expiredRowsMatchApi &&
    (report.consoleErrors?.length ?? 0) === 0
}

console.log(JSON.stringify(report, null, 2))

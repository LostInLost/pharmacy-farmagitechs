/**
 * Verifikasi halaman Master Obat di browser sungguhan (Chrome headless via CDP).
 *
 * Yang dibuktikan:
 *  1. Petugas melihat katalog lengkap TANPA tombol "Tambah Obat" dan tanpa
 *     tombol "Ubah" — server mengirim `can_write = false`.
 *  2. Supervisor melihat tombol tambah/ubah (server mengirim `can_write = true`),
 *     dan menyimpan obat baru lewat dialog benar-benar menambah barisnya.
 *  3. Filter status "Hanya nonaktif" menyaring lewat server (jumlah baris
 *     cocok dengan API), bukan menyaring data yang sudah ada di klien.
 *
 * Pemakaian:
 *   node scripts/verify-medicines.mjs <chromePath> <petugasCookie> <supervisorCookie>
 */
const [, , chromePath, petugasCookie, supervisorCookie] = process.argv

const APP = "http://localhost:4321"
const API = "http://localhost:8080"
const CDP_PORT = 9339

import { spawn, execFileSync } from "node:child_process"
import { mkdtempSync } from "node:fs"
import { tmpdir } from "node:os"
import { join } from "node:path"

const profile = mkdtempSync(join(tmpdir(), "cdp-medicines-"))

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

/** Baca DOM yang benar-benar tampil, bukan state React. */
const READ_PAGE = `
  (() => {
    const rows = [...document.querySelectorAll('tbody tr')]
      .filter((row) => row.querySelectorAll('td').length >= 5)

    return JSON.stringify({
      rowCount: rows.length,
      codes: rows.map((row) => row.querySelectorAll('td')[0].innerText.trim()),
      statuses: rows.map((row) => row.querySelectorAll('td')[3].innerText.trim()),
      addButton: [...document.querySelectorAll('button')]
        .some((button) => button.innerText.trim() === 'Tambah Obat'),
      editButtons: [...document.querySelectorAll('button')]
        .filter((button) => button.innerText.trim() === 'Ubah').length,
      hasError: /Tidak dapat menghubungi server|Respons server tidak dikenali|Gagal memuat/.test(document.body.innerText),
    })
  })()
`

/**
 * Pilih nilai pada Select shadcn (Radix) lalu kirim form filter.
 * Dipakai untuk kembali ke "Semua status" sebelum memeriksa baris baru.
 */
const pickStatus = (label) => `
  (async function () {
    const trigger = document.querySelector('#medicine_filter')

    trigger.click()
    await new Promise((resolve) => setTimeout(resolve, 700))

    const option = [...document.querySelectorAll('[role="option"]')]
      .find((node) => node.textContent.includes(${JSON.stringify(label)}))

    if (!option) return 'no-option'

    option.click()
    await new Promise((resolve) => setTimeout(resolve, 500))

    document.querySelector('form').requestSubmit()
    await new Promise((resolve) => setTimeout(resolve, 4000))

    return 'ok'
  })()
`

const report = {}
const consoleErrors = []

try {
  // Jumlah obat dibaca SEBELUM navigasi: halaman petugas harus menampilkan
  // tepat sebanyak yang dikirim server saat itu (bukan setelah skrip ini
  // menambah obat uji di langkah berikutnya).
  report.apiBaseline = process.env.VERIFY_JAR
    ? JSON.parse(
        execFileSync("curl.exe", ["-s", "-b", process.env.VERIFY_JAR, `${API}/api/medicines`], {
          encoding: "utf8",
        })
      ).data.length
    : null

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

  const evaluate = async (expression) => {
    const { result } = await client.send("Runtime.evaluate", {
      expression,
      returnByValue: true,
      awaitPromise: true,
    })

    return result.value
  }

  const setSession = async (cookie) => {
    await client.send("Network.setCookie", {
      name: "ci_session",
      value: cookie,
      domain: "localhost",
      path: "/",
      httpOnly: true,
    })
  }

  // --- 1. Petugas: katalog terbaca, tanpa aksi tulis -------------------
  await setSession(petugasCookie)
  await client.send("Page.navigate", { url: `${APP}/medicines` })
  await sleep(6000)

  report.asPetugas = JSON.parse(await evaluate(READ_PAGE))

  // --- 2. Supervisor: aksi tulis muncul ---------------------------------
  await setSession(supervisorCookie)
  await client.send("Page.navigate", { url: `${APP}/medicines` })
  await sleep(6000)

  report.asSupervisor = JSON.parse(await evaluate(READ_PAGE))

  // --- 3. Filter "Hanya nonaktif" (lewat server) ------------------------
  report.filterResult = await evaluate(pickStatus("Hanya nonaktif"))
  report.afterFilter = JSON.parse(await evaluate(READ_PAGE))

  // --- 4. Simpan obat baru lewat dialog sebagai supervisor --------------
  const newCode = `OBT-UI-${Date.now()}`

  report.dialog = await evaluate(`
    (async function () {
      const openButton = [...document.querySelectorAll('button')]
        .find((button) => button.innerText.trim() === 'Tambah Obat')

      if (!openButton) return 'no-add-button'

      openButton.click()
      await new Promise((resolve) => setTimeout(resolve, 800))

      const setValue = (selector, value) => {
        const input = document.querySelector(selector)

        if (!input) return false

        const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set
        setter.call(input, value)
        input.dispatchEvent(new Event('input', { bubbles: true }))

        return true
      }

      const ok = [
        setValue('#medicine_code', ${JSON.stringify(newCode)}),
        setValue('#medicine_name', 'Obat dari verifikasi UI'),
        setValue('#medicine_unit', 'tablet'),
      ]

      if (ok.some((value) => value === false)) return 'input-not-found'

      await new Promise((resolve) => setTimeout(resolve, 300))

      const form = [...document.querySelectorAll('form')]
        .find((node) => node.querySelector('#medicine_code'))

      form.requestSubmit()

      return 'submitted'
    })()
  `)

  await sleep(5000)

  // Obat baru dibuat aktif, sedangkan filter masih "Hanya nonaktif" —
  // kembalikan ke "Semua status" supaya barisnya memang bisa tampil.
  report.resetFilter = await evaluate(pickStatus("Semua status"))

  report.afterSave = JSON.parse(await evaluate(READ_PAGE))
  report.newCode = newCode
  report.savedVisible = report.afterSave.codes.includes(newCode)
  report.consoleErrors = consoleErrors.slice(0, 5)

  client.close()
} finally {
  chrome.kill()
}

// --- Bandingkan dengan angka server langsung ---------------------------
if (process.env.VERIFY_JAR) {
  const jar = process.env.VERIFY_JAR
  const read = (path) =>
    JSON.parse(execFileSync("curl.exe", ["-s", "-b", jar, `${API}${path}`], { encoding: "utf8" }))

  const all = read("/api/medicines")
  const inactive = read("/api/medicines?status=inactive")

  report.api = {
    total: all.data.length,
    inactive: inactive.data.length,
    canWriteAsPetugas: all.can_write,
  }

  report.assertions = {
    // Jumlah baris petugas dibandingkan dengan jumlah yang dikirim server
    // SEBELUM skrip ini menambah obat uji (lihat `apiBaseline`).
    petugasSeesCatalog: report.asPetugas.rowCount === report.apiBaseline,
    petugasHasNoAddButton: report.asPetugas.addButton === false,
    petugasHasNoEditButtons: report.asPetugas.editButtons === 0,
    supervisorHasAddButton: report.asSupervisor.addButton === true,
    supervisorHasEditButtons: report.asSupervisor.editButtons > 0,
    filterMatchesApi: report.afterFilter.rowCount === inactive.data.length,
    filterOnlyInactive: report.afterFilter.statuses.every((status) => status === "nonaktif"),
    saveAddsRow: report.savedVisible === true,
    noErrors:
      !report.asPetugas.hasError &&
      !report.asSupervisor.hasError &&
      (report.consoleErrors?.length ?? 0) === 0,
  }

  report.pass = Object.values(report.assertions).every(Boolean)
}

console.log(JSON.stringify(report, null, 2))

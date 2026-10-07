/**
 * Verifikasi dua jalur yang paling mudah salah:
 *
 * 1. CSRF basi — token disetel ke nilai palsu lewat localStorage? Tidak:
 *    token diambil dari endpoint bootstrap, jadi yang disimulasikan adalah
 *    token yang sudah berotasi (dipakai dua kali). requestJson harus
 *    mengambil token segar dari header respons lalu mencoba ulang.
 * 2. Validasi 422 — `errors[]` dari server harus dirender, bukan ditelan.
 *
 * Pemakaian:
 *   node scripts/verify-csrf-validation.mjs <chromePath> <sessionCookie>
 */
const [, , chromePath, sessionCookie] = process.argv

const APP = "http://localhost:4321"
const CDP_PORT = 9338

import { spawn } from "node:child_process"
import { mkdtempSync } from "node:fs"
import { tmpdir } from "node:os"
import { join } from "node:path"

const profile = mkdtempSync(join(tmpdir(), "cdp-csrf-"))

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

const report = {}

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

  // Halaman apa pun dari origin yang sama, agar fetch relatif bekerja.
  await client.send("Page.navigate", { url: `${APP}/receptions` })
  await sleep(4000)

  const evaluate = async (expression) => {
    const { result } = await client.send("Runtime.evaluate", {
      expression,
      returnByValue: true,
      awaitPromise: true,
    })

    return result.value
  }

  // Jalankan di konteks halaman: panggil modul aplikasi lewat impor Vite,
  // lalu picu alur tulis dengan token yang sengaja dibuat basi.
  report.csrfStale = JSON.parse(
    await evaluate(`
      (async function () {
        const api = await import('/src/features/receptions/api.ts')

        // Ambil token, lalu pakai dua kali: pemakaian pertama membuat token
        // berotasi, jadi percobaan kedua memakai token yang sudah basi.
        const csrf = await import('/src/foundations/api/csrf.ts')
        const stale = await csrf.bootstrapCsrfToken()

        const payload = {
          reference_no: 'CSRF-STALE-' + Date.now(),
          supplier_id: 1,
          received_at: '2026-10-07T14:00',
          items: [{ medicine_id: 101, batch_no: 'CSRF-B1', expires_on: '2031-06-30', quantity: 3 }],
        }

        // Panggilan pertama menghabiskan token lama.
        await api.createReception(payload)

        // Panggilan kedua memakai token yang sama (basi) - harus tetap sukses
        // berkat retry sekali dengan token segar dari header respons.
        const second = await api.createReception({
          ...payload,
          reference_no: payload.reference_no + '-B',
        })

        return JSON.stringify({
          staleTokenUsed: typeof stale === 'string' && stale.length > 0,
          retrySucceeded: second.ok === true,
          retryError: second.ok ? null : second.message,
        })
      })()
    `)
  )

  // Validasi 422: kirim payload yang ditolak server (reference duplikat).
  report.validation = JSON.parse(
    await evaluate(`
      (async function () {
        const api = await import('/src/features/receptions/api.ts')

        const result = await api.createReception({
          reference_no: 'OWNER-TEST-SUPERVISOR',
          supplier_id: 1,
          received_at: '2026-10-07T14:00',
          items: [{ medicine_id: 101, batch_no: 'DUP-B1', expires_on: '2031-06-30', quantity: 3 }],
        })

        return JSON.stringify({
          ok: result.ok,
          status: result.status,
          message: result.ok ? null : result.message,
          errors: result.ok ? [] : result.errors,
        })
      })()
    `)
  )

  client.close()
} finally {
  chrome.kill()
}

report.pass =
  report.csrfStale?.retrySucceeded === true &&
  report.validation?.ok === false &&
  report.validation?.status === 422 &&
  (report.validation?.errors?.length ?? 0) > 0

console.log(JSON.stringify(report, null, 2))

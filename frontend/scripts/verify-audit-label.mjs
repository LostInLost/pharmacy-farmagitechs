/**
 * Verifikasi label aksi audit hasil render nyata di browser (CDP).
 *
 * Membuktikan nilai `audit_logs.action` yang berupa kunci i18n
 * (`Audit.receptions.action.create`) benar-benar diterjemahkan saat tampil:
 * halaman CI4 lewat boot i18n + `reception-form.js`, halaman Astro lewat peta
 * `features/audit/labels.ts` — bukan sekadar "seharusnya jalan".
 *
 * Pemakaian:
 *   node scripts/verify-audit-label.mjs <chromePath> <sessionCookie> <receptionId>
 *
 * Cookie sesi CI4 bersifat HttpOnly, jadi nilainya diambil dari browser yang
 * sudah login (DevTools → Application → Cookies → `ci_session`).
 */
const [, , chromePath, sessionCookie, receptionId] = process.argv

if (!chromePath || !sessionCookie || !receptionId) {
  console.error("Pemakaian: node scripts/verify-audit-label.mjs <chromePath> <sessionCookie> <receptionId>")
  process.exit(2)
}

const CI4 = "http://localhost:8080"
const ASTRO = "http://localhost:4321"
const CDP_PORT = 9339

const EXPECTED = {
  "Audit.receptions.action.create": "Menambahkan data penerimaan",
  "Audit.receptions.action.update": "Mengubah data penerimaan",
}

import { spawn } from "node:child_process"
import { mkdtempSync } from "node:fs"
import { tmpdir } from "node:os"
import { join } from "node:path"

const profile = mkdtempSync(join(tmpdir(), "cdp-audit-"))

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
      // Chrome belum siap; coba lagi.
    }

    await sleep(250)
  }

  throw new Error("Chrome DevTools tidak siap")
}

/** Klien CDP minimal: kirim perintah, tunggu balasan dengan id yang sama. */
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

  // Cookie sesi CI4 bersifat HttpOnly, jadi hanya bisa dipasang dari sini.
  await client.send("Network.setCookie", {
    name: "ci_session",
    value: sessionCookie,
    domain: "localhost",
    path: "/",
    httpOnly: true,
  })

  // --- 1. Halaman CI4: jQuery mengisi tabel riwayat dari API ---------------
  await client.send("Page.navigate", { url: `${CI4}/receptions/${receptionId}/edit` })
  await sleep(5000)

  const ci4 = await client.send("Runtime.evaluate", {
    expression: `JSON.stringify({
      visible: getComputedStyle(document.querySelector('#log-section')).display !== 'none',
      rows: [...document.querySelectorAll('#log-tbody tr')].map((row) => ({
        action: row.querySelectorAll('td')[1]?.innerText?.trim() ?? null,
        summary: row.querySelectorAll('td')[3]?.innerText?.trim() ?? null
      }))
    })`,
    returnByValue: true,
  })
  report.ci4 = JSON.parse(ci4.result.value)

  // --- 2. Halaman Astro: island React memakai features/audit/labels.ts -----
  await client.send("Page.navigate", { url: `${ASTRO}/receptions/${receptionId}/edit` })
  await sleep(7000)

  const astro = await client.send("Runtime.evaluate", {
    expression: `JSON.stringify({
      hasHistory: document.body.innerText.includes('Riwayat Aksi'),
      rows: [...document.querySelectorAll('tbody tr')].map((row) => {
        const cells = [...row.querySelectorAll('td')]
        return cells.length >= 4 ? { action: cells[1].innerText.trim(), summary: cells[3].innerText.trim() } : null
      }).filter(Boolean)
    })`,
    returnByValue: true,
  })
  report.astro = JSON.parse(astro.result.value)

  client.close()
} finally {
  chrome.kill()
}

// --- Kesimpulan: setiap baris aksi harus terbaca sebagai kalimat terjemahan.
const checks = []

for (const [page, rows] of [["ci4", report.ci4?.rows ?? []], ["astro", report.astro?.rows ?? []]]) {
  const translated = rows.filter((row) => Object.values(EXPECTED).includes(row.action))
  const rawKeys = rows.filter((row) => /^Audit\./.test(row.action))

  checks.push({ page, rows: rows.length, translated: translated.length, rawKeys: rawKeys.length })
}

report.checks = checks
report.pass = checks.every((row) => row.rows > 0 && row.rawKeys === 0)

console.log(JSON.stringify(report, null, 2))

process.exit(report.pass ? 0 : 1)

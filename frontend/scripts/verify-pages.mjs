/**
 * Verifikasi end-to-end di browser sungguhan via Chrome DevTools Protocol.
 *
 * Yang dibuktikan: halaman Astro (SSR) + island React benar-benar memanggil API
 * lintas origin dengan cookie sesi, memvalidasi respons dengan Zod, lalu
 * merender datanya — bukan sekadar "seharusnya jalan".
 *
 * Pemakaian:
 *   node scripts/verify-pages.mjs <chromePath> <sessionCookie> [pagePath...]
 */
const [, , chromePath, sessionCookie, ...pages] = process.argv

const APP = "http://localhost:4321"
const targets = pages.length > 0 ? pages : ["/receptions", "/stocks", "/dashboard"]
const CDP_PORT = 9333

import { spawn } from "node:child_process"
import { mkdtempSync } from "node:fs"
import { tmpdir } from "node:os"
import { join } from "node:path"

const profile = mkdtempSync(join(tmpdir(), "cdp-verify-"))

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
      // Endpoint level-browser tidak mendukung Network/Page/Runtime, jadi
      // harus menyambung ke target halaman.
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
  const listeners = []
  let nextId = 1

  const ready = new Promise((resolve, reject) => {
    socket.addEventListener("open", () => resolve())
    socket.addEventListener("error", () => reject(new Error("WebSocket gagal")))
  })

  socket.addEventListener("message", (event) => {
    const message = JSON.parse(event.data)

    if (message.id !== undefined) {
      const entry = pending.get(message.id)

      if (entry) {
        pending.delete(message.id)
        entry(message)
      }

      return
    }

    // Event (bukan balasan perintah) diteruskan ke pemanggil.
    for (const listener of listeners) listener(message)
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

/** Daftarkan listener event CDP; dipisah agar blok utama tetap ringkas. */
function socketMessageHook(client, listener) {
  client.onEvent(listener)
}

const results = []

try {
  const client = connect(await cdpUrl())
  await client.ready

  const consoleErrors = []

  socketMessageHook(client, (message) => {
    if (message.method === "Runtime.exceptionThrown") {
      consoleErrors.push(
        message.params?.exceptionDetails?.exception?.description ??
          message.params?.exceptionDetails?.text ??
          "exception"
      )
    }

    if (message.method === "Runtime.consoleAPICalled" && message.params?.type === "error") {
      consoleErrors.push(
        (message.params.args ?? [])
          .map((arg) => arg.value ?? arg.description ?? "")
          .join(" ")
      )
    }
  })

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

  for (const path of targets) {
    consoleErrors.length = 0

    await client.send("Page.navigate", { url: `${APP}${path}` })
    // Beri waktu island React memuat, fetch, dan render.
    await sleep(6000)

    const { result } = await client.send("Runtime.evaluate", {
      expression: "document.body.innerText",
      returnByValue: true,
    })

    const text = result.value ?? ""

    results.push({
      path,
      url: (await client.send("Runtime.evaluate", {
        expression: "location.pathname",
        returnByValue: true,
      })).result.value,
      len: text.length,
      hasRef: /VERIFY-FRONTEND-001/.test(text),
      medCount: (text.match(/OBT-\d+/g) ?? []).length,
      hasError: /Tidak dapat menghubungi server|Respons server tidak dikenali|Gagal memuat/.test(text),
      consoleErrors: [...consoleErrors].slice(0, 3),
      preview: text.replace(/\s+/g, " ").slice(0, 220),
    })
  }

  client.close()
} finally {
  chrome.kill()
}

for (const row of results) {
  console.log(JSON.stringify(row, null, 2))
}

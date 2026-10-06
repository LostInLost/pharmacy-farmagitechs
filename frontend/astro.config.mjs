// @ts-check

import tailwindcss from "@tailwindcss/vite"
import node from "@astrojs/node"
import { defineConfig } from "astro/config"
import react from "@astrojs/react"

// https://astro.build/config
export default defineConfig({
  // Middleware auth (guest + authenticated) butuh SSR: setiap request
  // dirender server agar guard berjalan sebelum halaman dikirim.
  output: "server",
  adapter: node({ mode: "standalone" }),
  vite: {
    plugins: [tailwindcss()],
  },
  integrations: [react()],
})

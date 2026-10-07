import js from "@eslint/js"
import globals from "globals"
import reactHooks from "eslint-plugin-react-hooks"
import reactRefresh from "eslint-plugin-react-refresh"
import tseslint from "typescript-eslint"
import { defineConfig, globalIgnores } from "eslint/config"

export default defineConfig([
  globalIgnores(["dist", ".astro"]),
  {
    files: ["**/*.{ts,tsx}"],
    extends: [
      js.configs.recommended,
      tseslint.configs.recommended,
      reactHooks.configs.flat.recommended,
      reactRefresh.configs.vite,
    ],
    languageOptions: {
      globals: globals.browser,
    },
    rules: {
      "react-refresh/only-export-components": "off",
    },
  },
  {
    // Batas arsitektur: foundations adalah infra generik, tidak boleh
    // tahu apa pun soal domain (features).
    files: ["src/foundations/**/*.{ts,tsx}"],
    rules: {
      "no-restricted-imports": [
        "error",
        {
          patterns: [
            {
              group: ["@/features/*"],
              message: "foundations tidak boleh mengimpor features.",
            },
            {
              group: ["@/lib/*", "@/hooks/*"],
              message: "jalur lama sudah dihapus (refactor lib→features+foundations).",
            },
          ],
        },
      ],
    },
  },
  {
    // Cegah impor balik ke jalur lama pasca refactor.
    files: ["src/**/*.{ts,tsx}"],
    ignores: ["src/foundations/**"],
    rules: {
      "no-restricted-imports": [
        "error",
        {
          patterns: [
            {
              group: ["@/lib/*", "@/hooks/use-mobile", "./lib/*", "../lib/*"],
              message: "pakai @/foundations/* atau @/features/*.",
            },
          ],
        },
      ],
    },
  },
])

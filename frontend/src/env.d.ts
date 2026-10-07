/// <reference types="astro/client" />

import type { AuthUser } from "./features/auth/schemas"

declare global {
  namespace App {
    interface Locals {
      /** User dari `GET /api/me`; `null` bila anonim atau backend tak terjangkau. */
      user: AuthUser | null
    }
  }
}

export {}

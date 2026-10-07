import { z } from "zod"

/** Baris tabel dashboard (dari `@/data/dashboard.json`). */
export const dashboardRowSchema = z.object({
  id: z.number(),
  header: z.string(),
  type: z.string(),
  status: z.string(),
  target: z.string(),
  limit: z.string(),
  reviewer: z.string(),
})

export type DashboardRow = z.infer<typeof dashboardRowSchema>

export const dashboardDataSchema = z.array(dashboardRowSchema)

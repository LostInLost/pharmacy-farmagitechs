import * as React from "react"
import { z } from "zod"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Field, FieldError, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { POST_LOGIN_PATH } from "@/foundations/api/config"
import { login, type LoginErrorKind } from "@/features/auth/api"
import { loginFormSchema } from "@/features/auth/schemas"
import { setUserSession } from "@/features/auth/session"
import { CircleAlertIcon } from "lucide-react"

type Props = {
  appName: string
}

type FieldErrors = {
  username?: string
  password?: string
}

const MESSAGES: Record<LoginErrorKind, string> = {
  validation: "Username dan kata sandi wajib diisi.",
  "invalid-credentials": "Username atau kata sandi salah.",
  "already-authenticated": "Anda sudah masuk. Muat ulang halaman ini.",
  csrf: "Sesi keamanan kedaluwarsa. Coba lagi.",
  network: "Tidak dapat menghubungi server.",
  server: "Terjadi kesalahan pada server.",
}

export function LoginForm({ appName }: Props) {
  const [username, setUsername] = React.useState("")
  const [password, setPassword] = React.useState("")
  const [pending, setPending] = React.useState(false)
  const [error, setError] = React.useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = React.useState<FieldErrors>({})

  async function onSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    if (pending) return

    // Satu sumber kebenaran pesan validasi: skema. Form hanya merender
    // pesan per-field dari hasil parse; `login()` tetap menjaga kontrak 422.
    const parsed = loginFormSchema.safeParse({
      username: username.trim(),
      password,
    })

    if (!parsed.success) {
      const fields = z.flattenError(parsed.error).fieldErrors
      setFieldErrors({
        username: fields.username?.[0],
        password: fields.password?.[0],
      })
      return
    }

    setFieldErrors({})
    setPending(true)
    setError(null)

    const result = await login(parsed.data.username, parsed.data.password)

    if (result.ok) {
      setUserSession(result.user)
      window.location.assign(POST_LOGIN_PATH)
      return
    }

    setError(result.message || MESSAGES[result.kind])
    setPending(false)
  }

  return (
    <Card className="w-full max-w-sm">
      <CardHeader>
        <CardTitle>Masuk ke {appName}</CardTitle>
        <CardDescription>
          Gunakan akun Anda untuk mengakses aplikasi.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form id="login-form" onSubmit={onSubmit} noValidate className="grid gap-4">
          <Field>
            <FieldLabel htmlFor="username">Username</FieldLabel>
            <Input
              id="username"
              name="username"
              autoComplete="username"
              autoFocus
              required
              value={username}
              onChange={(event) => setUsername(event.target.value)}
              aria-invalid={fieldErrors.username !== undefined}
            />
            <FieldError>{fieldErrors.username}</FieldError>
          </Field>
          <Field>
            <FieldLabel htmlFor="password">Kata sandi</FieldLabel>
            <Input
              id="password"
              name="password"
              type="password"
              autoComplete="current-password"
              required
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              aria-invalid={fieldErrors.password !== undefined}
            />
            <FieldError>{fieldErrors.password}</FieldError>
          </Field>
          {error !== null && (
            <Alert variant="destructive">
              <CircleAlertIcon />
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}
          <Button type="submit" disabled={pending} className="w-full">
            {pending ? "Memproses..." : "Masuk"}
          </Button>
        </form>
      </CardContent>
      <CardFooter className="text-xs text-muted-foreground">
        {appName} · SIMRS &amp; SIM Klinik
      </CardFooter>
    </Card>
  )
}

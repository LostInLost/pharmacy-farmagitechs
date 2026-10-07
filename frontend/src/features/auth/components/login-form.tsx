import * as React from "react"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { POST_LOGIN_PATH, login, type LoginErrorKind } from "@/lib/auth"
import { setUserSession } from "@/lib/session"

type Props = {
  appName: string
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

  async function onSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()

    if (pending) return

    if (!username.trim() || !password) {
      setError(MESSAGES.validation)
      return
    }

    setPending(true)
    setError(null)

    const result = await login(username.trim(), password)

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
          <div className="grid gap-2">
            <Label htmlFor="username">Username</Label>
            <Input
              id="username"
              name="username"
              autoComplete="username"
              autoFocus
              required
              value={username}
              onChange={(event) => setUsername(event.target.value)}
              aria-invalid={error !== null}
            />
          </div>
          <div className="grid gap-2">
            <Label htmlFor="password">Kata sandi</Label>
            <Input
              id="password"
              name="password"
              type="password"
              autoComplete="current-password"
              required
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              aria-invalid={error !== null}
            />
          </div>
          {error !== null && (
            <p role="alert" className="text-sm text-destructive">
              {error}
            </p>
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

# Tema frontend Farmagitechs (Astro + shadcn)

> Bagian dari [README](../README.md). Lihat juga [frontend/README.md](../frontend/README.md) untuk cara menjalankan dan struktur frontend.

Sumber tema: preset shadcn `b7D6016rcO`, base **radix**, template **astro**.

Perintah init yang dipakai:

```bash
pnpm dlx shadcn@latest init --preset b7D6016rcO --base radix --template astro
```

Warna mengikuti preset tersebut (bukan sampling manual dari landing page).
Token didefinisikan sebagai CSS variables di `frontend/src/styles/global.css`
dan dipetakan ke utilitas Tailwind v4 lewat blok `@theme inline`.

## Token utama (light)

| Token | Nilai (oklch) | Peran |
| --- | --- | --- |
| `--background` | `1 0 0` | Latar halaman |
| `--foreground` | `0.147 0.004 49.3` | Teks utama |
| `--primary` | `0.555 0.163 48.998` | Aksi utama / CTA |
| `--primary-foreground` | `0.987 0.022 95.277` | Teks di atas primary |
| `--secondary` | `0.967 0.001 286.375` | Aksi sekunder |
| `--muted` | `0.96 0.002 17.2` | Latar lembut |
| `--muted-foreground` | `0.547 0.021 43.1` | Teks sekunder |
| `--destructive` | `0.577 0.245 27.325` | Error / bahaya |
| `--border` | `0.922 0.005 34.3` | Garis |
| `--radius` | `0.875rem` | Radius dasar |

Mode gelap tersedia lewat kelas `.dark` (lihat `global.css`).

## Tipografi

- Body: `Inter Variable` (`@fontsource-variable/inter`)
- Heading: `IBM Plex Sans Variable` (`@fontsource-variable/ibm-plex-sans`)

Dipetakan sebagai `--font-sans` dan `--font-heading`; kelas `font-heading`
dipakai untuk judul (CardTitle, judul halaman).

## Pemakaian di UI

- Tombol utama: `<Button>` (variant `default`) — `bg-primary`.
- Panel brand halaman login: `bg-primary text-primary-foreground`.
- Error form: `text-destructive`.

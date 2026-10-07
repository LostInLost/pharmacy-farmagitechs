"use client"

import * as React from "react"
import { cn } from "cn"
import { CheckIcon, ChevronDownIcon, PlusIcon } from "lucide-react"

import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"

/**
 * Satu pilihan combobox; `hint` tampil samar di kanan label (mis. satuan).
 * Hint disembunyikan bila label sudah memuatnya — nama obat di data nyata
 * sering berakhir dengan bentuk sediaan ("Paracetamol 500 mg tablet").
 */
export type ComboboxOption = {
  value: string
  label: string
  hint?: string
}

type Props = {
  id?: string
  value: string
  onValueChange: (value: string) => void
  options: ComboboxOption[]
  placeholder?: string
  searchPlaceholder?: string
  emptyText?: string
  disabled?: boolean
  className?: string
  /**
   * Mode ketik-bebas (creatable): daftar hanya saran, dan ketikan yang tidak
   * cocok dengan opsi mana pun tetap bisa dipakai sebagai nilai baru — baris
   * "pakai ..." muncul di puncak daftar. Default `false` = pilih-satu ketat.
   */
  freeText?: boolean
  /** Label baris pembuat nilai baru; `%s` diganti teks ketikan. */
  createLabel?: string
  "aria-invalid"?: boolean
}

/**
 * Combobox pilih-satu: tombol pembuka + daftar yang bisa dicari.
 *
 * Dibangun di atas primitif Radix yang sudah dipakai proyek (`Popover`),
 * bukan komponen `combobox` registry shadcn — versi registry itu butuh
 * `@base-ui/react`, sedangkan proyek ini berbasis `radix-ui`. Pencarian
 * disaring di klien karena daftar referensi memang dikirim utuh oleh
 * `GET /api/references/*`.
 *
 * Dengan `freeText` kotak pencarian menjadi pembuat nilai: ketikan yang tidak
 * cocok opsi mana pun bisa langsung dipakai (baris pembuat di puncak daftar),
 * sehingga nomor batch baru bisa diketik alih-alih memaksa memilih yang ada.
 *
 * Kotaknya sengaja SELALU di-portal ke body dan modal. Konten non-portal
 * tidak bisa dipakai di dalam Sheet pada tabel: pembungkus `Table` memakai
 * `relative overflow-x-auto`, sehingga elemen yang diposisikan Radix
 * (wrapper `position: fixed`) terpotong oleh overflow-x dan teks panjang
 * meluber keluar kotak. Mode modal juga yang membuat roda mouse bisa
 * menggulir daftar di dalam Sheet (scroll-lock bersarang, lihat catatan di
 * `Sheet`).
 */
export function Combobox({
  id,
  value,
  onValueChange,
  options,
  placeholder = "- pilih -",
  searchPlaceholder = "Cari...",
  emptyText = "Tidak ada pilihan yang cocok.",
  disabled = false,
  className,
  freeText = false,
  createLabel = "Pakai \"%s\"",
  "aria-invalid": ariaInvalid,
}: Props) {
  const [open, setOpen] = React.useState(false)
  const [query, setQuery] = React.useState("")
  const [activeIndex, setActiveIndex] = React.useState(0)
  const inputRef = React.useRef<HTMLInputElement>(null)
  const listId = React.useId()

  const selected = options.find((option) => option.value === value)
  const needle = query.trim().toLowerCase()
  const filtered =
    needle === ""
      ? options
      : options.filter((option) => option.label.toLowerCase().includes(needle))

  // Baris pembuat nilai baru: hanya bila ketikan belum menjadi opsi (persis).
  const typed = query.trim()
  const canCreate =
    freeText &&
    typed !== "" &&
    !options.some((option) => option.label.toLowerCase() === typed.toLowerCase())
  const createLabelText = createLabel.replace("%s", typed)

  // Baris pembuat di puncak daftar; indeks sorotan digeser satu.
  const lastIndex = filtered.length - 1 + (canCreate ? 1 : 0)
  const highlightedIndex = Math.max(0, Math.min(activeIndex, lastIndex))
  const highlighted = canCreate
    ? highlightedIndex === 0
      ? { value: typed, label: createLabelText, create: true as const }
      : { ...filtered[highlightedIndex - 1], create: false as const }
    : filtered[highlightedIndex] === undefined
      ? undefined
      : { ...filtered[highlightedIndex], create: false as const }

  // Sorotan keyboard (aria-activedescendant) harus selalu terlihat.
  const highlightedKey = highlighted?.value
  React.useEffect(() => {
    if (!open || highlightedKey === undefined) return

    document
      .getElementById(`${listId}-opt-${highlightedKey}`)
      ?.scrollIntoView({ block: "nearest" })
  }, [open, highlightedKey, listId])

  function changeOpen(next: boolean) {
    setOpen(next)

    if (next) {
      setQuery("")
      setActiveIndex(0)
    }
  }

  function showHint(option: ComboboxOption): boolean {
    if (option.hint === undefined) return false

    return !option.label.toLowerCase().includes(option.hint.toLowerCase())
  }

  function select(option: ComboboxOption) {
    onValueChange(option.value)
    setOpen(false)
  }

  function onInputKeyDown(event: React.KeyboardEvent<HTMLInputElement>) {
    if (event.key === "ArrowDown") {
      event.preventDefault()
      setActiveIndex(Math.min(highlightedIndex + 1, lastIndex))
    } else if (event.key === "ArrowUp") {
      event.preventDefault()
      setActiveIndex(Math.max(highlightedIndex - 1, 0))
    } else if (event.key === "Enter") {
      event.preventDefault()

      if (highlighted !== undefined) select(highlighted)
    }
  }

  return (
    <Popover modal open={open} onOpenChange={changeOpen}>
      <PopoverTrigger asChild>
        <button
          type="button"
          id={id}
          role="combobox"
          aria-expanded={open}
          aria-haspopup="listbox"
          aria-controls={open ? listId : undefined}
          aria-invalid={ariaInvalid}
          disabled={disabled}
          data-slot="combobox-trigger"
          data-placeholder={selected === undefined && value === "" ? "" : undefined}
          className={cn(
            "flex h-8 w-full items-center justify-between gap-1.5 rounded-lg border border-input bg-transparent py-2 pr-2 pl-2.5 text-sm whitespace-nowrap transition-colors outline-none select-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20 data-placeholder:text-muted-foreground dark:bg-input/30 dark:hover:bg-input/50 dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
            className
          )}
        >
          <span data-slot="combobox-value" className="line-clamp-1">
            {selected?.label ?? (value === "" ? placeholder : value)}
          </span>
          <ChevronDownIcon className="size-4 shrink-0 text-muted-foreground" />
        </button>
      </PopoverTrigger>

      <PopoverContent
        align="start"
        sideOffset={4}
        data-slot="combobox-content"
        className="w-(--radix-popover-trigger-width) min-w-64 flex-col gap-0 p-0"
        onOpenAutoFocus={(event) => {
          // Radix memfokuskan kontainer; kotak pencarian yang lebih berguna.
          event.preventDefault()
          inputRef.current?.focus()
        }}
      >
        <div className="border-b p-1">
          <input
            ref={inputRef}
            data-slot="combobox-search"
            type="text"
            value={query}
            onChange={(event) => {
              setQuery(event.target.value)
              setActiveIndex(0)
            }}
            onKeyDown={onInputKeyDown}
            placeholder={freeText ? "Ketik atau cari..." : searchPlaceholder}
            aria-label={searchPlaceholder}
            aria-controls={listId}
            aria-activedescendant={
              highlighted === undefined
                ? undefined
                : `${listId}-opt-${highlighted.value}`
            }
            autoComplete="off"
            spellCheck={false}
            className="h-7 w-full bg-transparent px-1.5 text-sm outline-none placeholder:text-muted-foreground"
          />
        </div>

        <ul
          id={listId}
          role="listbox"
          data-slot="combobox-list"
          className="max-h-64 overflow-y-auto overscroll-contain p-1"
        >
          {canCreate && (
            <li
              id={`${listId}-opt-${typed}`}
              role="option"
              aria-selected={false}
              data-slot="combobox-item-create"
              data-highlighted={highlightedIndex === 0 ? "" : undefined}
              onClick={() => select({ value: typed, label: typed })}
              onMouseMove={() => setActiveIndex(0)}
              className="flex cursor-default items-center gap-2 rounded-md py-1.5 pr-2 pl-1.5 text-sm outline-hidden select-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
            >
              <PlusIcon className="size-4 shrink-0" />
              <span className="truncate">{createLabelText}</span>
            </li>
          )}

          {filtered.map((option, index) => (
            <li
              key={option.value}
              id={`${listId}-opt-${option.value}`}
              role="option"
              aria-selected={option.value === value}
              data-slot="combobox-item"
              data-highlighted={
                index + (canCreate ? 1 : 0) === highlightedIndex ? "" : undefined
              }
              onClick={() => select(option)}
              onMouseMove={() => setActiveIndex(index + (canCreate ? 1 : 0))}
              className="flex cursor-default items-center gap-2 rounded-md py-1.5 pr-2 pl-1.5 text-sm outline-hidden select-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
            >
              <CheckIcon
                className={cn(
                  "size-4 shrink-0",
                  option.value === value ? "opacity-100" : "opacity-0"
                )}
              />
              <span className="truncate">{option.label}</span>
              {showHint(option) && (
                <span className="ml-auto shrink-0 text-xs text-muted-foreground">
                  {option.hint}
                </span>
              )}
            </li>
          ))}
        </ul>

        {filtered.length === 0 && !canCreate && (
          <p
            data-slot="combobox-empty"
            className="px-4 py-6 text-center text-sm text-balance text-muted-foreground"
          >
            {emptyText}
          </p>
        )}
      </PopoverContent>
    </Popover>
  )
}

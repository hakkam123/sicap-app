# AGENTS.md — sicap-app

## Wajib Dibaca Setiap Sesi

Sebelum mengerjakan task apapun, baca file-file berikut di root project ini:

1. `Project.md` — PRD lengkap: requirements, skema database, user stories, do's & don'ts
2. `Master Data Sparepart.xlsx` — Data master historis (4.306 baris, 2020–2025):
   - Sheet **"Table"**: data consume historis per tahun (kolom: Year, PN BAAN, Desc, Qty, Amount, Price Per Unit, Area, Machine/Station)
   - Sheet **"Sheet2"**: mapping Part → Area → Machine — ini adalah **data seed awal** database (3.244 baris)
3. `Data Consume Sparepart 0726.xlsx` — Contoh format input consume harian Juli 2026 (kolom: Date, Part Number, Desc, qty, Amount) — ini **format Excel yang harus bisa diimport** oleh sistem

---

## Ringkasan Proyek

Nama aplikasi: **SICAP** (Sistem Informasi Consume Sparepart)

Aplikasi web internal **monolith** — Laravel 11 + Inertia.js + Vue 3 dalam **satu project**. Tidak ada project frontend terpisah.

| Atribut | Detail |
|---|---|
| Framework | Laravel 11, PHP 8.2+ |
| Database | SQL Server 2019/2022 |
| Frontend | Vue 3 + Inertia.js + Tailwind CSS |
| Auth (web) | Laravel Breeze (session-based) |
| Auth (API) | Laravel Sanctum (Bearer token) |
| Role | `admin`, `user` (via Spatie Laravel Permission) |
| Queue | Laravel Queue — database driver |
| Import Excel | Maatwebsite Laravel Excel |
| Chart | Chart.js via vue-chartjs |

---

## Struktur Navigasi

```
Sidebar:
├── Dashboard
├── Master Data
│   ├── Area
│   ├── Machine / Station
│   └── Part Number
├── Mapping
│   ├── Part ↔ Area        ← menu TERPISAH dari Part ↔ Machine
│   └── Part ↔ Machine     ← menu TERPISAH dari Part ↔ Area
├── Consume
│   ├── Input Manual
│   └── Import Excel
├── Import Log
└── User Management        [admin only]
```

---

## Fakta Kritis dari Data Aktual — Wajib Dipatuhi

- **Format tanggal di Excel consume** adalah teks Indonesia: `"1 juli 2026"` — harus diparsing dengan mapping nama bulan Indonesia, bukan `strtotime()` biasa
- **`qty` bernilai negatif** — konvensi keluar stok = negatif. Jangan validasi `quantity > 0`; kolom `quantity` di tabel `consumes` harus menerima nilai negatif
- **Data consume harian TIDAK punya kolom Area & Machine** — `area_id` dan `machine_id` di tabel `consumes` harus **NULLABLE**
- **Dua menu Mapping terpisah**: Part↔Area dan Part↔Machine — jangan digabung dalam satu halaman/form

---

## Skema Database (Ringkas)

```
users
areas          (soft delete)
machines       (soft delete, FK → areas)
part_numbers   (soft delete)
area_part_number        ← pivot: areas ↔ part_numbers
machine_part_number     ← pivot: machines ↔ part_numbers
consumes       (area_id NULLABLE, machine_id NULLABLE, quantity boleh negatif)
import_logs
```

Index wajib di tabel `consumes`: `consumed_at`, `area_id`, `machine_id`, `part_number_id`

---

## Konvensi Kode

- PHP: PSR-12. Vue: Vue 3 Composition API + `<script setup>`
- Validasi server-side: selalu gunakan **Laravel FormRequest**
- **Hindari N+1 query** — gunakan eager loading (`with()`) dan agregasi SQL
- **SQL Server**: hindari sintaks MySQL-only (`GROUP_CONCAT`, `ON DUPLICATE KEY`, `LIMIT` → gunakan `TOP`, dll)
- Import Excel dijalankan via **Queue Job** (`ImportExcelJob`) — jangan proses dalam HTTP request
- Semua konfigurasi sensitif di **`.env`** — jangan hardcode

---

## Yang Tidak Boleh Dilakukan

- ❌ Membuat project frontend terpisah (Nuxt, Next.js, dll) — ini monolith Inertia
- ❌ Menggunakan `quantity > 0` validation di consume
- ❌ Hard delete master data yang punya relasi ke `consumes` — gunakan soft delete
- ❌ Proses import Excel >500 baris secara sinkron dalam satu HTTP request
- ❌ Menggunakan sintaks SQL khusus MySQL
- ❌ Menggabungkan menu Mapping Part↔Area dan Part↔Machine dalam satu halaman
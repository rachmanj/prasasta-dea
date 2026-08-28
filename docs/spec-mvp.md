# Prasasta ERP — Spec MVP

> Aplikasi keuangan (ERP ringan) untuk Yayasan LPK (Lembaga Pendidikan & Keterampilan).
> Status: DRAFT — menunggu review Iwan sebelum implementasi.

---

## 1. Goal

Bikin aplikasi pencatatan keuangan sederhana buat yayasan LPK. Prioritas MVP:

1. **Catat uang masuk & keluar** (double-entry / jurnal berpasangan)
2. **Rekonsiliasi bank** (import CSV statement, auto-match + manual match)
3. **Hutang / piutang** (siklus ringan: jatuh tempo, status, riwayat pembayaran)
4. **Export ke Excel** (semua laporan + daftar transaksi)

---

## 2. Scope

### IN (MVP)

- Auth multi-user + role: **Admin**, **Bendahara**, **Pengurus/Ketua**
- Chart of Accounts (template standar yayasan LPK)
- Pencatatan transaksi: **BKM** (uang masuk), **BKK** (uang keluar), **Transfer** antar rekening, **Jurnal Umum** (penyesuaian)
- Jurnal berpasangan otomatis di belakang layar (user cukup isi form)
- Rekonsiliasi bank: import CSV (BCA / BRI / Mandiri), auto-match tanggal+nominal, manual match
- Hutang/Piutang: tagihan dengan jatuh tempo, status (belum lunas / sebagian / lunas), riwayat pembayaran
- Laporan: **Arus Kas**, **Posisi Hutang/Piutang**, **Laba Rugi sederhana**
- Export Excel (semua laporan + daftar transaksi)
- Dark mode, UI Bahasa Indonesia

### OUT (MVP — nanti / tidak)

- Pajak (PPN/PPh)
- Budgeting (anggaran vs realisasi)
- Multi-tenant / multi-yayasan
- Multi-mata uang (IDR saja)
- Modul payroll, manajemen aset, penyusutan
- Approval workflow / otorisasi multi-level

---

## 3. Tech Decisions

| Aspek | Keputusan |
|---|---|
| Backend | Laravel 13.x |
| Frontend | Inertia.js v3 + React + Ant Design (AntD) |
| DB | MySQL / MariaDB |
| Auth | Laravel Breeze (Inertia React) |
| RBAC | Spatie Laravel Permission (role: admin, bendahara, pengurus) |
| Export Excel | Maatwebsite / Laravel Excel |
| Bahasa UI | Bahasa Indonesia (`dayjs.locale('id')`) |
| Mata uang | IDR tunggal |
| Nomor voucher | Auto-sequential per jenis: `BKM-2026-0001`, `BKK-2026-0001`, `JU-2026-0001` |

> **Catatan arsitektur:** semua halaman pakai **Inertia server-side props** (bukan client-side API call) — konsisten dengan standar Iwan.

---

## 4. DB Schema

### `users`
- `id`, `name`, `email`, `password`, `remember_token`, timestamps
- Role via Spatie (`roles`, `permissions`, `role_has_permissions`)

### `accounts` — Chart of Accounts
- `id`
- `code` (varchar, unique — kode akun 4 digit, mis. `4100`)
- `name`
- `type` (enum: `asset`, `liability`, `equity`, `revenue`, `expense`)
- `is_bank` (bool — akun rekening bank)
- `bank_name` (nullable), `account_number` (nullable) — hanya jika `is_bank`
- `is_active` (bool), timestamps

### `transactions` — header jurnal
- `id`
- `journal_no` (auto: BKM-… / BKK-… / JU-…)
- `type` (enum: `receipt`, `payment`, `transfer`, `journal`)
- `date`
- `description` / memo
- `ref_no` (nomor referensi eksternal, nullable)
- `status` (enum: `draft`, `posted`)
- `user_id` (pembuat), timestamps

### `journal_entries` — baris jurnal
- `id`, `transaction_id` (FK)
- `account_id` (FK)
- `debit` (decimal 15,2), `credit` (decimal 15,2)
- `description` (nullable), timestamps
- *Constraint: total debit = total credit per transaction.*

### `contacts` — counterparty (siswa / vendor / pengajar / donatur)
- `id`, `name`, `type` (enum: `student`, `vendor`, `instructor`, `donor`, `other`)
- `phone` (nullable), `address` (nullable), timestamps

### `bills` — tagihan hutang/piutang (siklus ringan)
- `id`, `contact_id` (FK)
- `type` (enum: `receivable`, `payable`)
- `bill_no` (auto)
- `description`
- `amount` (decimal 15,2)
- `due_date`
- `status` (enum: `open`, `partial`, `paid`)
- `paid_amount` (decimal, dihitung dari pembayaran)
- timestamps

### `bill_payments` — riwayat pembayaran tagihan
- `id`, `bill_id` (FK), `transaction_id` (FK — transaksi kas yang melunasi)
- `amount`, `date`, timestamps

### `bank_statement_lines` — baris statement import CSV
- `id`, `account_id` (FK — rekening bank)
- `date`, `description`, `amount` (signed)
- `source_ref` (unique id baris CSV, buat dedup)
- `is_matched` (bool), `transaction_id` (FK, nullable — transaksi yang match)
- timestamps

### `reconciliations` — sesi rekonsiliasi
- `id`, `account_id` (FK), `as_of_date`, `closing_balance` (saldo per statement)
- `status` (enum: `open`, `completed`), `started_by`, timestamps

### Alur double-entry otomatis

| Transaksi | Debit | Kredit |
|---|---|---|
| BKM (uang masuk) | Kas/Bank | Akun pendapatan (kategori) |
| BKK (uang keluar) | Akun beban (kategori) | Kas/Bank |
| Transfer | Bank tujuan | Bank asal |
| Jurnal Umum | manual | manual |
| Buat tagihan piutang | Piutang Usaha | Pendapatan terkait |
| Buat tagihan hutang | Beban terkait | Hutang Usaha |
| Terima bayar piutang | Kas/Bank | Piutang Usaha |
| Bayar hutang | Hutang Usaha | Kas/Bank |

> **Keputusan penting (dikonfirmasi saat review):** hutang/piutang diposting ke GL (accrual-lite) supaya Laba Rugi & Neraca konsisten. Alternatif lebih sederhana = cash-basis (tagihan murni memorandum, Laba Rugi diakui saat kas masuk/keluar). Dea rekomendasi accrual-lite karena Iwan sudah pilih double-entry.

### Template Chart of Accounts (default)

```
1xxx ASET
  1000  Kas
  1010  Bank (is_bank; 1011 Bank BCA, 1012 Bank BRI, dst.)
  1100  Piutang Usaha
2xxx KEWAJIBAN
  2100  Hutang Usaha
3xxx EKUITAS
  3000  Ekuitas Yayasan
  3100  Saldo Awal / Laba Ditahan
4xxx PENDAPATAN
  4100  Pendapatan Kursus / Pelatihan
  4200  Pendapatan Donasi
  4300  Pendapatan Hibah / Bantuan
5xxx BEBAN
  5100  Beban Honor Pengajar
  5200  Beban Gaji Staf
  5300  Beban Sewa
  5400  Beban Listrik / Air / Internet
  5500  Beban Konsumsi
  5510  Beban ATK
  5600  Beban Pemeliharaan
  5700  Beban Operasional Lainnya
```

> **Keputusan:** akun pendapatan/beban **berfungsi sebagai kategori** (Q7). Tidak ada tabel `categories` terpisah di MVP — bikin lebih bersih. Bisa ditambah layer label kategori nanti kalau Iwan mau nama yang lebih ramah.

---

## 5. UI/UX (Halaman)

- **Login**
- **Dashboard** — ringkasan saldo kas/bank, arus kas bulan berjalan, total piutang/hutang outstanding
- **Master:**
  - Chart of Accounts (CRUD)
  - Kontak (CRUD — siswa, vendor, pengajar, donatur)
  - Rekening Bank (CRUD)
  - Users & Roles (admin only)
- **Transaksi:**
  - Uang Masuk (BKM) — list + create/edit/delete
  - Uang Keluar (BKK) — list + create/edit/delete
  - Transfer antar rekening
  - Jurnal Umum
- **Hutang/Piutang:**
  - Piutang — list + create + catat pembayaran
  - Hutang — list + create + catat pembayaran
- **Rekonsiliasi Bank:**
  - Import CSV statement
  - Match otomatis + manual
  - Status rekonsiliasi per rekening
- **Laporan:**
  - Arus Kas (per kategori, per periode)
  - Laba Rugi
  - Posisi Hutang/Piutang
  - Export Excel (semua)

---

## 6. Routes (Inertia web routes, server-side props)

```
GET   /login
GET   /dashboard
GET   /accounts                 (COA)
POST  /accounts  PUT/PATCH  /accounts/{id}  DELETE /accounts/{id}
GET   /contacts + CRUD
GET   /bank-accounts + CRUD
GET   /transactions?type=receipt|payment|transfer|journal
POST  /transactions  PUT/PATCH /transactions/{id}  DELETE /transactions/{id}
GET   /bills?type=receivable|payable
POST  /bills  PUT/PATCH /bills/{id}  DELETE /bills/{id}
POST  /bills/{id}/payments        (catat pembayaran)
GET   /reconciliations  POST /reconciliations/import  POST /reconciliations/{id}/match
GET   /reports/cash-flow
GET   /reports/profit-loss
GET   /reports/receivables-payables
GET   /reports/export?type=...    (Excel download)
```

---

## 7. Risks

1. **Format CSV bank bervariasi** — perlu parser yang toleran + fallback mapping kolom manual (BCA/BRI/Mandiri beda layout). Mitigasi: mapping kolom interaktif saat import.
2. **Kompleksitas double-entry vs "sederhana"** — mitigasi: auto-journal, user cuma isi form satu halaman.
3. **Nama mirip "Prasasta" vs "Prasaba" (hotel-resort-erp)** — dua app berbeda, gampang tertukar. Repo/DB/nama proyek harus konsisten pakai `prasasta`.
4. **Keputusan accrual-lite vs cash-basis** (hutang/piutang) — harus dikunci sebelum implementasi biar laporan konsisten.
5. **Saldo awal** — perlu fitur set saldo awal tiap akun saat onboarding (migration), biar laporan akurat sejak hari pertama.

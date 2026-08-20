# Spec: Cash Advance (Kas Bon / Uang Muka Karyawan)

> Status: APPROVED (hasil grill-me 2026-08-20) · Siap implementasi

## 1. Goal

Karyawan lapangan sering meminta **uang muka (cash advance)** ke kasir (Elma). Kas keluar saat itu juga, tapi Elma baru mencatatnya sebagai **beban** setelah karyawan **merealisasikan** advance dan menyerahkan nota.

Fitur ini memungkinkan:
- Mencatat uang muka yang keluar sebagai **aset (piutang karyawan)**, bukan beban.
- Mencatat **realisasi** (per nota) yang mengubahnya menjadi beban.
- Melacak **bon outstanding** per karyawan.

## 2. Scope

### IN
- Buat kas bon (advance) → journal `Dr 1150 / Cr Kas`.
- Realisasi advance → **multi-baris** (akun beban + nominal + keterangan) → `Dr Beban / Cr 1150`.
- **Sisa kas balik** → `Dr Kas / Cr 1150`.
- **Kurang bayar** (nota > sisa advance) → `Dr Beban / Cr Kas` (reimburse selisih).
- List advance + filter per karyawan + status (`open` / `partial` / `settled`).
- Detail advance (jurnal + riwayat realisasi).
- Status otomatis: `open → partial → settled`.

### OUT (MVP)
- Approval workflow / batas nominal.
- Upload scan nota (cukup catatan teks di deskripsi).
- Tenggat/deadline advance otomatis.
- Multi-mata uang (IDR tunggal).
- Integrasi rekonsiliasi bank (advance dari kas, bukan bank).

## 3. Tech Decisions

- **Modul terpisah** (mirip Bills): `CashAdvance` model + `CashAdvanceService` + `CashAdvanceController` + Inertia pages.
- Semua posting lewat **`TransactionService`** (`type = 'journal'`) → double-entry terjamin & numbering konsisten.
- Akun baru **`1150 Kas Bon / Uang Muka Karyawan`** (`asset`) — resolve by `code`, bukan hardcoded ID.
- Kontak tipe baru **`employee`** (tambah ke enum `contacts.type`).
- Nomor dokumen **`ADV-YYYY-NNNN`** via `CashAdvanceService::generateAdvanceNo()` (mirip `BillService::generateBillNo`).
- RBAC: `bendahara` + `admin` buat/realisasi/hapus; `pengurus` read-only.

## 4. DB Changes

### Migrasi 1 — tipe kontak
Alter `contacts.type` enum: tambah `'employee'`.
```php
// ->change() butuh doctrine/dbal (lihat .cursorrules)
$table->enum('type', ['student','vendor','instructor','donor','employee','other'])->default('other')->change();
```

### Migrasi 2 — `cash_advances`
| kolom | tipe | ket |
|---|---|---|
| id | bigint pk | |
| advance_no | string(50) unique | `ADV-2026-0001` |
| contact_id | foreignId | karyawan (kontak tipe employee) |
| amount | decimal(15,2) | nominal advance |
| date | date | tgl kas keluar |
| description | string(255) nullable | keperluan |
| status | enum(open,partial,settled) default open | |
| realized_amount | decimal(15,2) default 0 | total realisasi (beban) |
| returned_amount | decimal(15,2) default 0 | total kas balik |
| transaction_id | foreignId nullable | journal `Dr 1150 / Cr Kas` (advance) |
| user_id | foreignId nullable | pembuat |
| timestamps | | |

### Migrasi 3 — `cash_advance_realizations`
Satu baris = satu **event realisasi** (bisa multi-baris akun; baris detail tersimpan di `journal_entries` via `transaction_id`).
| kolom | tipe | ket |
|---|---|---|
| id | bigint pk | |
| cash_advance_id | foreignId | |
| date | date | tgl realisasi |
| transaction_id | foreignId | journal realisasi |
| description | string(255) nullable | catatan/nota |
| timestamps | | |

## 5. UI/UX (Bahasa Indonesia, AntD dark mode)

- **Menu**: "Kas Bon" di sidebar (grup Transaksi).
- **Index** (`Pages/CashAdvances/Index.tsx`): table daftar advance — No, Tanggal, Karyawan, Nominal, Terealisasi, Sisa, Status (Tag `open`/`partial`/`settled`), aksi (detail/realisasi). Filter per karyawan + status.
- **Create** (`Form.tsx`): pilih Karyawan (kontak `employee`), tanggal, nominal, keperluan.
- **Show** (`Show.tsx`): detail advance + jurnal advance (expandable) + riwayat realisasi (expandable journal) + sisa outstanding.
- **Realisasi** (`Realize.tsx` atau modal): form multi-baris — tiap baris (Akun beban, Nominal, Keterangan) + opsional baris "Kas kembali". Submit → validasi total tidak melebihi sisa (kecuali kurang bayar), post journal, refresh status.

## 6. Endpoints (routes/web.php, literal sebelum `{param}`)

```
GET   cash-advances                 -> index      (auth)
GET   cash-advances/create          -> create     (role:admin|bendahara)
POST  cash-advances                 -> store      (role:admin|bendahara)
GET   cash-advances/{advance}       -> show       (auth)
POST  cash-advances/{advance}/realize -> realize  (role:admin|bendahara)
DELETE cash-advances/{advance}      -> destroy    (role:admin|bendahara)
```

## 7. Accounting Rules (jurnal)

1. **Buat advance** (amount A): `Dr 1150 A / Cr 1000 A` — status `open`.
2. **Realisasi** (total R = Σ beban + kas kembali):
   - `Dr [akun beban…] + Dr 1000 (jika ada kas kembali) / Cr 1150 R` (selama `R ≤ sisa`).
   - Kurang bayar: jika `R > sisa`, `Cr 1150 = sisa`, sisanya `Cr 1000` (reimburse ke karyawan).
3. **Status**: `settled` bila `realized + returned ≥ amount`; `partial` bila `0 < realized+returned < amount`.

> Implementasi rinci (pemecahan baris jurnal, validasi, refresh status) final saat delegasi ke cursor-agent.

## 8. Risks / Pitfalls

- **Enum `contacts.type` butuh `doctrine/dbal`** untuk `->change()` (sudah terpasang — lihat catatan migrasi sebelumnya).
- **Route ordering**: `cash-advances/create` harus sebelum `cash-advances/{advance}` (pitfall #7 .cursorrules).
- **Jangan pernah Dr 1150 negatif**: realisasi/return tidak boleh melebihi sisa advance (validasi di service).
- **Seed drift**: akun `1150` harus ditambah ke `AccountSeeder` sekalian, biar `migrate:fresh --seed` tidak kehilangan.
- **Hapus advance** yang sudah ada realisasi harus diblokir (mirip `BillService::delete`).
- **Laba Rugi tidak boleh kena**: advance (Dr 1150) bukan beban; hanya realisasi (Dr Beban) yang masuk P&L.

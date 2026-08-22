# Spec: Upload Rekening Koran + Rekonsiliasi Bank (Mandiri Kopra)

> Status: APPROVED (hasil grill-me 2026-08-20) · Siap implementasi

## 1. Goal

Mengganti modul rekonsiliasi lama (CSV + match 1:1) dengan versi baru yang bisa **upload Rekening Koran PDF (Mandiri Kopra)**, parse otomatis menjadi baris transaksi, lalu mencocokkan dengan **buku (GL rekening Giro Mandiri)** memakai **N:M matching**, lengkap dengan **reconciliation statement** dan **carry-forward** item outstanding.

Contoh file: `RK JULI 2026.pdf` (Mandiri Kopra, PDF berbasis teks).

## 2. Scope

### IN
- Upload PDF Rekening Koran (Mandiri Kopra) → parse → baris bank (tgl, keterangan, ref, debit, kredit, saldo).
- Sesi rekonsiliasi **per bulan per rekening** (unique `account_id + periode`).
- **N:M match groups** (beberapa baris buku → 1 baris bank, dan sebaliknya) — penting karena bank menggabungkan (contoh: "Tarik Tunai 14.333.900" = 3 baris buku).
- Auto-match (nominal sama + tanggal dekat) + manual match.
- **Opening/closing balance** bank (dari statement) & buku (dari GL).
- **Reconciliation statement**: adjusted bank vs book, unexplained difference ≈ 0.
- **Carry-forward** item outstanding ke bulan berikutnya.
- Single-user (bendahara/admin), tanpa validation workflow.

### OUT (MVP)
- AI/OpenRouter parsing (PDF teks, tidak butuh).
- CSV import (dibuang).
- Validation workflow preparer/validator.
- Multi-bank (desain tetap pakai `account_id`, tapi fokus Giro Mandiri).

## 3. Tech Decisions

- **Ganti total** modul lama: `ReconciliationController`, `BankStatementImporter` (CSV), tabel `reconciliations` + `bank_statement_lines` (semua kosong, 0 data).
- PDF parser PHP untuk Mandiri Kopra: rekomendasi `smalot/pdfparser` atau `spatie/pdf-to-text` (butuh `pdftotext`). Output → parse format Mandiri dengan regex (tanggal `DD Mmm YYYY`, nominal `1.234.567,89`, saldo berjalan).
- **Sign convention** (sama seperti sarang/payreq): bank **debit** (uang keluar) ↔ buku **kredit** (Cr 1011). Match valid bila `Σ(bank debit−credit) + Σ(book credit−debit) ≈ 0` (toleransi 0.005).
- **Book lines** = transaksi (journal entries) pada akun bank (`1011`) dalam periode; tidak perlu tabel snapshot (GL lokal).
- Status sesi: `draft → in_review → completed` (tanpa validasi).

## 4. DB Changes

Drop `reconciliations` & `bank_statement_lines` (kosong), lalu buat ulang:

### `bank_reconciliations`
| kolom | tipe | ket |
|---|---|---|
| id | bigint pk | |
| account_id | foreignId | rekening bank |
| period | date | awal bulan (2026-07-01) |
| opening_balance_bank | decimal(15,2) nullable | dari statement |
| closing_balance_bank | decimal(15,2) nullable | dari statement |
| opening_balance_book | decimal(15,2) nullable | dari GL |
| closing_balance_book | decimal(15,2) nullable | dari GL |
| status | enum('draft','in_review','completed') default 'draft' | |
| started_by | foreignId nullable | |
| completed_at / completed_by | nullable | |
| notes | text nullable | |
| timestamps | | |
| **UNIQUE (account_id, period)** | | |

### `bank_statement_lines`
| kolom | tipe | ket |
|---|---|---|
| id | bigint pk | |
| bank_reconciliation_id | foreignId cascade | |
| transaction_date | date | posting date |
| description | text | "Tarik Tunai", "Biaya Adm", dst |
| reference | varchar(191) nullable | reference no |
| debit | decimal(15,2) default 0 | |
| credit | decimal(15,2) default 0 | |
| balance | decimal(15,2) nullable | saldo berjalan |
| matched_status | enum('unmatched','matched','manual','excluded') default 'unmatched' | |
| exclude_reason | varchar(255) nullable | |
| line_order | int nullable | |
| timestamps | | |

### `reconciliation_match_groups`
| kolom | tipe | ket |
|---|---|---|
| id | bigint pk | |
| bank_reconciliation_id | foreignId cascade | |
| match_type | enum('auto','manual') | |
| bank_total / book_total / difference | decimal(15,2) | |
| created_by | foreignId nullable | |
| timestamps | | |

### Pivot
- `match_group_bank_lines`: `match_group_id`, `bank_statement_line_id` (UNIQUE).
- `match_group_book_lines`: `match_group_id`, `transaction_id` (UNIQUE; book side = transaksi pada akun bank).

## 5. PDF Parser — Format Mandiri Kopra

Contoh `RK JULI 2026.pdf`. Struktur yang harus di-parse:
- **Summary**: `Account No`, `Period` (`01 Jul 2026 - 31 Jul 2026`), `Opening Balance`, `Closing Balance`, `No. of Debit`, `Total Amount Debited`, `No. of Credit`, `Total Amount Credited`.
- **Kolom tabel**: `Posting Date` · `Remark` · `Reference No.` · `Debit` · `Credit` · `Balance`.
- Baris contoh: `06 Jul 2026 13:17:34` | `CK 180878-Tarik Tunai` | `00180878` | debit `14,333,900.00` | credit `0.00` | balance `2,547,380.35`.

Parser harus menghasilkan: `{opening_balance, closing_balance, lines: [{transaction_date, description, reference, debit, credit, balance}]}`.

> **Penting:** teks PDF Mandiri ekstraknya "jumbled" (kolom terpecah antar-baris). Parser harus rekonstruksi baris dari saldo berjalan + urutan tanggal/nominal. Test pakai file contoh asli.

## 6. UI/UX (Bahasa Indonesia, AntD dark)

- **Menu**: "Rekonsiliasi Bank" (ganti halaman lama).
- **Index**: list sesi per bulan + status + tombol "Mulai Rekonsiliasi".
- **Create**: pilih rekening bank (default Giro Mandiri) + periode + upload PDF → parse → buat sesi.
- **Show (Review)**: dua panel (baris bank vs baris buku), checkbox, bilah balance (bank net / book net / difference), auto-match, match manual, exclude, opening/closing balances, statement, tombol "Selesaikan".
- **Carry-forward**: saat sesi baru, item `unmatched` dari sesi sebelumnya tampil sebagai catatan pembuka.

## 7. Accounting Rules

1. **Sign convention**: bank debit ↔ book credit (berlawanan).
2. **Match valid**: `bank_total + book_total ≈ 0` (bank `debit−credit`, book `credit−debit`).
3. **Statement**: `adjusted_bank = closing_bank + Σ book unmatched`; `adjusted_book = closing_book − Σ bank unmatched`; `unexplained = adjusted_bank − adjusted_book` (≈ 0 untuk selesai).
4. **Selesai** bila tidak ada `unmatched` + `unexplained ≈ 0`.
5. **Carry-forward**: baris bank `unmatched` (dan book unmatched) dari bulan lalu masuk sebagai pembuka sesi bulan ini.

## 8. Risks / Pitfalls

- **Jangan ubah sign convention** — salah arah = match tak pernah balance (pakai test `BankReconciliationSignConventionTest`).
- **PDF text jumbled** — parser harus robust; test dengan `RK JULI 2026.pdf` asli.
- **Route ordering**: literal route (`/reconciliations/create`) sebelum `/{param}`.
- **Referensi implementasi**: `docs/bank-reconciliation-module.md` (sarang-erp) — port `ReconciliationBalanceService` + matching, TAPI skip AI/OpenRouter, skip queue (parse PDF sinkron), skip validation workflow.
- **Tabel lama kosong** — aman drop; jangan ada data loss.
- **Laba Rugi tidak terpengaruh** — rekonsiliasi hanya mencocokkan, tidak posting jurnal baru.

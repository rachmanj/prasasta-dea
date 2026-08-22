# Spec: Kas Opname (Cash Opname / Petty Cash Balance Control)

> Status: APPROVED (hasil grill-me 2026-08-22) · Siap implementasi

## 1. Goal

Fitur untuk mencatat **opname fisik kas tunai** (akun `1000 Kas`) — membandingkan **saldo buku** (GL) dengan **hasil hitung fisik uang tunai** (per denominasi pecahan), lalu menghasilkan **berita acara** + menandai **selisih** (jika ada) dan boleh mem-posting **jurnal penyesuaian**.

Acuan format: `PCBC JULI 2026.pdf` (Petty Cash Balance Control ARKA) — Section A saldo buku, Section B denominasi (uang kertas + logam), Section C total fisik, Difference (A−C), terbilang, 3 kolom tanda tangan.

## 2. Scope

### IN
- Buat record opname per tanggal: saldo buku (snapshot), rincian fisik per denominasi, total fisik, selisih, terbilang.
- Saldo buku **otomatis dari GL** akun `1000 Kas` per tanggal opname (read-only, snapshot).
- Denominasi tetap (lihat §5). User hanya isi jumlah lembar/keping.
- Selisih `fisik ≠ buku` → simpan sebagai **temuan** + tombol opsional **"Posting Penyesuaian"** (jurnal).
- **PDF berita acara** (format mirip PCBC) + record tersimpan.
- Single pengisi (bendahara/admin), tanpa workflow validasi.
- Nomor dokumen otomatis `OPN-YYYY-NNNN`.

### OUT (MVP)
- Opname bank/giro (sudah pakai rekonsiliasi bank).
- Multi akun kas (fokus `1000 Kas` saja).
- Approval workflow / tanda tangan elektronik (Checked/Verified dibiarkan kosong untuk tanda tangan manual di kertas).
- Reminder/pengingat opname berkala.
- Rekonsiliasi otomatis selisih tanpa persetujuan.

## 3. Tech Decisions

- **PDF**: tambah `barryvdh/laravel-dompdf` (pure PHP, aman di VPS PHP 8.4). Render Blade template `resources/views/pdf/cash-opname.blade.php`.
- **Terbilang**: helper baru `app/Support/Terbilang.php` → `Terbilang::rupiah(float $amount): string` (contoh: `15.183.500` → `Lima Belas Juta Seratus Delapan Puluh Tiga Ribu Lima Ratus Rupiah`).
- **Saldo buku**: `ReportService::accountBalance($cashId, $asOfDate)` — snapshot saat create, disimpan ke kolom (bukan dihitung ulang).
- **Akun penyesuaian**: selisih kurang → `5700 Beban Operasional Lainnya`; selisih lebih → **akun baru `4600 Pendapatan Lain-lain`** (tambah ke `AccountSeeder`).
- **Jurnal penyesuaian**: lewat `TransactionService` (type `journal`, nomor `JU-YYYY-NNNN`), note merujuk nomor opname.

## 4. DB Changes

### `cash_opnames`
| kolom | tipe | ket |
|---|---|---|
| id | bigint pk | |
| number | varchar(191) unique | `OPN-YYYY-NNNN` |
| date | date | tanggal opname |
| account_id | foreignId | akun `1000 Kas` |
| book_balance | decimal(15,2) | snapshot saldo GL saat opname |
| physical_balance | decimal(15,2) | total fisik (Σ line amount) |
| difference | decimal(15,2) | `book_balance − physical_balance` |
| status | enum('open','adjusted') default 'open' | |
| adjustment_transaction_id | foreignId nullable → transactions | jurnal penyesuaian |
| prepared_by | foreignId nullable → users | pengisi |
| notes | text nullable | |
| timestamps | | |

### `cash_opname_lines`
| kolom | tipe | ket |
|---|---|---|
| id | bigint pk | |
| cash_opname_id | foreignId cascade | |
| denomination | int | nilai pecahan (rupiah) |
| type | enum('banknote','coin') | uang kertas / logam |
| units | int default 0 | jumlah lembar/keping |
| amount | decimal(15,2) | `denomination × units` |
| timestamps | | |

## 5. Denominasi (tetap, seeded/const)

- **Uang kertas (banknote)**: 100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 100.
- **Uang logam (coin)**: 1000, 500, 200, 100, 50, 25.

Simpan sebagai konstanta di `CashOpnameService` (array 15 pecahan). Form menampilkan grid dua kelompok (kertas vs logam), user isi `units`.

## 6. Formula & Status

- `physical_balance = Σ(denomination × units)`.
- `difference = book_balance − physical_balance`.
  - `> 0` → **Selisih Kurang** (fisik < buku, shortage)
  - `< 0` → **Selisih Lebih** (fisik > buku, surplus)
  - `= 0` → **Cocok / Balanced**
- Badge status: **Cocok** (diff 0) · **Selisih Kurang X** · **Selisih Lebih X** · **Disesuaikan** (sudah posting).

## 7. Accounting Rules (Posting Penyesuaian)

Hanya jika `difference ≠ 0` dan belum ada `adjustment_transaction_id`:

1. **Selisih kurang** (`difference > 0`): `Dr 5700 Beban Operasional Lainnya` / `Cr 1000 Kas`.
2. **Selisih lebih** (`difference < 0`): `Dr 1000 Kas` / `Cr 4600 Pendapatan Lain-lain` (nominal = `abs(difference)`).
3. Nomor jurnal `JU-YYYY-NNNN` via `TransactionService::generateJournalNo()`, type `journal`, note `Penyesuaian selisih kas opname {OPN-XXXX}`.
4. Setelah posting: `status = 'adjusted'`, `adjustment_transaction_id = transaksi`. Guard: tidak bisa posting ulang; tidak bisa hapus opname yang sudah adjusted (atau batalkan jurnal dulu).
5. Opname **tidak** mem-posting jurnal saat create — hanya saat user klik "Posting Penyesuaian".

## 8. UI/UX (Bahasa Indonesia, AntD dark)

- **Menu**: "Kas Opname" (di bawah Rekonsiliasi Bank).
- **Index**: list opname (nomor, tanggal, saldo buku, fisik, selisih, status) + tombol "Opname Baru".
- **Create**: pilih tanggal → grid denominasi (kertas & logam) → preview saldo buku (read-only) + total fisik + selisih + terbilang realtime → simpan.
- **Show**: detail (header, dua kelompok denom, total, selisih badge, terbilang) + tombol **"Cetak PDF"** (berita acara) + **"Posting Penyesuaian"** (hanya jika diff ≠ 0 & open) + tombol kembali.
- **PDF berita acara**: mirror PCBC — header (Prasasta Learning Centre · BERITA ACARA CASH OPNAME · tanggal · proyek "Prasasta"), Section A saldo buku, Section B tabel denom, Section C total + difference, terbilang, 3 kolom tanda tangan (Prepared = nama user + role; Checked By & Verified kosong).

## 9. Roles / Routes

- `bendahara`, `admin`: create, show, pdf, adjust.
- `pengurus`: read-only (index, show, pdf).

Routes (literal sebelum param):

```
GET  /cash-opnames                 index
GET  /cash-opnames/create          create form
POST /cash-opnames                 store
GET  /cash-opnames/{opname}        show
GET  /cash-opnames/{opname}/pdf    download berita acara (PDF)
POST /cash-opnames/{opname}/adjust post penyesuaian
```

Route binding `{opname}` → `CashOpname` di `AppServiceProvider` (seperti `{advance}`).

## 10. Tests (PHPUnit)

`CashOpnameTest`:
- create balanced (diff 0) → saldo buku = fisik, status open.
- create shortage (fisik < buku) → diff positif, badge.
- create surplus (fisik > buku) → diff negatif.
- posting penyesuaian shortage → jurnal balanced (Dr 5700 / Cr 1000), status adjusted.
- posting penyesuaian surplus → jurnal balanced (Dr 1000 / Cr 4600).
- guard: posting saat diff 0 ditolak; posting ulang ditolak.
- nomor `OPN-2026-0001` sekuensial.
- role: pengurus tidak bisa create/adjust.

## 11. Risks / Pitfalls

- **Route ordering**: `/cash-opnames/create` dan `/cash-opnames/{opname}/pdf` & `/adjust` sebelum `/{opname}`.
- **Snapshot jangan dihitung ulang** — `book_balance`/`physical_balance`/`difference` disimpan; jangan render dari GL saat Show.
- **Terbilang** harus benar untuk 0, jutaan, miliaran (test `15.183.500` → string yang tepat).
- **dompdf** font: pastikan output teks Latin/angka rapi (default ok).
- **Bahasa Indonesia** di semua label; tanggal `DD-MMM-YYYY` (dayjs `id`).
- Referensi pola: `CashAdvanceService` (numbering + guard jurnal), `ReconciliationService` (snapshot balance), `ReportService::accountBalance()`.

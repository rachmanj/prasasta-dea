# Spec: Program Pelatihan (Course/Training Program)

> Status: APPROVED (hasil grill-me 2026-08-22) · Siap implementasi

## 1. Goal

Menambah dimensi **Program Pelatihan** (kursus/batch) di atas double-entry yang sudah ada, supaya Prasasta ERP bisa menghitung **Laba Rugi per program**. Transaksi (uang masuk/keluar) bisa di-*tag* ke program; laporan menampilkan pendapatan, biaya, dan laba per batch.

Acuan data: `Laporan Program Pelatihan MAB-BMC Batch II Tahun 2026.xlsx` (pendapatan 96,8jt, biaya 32,2jt, laba 64,6jt).

## 2. Scope

### IN (MVP)
- Master `Program` (nama, jenis group/individual, periode, status).
- Tag `program_id` (nullable) di transaksi (uang masuk/keluar) — pilih program di form.
- **Peserta** ringan per program: nama, NIS (opsional), region (opsional), fee, jumlah dibayar, tanggal bayar, status bayar (informasional — bukan sumber jurnal).
- **Laba Rugi per program**: pendapatan (akun revenue) − biaya (akun expense), plus drill-down rincian.
- Akun COA baru untuk kategori biaya yang belum ada.

### OUT (MVP)
- Modul siswa lengkap (alamat, WA, ukuran baju/sepatu) → fase lanjutan.
- Jadwal mengajar / absensi trainer → fase lanjutan.
- Auto-jurnal dari pembayaran peserta (peserta = roster informasional; sumber kebenaran keuangan tetap transaksi).
- Dashboard / rekap per trainer.

## 3. Tech Decisions

- Program = dimensi tag, BUKAN modul akuntansi terpisah. Journal tetap via `TransactionService`.
- `program_id` nullable di `transactions` (tag satu program per transaksi; cukup untuk use case ini).
- Peserta = roster informasional. P&L pendapatan dihitung dari transaksi tag program (akun `4100`), bukan dari kolom `paid_amount` peserta.
- Report pakai tipe akun (`revenue` vs `expense`) dari journal entries transaksi yang di-tag program.

## 4. DB Changes

### `programs`
| kolom | tipe | ket |
|---|---|---|
| id | bigint pk | |
| code | varchar(191) unique | `PRG-YYYY-NNN` auto |
| name | varchar(255) | nama program |
| type | enum('group','individual') default 'group' | |
| start_date | date nullable | |
| end_date | date nullable | |
| status | enum('active','completed','cancelled') default 'active' | |
| notes | text nullable | |
| timestamps | | |

### `program_participants`
| kolom | tipe | ket |
|---|---|---|
| id | bigint pk | |
| program_id | foreignId cascade | |
| name | varchar(191) | nama siswa |
| nis | varchar(50) nullable | |
| region | varchar(50) nullable | |
| fee | decimal(15,2) default 0 | nominal kursus |
| paid_amount | decimal(15,2) default 0 | sudah dibayar |
| payment_date | date nullable | |
| timestamps | | |

Status bayar = accessor: `paid` (paid_amount >= fee), `partial` (0 < paid_amount < fee), `unpaid` (0).

### `transactions` (tambah kolom)
`program_id` foreignId nullable → programs.

## 5. COA (AccountSeeder + prod)

Tambah 2 akun baru:
- `5810` Beban Sertifikasi (expense)
- `5820` Beban Asuransi (expense)

Mapping kategori Excel → COA (untuk import historis, referensi):

| Kategori Excel | Akun |
|---|---|
| Makan · Air (galon/minum) | 5500 ATK & Konsumsi |
| Perlengkapan ATK | 5500 ATK & Konsumsi |
| Seragam · Training · Perlengkapan safety | 5800 Beban Pelatihan |
| Biaya fee pelatih | 5100 Honor Pengajar |
| Fuel | 5700 Operasional Lainnya |
| Biaya Sertifikasi | 5810 Beban Sertifikasi (baru) |
| Biaya Asuransi | 5820 Beban Asuransi (baru) |
| Pendapatan kursus siswa | 4100 Pendapatan Kursus/Pelatihan |

## 6. Accounting Rules

1. Transaksi uang masuk/keluar yang di-tag program TIDAK mengubah logika jurnal — hanya menambah `program_id` di header.
2. Laba Rugi program = `Σ net akun type=revenue` − `Σ net akun type=expense` dari semua transaksi `program_id = X`.
3. Peserta tidak mem-posting jurnal. `paid_amount` peserta = catatan; penerimaan uang tetap dicatat via "Uang Masuk" (tag program, akun 4100).
4. Hapus program: dilarang jika masih ada transaksi yang di-tag (guard), atau set `program_id` transaksi jadi null dulu.

## 7. UI/UX (Bahasa Indonesia, AntD dark)

- **Menu**: "Program" (top-level, di bawah Dashboard) untuk admin/bendahara; pengurus read-only.
- **Index**: list program + kolom ringkas (nama, jenis, periode, status, pendapatan, biaya, laba) + tombol "Program Baru".
- **Create/Edit**: nama, jenis (group/individual), tanggal mulai/selesai, status, notes.
- **Show**: detail program + **Laba Rugi** (pendapatan, biaya, laba) + 2 panel drill-down (rincian pendapatan & biaya) + tabel **Peserta** (tambah/edit/hapus peserta, status bayar badge).
- **Form Transaksi (Uang Masuk/Keluar)**: tambah dropdown "Program" (opsional, nullable).

## 8. Routes / Roles

```
GET    /programs                       index        (auth)
GET    /programs/create                create       (admin|bendahara)
POST   /programs                       store        (admin|bendahara)
GET    /programs/{program}             show         (auth)
GET    /programs/{program}/edit        edit         (admin|bendahara)
PUT    /programs/{program}             update       (admin|bendahara)
DELETE /programs/{program}             destroy      (admin|bendahara)
POST   /programs/{program}/participants        store   (admin|bendahara)
DELETE /participants/{participant}             destroy (admin|bendahara)
```

Binding `{program}` → Program di `AppServiceProvider`. Literal route sebelum param.

## 9. Service

`app/Services/ProgramService.php`:
- `generateCode()` → `PRG-YYYY-NNN`.
- `create(array)`, `update(Program, array)`.
- `addParticipant(Program, array)`, `updateParticipant`, `removeParticipant`.
- `destroy(Program)` — guard bila ada transaksi tag program.
- `profitLoss(Program)` → `{ revenue, expense, profit, revenue_lines, expense_lines }` (dari journal entries transaksi tag program, dikelompokkan tipe akun).

ReportService: tambah `programSummaries()` (list program + P&L ringkas) untuk Index.

## 10. TransactionService

Terima `program_id` (nullable) dan teruskan ke `Transaction::create()`. Model `Transaction` tambah `program_id` di `$fillable` + relasi `program()`.

## 11. Tests (PHPUnit)

`ProgramTest`:
- create program → code `PRG-2026-0001` sekuensial.
- transaksi di-tag program → `profitLoss()` menghitung pendapatan/biaya/laba benar.
- pendapatan (uang masuk 4100) & biaya (uang keluar 5700/5810) terpisah benar di P&L.
- peserta: status bayar unpaid/partial/paid benar.
- guard: hapus program yang masih punya transaksi ditolak.
- role: pengurus read-only.

## 12. Pitfalls

- `program_id` nullable — jangan wajib, biar transaksi lama/umum tetap bisa tanpa tag.
- P&L program dihitung dari **tipe akun** (revenue/expense), bukan tipe transaksi, supaya jurnal umum pun ikut terhitung.
- Route literal (`/programs/create`) sebelum `/{program}`.
- Nama model `Program` — pastikan tidak bentrok dengan keyword/namespace apa pun.

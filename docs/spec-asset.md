# Spec: Aset Tetap (Fixed Asset) + Penyusutan

> Status: APPROVED (hasil grill-me 2026-08-20) · Siap implementasi

## 1. Goal

Monitor (Rp 1.655.500) + UPS (Rp 1.600.000) yang tadinya dicatat sebagai `5900 Beban Peralatan Kantor` seharusnya adalah **Aset Tetap yang disusutkan**. Fitur ini menambahkan pencatatan aset tetap sederhana dengan penyusutan garis lurus, sehingga nilai buku aset terpantau dan beban penyusutan masuk Laba Rugi secara berkala.

## 2. Scope

### IN
- Register aset (nama, harga perolehan, tanggal perolehan, masa manfaat bulan).
- Hitung penyusutan **garis lurus** otomatis: `(cost − 0) ÷ masa_manfaat_bulan`.
- Posting penyusutan **manual per bulan** (bendahara generate → review → post).
- List aset + nilai buku (cost − akumulasi penyusutan) + riwayat penyusutan.
- **Reklasifikasi** Monitor + UPS dari `5900` → `1200` (data correction).

### OUT (MVP)
- Disposal/penjualan aset.
- Lokasi / kategori / departemen / penanggung jawab aset.
- Metode saldo menurun / unit produksi.
- Penyusutan otomatis via cron.
- Laporan Neraca (aset kelihatan di Buku Besar dulu).

## 3. Tech Decisions

- **Modul terpisah** (mirip Kas Bon): `Asset` model + `AssetService` + `AssetController` + Inertia pages.
- Semua posting lewat **`TransactionService`** (`type='journal'`) → double-entry terjamin.
- Akun baru: **`1200 Aset Tetap`** (asset), **`1201 Akumulasi Penyusutan`** (asset, contra), **`5950 Beban Penyusutan`** (expense). Resolve by `code`.
- Metode penyusutan **garis lurus**, mulai **bulan perolehan (penuh)**, nilai sisa **0**.
- Nomor aset **`AST-YYYY-NNNN`** (mirip generateAdvanceNo).
- RBAC: `bendahara` + `admin` register/posting penyusutan; `pengurus` read-only.

## 4. DB Changes

### Migrasi 1 — `assets`
| kolom | tipe | ket |
|---|---|---|
| id | bigint pk | |
| asset_no | string(50) unique | `AST-2026-0001` |
| name | string(191) | nama aset |
| cost | decimal(15,2) | harga perolehan |
| acquisition_date | date | tanggal perolehan |
| useful_life_months | int unsigned | masa manfaat (bulan) |
| monthly_depreciation | decimal(15,2) | cost / useful_life_months |
| accumulated_depreciation | decimal(15,2) default 0 | total susut yang sudah diposting |
| status | enum('active','fully_depreciated') default 'active' | |
| transaction_id | foreignId nullable | jurnal perolehan (Dr 1200 / Cr Kas) |
| user_id | foreignId nullable | pembuat |
| timestamps | | |

### Migrasi 2 — `asset_depreciations`
| kolom | tipe | ket |
|---|---|---|
| id | bigint pk | |
| asset_id | foreignId | |
| date | date | periode penyusutan |
| amount | decimal(15,2) | nominal susut bulan ini |
| transaction_id | foreignId | jurnal penyusutan |
| timestamps | | |

## 5. UI/UX (Bahasa Indonesia, AntD dark mode)

- **Menu**: "Aset" (grup Master/Transaksi) + submenu "Penyusutan".
- **Index** (`Pages/Assets/Index.tsx`): table — No, Nama, Harga Perolehan, Tgl Perolehan, Masa Manfaat, Akumulasi Susut, Nilai Buku, Status.
- **Form** (`Form.tsx`): nama, harga perolehan, tanggal perolehan, masa manfaat (bulan), akun kas/bank (default 1000).
- **Show** (`Show.tsx`): detail aset + jurnal perolehan + riwayat penyusutan (expandable journal).
- **Depreciation** (`Depreciation.tsx`): pilih periode (default bulan berjalan) → tombol **Generate** (preview daftar aset aktif + nominal) → tombol **Posting** (jurnal `Dr 5950 / Cr 1201` total + catat per aset).

## 6. Endpoints (routes/web.php, literal sebelum `{param}`)

```
GET    assets                    -> index        (auth)
GET    assets/create             -> create       (role:admin|bendahara)
POST   assets                    -> store        (role:admin|bendahara)
GET    assets/{asset}            -> show         (auth)
DELETE assets/{asset}            -> destroy      (role:admin|bendahara)
GET    assets/depreciation       -> depreciation (auth)   — note: register BEFORE assets/{asset}
POST   assets/depreciation       -> post         (role:admin|bendahara)
```

## 7. Accounting Rules (jurnal)

1. **Perolehan aset**: `Dr 1200 / Cr 1000 (kas)` — status `active`.
2. **Penyusutan bulanan** (1 jurnal per periode, total semua aset): `Dr 5950 (total) / Cr 1201 (total)`. Detail per aset dicatat di `asset_depreciations`.
3. **Fully depreciated**: bila `accumulated_depreciation >= cost`, status jadi `fully_depreciated`, berhenti disusutkan (nilai buku 0).
4. **Pembulatan**: nominal bulanan dibulatkan 2 desimal; bulan terakhir menyerap selisih supaya total = cost.

## 8. Reklasifikasi Monitor + UPS (data step — Dea, setelah deploy)

Monitor (Feb, `5900` 1.655.500) & UPS (Feb, `5900` 1.600.000) dipindah ke aset:
- Jurnal reklas: `Dr 1200 / Cr 5900` (1 jurnal per item) — kas tidak tersentuh.
- Register di tabel `assets` (acquisition_date = tgl Feb asli, masa manfaat default **48 bulan** — konfirmasi ke Iwan bila beda).
- Penyusutan berjalan mulai dari bulan perolehan (Feb 2026) saat pertama kali di-posting.

## 9. Risks / Pitfalls

- **Route ordering**: `assets/depreciation` harus sebelum `assets/{asset}`; `assets/create` sebelum `assets/{asset}` (pitfall #7).
- **Jangan susut melebihi cost**: validasi `accumulated + nominal <= cost` di service; bulan terakhir menyerap selisih.
- **Seed drift**: tambah `1200`, `1201`, `5950` ke `AccountSeeder`.
- **Block delete** aset yang sudah punya penyusutan.
- **Reklasifikasi jangan sentuh Kas**: murni `Dr 1200 / Cr 5900`; kalau salah, Laba Rugi Feb berubah (beban 5900 turun 3.255.500) — itu memang tujuannya.

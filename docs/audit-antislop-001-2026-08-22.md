# Anti-Slop Audit 001 — Prasasta ERP (UI)

> Tanggal: 2026-08-22 · Mode: AFTER (audit, tanpa perubahan)
> Scope: resources/js (theme, layout, semua halaman)
> Referensi: antislop (R-01..R-38) + antislop-ui + antd-antislop-audit

## Ringkasan

Aplikasi memakai **theme default AntD** (colorPrimary `#1677ff`) tanpa identitas visual
custom. Kerangka layout (sidebar + header + content) sudah benar untuk ERP, tapi
**theme, warna semantik, dan konsistensi antar-halaman** masih "stock AntD".

## Temuan (urut prioritas)

### 1. HIGH — R-20 / R-37 / R-31 — Tidak ada identitas visual, theme stock AntD
- `resources/js/app.tsx:27-30` hanya set `colorPrimary: '#1677ff'` (biru default) + `borderRadius: 6`.
  Tidak ada `colorBgLayout`, tidak ada `fontFamily`, tidak ada token custom lain.
  `progress.color` juga `#1677ff` (baris 55).
- Akibat: ganti logo/nama "Prasasta ERP" → tampilan jadi generic AntD admin, tidak ada karakter
  (R-20). Tidak ada arah desain yang ditulis (R-37/R-31).
- **Fix (arah user):** token custom — colorPrimary teal/emerald gelap, borderRadius 8, fontFamily
  Inter/system-ui, colorBgLayout #F6F8FA, plus token semantic (success/warning/error) selaras.

### 2. MEDIUM — R-34 / R-21 — Sidebar hardcoded dark, tidak adaptif ke mode terang
- `resources/js/Components/AppLayout.tsx:146` (`<Sider>` tanpa `theme` → default navy `#001529`)
  dan `:162` (`<Menu theme="dark">`).
- Di mode terang, Content/Header jadi terang tapi Sidebar + Menu tetap navy gelap → inkonsisten
  saat toggle. Navy `#001529` juga memperkuat kesan "default AntD".

### 3. MEDIUM — R-29 / R-31 — Warna semantik AntD di-hardcode inline (15 tempat)
- `#52c41a` (hijau), `#ff4d4f` (merah), `#faad14` (oranye) ditulis langsung di `valueStyle`/inline
  style, bukan lewat token tema:
  - `Reports/ProfitLoss.tsx`, `Reports/CashFlow.tsx`, `Reports/ReceivablesPayables.tsx`
  - `Bills/Show.tsx`, `Reconciliations/Show.tsx`, `CashAdvances/Realize.tsx`, `CashAdvances/Show.tsx`
- Kalau colorPrimary diganti, warna success/warning/error ini tetap default AntD dan tidak selaras.

### 4. MEDIUM — R-14 / R-20 / C-1 — Kartu statistik dashboard seragam, tanpa hierarki
- `Dashboard.tsx`: 5 kartu `<Card><Statistic>` identik (ukuran/padding/warna sama), semua netral.
  "Uang Masuk/Keluar" di dashboard netral, tapi metrik yang sama di halaman Laporan diberi hijau/merah
  → inkonsisten antar-halaman. Tidak ada satu aksen/focal point.

### 5. LOW — R-02 — Em dash (—) di teks UI (pemisah kalimat)
- `CashOpnames/Show.tsx:102` `Kas Opname — {number}` dan `Reconciliations/Show.tsx:307`
  `Rekonsiliasi {code} —`. Ganti ke `·` atau `:`.

### 6. LOW — R-04 — Ikon `BankOutlined` dipakai ganda untuk "Kas Opname"
- `AppLayout.tsx:69-72`: ikon bank dipakai untuk "Rekonsiliasi Bank" (tepat) dan "Kas Opname"
  (tidak tepat — opname itu hitung uang tunai, bukan bank). Usul: `WalletOutlined`/`CalculatorOutlined`.

### 7. LOW — R-16 / konsistensi bahasa — Label Inggris di tengah UI Indonesia
- `Transactions/Index.tsx:114` tombol "Edit" (harusnya "Ubah") dan `AppLayout.tsx:107` menu
  "Users" (harusnya "Pengguna"). Sisanya sudah Bahasa Indonesia.

## Catatan (PASS, tidak perlu fix)

- R-03 mobile / R-25 contrast / R-32 keyboard / R-27 empty-loading-error → dijamin AntD (framework-level).
- R-17/R-38 angka → semua Statistic terhubung data nyata (props server), tidak ada angka inventif.
- R-21 dark mode → toggle bekerja dua arah (mode tersimpan localStorage); default gelap = preferensi Iwan.
- R-08 arrow / buzzword / testimoni → 0 temuan.

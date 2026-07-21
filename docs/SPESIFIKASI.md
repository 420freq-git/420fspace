# Spesifikasi Aplikasi — Sistem Produksi & Settlement 420Frequency

> Dokumen ini menggantikan `Dokumen Spek/SPEC - Aplikasi Penjualan & Stok TM420 (Laravel).docx`
> yang lama (yang keliru di beberapa hal mendasar). Disusun dari hasil wawancara 16 Juli 2026.
> Sumber data asli: `Dokumen Spek/LAPORAN PENJUALAN TM420.xlsx` dan `Dokumen Spek/MASTER PO TM FIX.pdf`.

---

## 1. Ringkasan & tujuan

Aplikasi web untuk memonitor **produksi apparel dan pelunasan ke vendor** dalam kerja sama tiga
pihak. **Ini bukan sistem penjualan/laba** — tidak ada input harga jual, omzet, diskon, atau
potongan marketplace. Yang dilacak murni: apa yang diproduksi vendor, berapa yang sudah laku,
dan berapa yang harus dibayar ke vendor.

Nilai uang: Rupiah (integer, tanpa desimal). Ukuran: S, M, L, XL, XXL.

## 2. Pihak & peran

| Pihak | Peran | Login |
|---|---|---|
| **420Frequency (420F)** | Pemilik sistem / penengah. Juga pemilik brand VOOJAH. | Ya — akses penuh |
| **TM420** | Brand eksternal. Menjual produk, membayar 420F. | Ya — hanya data TM420 |
| **Diferd** | Vendor **tunggal**. Memproduksi semua barang. | Ya — sisi produksi/vendor |
| **VOOJAH** | Brand milik 420F sendiri. **Bukan pihak terpisah** — dikelola dari akun 420F. | Tidak |

Tiga entitas hukum (420F, TM420, Diferd), dua brand (TM420, VOOJAH). Jumlah pihak & vendor tetap.

## 3. Dua mode brand

- **Mode eksternal (TM420):** Diferd → 420F → TM420 → marketplace. TM420 bayar 420F
  `harga_tm420`; 420F bayar Diferd `harga_diferd`. **Untung 420F = markup = harga_tm420 −
  harga_diferd.**
- **Mode milik sendiri (VOOJAH):** Diferd → 420F(VOOJAH) → marketplace langsung. Hanya
  `harga_diferd` yang dilacak (kewajiban 420F ke Diferd). Margin retail VOOJAH **di luar sistem**.

## 4. Glosarium

- **Master PO / Batch** — satu event produksi (satu tanggal order) berisi banyak PO per-artikel.
- **PO** — lembar order satu artikel: spesifikasi produksi + matriks ukuran + mockup/desain.
- **Saldo Diferd** — uang vendor dari barang yang sudah laku tapi belum diambil/dibayar.
- **Deposit** — modal awal yang disetor di muka per batch (TM420 → via 420F → Diferd).
- **Buy-out** — kewajiban melunasi sisa stok yang belum laku saat batch jatuh tempo 1 tahun.

## 5. Tech stack

- Laravel 11 (PHP 8.2+), MySQL 8 / MariaDB
- Auth: Laravel Breeze, **multi-user berbasis role** (420F / TM420 / Diferd)
- Frontend: Blade + Livewire 3 + TailwindCSS
- PDF export PO: dompdf atau Laravel-Snappy (wkhtmltopdf)
- Import file: Laravel Excel (maatwebsite/excel) — untuk order/settlement marketplace
- Enum PHP (casts) untuk ukuran, marketplace, vendor tier, status, role
- Testing: Pest/PHPUnit

## 6. Model data

Kolom bantu/agregat spreadsheet TIDAK disimpan — dihitung di aplikasi (computed).

### 6.1 `brands`
`id` · `nama` (TM420, VOOJAH) · `tipe` (enum: `eksternal`, `milik_sendiri`) · timestamps.

### 6.2 `categories` (kategori penentu harga)
`id` · `nama` (Reguler 24s, Longsleeve 24s, Oversized 20s, Hoodie Jumper 280gsm, Double Layer 24s, …)
· `aktif` · timestamps.

### 6.3 `category_prices` (harga per kategori × tier ukuran)
`id` · `category_id` (FK) · `size_tier` (enum: `s_xl`, `xxl`) · `harga_diferd` (int) ·
`harga_tm420` (int, nullable — hanya relevan mode eksternal) · timestamps.
Unik: (`category_id`, `size_tier`). Lihat Bab 7 untuk isinya.

### 6.4 `products`
`id` · `brand_id` (FK) · `category_id` (FK) · `sku_induk` (string, manual) ·
`nama_artikel` · `harga_diferd_override` (int, nullable) · `harga_tm420_override` (int, nullable) ·
timestamps.
- Harga efektif diambil dari `category_prices` sesuai kategori + tier ukuran, **kecuali** kolom
  override terisi (lihat 8.1).
- Unik logis: (`brand_id`, `nama_artikel`).

### 6.5 `product_sizes` (SKU turunan per ukuran)
`id` · `product_id` (FK) · `ukuran` (enum S..XXL) · `sku_turunan` (string, manual — samakan dengan
SKU marketplace) · timestamps. Unik: (`product_id`, `ukuran`).

### 6.6 `product_files` (design & mockup)
`id` · `product_id` (FK) · `tipe` (enum: `mockup`, `desain`) · `path` · `keterangan` (nullable) ·
timestamps. Mockup = tampak depan & belakang; desain = artwork detail.

### 6.7 `batches` (Master PO)
`id` · `brand_id` (FK) · `vendor` (default Diferd) · `nomor_batch` · `tanggal_order` (date) ·
`deadline` (date — default `tanggal_order` + 1 tahun) · `jenis_order` (enum: `full_order`, …) ·
`type_payment` (enum: `termin`, `cash`, …) · `deposit_awal` (int, default 0) ·
`status` (enum: `aktif`, `lunas`) · timestamps.

### 6.8 `purchase_orders` (PO per-artikel dalam batch)
`id` · `batch_id` (FK) · `product_id` (FK) · `nomor_po` (format `PO.<BRAND>.<MM>.<YY>.<seq>`) ·
Spesifikasi & sablon: `patrun` · `ukuran_rib` · `warna_bahan` · `jenis_bahan` · `supp_bahan` ·
`warna_benang` · `cat_sablon` · `finishing` ·
Ukuran desain: `desain_depan` · `desain_belakang` · `desain_lengan` (string, mis. "10 X 10 CM") ·
Label/aksesoris (boolean): `label_leher` · `label_bawah` · `slip_label` · `aksesoris` ·
`care_label` · `hangtag` · `plastik` · `note` (text, nullable) · timestamps.
> Field spesifikasi terisi otomatis dari spesifikasi master produk saat PO dibuat, boleh diedit
> per PO.

### 6.9 `po_size_items` (matriks ukuran × jenis)
`id` · `purchase_order_id` (FK) · `ukuran` (enum S..XXL) · `jenis` (enum: `pendek`, `panjang`,
`raglan_34`, `lekbong`) · `qty` (int, default 0) · timestamps.
Total per PO & total per size = computed.

### 6.10 `stocks` (stok per produk per batch)
`id` · `product_id` (FK) · `batch_id` (FK) · `ukuran` (enum) · `qty_produksi` (int) ·
`qty_terjual` (int, default 0) · `qty_dikirim` (int, default 0) · timestamps.
COMPUTED: `sisa = qty_produksi − qty_terjual`.

### 6.11 `sales` (log unit keluar — TANPA nominal jual)
`id` · `brand_id` (FK) · `product_id` (FK) · `batch_id` (FK — FIFO) · `ukuran` (enum) ·
`qty` (int, default 1) · `tanggal_terjual` (date) · `marketplace` (enum: `shopee`, `tiktokshop`,
`offline`) · `nomor_pesanan` (string) · `harga_diferd` (int — snapshot saat transaksi) ·
`harga_tm420` (int, nullable — snapshot, brand eksternal) · `keterangan` (nullable) · timestamps.
COMPUTED: `fee_420f = harga_tm420 − harga_diferd` (mode eksternal saja).

### 6.12 `shipments`
`id` · `sale_id` (FK, via nomor_pesanan) · `tanggal` (date) · `nama_barang` · `ukuran` ·
`marketplace` · `nomor_pesanan` · `status` (enum: `dalam_pengiriman`, `selesai`, …) · timestamps.

### 6.13 `vendor_ledger` (buku saldo vendor — deposit & pembayaran)
`id` · `brand_id` (FK) · `batch_id` (FK, nullable) · `tanggal` (date) ·
`tipe` (enum: `deposit`, `pembayaran`, `buyout`) · `jumlah` (int) · `keterangan` · timestamps.
Kewajiban dari penjualan tidak disimpan sebagai baris — dihitung dari `sales` (lihat 8.3).

## 7. Master harga per kategori

Diferd = 420F bayar ke vendor · TM420 = TM420 bayar ke 420F · Markup = untung 420F.

| Kategori | Diferd (S–XL) | TM420 (S–XL) | Diferd (XXL) | TM420 (XXL) | Markup |
|---|---|---|---|---|---|
| Reguler 24s | 62.000 | 67.000 | 67.000 | 72.000 | 5.000 |
| Longsleeve 24s | 73.000 | 78.000 | 78.000 | 83.000 | 5.000 |
| Oversized 20s | 70.000 | 80.000 | 75.000 | 85.000 | 10.000 |
| Hoodie Jumper 280gsm | 175.000 | 185.000 | 180.000 | 190.000 | 10.000 |
| Double Layer 24s | 78.000 | 83.000 | 83.000 | 88.000 | 5.000 |

- Tier ukuran: **S–XL** (dasar) dan **XXL** (lebih mahal).
- *Hoodie Zipper* & *Jaket* belum ada harga — tambahkan saat sudah ditetapkan.
- VOOJAH memakai kolom **Diferd** saja (tanpa markup brand).

## 8. Aturan bisnis & rumus (wajib)

### 8.1 Harga efektif produk
```
harga_diferd = override bila terisi, jika tidak → category_prices[kategori][tier].harga_diferd
harga_tm420  = override bila terisi, jika tidak → category_prices[kategori][tier].harga_tm420
tier = (ukuran == XXL) ? 'xxl' : 's_xl'
```
Tombol **"ubah harga khusus"** di produk mengisi kolom override (jaga-jaga; jarang dipakai).

### 8.2 Fee 420F (mode eksternal / TM420)
`fee_420f (per unit) = harga_tm420 − harga_diferd`. Total fee = Σ atas semua `sales` TM420.

### 8.3 Saldo Diferd (buku berjalan, per brand / per batch)
```
Saldo Diferd = (Σ unit terjual × harga_diferd) − (deposit + Σ pembayaran + Σ buyout)
```
- Positif → 420F masih harus bayar ke Diferd. Nol/minus → lunas / deposit tersisa.
- Deposit otomatis "terpakai" seiring barang laku. Bila deposit menipis (<20%), munculkan
  peringatan top-up.

### 8.4 Batch & deadline
- `deadline = tanggal_order + 1 tahun`. Batch boleh **overlap** (batch baru walau lama belum lunas).
- Penjualan menarik stok **FIFO** dari batch tertua yang masih ada stok.
- **Buy-out:** saat jatuh tempo, sisa stok yang belum laku **tetap wajib dibayar** ke Diferd
  (baris `vendor_ledger` tipe `buyout`) agar batch `lunas`.
- **Restock = selalu batch baru** (tidak menambah ke batch berjalan).

### 8.5 Stok
`sisa_stok = qty_produksi − qty_terjual` (per produk/ukuran/batch). Saat sale dibuat:
`qty_terjual += qty`, opsional buat `shipment` berstatus `dalam_pengiriman`. Gunakan
Observer/Event + DB transaction agar konsisten.

### 8.6 Validasi
Ukuran ∈ {S,M,L,XL,XXL} · marketplace ∈ {Shopee, Tiktokshop, Offline} · qty ≥ 1 · uang ≥ 0 ·
tanggal valid · nomor_pesanan wajib untuk sale.

## 9. Pembuatan PO & export PDF

- PO **dibuat langsung di web**: pilih brand → buat Batch (Master PO) → tambah PO per artikel
  (pilih produk, spesifikasi autofill dari master, isi matriks qty per ukuran × jenis, upload/
  pilih mockup & desain).
- Nomor PO auto: `PO.<BRAND>.<bulan>.<tahun>.<urut>` (mis. `PO.TM.04.26.01`).
- **Export PDF per batch** — tata letak **mengikuti Master PO**: header + meta, spesifikasi &
  sablon, ukuran desain, label/aksesoris, matriks ukuran + total, dan **satu blok mockup (tampak
  depan & belakang sekaligus) + detail desain**. Contoh layout: lihat artifact preview PO.
- Vendor (Diferd) bisa mengunduh PDF PO per batch.

## 10. Hak akses (per role)

| Fitur | 420F | TM420 | Diferd |
|---|---|---|---|
| Kelola brand, kategori, harga | ✅ | — | — |
| Kelola produk, SKU, file, spesifikasi | ✅ | 👁️ (TM420) | 👁️ |
| Buat/kelola Batch & PO | ✅ | 👁️ (TM420) | 👁️ + input status produksi |
| Lihat harga_diferd & fee 420F | ✅ | ❌ | ✅ (sisi vendor) |
| Input penjualan (unit keluar) | ✅ | ✅ (TM420) | ❌ |
| Update status pengiriman | ✅ | ✅ | 👁️ |
| Settlement / saldo vendor | ✅ | 👁️ (miliknya) | ✅ (miliknya) |
| Kelola VOOJAH | ✅ | ❌ | 👁️ produksi |

TM420 **tidak boleh** melihat `harga_diferd` maupun fee 420F.

## 11. Modul & halaman

Dashboard · Brand · Kategori & Harga · Produk (+ SKU turunan, file, harga override) ·
Batch/PO (+ export PDF) · Stok · Penjualan (log unit keluar) · Pengiriman · Settlement/Saldo
Vendor · Laporan (per produk / TM420 / Diferd) · Monitoring.

**Monitoring:** hitung mundur deadline batch · progress produksi (diisi Diferd) · sell-through
per batch · aging saldo vendor · peringatan top-up deposit & deadline mendekat.

## 12. Alur kerja utama

1. **Setup:** 420F set kategori & harga → tambah produk (SKU induk + SKU turunan + file +
   spesifikasi master).
2. **Buat PO:** buat Batch → tambah PO per artikel (autofill spesifikasi, isi qty) → export PDF →
   kirim ke Diferd.
3. **Produksi:** Diferd update status produksi per batch (Antri → Produksi → Selesai → Kirim gudang).
4. **Deposit:** catat deposit awal batch di `vendor_ledger`.
5. **Penjualan:** input unit keluar (nomor pesanan, marketplace) → stok berkurang (FIFO) →
   shipment `dalam_pengiriman` → saldo Diferd bertambah.
6. **Pengiriman:** update status `selesai` setelah settlement diterima (opsional import file).
7. **Settlement bulanan:** bayar Diferd dari total terjual, catat di `vendor_ledger`.
8. **Tutup batch:** saat jatuh tempo, buy-out sisa stok → batch `lunas`.
9. **Laporan/monitoring:** filter periode, export.

## 13. Roadmap bertahap

1. **MVP:** Auth+role · brand · kategori & harga · produk (SKU, file, spesifikasi) · dashboard.
2. **PO:** Batch + PO per-artikel + matriks ukuran + **export PDF** + status produksi (Diferd).
3. **Stok & penjualan:** stok per batch · log unit keluar · pengurangan stok FIFO.
4. **Settlement:** deposit · saldo Diferd · pembayaran bulanan · buy-out · fee 420F.
5. **Pengiriman & laporan:** shipment + import file marketplace · laporan per produk/TM420/Diferd.
6. **Monitoring & polish:** deadline, sell-through, aging, peringatan, grafik dashboard.

> Integrasi API marketplace (Shopee/TikTok) DITUNDA — mulai dari input manual nomor pesanan +
> import file Excel/CSV order/settlement.

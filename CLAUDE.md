# 420Frequency — Panduan Proyek (untuk Claude)

Dokumen ini menjelaskan **konsep bisnis, model uang, model stok, alur produksi, dan konvensi
teknis** aplikasi ini. Baca ini lebih dulu sebelum mengubah kode — banyak logika uang & stok
saling terkait, dan salah paham konsep = salah hitung uang.

> Bahasa domain: Indonesia. Nama model/kolom/enum: Inggris/Indonesia campur (ikuti yang ada).
> Spesifikasi awal ada di `docs/SPESIFIKASI.md`.

---

## 1. Apa ini

**420Frequency** = aplikasi web pelacak **produksi apparel & settlement vendor** untuk bisnis
**konsinyasi 3 pihak**. Bukan toko/POS — ini alat internal untuk melacak: siapa produksi apa,
stok di mana, siapa berutang ke siapa, dan berapa.

Stack: **Laravel 13 · PHP 8.3 · MySQL 8 · Blade + Alpine.js + Tailwind v3 · barryvdh/laravel-dompdf
· PhpSpreadsheet**. Sesi kerja pakai **tinker script** untuk uji (lihat §12).

---

## 2. Pihak & Peran (POV)

Empat peran (`App\Enums\Role`, kolom `users.role`):

| Role (value)   | Pihak    | Peran bisnis                                                                 |
|----------------|----------|------------------------------------------------------------------------------|
| `420f`         | **420F** | Middleman & pemilik sistem (admin). Melihat semua, mengelola uang & approval.|
| `tm420`        | **TM420**| Brand eksternal. Mengajukan batch, kelola produk/pesanan brand-nya.          |
| `voojah`       | **VOOJAH**| Brand **milik sendiri** 420F. Sama seperti TM tapi ditagih harga modal.     |
| `difred`       | **Diferd**| **Vendor/produsen** (satu-satunya). Update tahap produksi, tarik hak.        |

- **Brand** (`brands`, `App\Enums\BrandType`): `eksternal` (TM420) atau `milik_sendiri` (VOOJAH).
  `users.brand_id` mengunci TM420/VOOJAH ke brand-nya. 420F & Diferd tidak terikat brand.
- **Scoping penting:** TM420/VOOJAH hanya boleh melihat/mengubah data **brand-nya sendiri**.
  Cek dilakukan di controller (`authorizeView` / `ensureOwn` / `pastikanBolehUbah`), BUKAN hanya
  middleware. Setiap mutasi by-ID yang bisa diakses TM WAJIB cek brand (cegah IDOR lintas-brand).

---

## 3. Model Uang (INTI — hati-hati)

### Harga per artikel/ukuran (`category_prices`, override di `products.*_override`)
- `harga_diferd` = **modal** (yang 420F bayar ke Diferd).
- `harga_tm420`  = **retail** (yang 420F tagih ke TM420).
- Tier ukuran: `SizeTier` = `s_xl` (S–XL) & `xxl`. Method `Product::effectiveDiferd($tier)` /
  `effectiveTm420($tier)` (dengan override produk).
- **`Product::hargaTagihan($tier)`** = harga yang **420F tagih ke brand**:
  - brand `milik_sendiri` (VOOJAH) → **`effectiveDiferd`** (VOOJAH bayar modal, **fee 420F = 0**),
  - brand `eksternal` (TM420) → **`effectiveTm420`** (retail).
  - **SELALU pakai `hargaTagihan()` untuk nilai/tagihan brand.** Pakai `effectiveTm420` langsung =
    bug untuk VOOJAH. Snapshot ke `sales.harga_tm420` juga pakai `hargaTagihan()`.

### Tipe pembayaran batch (`batches.type_payment`, `App\Enums\TypePayment`)
1. **Termin (konsinyasi) — default.** Bayar **per barang terjual**. Diferd punya "hak" atas barang
   terjual; ditarik lewat **Penarikan**. TM ditagih lewat **Invoice** saat pesanan cair.
2. **Cash — beli putus di muka.** Saat batch **disetujui**, otomatis dibayar penuh untuk seluruh
   qty PO: TM→420F (`hargaTagihan`) & 420F→Diferd (`diferd`), 420F simpan margin. Stok **keluar
   dari pool jual**. `Sale::consignment()` mengecualikan penjualan batch cash dari semua hitungan hak.
   `LedgerTipe::Cash`. Reject di batch cash → **Diferd wajib ganti** (barang/refund) via `cash_ganti`.

### Buy-out sisa stok (di deadline)
420F membeli sisa stok yang belum terjual. **Alurnya seperti TAGIHAN (bukan settle seketika):**
- Terbit **Invoice** ke TM di **harga tm420** (`invoices.jumlah_manual` + `pcs_manual`, `batch_id`).
  Uang masuk 420F saat invoice ditandai lunas.
- **Hak Diferd bertambah** di harga diferd (`VendorLedger` tipe `buyout`) → ditutup lewat Penarikan.
- 420F simpan margin (tm420 − diferd) saat invoice lunas.
- Stok **keluar dari pool jual** (`batches.dibuyout`).

### Deposit (modal produksi)
Sekali di awal kerja sama, **GLOBAL** (bukan per batch), bukan hutang. Diselesaikan sekali di akhir
(offset ke hak / dikembalikan) — `LedgerTipe::DepositSelesai`. `SettlementService::depositMengendap()`.

### Buku besar & penarikan
- **`vendor_ledger`** (uang 420F→Diferd): tipe `pembayaran` / `deposit` / `deposit_selesai` /
  `cash` / `buyout`. Kolom `jumlah` **signed** (boleh negatif utk refund).
- **`brand_ledger`** (uang brand→420F): keterangan bebas; pola penting `'Cash batch%'`,
  `'Pembayaran invoice%'`. `jumlah` **signed**.
- **Penarikan** Diferd bersifat **GLOBAL**, tapi saat **disetujui** dibekukan **FIFO** ke batch
  (batch tertua yg haknya belum tertutup dulu) sebagai baris `vendor_ledger` tipe `pembayaran`
  dengan `penarikan_id`. Setelah beku, sisa per batch jadi historis (tak bergeser).
  `PenarikanController::hakGlobal()` = penjualan konsinyasi + **buyout** (cash dikecualikan).

### Angka kunci yang HARUS konsisten lintas halaman
- **Hak Diferd terutang** = (Sale sold consignment × diferd) + buyout − (pembayaran + penarikan cair).
  Muncul sama di: Cashflow, Penarikan, Settlement, Dashboard (420F & Diferd).
- **Fee 420F** = margin penjualan lunas + margin cash + margin buy-out (tm420 − diferd tiap sumber).
- Kalau menambah sumber uang, perbarui **semua** tempat ini (Cashflow, Dashboard 420F & Diferd,
  Settlement `fee420f`, Saldo). Jangan hitung ulang beda-beda per halaman.

---

## 4. Model Stok

Basis: **stok jual = DITERIMA − TERJUAL − KELUAR-SISTEM** (jangan pakai qty produksi).
`App\Services\StockService` adalah **sumber kebenaran** — halaman lain harus mengikutinya.

- `producedInBatch` = Σ `po_size_items.qty` (qty PO). Bukan stok jual.
- `shippedInBatch` = Σ `pengiriman_items.qty` (dibuat surat jalan).
- `receivedInBatch` = Σ `pengiriman_items.qty_diterima` (status pengiriman `diterima`). **Dasar stok.**
- `soldInBatch` = Σ `sales.qty` scope `consuming()`.
- `availableInBatch` = received − sold, **kecuali** batch `dibuyout` ATAU `cash_dibayar` → **0**
  (stok sudah keluar sistem). `stokKeluarBatches()` = dibuyout OR cash_dibayar.
- `boughtOutTotal` = Σ (received − sold) pada batch yang stoknya keluar sistem — dipakai halaman
  Stok/Dashboard/Rapor untuk mengurangi dari stok jual.
- Keadaan lain: `unshippedInBatch` (di vendor), `inTransitInBatch` (di jalan), `rejectInBatch`
  (gagal QC setelah PO ditutup), `shortfallInBatch` (kurang/cacat saat terima).

Reject/kurang **ditanggung vendor** (Diferd). Retur: kondisi `layak` → balik stok tanpa hak;
`rusak` → stok hilang, brand tetap bayar produksi (`Sale::sold()` tetap true untuk rusak).

---

## 5. Alur Produksi & Status

`Batch` (Master PO) berisi banyak `PurchaseOrder` (PO per artikel), tiap PO punya `PoSizeItem`
(qty per ukuran×jenis).

**Status batch** (`BatchStatus`): `menunggu` → (420F setujui) `aktif` / (tolak) `ditolak` →
`lunas`. TM mengajukan, **hanya 420F yang menyetujui/menolak**. Batch `aktif` tapi produksi selesai
tetap berstatus `aktif` (status ≠ progres produksi).

**Tahap produksi PO** (`TahapProduksi`, 14 tahap): `belanja_bahan` … `siap_kirim` → `terkirim`.
- Diferd/420F memajukan tahap (`purchase-orders.status`).
- Dari `siap_kirim`: Diferd buat **surat jalan** (`pengiriman`, status `dikirim`).
- TM **terima** (`pengiriman.terima`) → set `qty_diterima`, status `diterima`, dan PO → `terkirim`.
  **Penting:** penandaan `terkirim` update **per-model** (bukan mass-update) supaya transisi
  terekam di **audit log** — riwayat produksi (`TahapTimelineService`) dibangun dari audit log.
- "Batch selesai" (Monitoring/Dashboard) = semua PO minimal `siap_kirim` **dan** semua sudah
  dikirim/diterima (tak ada sisa menunggu surat jalan). Bukan sekadar status `aktif`.

---

## 6. Entitas Utama (`app/Models`)

- **Batch** — Master PO. `type_payment`, `status`, `dibuyout`, `cash_dibayar`, `deposit_awal`,
  approval (`diajukan_oleh`/`disetujui_oleh`), deadline & deadline_produksi. `isCash()`.
- **PurchaseOrder** — PO per artikel dalam batch. `tahap`, `tahap_updated_at`, spec produksi
  (bahan, sablon, ukuran desain depan/belakang/lengan, label/aksesoris). `hasMany PoSizeItem`.
- **PoSizeItem** — qty per ukuran & jenis produksi.
- **Product** — artikel. `brand_id`, `category_id`, override harga, `sku_induk`.
  `hasMany ProductSize` (SKU turunan/ukuran), `ProductFile`, `hasOne ProductSpec`.
- **ProductFile** (`ProductFileType`): `mockup` / `desain` (artwork) / `mentahan` (file produksi).
  `is_image` accessor. `filesOfType()`.
- **Sale** — baris penjualan (= item order). Snapshot `harga_diferd` & `harga_tm420`. Scope:
  `consuming()` (mengurangi stok), `sold()` (jadi kewajiban bayar), `consignment()` (bukan cash).
- **Order** — pesanan marketplace. `items()` = hasMany Sale. `marketplace`, `status`, `invoice_id`.
- **Invoice** — tagihan ke TM. Bundel `orders` (penjualan) **atau** baris manual buy-out
  (`jumlah_manual`/`pcs_manual`/`batch_id`, `isBuyout()`). `bukti_transfer`. `total` = order + manual.
- **Pengiriman** / **PengirimanItem** — surat jalan; `qty` (dikirim) & `qty_diterima`.
- **VendorLedger** / **BrandLedger** — buku besar (lihat §3). `Penarikan` — penarikan Diferd.
- **CashGanti** — penyelesaian ganti reject batch cash (metode `barang`/`refund`).
- **AuditLog** (`Auditable` trait) — jejak created/updated/deleted; dasar riwayat tahap.
- **Setting**, **Brand**, **Category**, **CategoryPrice**, **User**, **ImportLog**.

---

## 7. Enum Penting (`app/Enums`)
`Role`, `BrandType`, `TypePayment`, `BatchStatus`, `TahapProduksi`, `JenisProduksi`
(pendek/panjang/raglan_34/lekbong), `Ukuran` (S/M/L/XL/XXL), `SizeTier` (s_xl/xxl), `Marketplace`
(shopee/tiktokshop/whatsapp/web — WA & Web **langsung lunas**), `OrderStatus`
(dipesan/dikirim/lunas/retur/batal), `LedgerTipe`, `ProductFileType`, `AlasanSelisih`, `JenisOrder`.

---

## 8. Aturan Bisnis Terkunci (jangan diubah tanpa konfirmasi)
- VOOJAH ditagih **harga_diferd** (fee 420F = 0) — via `hargaTagihan()`.
- Buy-out = **alur tagihan** (invoice tm420 + hak Diferd diferd), bukan settle seketika; stok keluar.
- Cash = **beli putus di muka**, dibayar penuh saat disetujui; stok keluar; reject ditanggung Diferd.
- Deposit = **global**, sekali, diselesaikan di akhir (bukan hutang, bukan per batch).
- Penarikan **global**, dibekukan **FIFO** ke batch saat disetujui.
- Stok jual berbasis **penerimaan** (bukan produksi); reject/kurang ditanggung vendor.
- Pengiriman hanya untuk PO bertahap `siap_kirim`; qty ≤ (produced − shipped).
- Approval batch: **hanya 420F**; TM tak boleh set status batch sendiri.
- **Auto-lunas:** batch `aktif` otomatis jadi `lunas` bila TUNTAS — semua PO `terkirim` **dan**
  hak Diferd lunas (saldo ≤ 0) **dan** stok jual habis. `SettlementService::reconcileLunas()`
  dipanggil saat daftar Batch/Settlement dibuka (idempoten, satu arah aktif→lunas).

---

## 9. Menu per POV (ringkas)
- **420F (admin):** semua. Uang khusus 420F: Cashflow, Saldo 420F, Rekonsiliasi TM, kelola Invoice,
  Users, Settings (+ tombol Reset transaksi berbackup), Audit log.
- **TM420 / VOOJAH:** Dashboard, Produk, Batch/PO (ajukan), Monitoring produksi, Stok, Pengiriman
  (lihat/terima), Pesanan (+import marketplace, monitoring cek, barang kembali), Invoice (lihat +
  unggah bukti transfer), Radar deadline, Rapor produk, Analisis channel, Rekomendasi, Laporan.
- **Diferd:** Dashboard, Monitoring produksi (update tahap), Pengiriman (buat surat jalan),
  Scorecard vendor (kinerjanya), Settlement (lihat), Penarikan (ajukan), Laporan.
- **Visibilitas nilai:** TM tak pernah lihat `harga_diferd`/fee. Diferd tak lihat pesanan/tagihan TM.

---

## 10. Fitur Tambahan
- **Import marketplace** (`MarketplaceImportService`): CSV/XLSX pesanan; guard — hanya buat sale
  yang menarik stok nyata (lewati produk stok 0). Snapshot harga pakai `hargaTagihan()`.
- **Radar deadline** (paparan buy-out), **Scorecard vendor** (reject%/kurang%/bebas-cacat/ketepatan
  deadline), **Rekonsiliasi TM** (mingguan; tagihan cair + invoice buy-out vs transfer),
  **Rekomendasi produksi ulang**, **Rapor per artikel**, **Analisis per channel**.
- **Notifikasi WhatsApp** (Fonnte): command `app:kirim-reminder` (jadwal harian). Perlu
  `FONNTE_TOKEN` di `.env`; tanpa itu hanya nulis log (aman).
- **PDF**: Master PO (`batches.pdf`), Invoice, Surat jalan — dompdf. Mockup/desain di PDF: upload
  ideal **Mockup 1400×800**, **Desain 1150×800** (landscape) agar penuh.

---

## 11. Environment & Menjalankan
- Dev: **Laragon** (Windows). Path PHP/MySQL **spesifik per-perangkat** — sesuaikan.
  Contoh lokal: PHP CLI `C:\laragon\bin\php\php-8.3.30-...\php.exe`, MySQL `...\mysql.exe`
  (`root`, tanpa password), DB `420frequency`.
- Jalankan: `php artisan serve --host=127.0.0.1 --port=8000` (server pakai PHP CLI, bukan Apache).
- **`php.ini`:** ekstensi `extension=zip` harus AKTIF (untuk unduh ZIP desain & upload xlsx
  settlement). Ini config sistem, **tidak** ikut git — aktifkan ulang di perangkat baru.
- Setelah pindah/deploy: `composer install`, `php artisan migrate`, `php artisan storage:link`,
  isi `.env` (`APP_KEY`, DB). Aset: `npm install && npm run build` (Vite + Tailwind).
- Akun awal (seeder/uji, ganti untuk produksi): `420freq@gmail.com` (420f), `tm420@420frequency.test`,
  `diferd@420frequency.test`, `voojah@420frequency.test`.

### Sebelum GO-LIVE (wajib)
`APP_ENV=production` · `APP_DEBUG=false` · `APP_URL` HTTPS · user DB berpassword (bukan root) ·
ganti semua password default & hapus akun uji · Reset transaksi (buang data uji) · pastikan ekstensi
zip aktif · (opsional) SMTP asli, `FONNTE_TOKEN`, cron `schedule:run`, `queue:work`, backup DB.

---

## 12. Cara Uji
- **Test suite formal ada** di `tests/Feature/Erp/` (uang konsinyasi/VOOJAH, cash, buy-out, stok,
  auto-lunas status, scoping/IDOR). Base: `tests/ErpTestCase.php` (seed master + helper alur
  `batchAktif`/`produksiTerima`/`jual`). Jalankan: `php artisan test`.
  - Pakai **DB MySQL test** `420frequency_test` (buat sekali; kredensial di `phpunit.xml`) karena
    app pakai SQL khas MySQL — SQLite :memory: tak cukup. `RefreshDatabase` migrate fresh tiap run.
  - **Migrasi HARUS fresh-installable** — hati-hati urutan timestamp/nama file bila tabel A merujuk
    tabel B (mis. `invoices` mengubah `orders`: file `..._invoices_and_penarikan` tanpa prefix
    "create_" agar sort setelah `..._create_orders_table`; pakai guard hasTable/hasColumn).
- Eksplorasi cepat masih boleh pakai **tinker script** yang memanggil controller/service asli
  dengan `Request::create()` + `setUserResolver()`.
- Pola: tulis script ke folder scratchpad, jalankan `php artisan tinker "path/script.php"`.
  **Jangan pipe ke grep** (tinker hang) — alihkan output ke file lalu baca file.
- **Gotcha tinker:** proses sering *hang saat exit* (exit 143) walau output sudah tercetak —
  redirect ke file + `timeout`, lalu `grep`/`cat` file hasilnya.
- Untuk uji yang tak boleh mengubah data: `DB::beginTransaction()` … `DB::rollBack()` (operasi
  biasa bisa rollback; `TRUNCATE` tidak).
- `php -l file.php` untuk lint; `php artisan view:cache` untuk cek blade kompilasi.

---

## 13. Gotcha / Catatan
- **Kolom `jumlah` ledger signed** — refund reject cash pakai nilai negatif (jangan asumsikan positif).
- **Konsistensi angka**: bila mengubah cara hitung uang/stok, samakan di SEMUA halaman (Dashboard,
  Cashflow, Settlement, Penarikan, Stok, Rapor). Bug sering muncul karena satu halaman menghitung beda.
- **Scoping brand** wajib di setiap mutasi by-ID milik TM/VOOJAH (IDOR). Pola: `authorizeView` /
  `ensureOwn` / `pastikanBolehUbah`.
- **Riwayat tahap dari audit log** — jangan mass-update `tahap` (bypass event → history salah).
- **Marketplace WA & Web langsung lunas**; TikTok/Shopee perlu ditandai lunas manual.
- Reset transaksi (`settings.reset`) TRUNCATE tabel transaksi, backup `.sql` dulu; master tetap.
  Kalau menambah tabel transaksi baru, daftarkan di konstanta `SettingController`.

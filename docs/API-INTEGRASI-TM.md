# Sketsa API Integrasi — ERP TM420 → 420Frequency

Status: **rancangan (belum dibangun).** Dokumen ini adalah kontrak untuk pengembang ERP TM.
Tujuan: TM upload file marketplace **sekali** di ERP-nya; ERP meneruskan datanya ke 420Frequency
lewat API — tidak ada upload dua kali.

> Baca `CLAUDE.md` untuk konsep bisnis. API ini adalah "pintu masuk" ke mesin import yang sudah
> ada (`MarketplaceImportService`) — aturan bisnis (stok, harga, scoping brand) tetap sama.

---

## 1. Alur & prinsip

```
Marketplace ──export──► ERP TM420 ──(parse + normalisasi)──► API 420Frequency ──► Pesanan/Settlement
                        (upload 1x)         JSON, token brand        (reuse import service)
```

Prinsip:
- **Push dari ERP TM** (bukan 420F menarik) → paling sederhana & real-time untuk "upload sekali".
- **Idempoten** — kirim ulang data yang sama tidak menggandakan (kunci: `nomor_pesanan`).
- **Reuse logika lama** — hasil identik dengan upload manual (guard stok, snapshot harga `hargaTagihan()`).
- **Token terkunci brand** — ERP TM hanya boleh menyentuh data brand-nya sendiri.
- **Upload manual tetap ada** sebagai cadangan.

---

## 2. Autentikasi

- **Laravel Sanctum**, Bearer token. 420F menerbitkan **1 token per brand** untuk ERP TM.
- Token menyimpan `brand_id`. Semua request diproses **atas nama brand itu** — pesanan otomatis
  brand tersebut; SKU di luar brand ditolak.
- Header: `Authorization: Bearer <token>` · `Accept: application/json` · `Content-Type: application/json`.
- Token bisa dicabut/rotasi dari 420F. **HTTPS wajib.**

---

## 3. Konvensi umum

- **Base URL:** `https://<domain-420f>/api/v1`
- **Versi:** di path (`/v1`) — perubahan tak-kompatibel = `/v2`.
- **Format:** JSON (request & response).
- **Idempotency:** header opsional `Idempotency-Key: <uuid>` per request batch (mencegah dobel
  saat retry). Selain itu `nomor_pesanan` unik jadi pengaman kedua.
- **Rate limit:** mis. 60 req/menit per token (disesuaikan).
- **Amplop respons standar:**
  ```json
  { "ok": true, "message": "…", "data": { … }, "errors": [] }
  ```
- **Kode HTTP:** `200` sukses · `207` sebagian sukses (ada baris ditolak) · `401` token salah ·
  `403` brand tidak cocok · `422` validasi gagal · `429` rate limit · `500` error server.

---

## 4. Endpoint

### 4.1 `GET /ping` — cek koneksi & token
Respons:
```json
{ "ok": true, "data": { "brand": "TM420", "brand_id": 1, "server_time": "2026-07-23T10:00:00+07:00" } }
```

### 4.2 `GET /products` — sinkron pemetaan SKU (opsional, disarankan)
ERP menarik daftar produk+ukuran brand-nya agar SKU cocok. **SKU adalah kunci bersama.**
```json
{ "ok": true, "data": [
  { "sku_induk": "TS-STONEDICONS", "nama_artikel": "Stoned Icon",
    "ukuran": [ { "sku": "TS-STI-M", "ukuran": "M" }, { "sku": "TS-STI-L", "ukuran": "L" } ] }
] }
```
`sku` (= `sku_turunan`, per ukuran) dipakai sebagai identitas item pesanan.

### 4.3 `POST /orders` — kirim pesanan (batch)
Kirim banyak pesanan sekaligus. Item pakai `sku` (sku_turunan).

Request:
```json
{
  "orders": [
    {
      "nomor_pesanan": "584281819787461645",
      "marketplace": "tiktokshop",
      "tanggal_pesanan": "2026-07-20",
      "status": "dipesan",
      "resi": "JX123456789",
      "items": [
        { "sku": "TS-STI-M", "qty": 2 },
        { "sku": "TS-STI-L", "qty": 1 }
      ]
    }
  ]
}
```

Perilaku (mengikuti aturan sistem):
- `marketplace` ∈ `shopee | tiktokshop | whatsapp | web`. **`whatsapp` & `web` → otomatis `lunas`.**
- Harga di-*snapshot* server pakai `hargaTagihan()` (TM=tm420) & `effectiveDiferd` — **ERP tidak
  mengirim harga.**
- **Guard stok:** hanya baris yang menarik stok nyata yang dibuat; qty melebihi stok / produk stok 0
  **dilewati** dan dilaporkan (bukan bikin pesanan hantu).
- **Idempoten:** `nomor_pesanan` sudah ada → di-*update* (atau di-skip bila identik), tak digandakan.
- SKU tak dikenal / bukan milik brand token → baris ditolak.

Respons (`200` atau `207` bila ada yang dilewati):
```json
{ "ok": true, "data": {
  "ringkasan": { "diterima": 1, "dibuat": 1, "diupdate": 0, "dilewati_stok": 0, "ditolak": 0 },
  "detail": [
    { "nomor_pesanan": "584281819787461645", "status": "dibuat",
      "items": [ { "sku": "TS-STI-M", "qty": 2, "teralokasi": 2 },
                 { "sku": "TS-STI-L", "qty": 1, "teralokasi": 1 } ] }
  ]
}, "errors": [] }
```

### 4.4 `POST /settlements` — tandai pesanan cair/lunas (dari file settlement marketplace)
Setelah dana marketplace cair, ERP kirim daftar nomor pesanan + tanggal cair.
Request:
```json
{ "settlements": [
  { "nomor_pesanan": "584281819787461645", "tgl_cair": "2026-07-22" },
  { "nomor_pesanan": "584269069755713275", "tgl_cair": "2026-07-22" }
] }
```
Perilaku: set status `lunas` + `tgl_cair` (memicu kelayakan invoice). Nomor tak ditemukan → dilaporkan.
Respons:
```json
{ "ok": true, "data": { "dicairkan": 2, "tidak_ditemukan": 0, "sudah_lunas": 0 } }
```

### 4.5 `GET /orders/{nomor_pesanan}` — cek status (opsional)
```json
{ "ok": true, "data": { "nomor_pesanan": "…", "status": "lunas", "tgl_cair": "2026-07-22",
  "items": [ { "sku": "TS-STI-M", "qty": 2 } ] } }
```

### 4.6 (Opsional) `POST /orders/{nomor_pesanan}/retur` — tandai retur/batal
Bila ERP juga melacak retur: kirim status `retur` (barang balik) atau kondisi diterima
(`layak`/`rusak`). Mengikuti aturan retur sistem.

---

## 5. Nilai enum valid (kontrak)
- `marketplace`: `shopee`, `tiktokshop`, `whatsapp`, `web`
- `status` pesanan: `dipesan`, `dikirim`, `lunas`, `retur`, `batal` (ERP umumnya cukup kirim
  `dipesan`/`dikirim`; pelunasan lewat `/settlements`)
- `ukuran`: `S`, `M`, `L`, `XL`, `XXL`

---

## 6. Aturan yang TETAP berlaku (jangan diakali dari ERP)
- Harga dihitung **server** (VOOJAH=modal, TM=retail) — ERP tak boleh menentukan harga.
- Stok berbasis **penerimaan**; guard menolak pesanan tanpa stok nyata.
- Satu pesanan = satu brand (dijamin oleh token brand).
- Snapshot harga & alokasi FIFO batch ditangani server.

---

## 7. Peta ke kode internal (untuk pengembang 420F)
- **Sanctum**: `composer require laravel/sanctum`, publish migrasi, `HasApiTokens` di `User`
  (atau model token brand khusus). Simpan `brand_id` di `abilities`/kolom token.
- **Route**: `routes/api.php` grup `middleware('auth:sanctum')`, prefix `v1`.
- **Controller API tipis** (mis. `Api/OrderApiController`, `Api/SettlementApiController`) yang:
  1) resolve brand dari token, 2) validasi payload, 3) panggil **`MarketplaceImportService`**
  (atau `OrderController` core) yang sudah memuat guard stok + snapshot harga, 4) balikan ringkasan.
- Reuse guard/idempoten dari `MarketplaceImportService`; jangan tulis ulang aturan.
- Resolusi SKU: `sku` → `ProductSize` (sku_turunan) → `product_id` + `ukuran`, filter `brand_id` token.
- Catat di `ImportLog` dengan `sumber = 'api'` agar bisa diaudit sama seperti upload manual.

---

## 8. Keamanan & operasional
- **HTTPS wajib**, token per brand, bisa dicabut/rotasi, rate limit, validasi ketat + batasi ukuran batch.
- Log tiap panggilan (jumlah dibuat/dilewati/ditolak) untuk audit & debugging.
- Balikan **detail per baris** supaya ERP tahu persis apa yang gagal (SKU/stok/duplikat).
- Idempotency-Key untuk aman saat retry jaringan.

---

## 9. Rencana bertahap (saran)
1. **Fase 1 — MVP:** Sanctum + `POST /orders` + `POST /settlements` + `GET /ping`. (Menutup
   kebutuhan "upload sekali".)
2. **Fase 2:** `GET /products` (sinkron SKU) + `GET /orders/{nomor}` (rekonsiliasi).
3. **Fase 3:** retur via API + webhook balik dari 420F (mis. notifikasi saat invoice terbit).

Yang harus disepakati dulu dengan tim ERP TM: **skema JSON final**, **SKU sebagai kunci**, dan
**mekanisme penerbitan/rotasi token**.

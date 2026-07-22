# Panduan Deploy ke Hostinger — 420Frequency

Laravel 13 + PHP 8.3 + MySQL. Dokumen ini langkah go-live. Untuk konsep aplikasi lihat `CLAUDE.md`.

> **Disarankan plan Hostinger yang punya SSH & Composer** (Business/Cloud). Bisa juga tanpa SSH
> (upload manual + import SQL), lebih repot di bagian `composer`/`artisan`.

---

## 0. Siapkan di lokal (sebelum upload)
1. **Build aset** (Tailwind/Vite):
   ```
   npm install
   npm run build          # hasil di public/build — WAJIB diupload
   ```
2. **Dump database go-live** sudah dibuat: `database/dump/420frequency-golive.sql`
   (skema penuh + data master: 33 produk, harga, brand, user, settings; **transaksi kosong**).
   File `.sql` di-gitignore — ambil manual untuk diupload.
3. Siapkan **APP_KEY** bila server tak bisa `artisan`: jalankan lokal `php artisan key:generate --show`
   lalu tempel ke `.env` server.
4. Siapkan file gambar produk (mockup/desain) di `storage/app/public/` untuk diupload juga.

---

## 1. Database (hPanel)
1. hPanel → **Databases → MySQL Databases**: buat database + user + password kuat. Catat namanya.
2. Buka **phpMyAdmin** database itu → tab **Import** → unggah `420frequency-golive.sql` → Go.
3. Verifikasi: tabel `products` berisi 33 baris, tabel transaksi (`batches`, `orders`) kosong.

## 2. Upload kode
**Opsi A (SSH — disarankan):**
```
cd ~/domains/<domainmu>
git clone <repo-url> app        # atau upload zip lalu unzip
cd app
composer install --no-dev --optimize-autoloader
```
**Opsi B (tanpa SSH):** upload seluruh folder project via File Manager/FTP **termasuk folder
`vendor/`** (jalankan `composer install --no-dev` di lokal dulu) dan `public/build/`.

Letakkan project **di luar** `public_html` (mis. `~/domains/<domain>/app`), lalu arahkan document root
ke `app/public` (langkah 4).

## 3. Konfigurasi `.env`
```
cp .env.production.example .env
```
Isi: `APP_URL` (HTTPS), `DB_DATABASE/DB_USERNAME/DB_PASSWORD` (dari langkah 1), lalu:
```
php artisan key:generate        # (SSH) — atau tempel APP_KEY dari lokal
```
Pastikan `APP_ENV=production` & `APP_DEBUG=false`.

## 4. Document root ke /public
- hPanel → **Website → domain → Advanced / Change document root** → set ke
  `.../app/public`. **Jangan** taruh isi Laravel langsung di `public_html`.
- Kalau plan tak bisa ubah document root: taruh isi folder `public/` ke `public_html/`, dan edit
  `public_html/index.php` agar `require __DIR__.'/../app/vendor/autoload.php'` & bootstrap menunjuk
  ke lokasi `app`. (Opsi ubah document root jauh lebih bersih.)

## 5. Storage & permission
```
php artisan storage:link                 # symlink public/storage -> storage/app/public
chmod -R 775 storage bootstrap/cache
```
- Kalau `storage:link` gagal (symlink diblok shared hosting): buat symlink via File Manager, atau
  salin isi `storage/app/public` ke `public/storage` manual (tapi symlink lebih baik).
- **Upload file gambar** produk ke `storage/app/public/...` sesuai path yang ada di DB
  (kolom `product_files.path`).

## 6. Ekstensi PHP & versi
- hPanel → **PHP Configuration** → pilih **PHP 8.3** → aktifkan ekstensi:
  `zip`, `pdo_mysql`, `mbstring`, `gd`/`imagick`, `fileinfo`, `openssl`, `bcmath`, `ctype`, `curl`.
  (`zip` WAJIB untuk unduh ZIP desain & upload xlsx settlement.)

## 7. Optimasi produksi (SSH)
```
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
> Setiap ganti `.env`, ulangi `php artisan config:cache` (atau `config:clear`).

## 8. HTTPS
- hPanel → **Security → SSL**: pasang sertifikat gratis. Pastikan **Force HTTPS** aktif.
- `SESSION_SECURE_COOKIE=true` (sudah di template) mengharuskan HTTPS.

## 9. Cron (penjadwalan reminder)
- hPanel → **Advanced → Cron Jobs** → tambah, tiap menit:
  ```
  php /home/USER/domains/<domain>/app/artisan schedule:run >> /dev/null 2>&1
  ```

## 10. 🔴 WAJIB setelah live — keamanan akun
Dump membawa 4 akun uji berpassword `password`. **Segera:**
1. Login sebagai admin (`420freq@gmail.com`) lewat HTTPS.
2. **Ganti semua password** & **perbarui email** ke email asli (menu **Users**, atau profil).
3. Hapus/ubah akun uji `@420frequency.test` jika tak dipakai.
   Alternatif via SSH tinker (kamu yang mengetik password):
   ```
   php artisan tinker
   >>> $u = App\Models\User::where('email','420freq@gmail.com')->first();
   >>> $u->update(['email'=>'EMAIL_ASLI','password'=>bcrypt('PASSWORD_BARU_KUAT')]);
   ```

---

## ✅ Checklist go-live
- [ ] DB dibuat + `420frequency-golive.sql` diimpor (produk 33, transaksi 0)
- [ ] Kode + `vendor/` + `public/build/` terupload; `composer install --no-dev`
- [ ] `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` HTTPS, DB terisi, `APP_KEY` di-set
- [ ] Document root → `/public`
- [ ] `storage:link` + file gambar produk terupload + permission 775
- [ ] PHP 8.3 + ekstensi (`zip`, dll) aktif
- [ ] `config:cache route:cache view:cache`
- [ ] SSL/HTTPS aktif + force
- [ ] Cron `schedule:run` terpasang
- [ ] **Semua password default diganti & email diperbarui**
- [ ] (opsional) SMTP asli, `FONNTE_TOKEN`, backup DB terjadwal

Setelah semua ✔, buka `https://<domainmu>` — halaman login muncul. Selesai.

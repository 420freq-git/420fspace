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
   Dump ini sudah diuji impor bersih: `products` 33, `batches`/`orders`/`sales` 0, dan
   `php artisan migrate` sesudahnya melaporkan **"Nothing to migrate"**.
3. Siapkan **APP_KEY** bila server tak bisa `artisan`: jalankan lokal `php artisan key:generate --show`
   lalu tempel ke `.env` server.
4. Siapkan file gambar produk (mockup/desain) di `storage/app/public/` untuk diupload juga.

---

## 1. Database (hPanel)
1. hPanel → **Databases → MySQL Databases**: buat database + user + password kuat. Catat namanya.
2. Buka **phpMyAdmin** database itu → tab **Import** → unggah `420frequency-golive.sql` → Go.
3. Verifikasi: tabel `products` berisi 33 baris, tabel transaksi (`batches`, `orders`) kosong.

## 2. Upload kode
> ⚠️ Repo ini **belum punya git remote** (`git remote -v` kosong). Jadi `git clone` belum bisa
> dipakai sampai kamu push ke GitHub/GitLab dulu. Selama belum, pakai jalur upload arsip.

**Opsi A (SSH + upload arsip — dipakai sekarang):**
Di lokal, buat arsip **tanpa** `node_modules`, `vendor`, `.git`, dan `.env`:
```
git archive --format=zip -o 420f.zip HEAD
```
`git archive` hanya mengambil file yang di-track, jadi `public/build/` (di-gitignore) **tidak ikut** —
upload folder itu terpisah, atau bangun di server bila Node tersedia. Lalu di server:
```
cd ~/domains/<domainmu> && mkdir -p app && cd app
unzip ~/420f.zip
composer install --no-dev --optimize-autoloader
```

**Opsi B (setelah punya remote git):**
```
cd ~/domains/<domainmu>
git clone <repo-url> app
cd app && composer install --no-dev --optimize-autoloader
```

**Opsi C (tanpa SSH):** upload seluruh folder project via File Manager/FTP **termasuk folder
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

**Keputusan go-live ini:**
- `MAIL_MAILER=log` — belum pakai SMTP. Email tidak terkirim, hanya dicatat di
  `storage/logs`. Konsekuensinya fitur **"Lupa password" tidak berfungsi**; tautannya otomatis
  disembunyikan di halaman login selama mailer masih `log`. Reset password dilakukan admin 420F
  lewat menu **Users → Reset password**.
- `FONNTE_TOKEN` dikosongkan — notifikasi WhatsApp mati (aman, hanya menulis log).

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
- ⚠️ **Cek dulu `public/storage` itu symlink, bukan folder biasa.** Saat project disalin/di-zip
  antar perangkat atau diupload via FTP, symlink sering berubah jadi **folder kosong**. Akibatnya
  `storage:link` diam saja (menganggap link sudah ada) dan **semua gambar gagal tampil** —
  mockup/desain jadi ikon rusak — padahal tombol unduh tetap normal karena unduhan lewat
  controller, bukan URL `/storage/...`. Terjadi di lokal 23 Jul 2026 setelah pindah perangkat.
  ```bash
  ls -la public/            # harus tampil: storage -> .../storage/app/public
  rmdir public/storage      # hapus HANYA kalau folder kosong, lalu:
  php artisan storage:link
  ```
  Verifikasi cepat lewat HTTP (harus `HTTP 200` + `image/jpeg`):
  ```bash
  curl -s -o /dev/null -w "%{http_code} %{content_type}\n" https://<domain>/storage/products/3/<nama-file>.jpg
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

## 9. Cron (penjadwalan reminder) — *opsional saat ini*
Reminder WhatsApp belum dipakai (`FONNTE_TOKEN` kosong), jadi cron **boleh dilewati dulu**.
Pasang saat mengaktifkan Fonnte (perlu juga mengisi `no_hp` tiap user — saat ini masih kosong semua).
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
- [ ] **Semua password default diganti & email diperbarui** (4 akun, semua masih `password`)
- [ ] (dilewati dulu) Cron `schedule:run` — baru perlu saat Fonnte aktif
- [ ] (opsional) SMTP asli, `FONNTE_TOKEN`, backup DB terjadwal

Setelah semua ✔, buka `https://<domainmu>` — halaman login muncul. Selesai.

---

## 🔧 Kalau ada bug setelah live

### Aturan pertama: JANGAN nyalakan `APP_DEBUG=true` di produksi
Halaman error Laravel dalam mode debug **membocorkan isi `.env`** (password DB, `APP_KEY`) ke
siapa pun yang membuka halaman itu. Diagnosis lewat log, bukan lewat debug mode.

### Ambil lognya
```bash
tail -n 100 ~/domains/<domain>/app/storage/logs/laravel-$(date +%F).log
```
Log dirotasi harian (`LOG_STACK=daily`), jadi file kemarin ada di `laravel-YYYY-MM-DD.log`.

### Backup dulu sebelum perbaikan apa pun yang menyentuh data
```bash
mysqldump -u <user> -p <nama_db> > ~/backup-$(date +%F-%H%M).sql
```

### Setelah mengubah kode atau `.env` di server
```bash
php artisan config:clear && php artisan config:cache
php artisan route:clear  && php artisan route:cache
php artisan view:clear   && php artisan view:cache
```
> Lupa `config:cache` sesudah ganti `.env` = perubahan tidak berefek. Ini penyebab paling sering
> "sudah diubah kok masih sama".

### Jangan sentuh ini di produksi
- **Settings → Reset transaksi** — TRUNCATE seluruh tabel transaksi. Ini alat pembersih data uji.
- `php artisan migrate:fresh` — menghapus seluruh database.

### Info yang perlu dikumpulkan saat lapor bug
Makin lengkap, makin cepat ketemu. Idealnya semua ini:
1. **Potongan log** dari perintah `tail` di atas (bagian `[timestamp] production.ERROR`).
2. **URL** halaman dan **role/POV** yang sedang login (420F / TM420 / VOOJAH / Diferd).
3. **Langkah** yang dilakukan sampai muncul masalah.
4. **Yang diharapkan vs yang terjadi** — khusus bug angka uang/stok, sebutkan angka yang muncul
   di layar dan angka yang kamu anggap benar, beserta halamannya.
5. Untuk masalah "siapa mengubah apa": cek menu **Audit log** (420F).

### Setiap bug uang/stok yang diperbaiki → tambahkan test
Suite di `tests/Feature/Erp/` (51 test) adalah jaring pengaman logika uang. Bug hitungan yang
diperbaiki tanpa test cenderung kembali saat ada perubahan lain. Pola: tulis test yang gagal dulu
(mereproduksi bug), baru perbaiki. Jalankan `php artisan test` sebelum deploy.

### Kenapa git remote penting untuk ini
Tanpa remote, tidak ada cara memastikan kode yang jalan di server sama dengan kode di laptop —
diagnosis jadi menebak. Dengan remote, versi terpasang bisa dipastikan (`git log -1`) dan
perbaikan bisa di-deploy dengan `git pull` + rangkaian `*:cache` di atas.

### Catatan soal sesi bantuan berikutnya
Asisten memulai tiap sesi tanpa ingatan sesi sebelumnya. Konteks yang menolong ada di repo:
`CLAUDE.md` (konsep bisnis & aturan uang), dokumen ini, `tests/Feature/Erp/`, dan riwayat git.
Untuk bug hitungan uang/stok, `CLAUDE.md` §3–§4 adalah rujukan utama — jangan perbaiki angka di
satu halaman saja tanpa menyamakan di semua halaman (Cashflow, Dashboard, Settlement, Penarikan,
Stok, Rapor).

# 420Frequency — Design System (untuk web ERP & proyek lain)

Dokumen ini menangkap **tema/warna/komponen** aplikasi 420Frequency agar produk lain (mis. **ERP
420Frequency**) tampil **seragam**. Salin file ini ke repo proyek baru dan rujuk di `CLAUDE.md`-nya
("gunakan `docs/DESIGN-SYSTEM.md` untuk semua UI").

**Stack UI:** Tailwind CSS v3 · `@tailwindcss/forms` · Alpine.js · font **Figtree** · Blade (atau
framework lain, kelasnya tetap sama).

---

## 1. Karakter visual
Bersih, tenang, **padat data** (dashboard/tabel/uang). Netral **hangat** (sand) sebagai dasar,
**hijau** (brand) sebagai aksen utama. Sudut membulat (`rounded-lg`/`rounded-xl`), garis tipis
(`border-sand-200`), bayangan halus (`shadow-sm`). Angka pakai tabular (`.tnum`). Bahasa: Indonesia.

---

## 2. Palet warna (WAJIB sama)

`brand` = hijau (aksi/positif). `sand` = netral hangat (teks, garis, latar).

```js
// tailwind.config.js — bagian theme.extend.colors
brand: {
  50:'#F0F6EC', 100:'#DBEBD0', 200:'#BAD8A6', 300:'#93C077', 400:'#6BA34C',
  500:'#4C8431', 600:'#3A6A26', 700:'#2E5622', 800:'#26451D', 900:'#1C3416',
},
sand: {
  50:'#FBFAF7', 100:'#F4F2EB', 200:'#E7E4DA', 300:'#D5D1C4', 400:'#A9A597',
  500:'#7C7869', 600:'#5A5750', 700:'#403E38', 800:'#2A2925', 900:'#1A1917',
},
```

**Warna semantik** (pakai skala bawaan Tailwind, konsisten):
- Sukses/positif → **brand** (hijau). Contoh badge: `bg-brand-100 text-brand-800`.
- Peringatan/mepet → **amber**. `bg-amber-100 text-amber-800`, kartu `border-amber-200 bg-amber-50`.
- Bahaya/telat/hapus → **red**. `bg-red-100 text-red-700`, kartu `border-red-200 bg-red-50`.
- Info/uang muka/deposit → **blue**. `bg-blue-100 text-blue-800`.
- Cash/khusus → **indigo**. Refund/reject → **rose**.
> Aksen utama tetap **brand (hijau)**; semantik hanya untuk status, bukan mengganti aksen.

---

## 3. Konfigurasi (copy-paste ke proyek baru)

**`tailwind.config.js`:**
```js
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

export default {
  content: ['./resources/**/*.blade.php', './app/**/*.php', './resources/**/*.js'],
  theme: {
    extend: {
      fontFamily: { sans: ['Figtree', ...defaultTheme.fontFamily.sans] },
      colors: { /* brand & sand dari §2 */ },
    },
  },
  plugins: [forms],
};
```

**`resources/css/app.css`:**
```css
@tailwind base;
@tailwind components;
@tailwind utilities;

@layer base { body { @apply bg-sand-50 text-sand-800; } }
@layer utilities { .tnum { font-variant-numeric: tabular-nums; } }
[x-cloak] { display: none !important; }
```

**Font (di `<head>`):**
```html
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
```
`<body class="font-sans antialiased text-sand-800">`

---

## 4. Resep komponen (kelas persis seperti aplikasi ini)

**Container halaman:**
```html
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6"> … </div>
```

**Header halaman:**
```html
<h1 class="text-lg font-semibold text-sand-900">Judul</h1>
<p class="text-xs text-sand-500">Sub-judul penjelas.</p>
```

**Judul seksi:**
```html
<h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400">Nama Seksi</h2>
```

**Kartu / panel:**
```html
<div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6"> … </div>
```

**Kartu KPI (angka besar):**
```html
<div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
  <p class="text-sm text-sand-500">Label</p>
  <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">Rp 1.234.000</p>
  <p class="mt-1 text-xs text-sand-400">keterangan kecil</p>
</div>
```
(Varian status: ganti border/bg ke `amber`/`red`/`brand` sesuai kondisi.)

**Tombol primer / sekunder:**
```html
<button class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Simpan</button>
<button class="rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Batal</button>
```

**Badge / pill status:**
```html
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-brand-100 text-brand-800">Lunas</span>
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Menunggu</span>
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Telat</span>
```

**Input (dengan @tailwindcss/forms):**
```html
<label class="block text-xs font-medium text-sand-600">Jumlah</label>
<input class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600 tnum">
```

**Tabel:**
```html
<div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
          <th class="px-5 py-3 font-semibold">Kolom</th>
          <th class="px-5 py-3 font-semibold text-right">Nilai</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-sand-100">
        <tr class="hover:bg-sand-50/50">
          <td class="px-5 py-3 text-sand-700">…</td>
          <td class="px-5 py-3 text-right tnum text-sand-800">…</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
```

**Bar progres:**
```html
<div class="h-2 rounded-full bg-sand-100 overflow-hidden">
  <div class="h-full rounded-full bg-brand-500" style="width:70%"></div>
</div>
```

**Alert flash:**
```html
<div class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800">Berhasil.</div>
<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">Gagal.</div>
```

---

## 5. Aturan konsistensi
- **Uang & angka** selalu `tnum`, format `Rp 1.234.000` (titik ribuan, tanpa desimal).
- Aksi utama = **brand-700**; jangan pakai warna semantik untuk tombol biasa.
- Sudut kartu `rounded-xl`, tombol/input `rounded-lg`, badge `rounded-full`.
- Garis `border-sand-200`, pemisah tabel `divide-sand-100`, bayangan `shadow-sm`.
- Teks: judul `text-sand-900`, isi `text-sand-700/800`, sekunder `text-sand-500`, samar `text-sand-400`.
- Toggle/interaksi ringan pakai **Alpine.js** (`x-data`, `x-show`, `x-cloak`).

---

## 6. Catatan untuk ERP 420Frequency
- Domain berbeda (keuangan: saldo, pembelian, pengeluaran, cicilan) → **repo terpisah**, tapi
  **tema identik** via dokumen ini.
- Kalau ERP perlu menampilkan data dari 420Frequency (mis. saldo/utang), sambungkan via **API**
  (lihat pola di `docs/API-INTEGRASI-TM.md` proyek 420Frequency) — jangan berbagi database.
- Salin `tailwind.config.js`, `app.css`, dan link font di atas apa adanya agar warna 1:1 sama.

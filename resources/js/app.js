import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

/**
 * Ganti tahap produksi tanpa reload halaman.
 *
 * Dulu dropdown ini men-submit form biasa, jadi tiap ganti tahap seluruh halaman dimuat ulang
 * (posisi scroll ditambal lewat sessionStorage). Sekarang: PATCH lewat fetch, lalu baris PO
 * diganti dengan HTML yang DIRENDER SERVER — supaya aturan tahap tetap satu sumber di PHP.
 *
 * Reload hanya dilakukan bila status "selesai" batch berubah, karena batch yang jadi selesai
 * harus pindah bagian di halaman monitoring dan itu tak bisa ditambal per-baris.
 *
 * Pakai event delegation agar baris hasil penggantian tetap ikut aktif.
 */
document.addEventListener('change', async (e) => {
    const select = e.target.closest('[data-tahap-select]');
    if (!select) return;

    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!token) return;                      // tanpa CSRF token, biarkan (tak ada aksi diam-diam)

    const sebelumnya = select.dataset.tahapTerakhir || '';
    select.disabled = true;

    try {
        const res = await fetch(select.dataset.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ _method: 'PATCH', tahap: select.value }),
        });

        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();

        // Progress batch (halaman monitoring).
        const bar = document.querySelector(`[data-batch-progress="${data.batch_id}"]`);
        const angka = document.querySelector(`[data-batch-progress-text="${data.batch_id}"]`);
        if (bar) bar.style.width = data.progress + '%';
        if (angka) angka.textContent = data.progress + '%';

        // Batch berubah selesai/belum → susunan halaman berubah, muat ulang.
        const wadah = document.querySelector(`[data-batch-selesai="${data.batch_id}"]`);
        if (wadah && String(data.selesai ? 1 : 0) !== wadah.dataset.selesaiNilai) {
            window.location.reload();
            return;
        }

        // Ganti baris PO dengan versi terbaru dari server.
        const baris = document.querySelector(`[data-po-row="${data.po_id}"]`);
        if (baris && data.row_html) {
            baris.outerHTML = data.row_html;
            const baru = document.querySelector(`[data-po-row="${data.po_id}"]`);
            const status = baru?.querySelector('[data-tahap-status]');
            if (status) {
                status.textContent = '✓ tersimpan';
                status.style.opacity = '1';
                setTimeout(() => { status.style.opacity = '0'; }, 2000);
            }
        } else {
            tandaiTersimpan(select);         // halaman detail batch: cukup penanda kecil
        }
    } catch (err) {
        select.value = sebelumnya || select.value;
        select.disabled = false;
        tandaiGagal(select);
    }
});

/** Ingat nilai terakhir supaya bisa dikembalikan kalau simpan gagal. */
document.addEventListener('focusin', (e) => {
    const select = e.target.closest('[data-tahap-select]');
    if (select) select.dataset.tahapTerakhir = select.value;
});

function tandaiTersimpan(select) {
    select.disabled = false;
    const tanda = pesanDekat(select);
    tanda.textContent = '✓ tersimpan';
    tanda.className = 'ml-2 text-xs text-brand-600';
    setTimeout(() => tanda.remove(), 2000);
}

function tandaiGagal(select) {
    const tanda = pesanDekat(select);
    tanda.textContent = '✗ gagal disimpan';
    tanda.className = 'ml-2 text-xs text-red-600';
    setTimeout(() => tanda.remove(), 4000);
}

function pesanDekat(select) {
    let tanda = select.parentElement.querySelector('[data-tahap-flash]');
    if (!tanda) {
        tanda = document.createElement('span');
        tanda.setAttribute('data-tahap-flash', '');
        select.parentElement.appendChild(tanda);
    }
    return tanda;
}

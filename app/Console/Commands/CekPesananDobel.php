<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Mencari pesanan yang tercatat DUA KALI untuk brand yang sama: nomor asli ('123') DAN pecahan
 * bersufiks ('123-TM'). Terjadi sebelum dedup per (nomor asli, brand) ada — pesanan campur TM+VOOJAH
 * yang dulu tertarik sebagai TM-saja, lalu tertarik ulang sebagai campur.
 *
 * HANYA MEMBACA. Penghapusan sengaja manual (lewat menu Pesanan/Invoice) karena menyentuh tagihan,
 * stok, dan hak Diferd — perlu dilihat orang dulu.
 */
class CekPesananDobel extends Command
{
    protected $signature = 'pesanan:cek-dobel';

    protected $description = 'Daftar pesanan yang tercatat ganda (nomor asli + pecahan -KODE, brand sama)';

    public function handle(): int
    {
        $pecahan = Order::with(['brand', 'invoice', 'items.product'])
            ->where('nomor_pesanan', 'like', '%-%')->get();

        $baris = [];
        foreach ($pecahan as $p) {
            $asli = substr($p->nomor_pesanan, 0, strrpos($p->nomor_pesanan, '-'));
            $kembar = Order::with(['invoice', 'items.product'])
                ->where('brand_id', $p->brand_id)->where('nomor_pesanan', $asli)->first();
            if (! $kembar) {
                continue;
            }

            foreach ([$kembar, $p] as $o) {
                $baris[] = [
                    $o->id,
                    $o->nomor_pesanan,
                    $p->brand?->nama,
                    $o->status->value,
                    $o->invoice?->nomor.($o->invoice ? ' ('.$o->invoice->status.')' : '') ?: '—',
                    $o->items->map(fn ($s) => ($s->product?->nama_artikel ?? '?').' '.($s->ukuran->value ?? $s->ukuran).'×'.$s->qty)->implode(', '),
                ];
            }
        }

        if (! $baris) {
            $this->info('Tidak ada pesanan dobel.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Nomor', 'Brand', 'Status', 'Invoice', 'Isi'], $baris);
        $this->warn((count($baris) / 2).' pasang pesanan dobel. Hapus SALAH SATU dari tiap pasang (lihat panduan di commit).');

        return self::SUCCESS;
    }
}

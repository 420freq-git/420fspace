<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    /**
     * Tabel transaksi yang DIHAPUS saat reset. Master data (products, product_sizes/files/specs,
     * categories, category_prices, brands, users, settings) dan tabel sistem TIDAK disentuh.
     * Urutan tidak penting karena FK checks dimatikan sementara.
     */
    private const TABEL_TRANSAKSI = [
        'sales', 'orders', 'invoices',
        'pengiriman_items', 'pengiriman',
        'po_size_items', 'purchase_orders', 'batches',
        'vendor_ledger', 'brand_ledger', 'penarikan',
        'import_logs', 'audit_logs',
    ];

    public const DEFAULTS = [
        'monitor_hari' => 12,
        'cek_ulang_hari' => 2,
        'mepet_hari' => 3,
    ];

    public function index()
    {
        return view('settings.index', [
            'monitor_hari' => Setting::intVal('monitor_hari', self::DEFAULTS['monitor_hari']),
            'cek_ulang_hari' => Setting::intVal('cek_ulang_hari', self::DEFAULTS['cek_ulang_hari']),
            'mepet_hari' => Setting::intVal('mepet_hari', self::DEFAULTS['mepet_hari']),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'monitor_hari' => ['required', 'integer', 'min:1', 'max:90'],
            'cek_ulang_hari' => ['required', 'integer', 'min:1', 'max:30'],
            'mepet_hari' => ['required', 'integer', 'min:0', 'max:30'],
        ]);

        foreach ($data as $key => $value) {
            Setting::put($key, $value);
        }

        return back()->with('success', 'Pengaturan monitoring disimpan.');
    }

    /**
     * Reset transaksi — hapus semua data transaksi (batch, PO, pesanan, penjualan, pengiriman,
     * invoice, ledger, penarikan, audit). Produk & master data tetap. Tak bisa dibatalkan; karena
     * itu wajib ketik "RESET" untuk konfirmasi dan sistem membuat backup .sql lebih dulu.
     */
    public function resetTransaksi(Request $request)
    {
        $request->validate([
            'konfirmasi' => ['required', 'in:RESET'],
        ], [
            'konfirmasi.in' => 'Ketik RESET (huruf kapital) untuk mengonfirmasi.',
        ]);

        $backup = $this->backupDatabase();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (self::TABEL_TRANSAKSI as $tabel) {
            DB::table($tabel)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $pesan = 'Semua data transaksi dihapus. Produk & master data tetap utuh.';
        $pesan .= $backup ? ' Backup dibuat: '.basename($backup).'.' : ' (Backup otomatis gagal — data tetap dihapus.)';

        return back()->with('success', $pesan);
    }

    /** Backup seluruh DB ke storage/backup sebelum reset. Return path bila berhasil. */
    private function backupDatabase(): ?string
    {
        $dump = collect(glob('C:/laragon/bin/mysql/*/bin/mysqldump.exe'))->first();
        if (! $dump) {
            return null;
        }

        $dir = storage_path('backup');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir.DIRECTORY_SEPARATOR.'sebelum-reset-'.now()->format('Ymd-His').'.sql';

        $db = config('database.connections.'.config('database.default'));
        $cmd = sprintf(
            '"%s" -u%s %s %s > "%s" 2>&1',
            $dump,
            escapeshellarg($db['username']),
            $db['password'] ? '-p'.escapeshellarg($db['password']) : '',
            escapeshellarg($db['database']),
            $file
        );
        @exec($cmd, $out, $status);

        return ($status === 0 && is_file($file) && filesize($file) > 0) ? $file : null;
    }
}

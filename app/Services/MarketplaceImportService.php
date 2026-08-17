<?php

namespace App\Services;

use App\Enums\Marketplace;
use App\Enums\SizeTier;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSize;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MarketplaceImportService
{
    public function __construct(private StockService $stock) {}

    /** Peta kolom per marketplace: [orderId, status, resi, sku, qty, tanggal, nama]. */
    private array $maps = [
        'tiktokshop' => [
            'orderId' => 'Order ID', 'status' => 'Order Status', 'resi' => 'Tracking ID',
            'sku' => 'Seller SKU', 'qty' => 'Quantity', 'tanggal' => 'Created Time', 'nama' => 'Product Name',
        ],
        'shopee' => [
            'orderId' => 'No. Pesanan', 'status' => 'Status Pesanan', 'resi' => 'No. Resi',
            'sku' => 'Nomor Referensi SKU', 'sku_alt' => 'SKU Induk', 'qty' => 'Jumlah',
            'tanggal' => 'Waktu Pesanan Dibuat', 'nama' => 'Nama Produk',
        ],
    ];

    public function import(UploadedFile $file): array
    {
        try {
            [$headers, $rows] = $this->readSheet($file);
        } catch (\Throwable $e) {
            return ['error' => $this->pesanGagalBaca($e)];
        }
        $marketplace = $this->detectMarketplace($headers);

        if (! $marketplace) {
            return ['error' => 'Format file tidak dikenali (bukan export pesanan TikTok atau Shopee).'];
        }

        $map = $this->maps[$marketplace];
        $idx = $this->headerIndex($headers, $map);
        $skuMap = $this->skuMap(); // sku_turunan(upper) => ProductSize

        $summary = [
            'marketplace' => Marketplace::from($marketplace)->label(),
            'imported_orders' => 0, 'imported_items' => 0,
            'skip_stok0' => 0, 'skip_order_stok0' => 0, 'melebihi_stok' => 0,
            'skip_dibatalkan' => 0, 'skip_sudah_ada' => 0, 'skip_sku_tak_dikenal' => 0,
            'sku_tak_dikenal' => [],
        ];

        // Kelompokkan baris per Order ID
        $grouped = [];
        foreach ($rows as $row) {
            $orderId = $this->cell($row, $idx['orderId']);
            if ($orderId === '') {
                continue;
            }
            $status = strtolower($this->cell($row, $idx['status']));
            if (str_contains($status, 'batal') || str_contains($status, 'cancel')) {
                $summary['skip_dibatalkan']++;
                continue;
            }
            $sku = strtoupper($this->cell($row, $idx['sku']));
            if ($sku === '' && isset($idx['sku_alt'])) {
                $sku = strtoupper($this->cell($row, $idx['sku_alt']));
            }
            if ($sku === '' || ! $skuMap->has($sku)) {
                $summary['skip_sku_tak_dikenal']++;
                if ($sku !== '') {
                    $summary['sku_tak_dikenal'][$sku] = true;
                }
                continue;
            }
            $grouped[$orderId][] = [
                'size' => $skuMap->get($sku),
                'qty' => max(1, (int) $this->cell($row, $idx['qty'])),
                'resi' => $this->cell($row, $idx['resi']),
                'tanggal' => $this->parseDate($this->cell($row, $idx['tanggal'])),
            ];
        }

        foreach ($grouped as $orderId => $lines) {
            $this->createOrder($orderId, $marketplace, $lines, $summary);
        }

        $summary['sku_tak_dikenal'] = array_slice(array_keys($summary['sku_tak_dikenal']), 0, 30);

        return $summary;
    }

    /**
     * Import pesanan dari data ERP 420F (arah balik: file diunggah di ERP, produksi menariknya).
     * Memakai ulang alokasi stok & dedupe nomor_pesanan yang sama dengan import file — jadi
     * tarik ulang aman (pesanan yang nomornya sudah ada dilewati). Tiap item ERP membawa SKU
     * level ukuran (sku_asli) untuk dicocokkan ke ProductSize.
     *
     * @param  array  $pesananList  daftar pesanan: [{no_pesanan, marketplace, tanggal, items:[{sku, qty}]}]
     */
    public function importDariErp(array $pesananList): array
    {
        $skuMap = $this->skuMap();
        $mpMap = ['tiktok' => 'tiktokshop', 'tiktokshop' => 'tiktokshop', 'shopee' => 'shopee', 'whatsapp' => 'whatsapp', 'web' => 'web'];

        $summary = [
            'sumber' => 'ERP 420F', 'pesanan_diterima' => count($pesananList),
            'imported_orders' => 0, 'imported_items' => 0,
            'skip_stok0' => 0, 'skip_order_stok0' => 0, 'melebihi_stok' => 0,
            'skip_dibatalkan' => 0, 'skip_sudah_ada' => 0, 'skip_sku_tak_dikenal' => 0,
            'sku_tak_dikenal' => [],
        ];

        foreach ($pesananList as $p) {
            $nomor = trim((string) ($p['no_pesanan'] ?? ''));
            if ($nomor === '') {
                continue;
            }
            $marketplace = $mpMap[strtolower((string) ($p['marketplace'] ?? ''))] ?? 'web';
            $tanggal = $this->parseDate((string) ($p['tanggal'] ?? '')) ?? now();

            $lines = [];
            foreach ($p['items'] ?? [] as $it) {
                $sku = strtoupper(trim((string) ($it['sku'] ?? '')));
                if ($sku === '' || ! $skuMap->has($sku)) {
                    $summary['skip_sku_tak_dikenal']++;
                    if ($sku !== '') {
                        $summary['sku_tak_dikenal'][$sku] = true;
                    }

                    continue;
                }
                $lines[] = [
                    'size' => $skuMap->get($sku),
                    'qty' => max(1, (int) ($it['qty'] ?? 1)),
                    'resi' => null,
                    'tanggal' => $tanggal,
                ];
            }

            if ($lines) {
                $this->createOrder($nomor, $marketplace, $lines, $summary);
            }
        }

        $summary['sku_tak_dikenal'] = array_slice(array_keys($summary['sku_tak_dikenal']), 0, 30);

        return $summary;
    }

    /**
     * Satu pesanan marketplace bisa berisi produk >1 brand (mis. TM & VOOJAH dalam 1 keranjang).
     * Karena invariant "1 pesanan = 1 brand", pesanan campur DIPECAH otomatis jadi pesanan terpisah
     * per brand. Nomor diberi sufiks kode brand agar unik (nomor_pesanan unik). Settlement tetap
     * cocok karena importSettlement mencocokkan nomor asli maupun awalannya (lihat method itu).
     */
    private function createOrder(string $orderId, string $marketplace, array $lines, array &$summary): void
    {
        $perBrand = [];
        foreach ($lines as $line) {
            $perBrand[$line['size']->product->brand_id][] = $line;
        }
        $multiBrand = count($perBrand) > 1;

        foreach ($perBrand as $brandLines) {
            $brand = $brandLines[0]['size']->product->brand;
            $nomor = $multiBrand ? $orderId.'-'.($brand->kode ?: 'B'.$brand->id) : $orderId;
            $this->buatPesanan($nomor, $marketplace, $brandLines, $summary);
        }
    }

    /** Buat satu pesanan (satu brand) dari baris-baris yang sudah dikelompokkan. */
    private function buatPesanan(string $nomor, string $marketplace, array $lines, array &$summary): void
    {
        if (Order::where('nomor_pesanan', $nomor)->exists()) {
            $summary['skip_sudah_ada']++;

            return;
        }

        DB::transaction(function () use ($nomor, $marketplace, $lines, &$summary) {
            $first = $lines[0];
            $size = $first['size'];
            $product = $size->product;

            $order = Order::create([
                'brand_id' => $product->brand_id,
                'nomor_pesanan' => $nomor,
                'resi' => $first['resi'] ?: null,
                'marketplace' => $marketplace,
                'tanggal_pesanan' => $first['tanggal'],
                'status' => 'dipesan',
                'sumber' => 'import',
            ]);

            $adaItem = false;
            foreach ($lines as $line) {
                /** @var ProductSize $size */
                $size = $line['size'];
                $product = $size->product;
                $ukuran = $size->ukuran->value;
                $tier = SizeTier::forUkuran($ukuran);
                $diferd = $product->effectiveDiferd($tier) ?? 0;
                $tm420 = $product->hargaTagihan($tier);   // VOOJAH ditagih harga Diferd

                $result = $this->stock->allocate($product->brand_id, $product->id, $ukuran, $line['qty']);

                // Guard: penjualan hanya sah bila menarik stok nyata dari sistem ini. Baris dengan
                // stok 0 (mungkin sudah di-buy-out / tak pernah diproduksi lewat sistem) diabaikan;
                // kelebihan di atas stok tersedia juga tidak dicatat (bukan penjualan yg terlacak).
                foreach ($result['alloc'] as $a) {
                    $this->makeItem($order, $product, $a['batch']->id, $ukuran, $a['qty'], $diferd, $tm420, $first['tanggal'], $marketplace);
                    $summary['imported_items']++;
                    $adaItem = true;
                }
                if (empty($result['alloc'])) {
                    $summary['skip_stok0'] += $line['qty'];
                } elseif ($result['remaining'] > 0) {
                    $summary['melebihi_stok'] += $result['remaining'];
                }
            }

            // Pesanan yang seluruh barisnya tanpa stok tidak dibuat sama sekali.
            if (! $adaItem) {
                $order->delete();
                $summary['skip_order_stok0']++;

                return;
            }
            $summary['imported_orders']++;
        });
    }

    /**
     * Import file settlement/income → tandai pesanan yang sudah cair menjadi LUNAS.
     * Cocok berdasarkan Order ID. TM420 discope via $brandId.
     */
    public function importSettlement(UploadedFile $file, ?int $brandId = null): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            // JANGAN hitung rumus (argumen ke-2 = false). File income Shopee menaruh baris ringkasan
            // berisi `==SUM(INDIRECT(...))` — dobel '=' dan tak valid, sehingga PhpSpreadsheet
            // melempar "Unexpected operator '='" dan SELURUH impor gagal. Importer hanya butuh nilai
            // mentah (nomor pesanan & tanggal cair), jadi rumus memang tak perlu dihitung.
            $data = $spreadsheet->getActiveSheet()->toArray(null, false, true, false);
        } catch (\Throwable $e) {
            return ['error' => $this->pesanGagalBaca($e)];
        }

        // Cari baris header (Shopee income diawali metadata)
        $headers = null;
        $hIdx = 0;
        foreach ($data as $i => $row) {
            $cells = array_map(fn ($c) => trim((string) $c), $row);
            if (in_array('ID Pesanan/Penyesuaian', $cells, true)
                || (in_array('No. Pesanan', $cells, true) && in_array('Tanggal Dana Dilepaskan', $cells, true))) {
                $headers = $cells;
                $hIdx = $i;
                break;
            }
        }
        if (! $headers) {
            return ['error' => 'File tidak dikenali sebagai settlement TikTok atau Shopee.'];
        }

        $flip = array_flip($headers);
        if (isset($flip['ID Pesanan/Penyesuaian'])) {
            $mp = 'TikTok Shop';
            $orderCol = $flip['ID Pesanan/Penyesuaian'];
            $amtCol = $flip['Jumlah penyelesaian pembayaran'] ?? null;
            $dateCol = $flip['Waktu pembayaran pesanan'] ?? null;
        } else {
            $mp = 'Shopee';
            $orderCol = $flip['No. Pesanan'];
            $amtCol = null;
            $dateCol = $flip['Tanggal Dana Dilepaskan'] ?? null;
        }

        $settled = [];
        foreach (array_slice($data, $hIdx + 1) as $row) {
            $orderId = trim((string) ($row[$orderCol] ?? ''));
            if ($orderId === '') {
                continue;
            }
            $isSettled = $amtCol !== null
                ? ((float) str_replace(',', '', (string) ($row[$amtCol] ?? 0)) > 0)
                : trim((string) ($row[$dateCol] ?? '')) !== '';
            if ($isSettled) {
                $settled[$orderId] = $dateCol !== null ? $this->parseDate((string) ($row[$dateCol] ?? '')) : now();
            }
        }

        $summary = ['marketplace' => $mp, 'cair' => 0, 'tak_ditemukan' => 0, 'sudah_lunas' => 0, 'dilewati' => 0];
        foreach ($settled as $orderId => $tanggal) {
            // Cocokkan nomor asli MAUPUN pecahannya (mis. '123-TM','123-VJ' dari pesanan campur-brand).
            $orders = Order::where(function ($q) use ($orderId) {
                $q->where('nomor_pesanan', $orderId)->orWhere('nomor_pesanan', 'like', $orderId.'-%');
            })->when($brandId, fn ($q) => $q->where('brand_id', $brandId))->get();

            if ($orders->isEmpty()) {
                $summary['tak_ditemukan']++;
                continue;
            }
            foreach ($orders as $order) {
                if ($order->status->value === 'lunas') {
                    $summary['sudah_lunas']++;
                    continue;
                }
                if (in_array($order->status->value, ['batal', 'retur'], true)) {
                    $summary['dilewati']++;
                    continue;
                }
                $order->update(['status' => 'lunas', 'tgl_cair' => $tanggal]);
                $summary['cair']++;
            }
        }

        return $summary;
    }

    private function makeItem(Order $order, Product $product, ?int $batchId, string $ukuran, int $qty, int $diferd, ?int $tm420, Carbon $tanggal, string $marketplace): void
    {
        $order->items()->create([
            'brand_id' => $product->brand_id,
            'product_id' => $product->id,
            'batch_id' => $batchId,
            'ukuran' => $ukuran,
            'qty' => $qty,
            'tanggal_terjual' => $tanggal,
            'marketplace' => $marketplace,
            'nomor_pesanan' => $order->nomor_pesanan,
            'harga_diferd' => $diferd,
            'harga_tm420' => $tm420,
        ]);
    }

    // ----- parsing helpers -----

    /**
     * Pesan gagal-baca yang bisa ditindaklanjuti user, plus catatan teknis ke log.
     * Tanpa ini, file rusak jadi layar 500 kosong di produksi (APP_DEBUG=false).
     */
    private function pesanGagalBaca(\Throwable $e): string
    {
        \Illuminate\Support\Facades\Log::error('Gagal membaca file impor marketplace', [
            'exception' => get_class($e),
            'pesan' => $e->getMessage(),
        ]);

        return 'File tidak bisa dibaca. Pastikan yang diunggah adalah file export asli dari '
            .'marketplace (.xlsx/.csv) dan tidak rusak/terproteksi. Bila file dibuka & disimpan '
            .'ulang lewat Excel, coba unggah versi aslinya.';
    }

    private function readSheet(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        // Rumus tidak dihitung — lihat alasannya di importSettlement(). Berlaku sama untuk file
        // pesanan: cukup nilai mentah, dan rumus cacat dari marketplace tak boleh menggagalkan impor.
        $data = $spreadsheet->getActiveSheet()->toArray(null, false, true, false);
        $headers = array_map(fn ($h) => trim((string) $h), $data[0] ?? []);
        $rows = array_slice($data, 1);

        return [$headers, $rows];
    }

    private function detectMarketplace(array $headers): ?string
    {
        if (in_array('Order ID', $headers, true) && in_array('Seller SKU', $headers, true)) {
            return 'tiktokshop';
        }
        if (in_array('No. Pesanan', $headers, true) && in_array('Status Pesanan', $headers, true)) {
            return 'shopee';
        }

        return null;
    }

    private function headerIndex(array $headers, array $map): array
    {
        $flip = array_flip($headers);
        $idx = [];
        foreach ($map as $key => $col) {
            $idx[$key] = $flip[$col] ?? null;
        }

        return $idx;
    }

    private function cell(array $row, ?int $index): string
    {
        if ($index === null || ! array_key_exists($index, $row)) {
            return '';
        }

        return trim((string) $row[$index]);
    }

    /** @return \Illuminate\Support\Collection<string, ProductSize> */
    private function skuMap()
    {
        return ProductSize::with('product')->whereNotNull('sku_turunan')->where('sku_turunan', '!=', '')
            ->get()->keyBy(fn ($s) => strtoupper(trim($s->sku_turunan)));
    }

    private function parseDate(string $raw): Carbon
    {
        $raw = trim($raw);
        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y/m/d H:i:s', 'Y/m/d H:i', 'd/m/Y', 'Y-m-d', 'Y/m/d'] as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $raw);
            } catch (\Throwable) {
                continue;
            }
        }

        return Carbon::now();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ImportLog;
use App\Services\MarketplaceImportService;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(private MarketplaceImportService $importer) {}

    public function form()
    {
        return view('orders.import');
    }

    /**
     * Aturan validasi berkas impor.
     *
     * Ekspor settlement TikTok menyusun isi zip-nya dengan [Content_Types].xml BUKAN di urutan
     * pertama, sehingga fileinfo hanya mengenalinya sebagai application/zip — bukan xlsx. Karena
     * itu ekstensi divalidasi terpisah (extensions) dan 'zip' ikut diterima sebagai tipe isi,
     * supaya file TikTok yang sah tidak ikut tertolak. Berkas yang benar-benar bukan spreadsheet
     * tetap gagal saat dibaca PhpSpreadsheet dan dilaporkan sebagai error impor.
     */
    private function aturanBerkas(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv,txt,xlsx,xls', 'mimes:csv,txt,xlsx,xls,zip', 'max:20480'],
        ];
    }

    public function store(Request $request)
    {
        $request->validate($this->aturanBerkas());

        $file = $request->file('file');

        if ($dup = $this->duplikat($file, 'order', $request)) {
            return view('orders.import', ['dupWarning' => $dup]);
        }

        $summary = $this->importer->import($file);

        if (isset($summary['error'])) {
            return back()->with('error', $summary['error']);
        }

        $this->catat($file, 'order', $summary['marketplace'] ?? null, $summary, $request);

        return view('orders.import', ['summary' => $summary]);
    }

    public function settlementForm()
    {
        return view('orders.import-settlement');
    }

    public function settlementStore(Request $request)
    {
        $request->validate($this->aturanBerkas());

        $file = $request->file('file');

        if ($dup = $this->duplikat($file, 'settlement', $request)) {
            return view('orders.import-settlement', ['dupWarning' => $dup]);
        }

        $user = $request->user();
        $brandId = (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) ? $user->brand_id : null;

        $summary = $this->importer->importSettlement($file, $brandId);

        if (isset($summary['error'])) {
            return back()->with('error', $summary['error']);
        }

        $this->catat($file, 'settlement', $summary['marketplace'] ?? null, $summary, $request);

        return view('orders.import-settlement', ['summary' => $summary]);
    }

    /** Deteksi file identik yang sudah pernah diimpor (kecuali user memaksa lanjut). */
    private function duplikat($file, string $jenis, Request $request): ?array
    {
        if ($request->boolean('paksa')) {
            return null;
        }

        $hash = hash_file('sha256', $file->getRealPath());
        $prev = ImportLog::with('user')->where('jenis', $jenis)->where('hash', $hash)->latest('id')->first();

        if (! $prev) {
            return null;
        }

        return [
            'tanggal' => $prev->created_at->format('d/m/Y H:i'),
            'oleh' => $prev->user?->name ?? 'seseorang',
            'nama_file' => $prev->nama_file,
        ];
    }

    private function catat($file, string $jenis, ?string $marketplace, array $summary, Request $request): void
    {
        ImportLog::create([
            'user_id' => $request->user()->id,
            'jenis' => $jenis,
            'marketplace' => $marketplace,
            'nama_file' => $file->getClientOriginalName(),
            'hash' => hash_file('sha256', $file->getRealPath()),
            'ringkasan' => $summary,
        ]);
    }
}

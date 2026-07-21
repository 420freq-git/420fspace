<?php

namespace App\Http\Controllers;

use App\Enums\ProductFileType;
use App\Enums\Role;
use App\Enums\Ukuran;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['brand', 'category.prices'])->withCount('sizes')->orderBy('nama_artikel');

        // TM420 hanya lihat produk brand-nya
        $user = $request->user();
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) {
            $query->where('brand_id', $user->brand_id);
        }

        // Pencarian nama artikel / SKU induk
        $cari = trim((string) $request->input('cari'));
        if ($cari !== '') {
            $query->where(function ($w) use ($cari) {
                $w->where('nama_artikel', 'like', "%{$cari}%")
                    ->orWhere('sku_induk', 'like', "%{$cari}%");
            });
        }

        return view('products.index', [
            'products' => $query->paginate(25)->withQueryString(),
            'cari' => $cari,
        ]);
    }

    public function show(Request $request, Product $product)
    {
        $this->authorizeView($request, $product);
        $product->load(['brand', 'category.prices', 'sizes', 'files', 'spec']);

        return view('products.show', compact('product'));
    }

    public function downloadFile(Request $request, ProductFile $file)
    {
        $this->authorizeView($request, $file->product);

        abort_unless(Storage::disk('public')->exists($file->path), 404, 'File tidak ditemukan.');

        return Storage::disk('public')->download($file->path, $file->nama_asli);
    }

    public function downloadZip(Request $request, Product $product)
    {
        $this->authorizeView($request, $product);
        $product->load('files');

        abort_if($product->files->isEmpty(), 404, 'Belum ada file untuk diunduh.');

        $zipName = 'produk-'.$product->id.'-'.date('Ymd-His').'.zip';
        $tmp = storage_path('app/'.$zipName);

        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($product->files as $file) {
            $abs = Storage::disk('public')->path($file->path);
            if (is_file($abs)) {
                $zip->addFile($abs, $file->tipe->value.'/'.$file->nama_asli);
            }
        }
        $zip->close();

        return response()->download($tmp)->deleteFileAfterSend(true);
    }

    /** Brand yang dipaksakan untuk TM420 (null = bebas pilih, mis. 420F). */
    private function lockedBrand(Request $request): ?int
    {
        $user = $request->user();

        return (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id) ? (int) $user->brand_id : null;
    }

    /** Pilihan brand di form: TM420 hanya brand-nya sendiri. */
    private function brandOptions(Request $request)
    {
        $query = Brand::where('aktif', true)->orderBy('nama');
        if ($locked = $this->lockedBrand($request)) {
            $query->where('id', $locked);
        }

        return $query->get();
    }

    private function authorizeView(Request $request, Product $product): void
    {
        $user = $request->user();
        if (in_array($user->role, [Role::Tm420, Role::Voojah], true) && $user->brand_id && $product->brand_id !== $user->brand_id) {
            abort(403, 'Produk ini bukan milik brand Anda.');
        }
    }

    public function create(Request $request)
    {
        return view('products.create', [
            'product' => new Product(['aktif' => true, 'brand_id' => $this->lockedBrand($request)]),
            'brands' => $this->brandOptions($request),
            'categories' => Category::where('aktif', true)->with('prices')->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        // TM420 hanya boleh membuat produk untuk brand-nya sendiri.
        if ($locked = $this->lockedBrand($request)) {
            $data['brand_id'] = $locked;
            $request->merge(['brand_id' => $locked]);
        }

        $product = Product::create($this->coreData($request, $data));
        $this->syncSizes($request, $product);
        $this->saveSpec($request, $product);
        $this->storeFiles($request, $product);

        return redirect()->route('products.index')->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Request $request, Product $product)
    {
        $this->authorizeView($request, $product);
        $product->load(['sizes', 'files', 'spec', 'category.prices']);

        return view('products.edit', [
            'product' => $product,
            'brands' => $this->brandOptions($request),
            'categories' => Category::where('aktif', true)->with('prices')->orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeView($request, $product);
        $data = $this->validated($request, $product);

        if ($locked = $this->lockedBrand($request)) {
            $data['brand_id'] = $locked;
            $request->merge(['brand_id' => $locked]);
        }

        $product->update($this->coreData($request, $data));
        $this->syncSizes($request, $product);
        $this->saveSpec($request, $product);
        $this->storeFiles($request, $product);

        return redirect()->route('products.index')->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product)
    {
        foreach ($product->files as $file) {
            Storage::disk('public')->delete($file->path);
        }
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Produk dihapus.');
    }

    public function destroyFile(ProductFile $file)
    {
        Storage::disk('public')->delete($file->path);
        $product = $file->product;
        $file->delete();

        return redirect()->route('products.edit', $product)->with('success', 'File dihapus.');
    }

    // ----- helpers -----

    private function coreData(Request $request, array $data): array
    {
        $core = [
            'brand_id' => $data['brand_id'],
            'category_id' => $data['category_id'],
            'sku_induk' => $data['sku_induk'] ?? null,
            'nama_artikel' => $data['nama_artikel'],
            'aktif' => $request->boolean('aktif'),
        ];

        $cols = [
            'harga_diferd_sxl_override', 'harga_diferd_xxl_override',
            'harga_tm420_sxl_override', 'harga_tm420_xxl_override',
        ];

        if ($request->boolean('harga_khusus')) {
            foreach ($cols as $c) {
                $v = $request->input($c);
                $core[$c] = ($v === null || $v === '') ? null : (int) $v;
            }
        } else {
            foreach ($cols as $c) {
                $core[$c] = null;
            }
        }

        return $core;
    }

    private function syncSizes(Request $request, Product $product): void
    {
        $induk = trim((string) $request->input('sku_induk'));
        foreach (Ukuran::cases() as $u) {
            $sku = trim((string) $request->input("sku_turunan.{$u->value}"));
            if ($sku === '' && $induk !== '') {
                $sku = $induk.'-'.$u->value; // otomatis dari SKU induk
            }
            $product->sizes()->updateOrCreate(
                ['ukuran' => $u->value],
                ['sku_turunan' => $sku !== '' ? $sku : null],
            );
        }
    }

    private function saveSpec(Request $request, Product $product): void
    {
        $strings = ['patrun', 'ukuran_rib', 'warna_bahan', 'jenis_bahan', 'supp_bahan',
            'warna_benang', 'cat_sablon', 'finishing', 'desain_depan', 'desain_belakang', 'desain_lengan', 'note'];
        $bools = ['label_leher', 'label_bawah', 'slip_label', 'aksesoris', 'care_label', 'hangtag', 'plastik'];

        $spec = [];
        foreach ($strings as $f) {
            $spec[$f] = $request->input("spec.$f") ?: null;
        }
        foreach ($bools as $f) {
            $spec[$f] = $request->boolean("spec.$f");
        }

        $product->spec()->updateOrCreate(['product_id' => $product->id], $spec);
    }

    /** Batas sisi terpanjang gambar yang disimpan — cukup tajam untuk cetak & preview. */
    private const MAKS_SISI_PX = 2000;

    private function storeFiles(Request $request, Product $product): void
    {
        $map = [
            'mockups' => ProductFileType::Mockup,
            'desains' => ProductFileType::Desain,
            'mentahans' => ProductFileType::Mentahan,
        ];

        foreach ($map as $field => $type) {
            foreach ((array) $request->file($field, []) as $file) {
                if (! $file) {
                    continue;
                }
                $path = $file->store("products/{$product->id}", 'public');
                $this->kecilkanGambar(Storage::disk('public')->path($path));

                $product->files()->create([
                    'tipe' => $type->value,
                    'path' => $path,
                    'nama_asli' => $file->getClientOriginalName(),
                ]);
            }
        }
    }

    /**
     * Perkecil gambar yang dimensinya berlebihan, ditimpa di tempat.
     *
     * Ukuran file di disk menipu: PNG 5120×2880 hanya ~0,7 MB terkompresi, tapi jadi ±56 MB
     * saat dibuka jadi bitmap — cukup untuk mematikan render PDF (dompdf memuat gambar utuh ke
     * memori). Membatasi dimensi di titik unggah mencegahnya, bukan menaikkan memory_limit yang
     * hanya menggeser ambang gagalnya.
     */
    public static function kecilkanGambar(string $absPath, int $maks = self::MAKS_SISI_PX): bool
    {
        if (! is_file($absPath) || ! extension_loaded('gd')) {
            return false;
        }

        $info = @getimagesize($absPath);
        if (! $info) {
            return false;   // bukan gambar (mis. .txt/.psd mentahan) — biarkan apa adanya
        }

        [$lebar, $tinggi] = $info;
        if ($lebar <= $maks && $tinggi <= $maks) {
            return false;
        }

        // Memperkecil tetap butuh memuat bitmap utuh dulu, jadi limit dinaikkan sementara
        // sebesar kebutuhan gambar ini saja, lalu dikembalikan. Ini operasi sekali jalan;
        // hasil simpanannya kecil, sehingga render PDF sesudahnya tetap ringan.
        $butuh = (int) ($lebar * $tinggi * 4 * 1.6) + 64 * 1024 * 1024;
        if ($butuh > 1024 * 1024 * 1024) {
            return false;   // di luar batas wajar — jangan paksakan, biarkan ditolak di validasi
        }

        $limitLama = ini_get('memory_limit');
        ini_set('memory_limit', (int) ceil($butuh / 1048576).'M');

        try {
            $sumber = match ($info[2]) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($absPath),
                IMAGETYPE_PNG => @imagecreatefrompng($absPath),
                IMAGETYPE_WEBP => @imagecreatefromwebp($absPath),
                default => null,
            };
            if (! $sumber) {
                return false;
            }

            $rasio = $maks / max($lebar, $tinggi);
            $lebarBaru = max(1, (int) round($lebar * $rasio));
            $tinggiBaru = max(1, (int) round($tinggi * $rasio));

            $target = imagecreatetruecolor($lebarBaru, $tinggiBaru);
            if ($info[2] === IMAGETYPE_PNG || $info[2] === IMAGETYPE_WEBP) {
                imagealphablending($target, false);
                imagesavealpha($target, true);
            }
            imagecopyresampled($target, $sumber, 0, 0, 0, 0, $lebarBaru, $tinggiBaru, $lebar, $tinggi);

            match ($info[2]) {
                IMAGETYPE_JPEG => imagejpeg($target, $absPath, 88),
                IMAGETYPE_PNG => imagepng($target, $absPath, 6),
                IMAGETYPE_WEBP => imagewebp($target, $absPath, 88),
            };

            imagedestroy($sumber);
            imagedestroy($target);

            return true;
        } finally {
            ini_set('memory_limit', $limitLama);
        }
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'brand_id' => ['required', 'exists:brands,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'nama_artikel' => [
                'required', 'string', 'max:255',
                Rule::unique('products', 'nama_artikel')
                    ->where('brand_id', $request->input('brand_id'))
                    ->ignore($product?->id),
            ],
            'sku_induk' => ['nullable', 'string', 'max:255'],
            'aktif' => ['nullable', 'boolean'],
            'harga_khusus' => ['nullable', 'boolean'],
            'harga_diferd_sxl_override' => ['nullable', 'integer', 'min:0'],
            'harga_diferd_xxl_override' => ['nullable', 'integer', 'min:0'],
            'harga_tm420_sxl_override' => ['nullable', 'integer', 'min:0'],
            'harga_tm420_xxl_override' => ['nullable', 'integer', 'min:0'],
            'sku_turunan' => ['nullable', 'array', function ($attr, $value, $fail) {
                $vals = array_filter($value ?? [], fn ($v) => filled($v));
                if (count($vals) !== count(array_unique(array_map('strval', $vals)))) {
                    $fail('SKU turunan tidak boleh sama antar ukuran.');
                }
            }],
            'sku_turunan.*' => [
                'nullable', 'string', 'max:255',
                Rule::unique('product_sizes', 'sku_turunan')->where(fn ($q) => $q->where('product_id', '!=', $product?->id ?? 0)),
            ],
            'spec' => ['nullable', 'array'],
            'mockups.*' => ['nullable', 'image', 'max:5120'],
            'desains.*' => ['nullable', 'image', 'max:5120'],
            // File mentahan produksi (AI/PSD/PDF/ZIP/dll) — sampai 25 MB per file
            'mentahans.*' => ['nullable', 'file', 'max:25600'],
        ]);
    }
}

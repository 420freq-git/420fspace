@php
    use App\Enums\SizeTier;
    use App\Enums\Ukuran;

    $catPrices = [];
    foreach ($categories as $c) {
        $s = $c->priceFor(SizeTier::SXL);
        $x = $c->priceFor(SizeTier::XXL);
        $catPrices[$c->id] = [
            'sxl' => ['d' => $s?->harga_diferd, 't' => $s?->harga_tm420],
            'xxl' => ['d' => $x?->harga_diferd, 't' => $x?->harga_tm420],
        ];
    }

    $ov = fn ($col) => old($col, $product->$col);
@endphp

<div x-data="{
        categoryId: '{{ old('category_id', $product->category_id) }}',
        hargaKhusus: {{ old('harga_khusus', $product->hasOverride()) ? 'true' : 'false' }},
        catPrices: @js($catPrices),
        fmt(n){ return (n === null || n === undefined || n === '') ? '—' : 'Rp ' + Number(n).toLocaleString('id-ID'); },
        price(tier, kind){ const c = this.catPrices[this.categoryId]; return c ? c[tier][kind] : null; },
        fillSku(e){ const v = e.target.value.trim(); this.$root.querySelectorAll('input[name^=sku_turunan]').forEach(inp => { const m = inp.name.match(/\[(.+)\]/); if (m) inp.value = v ? v + '-' + m[1] : ''; }); }
     }"
     class="space-y-8">

    {{-- 1. Informasi produk --}}
    <section class="space-y-5">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400">Informasi produk</h2>
        <div class="grid sm:grid-cols-2 gap-5">
            <div>
                <label for="brand_id" class="block text-sm font-medium text-sand-700">Brand</label>
                <select name="brand_id" id="brand_id" required class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600">
                    <option value="">— pilih brand —</option>
                    @foreach ($brands as $b)
                        <option value="{{ $b->id }}" @selected(old('brand_id', $product->brand_id) == $b->id)>{{ $b->nama }}</option>
                    @endforeach
                </select>
                @error('brand_id') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="category_id" class="block text-sm font-medium text-sand-700">Kategori</label>
                <select name="category_id" id="category_id" x-model="categoryId" required class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600">
                    <option value="">— pilih kategori —</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}">{{ $c->nama }}</option>
                    @endforeach
                </select>
                @error('category_id') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="nama_artikel" class="block text-sm font-medium text-sand-700">Nama artikel</label>
                <input type="text" name="nama_artikel" id="nama_artikel" value="{{ old('nama_artikel', $product->nama_artikel) }}" required
                       class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600" placeholder="mis. Peace Of God">
                @error('nama_artikel') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="sku_induk" class="block text-sm font-medium text-sand-700">SKU induk <span class="text-sand-400 font-normal">(opsional)</span></label>
                <input type="text" name="sku_induk" id="sku_induk" value="{{ old('sku_induk', $product->sku_induk) }}" @input="fillSku($event)"
                       class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600 tnum" placeholder="mis. TM-POG">
                @error('sku_induk') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <label class="inline-flex items-center gap-2.5">
            <input type="checkbox" name="aktif" value="1" @checked(old('aktif', $product->aktif ?? true)) class="rounded border-sand-300 text-brand-700 focus:ring-brand-600">
            <span class="text-sm text-sand-700">Produk aktif</span>
        </label>
    </section>

    {{-- 2. SKU turunan per ukuran --}}
    <section class="space-y-3">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400">SKU turunan per ukuran</h2>
        <p class="text-xs text-sand-400 -mt-1">Terisi otomatis dari SKU induk (format <span class="font-medium">induk-ukuran</span>) — boleh diedit bila SKU marketplace berbeda.</p>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            @foreach (Ukuran::cases() as $u)
                @php $val = old("sku_turunan.{$u->value}", $product->sizes->firstWhere('ukuran', $u->value)?->sku_turunan); @endphp
                <div>
                    <label class="block text-xs font-medium text-sand-600">{{ $u->value }}</label>
                    <input type="text" name="sku_turunan[{{ $u->value }}]" value="{{ $val }}"
                           class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600 text-sm tnum" placeholder="SKU {{ $u->value }}">
                </div>
            @endforeach
        </div>
    </section>

    {{-- 3. Harga --}}
    @php
        $lihatDiferd = auth()->user()->bolehLihatHargaDiferd();
        $lihatTm = auth()->user()->bolehLihatHargaTm420();
    @endphp
    <section class="space-y-3">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400">Harga</h2>

        {{-- Preview harga kategori --}}
        <div x-show="!hargaKhusus" class="rounded-lg border border-sand-200 bg-sand-50/60 p-4">
            <p class="text-xs text-sand-500 mb-3">Harga mengikuti kategori terpilih.</p>
            <template x-if="categoryId">
                @if ($lihatDiferd && $lihatTm)
                    {{-- 420F / Diferd: modal & retail berdampingan --}}
                    <div class="grid grid-cols-3 gap-x-4 gap-y-1.5 text-sm max-w-md">
                        <span></span><span class="text-xs font-medium text-sand-500">Diferd</span><span class="text-xs font-medium text-sand-500">TM420</span>
                        <span class="text-sand-600">S–XL</span><span class="tnum text-sand-800" x-text="fmt(price('sxl','d'))"></span><span class="tnum text-sand-800" x-text="fmt(price('sxl','t'))"></span>
                        <span class="text-sand-600">XXL</span><span class="tnum text-sand-800" x-text="fmt(price('xxl','d'))"></span><span class="tnum text-sand-800" x-text="fmt(price('xxl','t'))"></span>
                    </div>
                @else
                    {{-- TM420 → retail (t); VOOJAH → modal (d), harga yang ia bayar --}}
                    @php $kolom = $lihatTm ? 't' : 'd'; @endphp
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm max-w-xs">
                        <span></span><span class="text-xs font-medium text-sand-500">Harga</span>
                        <span class="text-sand-600">S–XL</span><span class="tnum text-sand-800" x-text="fmt(price('sxl','{{ $kolom }}'))"></span>
                        <span class="text-sand-600">XXL</span><span class="tnum text-sand-800" x-text="fmt(price('xxl','{{ $kolom }}'))"></span>
                    </div>
                @endif
            </template>
            <template x-if="!categoryId"><p class="text-sm text-sand-400">Pilih kategori dulu untuk melihat harga.</p></template>
        </div>

        <label class="inline-flex items-center gap-2.5">
            <input type="checkbox" name="harga_khusus" value="1" x-model="hargaKhusus" class="rounded border-sand-300 text-brand-700 focus:ring-brand-600">
            <span class="text-sm text-sand-700">Pakai harga khusus (override kategori)</span>
        </label>

        <div x-show="hargaKhusus" x-cloak class="grid sm:grid-cols-2 gap-4">
            @foreach (['sxl' => 'S–XL', 'xxl' => 'XXL'] as $tk => $tl)
                <div class="rounded-lg border border-sand-200 p-4">
                    <div class="text-sm font-medium text-sand-800 mb-3">Tier <span class="text-brand-700">{{ $tl }}</span></div>
                    <div class="space-y-3">
                        @php
                            // Kolom harga yang boleh diisi per peran: VOOJAH hanya modal (diferd),
                            // TM420 hanya retail (tm420), 420F/Diferd keduanya.
                            $kinds = [];
                            if ($lihatDiferd) $kinds['diferd'] = $lihatTm ? 'Diferd' : 'Harga';
                            if ($lihatTm) $kinds['tm420'] = 'TM420';
                        @endphp
                        @foreach ($kinds as $kind => $klabel)
                            @php $col = "harga_{$kind}_{$tk}_override"; @endphp
                            <div>
                                <label class="block text-xs font-medium text-sand-600">Harga {{ $klabel }}</label>
                                <div class="mt-1 relative">
                                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-sand-400">Rp</span>
                                    <input type="number" min="0" name="{{ $col }}" value="{{ $ov($col) }}"
                                           placeholder="ikut kategori"
                                           class="block w-full rounded-lg border-sand-300 pl-9 focus:border-brand-600 focus:ring-brand-600 tnum">
                                </div>
                                @error($col) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 4. Upload file --}}
    <section class="space-y-3">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400">Mockup, desain &amp; file mentahan</h2>
        <div class="grid sm:grid-cols-2 gap-5">
            <div>
                <label for="mockups" class="block text-sm font-medium text-sand-700">Mockup <span class="text-sand-400 font-normal">(depan &amp; belakang)</span></label>
                <input type="file" name="mockups[]" id="mockups" multiple accept="image/*"
                       class="mt-1 block w-full text-sm text-sand-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100">
                <p class="mt-1 text-xs text-sand-400">Gambar, maks 5 MB. Agar penuh di Master PO, ukuran ideal <span class="font-medium text-sand-500">1400 × 800 px</span> (landscape).</p>
                @error('mockups.*') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="desains" class="block text-sm font-medium text-sand-700">Desain <span class="text-sand-400 font-normal">(artwork/detail)</span></label>
                <input type="file" name="desains[]" id="desains" multiple accept="image/*"
                       class="mt-1 block w-full text-sm text-sand-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100">
                <p class="mt-1 text-xs text-sand-400">Gambar, maks 5 MB. Agar penuh di Master PO, ukuran ideal <span class="font-medium text-sand-500">1150 × 800 px</span> (landscape).</p>
                @error('desains.*') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label for="mentahans" class="block text-sm font-medium text-sand-700">File mentahan produksi <span class="text-sand-400 font-normal">(untuk vendor)</span></label>
            <input type="file" name="mentahans[]" id="mentahans" multiple
                   class="mt-1 block w-full text-sm text-sand-600 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-amber-800 hover:file:bg-amber-100">
            <p class="mt-1 text-xs text-sand-400">File produksi mentah (AI, PSD, PDF, CDR, ZIP, dll), maks 25 MB per file. Inilah yang diunduh vendor.</p>
            @error('mentahans.*') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </section>

    {{-- 5. Spesifikasi produksi --}}
    <section x-data="{ open: {{ $product->spec ? 'true' : 'false' }} }" class="rounded-lg border border-sand-200">
        <button type="button" @click="open = !open" class="flex w-full items-center justify-between px-4 py-3 text-left">
            <span class="text-sm font-semibold text-sand-800">Spesifikasi produksi <span class="font-normal text-sand-400">(untuk PO)</span></span>
            <svg class="h-5 w-5 text-sand-400 transition" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
        </button>
        <div x-show="open" x-cloak class="border-t border-sand-200 p-4 space-y-5">
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ([
                    'patrun' => 'Patrun', 'ukuran_rib' => 'Ukuran RIB leher', 'ukuran_rib_lengan' => 'Ukuran RIB lengan',
                    'warna_bahan' => 'Warna bahan', 'jenis_bahan' => 'Jenis bahan', 'supp_bahan' => 'Supp bahan',
                    'cat_sablon' => 'Cat sablon', 'finishing' => 'Finishing',
                    'desain_depan' => 'Desain depan', 'desain_belakang' => 'Desain belakang', 'desain_lengan' => 'Desain lengan',
                ] as $f => $label)
                    <div>
                        <label class="block text-xs font-medium text-sand-600">{{ $label }}</label>
                        <input type="text" name="spec[{{ $f }}]" value="{{ old("spec.$f", $product->spec?->$f) }}"
                               class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600 text-sm">
                    </div>
                @endforeach
            </div>

            <div>
                <span class="block text-xs font-medium text-sand-600 mb-2">Label &amp; aksesoris</span>
                <div class="flex flex-wrap gap-x-5 gap-y-2">
                    @foreach ([
                        'label_leher' => 'Label leher', 'label_bawah' => 'Label bawah', 'slip_label' => 'Slip label',
                        'aksesoris' => 'Aksesoris', 'care_label' => 'Care label', 'hangtag' => 'Hangtag', 'plastik' => 'Plastik',
                    ] as $f => $label)
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="spec[{{ $f }}]" value="1" @checked(old("spec.$f", $product->spec?->$f)) class="rounded border-sand-300 text-brand-700 focus:ring-brand-600">
                            <span class="text-sm text-sand-700">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-sand-600">Catatan (NOTE)</label>
                <textarea name="spec[note]" rows="2" class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600 text-sm">{{ old('spec.note', $product->spec?->note) }}</textarea>
            </div>
        </div>
    </section>
</div>

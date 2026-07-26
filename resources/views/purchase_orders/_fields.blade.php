@php
    use App\Enums\Ukuran;

    $specStr = ['patrun','ukuran_rib','ukuran_rib_lengan','warna_bahan','jenis_bahan','supp_bahan','cat_sablon','finishing','desain_depan','desain_belakang','desain_lengan'];
    $specBool = ['label_leher','label_bawah','slip_label','aksesoris','care_label','hangtag','plastik'];
    $labels = [
        'patrun'=>'Patrun','ukuran_rib'=>'Ukuran RIB leher','ukuran_rib_lengan'=>'Ukuran RIB lengan','warna_bahan'=>'Warna bahan','jenis_bahan'=>'Jenis bahan',
        'supp_bahan'=>'Supp bahan','cat_sablon'=>'Cat sablon','finishing'=>'Finishing',
        'desain_depan'=>'Desain depan','desain_belakang'=>'Desain belakang','desain_lengan'=>'Desain lengan',
        'label_leher'=>'Label leher','label_bawah'=>'Label bawah','slip_label'=>'Slip label','aksesoris'=>'Aksesoris',
        'care_label'=>'Care label','hangtag'=>'Hangtag','plastik'=>'Plastik',
    ];

    $productData = [];
    $productList = [];
    foreach ($products as $p) {
        $s = $p->spec;
        $spec = [];
        foreach ($specStr as $f) { $spec[$f] = $s?->$f ?? ''; }
        foreach ($specBool as $f) { $spec[$f] = (bool) ($s?->$f); }
        $spec['note'] = $s?->note ?? '';
        $productData[$p->id] = ['spec' => $spec];
        $productList[] = [
            'id' => $p->id,
            'label' => $p->nama_artikel,
            'kategori' => $p->category->nama ?? '',
            'jenis' => $p->category?->jenisProduksi()->label() ?? '',
        ];
    }

    $initSpec = [];
    foreach ($specStr as $f) { $initSpec[$f] = old($f, $po->$f ?? ''); }
    foreach ($specBool as $f) { $initSpec[$f] = (bool) old($f, $po->$f ?? false); }
    $initSpec['note'] = old('note', $po->note ?? '');
@endphp

<div x-data="{
        productId: '{{ old('product_id', $po->product_id) }}',
        productData: @js($productData),
        products: @js($productList),
        spec: @js($initSpec),
        cariOpen: false,
        q: '',
        sorot: 0,
        init() { this.q = this.labelOf(this.productId); },
        labelOf(id) { return this.products.find(p => String(p.id) === String(id))?.label ?? ''; },
        prodOf(id) { return this.products.find(p => String(p.id) === String(id)) ?? null; },
        cari() {
            const k = this.q.trim().toLowerCase();
            if (! k) return this.products;
            return this.products.filter(p => (p.label + ' ' + p.kategori).toLowerCase().includes(k));
        },
        pilih(p) { this.productId = p.id; this.q = p.label; this.cariOpen = false; },
        fillFromProduct(){
            const p = this.productData[this.productId];
            if(!p){ return; }
            Object.keys(p.spec).forEach(k => { this.spec[k] = p.spec[k]; });
        }
     }"
     class="space-y-8">

    {{-- Produk --}}
    <section class="space-y-4">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400">Artikel</h2>
        <div class="grid sm:grid-cols-2 gap-5 items-end">
            {{-- Pemilih artikel dengan pencarian: daftar produk sudah puluhan baris. --}}
            <div class="relative"
                 @click.outside="cariOpen = false; q = labelOf(productId)"
                 @keydown.escape.stop="cariOpen = false; q = labelOf(productId)">
                <label for="cari_produk" class="block text-sm font-medium text-sand-700">Produk / artikel</label>
                <input type="hidden" name="product_id" :value="productId">
                <input type="text" id="cari_produk" x-model="q" autocomplete="off"
                       placeholder="ketik untuk cari artikel…"
                       @focus="cariOpen = true; sorot = 0" @input="cariOpen = true; sorot = 0"
                       @keydown.down.prevent="sorot = Math.min(sorot + 1, cari().length - 1)"
                       @keydown.up.prevent="sorot = Math.max(sorot - 1, 0)"
                       @keydown.enter.prevent="const h = cari()[sorot]; if (h) pilih(h)"
                       class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600">
                <div x-show="cariOpen" x-cloak
                     class="absolute z-20 mt-1 w-full max-h-56 overflow-y-auto rounded-lg border border-sand-200 bg-white shadow-lg">
                    <template x-for="(p, k) in cari()" :key="p.id">
                        <button type="button" @click="pilih(p)" @mouseenter="sorot = k"
                                class="block w-full px-3 py-2 text-left text-sm"
                                :class="k === sorot ? 'bg-brand-50 text-brand-800' : 'text-sand-700 hover:bg-sand-50'">
                            <span x-text="p.label"></span>
                            <span class="text-xs text-sand-400" x-text="p.kategori ? ' · ' + p.kategori : ''"></span>
                        </button>
                    </template>
                    <p x-show="cari().length === 0" class="px-3 py-2 text-sm text-sand-400">Artikel tidak ditemukan.</p>
                </div>
                @error('product_id') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <button type="button" @click="fillFromProduct()" x-bind:disabled="!productId"
                        class="inline-flex items-center gap-2 rounded-lg border border-brand-300 bg-brand-50 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-100 disabled:opacity-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                    Isi spesifikasi dari produk
                </button>
            </div>
        </div>
        @if (! $po->exists)
            <p class="text-xs text-sand-400">Nomor PO dibuat otomatis saat disimpan.</p>
        @else
            <p class="text-xs text-sand-400">Nomor PO: <span class="font-medium text-sand-600 tnum">{{ $po->nomor_po }}</span></p>
        @endif
    </section>

    {{-- Rincian ukuran (qty per ukuran; jenis produksi otomatis dari kategori) --}}
    <section class="space-y-3">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400">Rincian ukuran</h2>

        {{-- Jenis produksi ditentukan otomatis dari kategori artikel — bukan diinput manual. --}}
        <div x-show="prodOf(productId)" x-cloak
             class="flex items-center gap-2 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-800">
            <svg class="h-4 w-4 flex-none" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
            <span>Jenis produksi: <b x-text="prodOf(productId)?.jenis || '—'"></b>
                <span class="text-brand-600/80">(otomatis dari kategori <span x-text="prodOf(productId)?.kategori"></span>)</span></span>
        </div>

        <div class="overflow-x-auto rounded-lg border border-sand-200">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-sand-50 text-xs uppercase tracking-wide text-sand-500">
                        <th class="px-4 py-2.5 text-left font-semibold">Ukuran</th>
                        <th class="px-4 py-2.5 text-center font-semibold">Jumlah (pcs)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @foreach (Ukuran::cases() as $u)
                        @php $qv = old("qty.{$u->value}", $po->exists ? $po->totalPerUkuran($u) : 0); @endphp
                        <tr>
                            <td class="px-4 py-2 font-medium text-sand-700">{{ $u->value }}</td>
                            <td class="px-2 py-1.5">
                                <input type="number" min="0" name="qty[{{ $u->value }}]" value="{{ $qv ?: '' }}" placeholder="0"
                                       class="block w-full rounded-md border-sand-300 text-center text-sm focus:border-brand-600 focus:ring-brand-600 tnum">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-sand-400">Isi jumlah per ukuran. Kosongkan bila 0.</p>
    </section>

    {{-- Spesifikasi & sablon --}}
    <section class="space-y-4">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400">Spesifikasi &amp; sablon</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($specStr as $f)
                <div>
                    <label class="block text-xs font-medium text-sand-600">{{ $labels[$f] }}</label>
                    <input type="text" name="{{ $f }}" x-model="spec.{{ $f }}"
                           class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600 text-sm">
                </div>
            @endforeach
        </div>

        <div>
            <span class="block text-xs font-medium text-sand-600 mb-2">Label &amp; aksesoris</span>
            <div class="flex flex-wrap gap-x-5 gap-y-2">
                @foreach ($specBool as $f)
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="{{ $f }}" value="1" x-model="spec.{{ $f }}" class="rounded border-sand-300 text-brand-700 focus:ring-brand-600">
                        <span class="text-sm text-sand-700">{{ $labels[$f] }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-sand-600">Catatan (NOTE)</label>
            <textarea name="note" rows="2" x-model="spec.note" class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600 text-sm"></textarea>
        </div>
    </section>
</div>

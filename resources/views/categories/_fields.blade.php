@php
    $sxl = $category->priceFor(\App\Enums\SizeTier::SXL);
    $xxl = $category->priceFor(\App\Enums\SizeTier::XXL);
    $sxlD = old('prices.s_xl.harga_diferd', $sxl?->harga_diferd ?? 0);
    $sxlT = old('prices.s_xl.harga_tm420', $sxl?->harga_tm420);
    $xxlD = old('prices.xxl.harga_diferd', $xxl?->harga_diferd ?? 0);
    $xxlT = old('prices.xxl.harga_tm420', $xxl?->harga_tm420);
@endphp

<div class="space-y-6">
    {{-- Nama --}}
    <div>
        <label for="nama" class="block text-sm font-medium text-sand-700">Nama kategori</label>
        <input type="text" name="nama" id="nama" value="{{ old('nama', $category->nama) }}" required autofocus
               class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600"
               placeholder="mis. Reguler 24s">
        @error('nama') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    {{-- Harga per tier --}}
    <div x-data="{
            sxl_d: {{ (int) $sxlD }}, sxl_t: '{{ $sxlT }}',
            xxl_d: {{ (int) $xxlD }}, xxl_t: '{{ $xxlT }}',
            markupFmt(d, t) {
                if (t === '' || t === null) return '—';
                const m = Number(t) - Number(d);
                return (m >= 0 ? '+Rp ' : '−Rp ') + Math.abs(m).toLocaleString('id-ID');
            }
         }">
        <div class="flex items-center justify-between mb-2">
            <span class="block text-sm font-medium text-sand-700">Master harga</span>
            <span class="text-xs text-sand-400">Rupiah, tanpa desimal</span>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            {{-- Tier S–XL --}}
            <div class="rounded-lg border border-sand-200 p-4">
                <div class="text-sm font-medium text-sand-800 mb-3">Tier <span class="text-brand-700">S–XL</span></div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-sand-600">Harga Diferd</label>
                        <div class="mt-1 relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-sand-400">Rp</span>
                            <input type="number" min="0" name="prices[s_xl][harga_diferd]" x-model.number="sxl_d"
                                   class="block w-full rounded-lg border-sand-300 pl-9 focus:border-brand-600 focus:ring-brand-600 tnum">
                        </div>
                        @error('prices.s_xl.harga_diferd') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-sand-600">Harga TM420 <span class="text-sand-400 font-normal">(opsional)</span></label>
                        <div class="mt-1 relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-sand-400">Rp</span>
                            <input type="number" min="0" name="prices[s_xl][harga_tm420]" x-model="sxl_t"
                                   class="block w-full rounded-lg border-sand-300 pl-9 focus:border-brand-600 focus:ring-brand-600 tnum">
                        </div>
                        @error('prices.s_xl.harga_tm420') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-center justify-between rounded-md bg-brand-50 px-3 py-2 text-sm">
                        <span class="text-sand-600">Markup 420F</span>
                        <span class="font-medium text-brand-700 tnum" x-text="markupFmt(sxl_d, sxl_t)"></span>
                    </div>
                </div>
            </div>

            {{-- Tier XXL --}}
            <div class="rounded-lg border border-sand-200 p-4">
                <div class="text-sm font-medium text-sand-800 mb-3">Tier <span class="text-brand-700">XXL</span></div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-sand-600">Harga Diferd</label>
                        <div class="mt-1 relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-sand-400">Rp</span>
                            <input type="number" min="0" name="prices[xxl][harga_diferd]" x-model.number="xxl_d"
                                   class="block w-full rounded-lg border-sand-300 pl-9 focus:border-brand-600 focus:ring-brand-600 tnum">
                        </div>
                        @error('prices.xxl.harga_diferd') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-sand-600">Harga TM420 <span class="text-sand-400 font-normal">(opsional)</span></label>
                        <div class="mt-1 relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-sand-400">Rp</span>
                            <input type="number" min="0" name="prices[xxl][harga_tm420]" x-model="xxl_t"
                                   class="block w-full rounded-lg border-sand-300 pl-9 focus:border-brand-600 focus:ring-brand-600 tnum">
                        </div>
                        @error('prices.xxl.harga_tm420') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-center justify-between rounded-md bg-brand-50 px-3 py-2 text-sm">
                        <span class="text-sand-600">Markup 420F</span>
                        <span class="font-medium text-brand-700 tnum" x-text="markupFmt(xxl_d, xxl_t)"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Aktif --}}
    <div>
        <label class="inline-flex items-center gap-2.5">
            <input type="checkbox" name="aktif" value="1" @checked(old('aktif', $category->aktif ?? true))
                   class="rounded border-sand-300 text-brand-700 focus:ring-brand-600">
            <span class="text-sm text-sand-700">Kategori aktif</span>
        </label>
    </div>
</div>

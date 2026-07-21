<x-app-layout>
    @php
        $isAdmin = auth()->user()->isAdmin();
        $tiers = [\App\Enums\SizeTier::SXL, \App\Enums\SizeTier::XXL];
        $fileTypes = [\App\Enums\ProductFileType::Mockup, \App\Enums\ProductFileType::Desain, \App\Enums\ProductFileType::Mentahan];
        $fmt = fn ($n) => $n !== null ? 'Rp '.number_format($n, 0, ',', '.') : '—';
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 min-w-0">
                <x-back-link :href="route('products.index')" />
                <div class="min-w-0">
                    <h1 class="text-lg font-semibold text-sand-900 truncate">{{ $product->nama_artikel }}</h1>
                    <p class="text-xs text-sand-500">{{ $product->brand->nama }} &middot; {{ $product->category->nama }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if ($isAdmin)
                    <a href="{{ route('products.edit', $product) }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">Ubah</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Info + harga --}}
        <div class="grid md:grid-cols-2 gap-6">
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400 mb-4">Informasi</h2>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-sand-500">Brand</dt><dd class="font-medium text-sand-800">{{ $product->brand->nama }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-sand-500">Kategori</dt><dd class="font-medium text-sand-800">{{ $product->category->nama }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-sand-500">SKU induk</dt><dd class="font-medium text-sand-800 tnum">{{ $product->sku_induk ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-sand-500">Status</dt><dd class="font-medium {{ $product->aktif ? 'text-brand-700' : 'text-sand-400' }}">{{ $product->aktif ? 'Aktif' : 'Nonaktif' }}</dd></div>
                </dl>
            </div>

            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400 mb-4">
                    Harga @if ($product->hasOverride())<span class="ms-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800">khusus</span>@endif
                </h2>
                <table class="w-full text-sm">
                    <thead><tr class="text-xs text-sand-500 text-left"><th class="pb-2 font-medium">Tier</th><th class="pb-2 font-medium text-right">Diferd</th><th class="pb-2 font-medium text-right">TM420</th><th class="pb-2 font-medium text-right">Markup</th></tr></thead>
                    <tbody>
                        @foreach ($tiers as $tier)
                            @php $d = $product->effectiveDiferd($tier); $t = $product->effectiveTm420($tier); $m = ($d !== null && $t !== null) ? $t - $d : null; @endphp
                            <tr class="border-t border-sand-100">
                                <td class="py-2 text-sand-600">{{ $tier->label() }}</td>
                                <td class="py-2 text-right tnum text-sand-800">{{ $fmt($d) }}</td>
                                <td class="py-2 text-right tnum text-sand-800">{{ $fmt($t) }}</td>
                                <td class="py-2 text-right tnum font-medium text-brand-700">{{ $m !== null ? '+'.$fmt($m) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- SKU turunan --}}
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400 mb-3">SKU turunan</h2>
            <div class="flex flex-wrap gap-2">
                @forelse ($product->sizes as $size)
                    <span class="inline-flex items-center gap-2 rounded-lg border border-sand-200 px-3 py-1.5 text-sm">
                        <span class="font-medium text-sand-700">{{ $size->ukuran->value }}</span>
                        <span class="text-sand-400 tnum">{{ $size->sku_turunan ?? '—' }}</span>
                    </span>
                @empty
                    <p class="text-sm text-sand-400">Belum ada SKU turunan.</p>
                @endforelse
            </div>
        </div>

        {{-- File & download --}}
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400">File produk</h2>
                @if ($product->files->isNotEmpty())
                    <a href="{{ route('products.download-zip', $product) }}"
                       class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-6L12 15m0 0l4.5-4.5M12 15V3"/></svg>
                        Download semua (ZIP)
                    </a>
                @endif
            </div>

            @if ($product->files->isEmpty())
                <p class="text-sm text-sand-400">Belum ada file diunggah.</p>
            @else
                <div class="space-y-5">
                    @foreach ($fileTypes as $type)
                        @php $files = $product->filesOfType($type); @endphp
                        @if ($files->isNotEmpty())
                            <div>
                                <p class="text-xs font-medium text-sand-600 mb-2">
                                    {{ $type->label() }}
                                    @if ($type === \App\Enums\ProductFileType::Mentahan)
                                        <span class="text-sand-400 font-normal">— file mentahan untuk vendor</span>
                                    @endif
                                </p>
                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                                    @foreach ($files as $file)
                                        <div class="rounded-lg border border-sand-200 overflow-hidden flex flex-col">
                                            @if ($file->is_image)
                                                <img src="{{ $file->url }}" alt="{{ $file->nama_asli }}" class="h-28 w-full object-cover bg-sand-100">
                                            @else
                                                <div class="h-28 w-full grid place-items-center bg-sand-50 text-sand-400">
                                                    <div class="text-center">
                                                        <svg class="h-8 w-8 mx-auto" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                                        <span class="mt-1 block text-[11px] font-semibold tnum">{{ $file->ext }}</span>
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="px-2.5 py-2 flex items-center gap-2">
                                                <span class="min-w-0 flex-1 truncate text-[11px] text-sand-500" title="{{ $file->nama_asli }}">{{ $file->nama_asli }}</span>
                                                <a href="{{ route('product-files.download', $file) }}" class="shrink-0 rounded-md p-1 text-brand-700 hover:bg-brand-50" title="Download">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-6L12 15m0 0l4.5-4.5M12 15V3"/></svg>
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

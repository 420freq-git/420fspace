<x-app-layout>
    @php
        $isAdmin = auth()->user()->isAdmin();
        $canManage = $isAdmin || in_array(auth()->user()->role, [\App\Enums\Role::Tm420, \App\Enums\Role::Voojah], true);
        $tierSXL = \App\Enums\SizeTier::SXL;
    @endphp
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-sand-900">Produk</h1>
                <p class="text-xs text-sand-500">Katalog artikel &mdash; SKU, harga, desain &amp; spesifikasi.</p>
            </div>
            @if ($canManage)
                <a href="{{ route('products.create') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-800 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Tambah produk
                </a>
            @endif
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-4">
        {{-- Pencarian --}}
        <form method="GET" class="flex items-center gap-3">
            <div class="relative flex-1 max-w-md">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sand-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                </span>
                <input type="search" name="cari" value="{{ $cari }}" placeholder="Cari nama artikel atau SKU…"
                       class="w-full rounded-lg border-sand-300 pl-9 text-sm focus:border-brand-600 focus:ring-brand-600">
            </div>
            <button type="submit" class="rounded-lg bg-sand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-sand-900">Cari</button>
            @if ($cari !== '')
                <a href="{{ route('products.index') }}" class="text-sm text-sand-500 hover:text-sand-800">Reset</a>
                <span class="text-sm text-sand-400">{{ $products->total() }} hasil</span>
            @endif
        </form>

        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            @if ($products->isEmpty())
                <div class="p-12 text-center">
                    @if ($cari !== '')
                        <p class="text-sand-500">Tidak ada produk cocok dengan "{{ $cari }}".</p>
                    @else
                        <p class="text-sand-500">Belum ada produk.</p>
                        @if ($isAdmin)
                            <a href="{{ route('products.create') }}" class="mt-3 inline-block text-sm font-medium text-brand-700 hover:underline">Tambah produk pertama</a>
                        @endif
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Artikel</th>
                                <th class="px-5 py-3 font-semibold">Brand</th>
                                <th class="px-5 py-3 font-semibold">Kategori</th>
                                <th class="px-5 py-3 font-semibold text-right">Harga S–XL (Diferd → TM420)</th>
                                <th class="px-5 py-3 font-semibold text-center">Status</th>
                                <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($products as $product)
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3.5">
                                        <div class="font-medium text-sand-900">{{ $product->nama_artikel }}</div>
                                        @if ($product->sku_induk)
                                            <div class="text-xs text-sand-400 tnum">{{ $product->sku_induk }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-sand-700">{{ $product->brand->nama }}</td>
                                    <td class="px-5 py-3.5 text-sand-700">{{ $product->category->nama }}</td>
                                    <td class="px-5 py-3.5 text-right">
                                        @php $d = $product->effectiveDiferd($tierSXL); $t = $product->effectiveTm420($tierSXL); @endphp
                                        <span class="tnum text-sand-800">
                                            {{ $d !== null ? 'Rp '.number_format($d, 0, ',', '.') : '—' }}
                                            <span class="text-sand-400">→</span>
                                            {{ $t !== null ? 'Rp '.number_format($t, 0, ',', '.') : '—' }}
                                        </span>
                                        @if ($product->hasOverride())
                                            <span class="ms-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800">khusus</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        @if ($product->aktif)
                                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-brand-700"><span class="h-1.5 w-1.5 rounded-full bg-brand-600"></span> Aktif</span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-sand-400"><span class="h-1.5 w-1.5 rounded-full bg-sand-300"></span> Nonaktif</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center justify-end gap-1">
                                            <a href="{{ route('products.show', $product) }}" class="rounded-md p-1.5 text-sand-500 hover:bg-sand-100 hover:text-sand-800" title="Detail &amp; download file">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08M15.75 4.5A2.25 2.25 0 0013.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25"/></svg>
                                            </a>
                                            @if ($canManage)
                                                <a href="{{ route('products.edit', $product) }}" class="rounded-md p-1.5 text-sand-500 hover:bg-sand-100 hover:text-sand-800" title="Ubah">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                                                </a>
                                            @endif
                                            @if ($isAdmin)
                                                <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('Hapus produk {{ $product->nama_artikel }}?');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="rounded-md p-1.5 text-sand-500 hover:bg-red-50 hover:text-red-700" title="Hapus">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($products->hasPages())
            <div>{{ $products->links() }}</div>
        @endif
    </div>
</x-app-layout>

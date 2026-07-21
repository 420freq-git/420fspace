<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('stok.index') }}" class="text-xs text-brand-700 hover:underline">&larr; Kembali ke Stok</a>
            <h1 class="text-lg font-semibold text-sand-900">Rekonsiliasi Stok</h1>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <p class="text-sm text-sand-500 -mt-2">
            Item pesanan (hasil import marketplace) yang <span class="font-medium text-sand-700">belum tertaut batch produksi</span> —
            terjadi saat produk terjual tapi batch produksinya belum ada di sistem. Setelah batch/PO produk dibuat,
            jalankan rekonsiliasi untuk menautkannya (FIFO) agar masuk perhitungan settlement.
        </p>

        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            @if ($groups->isEmpty())
                <div class="p-12 text-center text-sand-500">Semua item sudah tertaut batch. 🎉</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Produk</th>
                                <th class="px-5 py-3 font-semibold text-center">UK</th>
                                <th class="px-5 py-3 font-semibold text-center">Qty tanpa batch</th>
                                <th class="px-5 py-3 font-semibold text-center">Stok tersedia di batch</th>
                                <th class="px-5 py-3 font-semibold text-center">Bisa ditautkan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($groups as $g)
                                @php $bisa = min($g['qty'], $g['available']); @endphp
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3.5 font-medium text-sand-900">{{ $g['product']->nama_artikel }} <span class="text-xs text-sand-400">· {{ $g['product']->brand->nama }}</span></td>
                                    <td class="px-5 py-3.5 text-center text-sand-700">{{ $g['ukuran']->value }}</td>
                                    <td class="px-5 py-3.5 text-center tnum text-sand-800">{{ $g['qty'] }}</td>
                                    <td class="px-5 py-3.5 text-center tnum text-sand-600">{{ $g['available'] }}</td>
                                    <td class="px-5 py-3.5 text-center tnum font-medium {{ $bisa > 0 ? 'text-brand-700' : 'text-sand-400' }}">{{ $bisa }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center justify-between gap-3 px-5 py-4 border-t border-sand-200 bg-sand-50/50">
                    <p class="text-xs text-sand-400">Hanya yang ada stok batch tersedia yang akan ditautkan; sisanya tetap menunggu batch baru.</p>
                    <form method="POST" action="{{ route('stok.reconcile.run') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.99v4.99"/></svg>
                            Jalankan rekonsiliasi
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

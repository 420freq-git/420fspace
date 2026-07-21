<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-sand-900">Rekomendasi produksi ulang</h1>
                <p class="text-xs text-sand-500">Produk laris yang stoknya menipis — bahan untuk PO batch berikutnya.</p>
            </div>
            @if ($bolehBuatBatch && ! empty($rows))
                <a href="{{ route('batches.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Buat batch
                </a>
            @endif
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <div class="grid grid-cols-2 lg:grid-cols-3 gap-5">
            <div class="rounded-xl border {{ ! empty($rows) ? 'border-amber-200 bg-amber-50' : 'border-sand-200 bg-white' }} shadow-sm p-5">
                <p class="text-sm {{ ! empty($rows) ? 'text-amber-700' : 'text-sand-500' }}">Perlu diproduksi ulang</p>
                <p class="mt-1 text-2xl font-semibold {{ ! empty($rows) ? 'text-amber-800' : 'text-sand-900' }} tnum">{{ count($rows) }}</p>
                <p class="mt-1 text-xs text-sand-400">artikel laris &amp; menipis</p>
            </div>
            <div class="rounded-xl border {{ $habisCount > 0 ? 'border-red-200 bg-red-50' : 'border-sand-200 bg-white' }} shadow-sm p-5">
                <p class="text-sm {{ $habisCount > 0 ? 'text-red-700' : 'text-sand-500' }}">Sudah habis</p>
                <p class="mt-1 text-2xl font-semibold {{ $habisCount > 0 ? 'text-red-800' : 'text-sand-900' }} tnum">{{ $habisCount }}</p>
                <p class="mt-1 text-xs text-sand-400">stok jual 0 — kehilangan penjualan</p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Total saran qty</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ number_format($totalSaran, 0, ',', '.') }} <span class="text-sm font-normal">pcs</span></p>
            </div>
        </div>

        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            @if (empty($rows))
                <div class="p-12 text-center text-sand-500">Tidak ada yang perlu diproduksi ulang. Stok produk laris masih aman. 🎉</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Artikel</th>
                                <th class="px-5 py-3 font-semibold text-center">Terjual</th>
                                <th class="px-5 py-3 font-semibold text-center">Sell-through</th>
                                <th class="px-5 py-3 font-semibold text-center">Sisa stok</th>
                                <th class="px-5 py-3 font-semibold text-center">Saran produksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($rows as $r)
                                <tr class="hover:bg-sand-50/50 {{ $r['habis'] ? 'bg-red-50/40' : '' }}">
                                    <td class="px-5 py-3.5">
                                        <div class="font-medium text-sand-900">{{ $r['product']->nama_artikel }}</div>
                                        <div class="text-xs text-sand-400">{{ $r['kategori'] }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-center tnum text-sand-700">{{ number_format($r['terjual'], 0, ',', '.') }}</td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $r['sell_through'] >= 80 ? 'bg-brand-100 text-brand-800' : 'bg-sand-100 text-sand-700' }}">{{ $r['sell_through'] }}%</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-center tnum font-medium {{ $r['habis'] ? 'text-red-700' : 'text-amber-700' }}">{{ $r['habis'] ? 'Habis' : $r['sisa'] }}</td>
                                    <td class="px-5 py-3.5 text-center tnum font-semibold text-brand-700">{{ number_format($r['saran_qty'], 0, ',', '.') }} pcs</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <p class="text-xs text-sand-400">Kriteria: pernah diproduksi, sell-through ≥ {{ 60 }}% (laris), sisa stok ≤ {{ 15 }} pcs. Saran qty = tutup penjualan sejauh ini. Sesuaikan angka aslinya saat membuat PO.</p>
    </div>
</x-app-layout>

<x-app-layout>
    @php $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-lg font-semibold text-sand-900">Laporan Penjualan</h1>
                <p class="text-xs text-sand-500">Rekap barang terjual (pesanan lunas) per periode.</p>
            </div>
            <a href="{{ route('laporan.penjualan.pdf', request()->only('dari', 'sampai', 'brand')) }}" target="_blank"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m0 0l-2.25-2.25M12 16.5l2.25-2.25M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"/></svg>
                Export PDF
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Filter --}}
        <form method="GET" class="rounded-xl border border-sand-200 bg-white shadow-sm p-4 flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-xs font-medium text-sand-600">Dari</label>
                <input type="date" name="dari" value="{{ $dari->format('Y-m-d') }}" class="mt-1 rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
            </div>
            <div>
                <label class="block text-xs font-medium text-sand-600">Sampai</label>
                <input type="date" name="sampai" value="{{ $sampai->format('Y-m-d') }}" class="mt-1 rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
            </div>
            @if ($brands->isNotEmpty())
                <div>
                    <label class="block text-xs font-medium text-sand-600">Brand</label>
                    <select name="brand" class="mt-1 rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                        <option value="">Semua brand</option>
                        @foreach ($brands as $b)
                            <option value="{{ $b->id }}" @selected((string) $filterBrand === (string) $b->id)>{{ $b->nama }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button type="submit" class="rounded-lg bg-sand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-sand-900">Terapkan</button>
            <div class="ml-auto flex flex-wrap gap-2 text-xs">
                @foreach ([
                    'Bulan ini' => [now()->startOfMonth(), now()],
                    'Bulan lalu' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
                    '30 hari' => [now()->subDays(29), now()],
                    'Tahun ini' => [now()->startOfYear(), now()],
                ] as $label => $range)
                    <a href="{{ route('laporan.penjualan', array_filter(['dari' => $range[0]->format('Y-m-d'), 'sampai' => $range[1]->format('Y-m-d'), 'brand' => $filterBrand])) }}"
                       class="rounded-full border border-sand-200 px-3 py-1.5 text-sand-600 hover:border-brand-300 hover:text-brand-700">{{ $label }}</a>
                @endforeach
            </div>
        </form>

        {{-- Ringkasan --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="rounded-xl border border-brand-200 bg-brand-50 shadow-sm p-5">
                <p class="text-sm text-brand-700">Unit terjual</p>
                <p class="mt-1 text-2xl font-semibold text-brand-700 tnum">{{ number_format($totalQty, 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Artikel</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ $jumlahArtikel }}</p>
            </div>
            @if ($showTm)
                <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                    <p class="text-sm text-sand-500">Nilai jual (TM420)</p>
                    <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ $fmt($totalTm420) }}</p>
                </div>
            @endif
            @if ($showDiferd)
                <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                    <p class="text-sm text-sand-500">Nilai Diferd</p>
                    <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ $fmt($totalDiferd) }}</p>
                    <p class="mt-1 text-xs text-sand-400">harga Diferd ke 420F</p>
                </div>
            @endif
            @if ($showFee)
                <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                    <p class="text-sm text-sand-500">Fee 420F</p>
                    <p class="mt-1 text-2xl font-semibold text-brand-700 tnum">{{ $fmt($totalFee) }}</p>
                </div>
            @endif
        </div>

        {{-- Per artikel --}}
        @php $cols = 3 + ($showTm ? 1 : 0) + ($showDiferd ? 1 : 0) + ($showFee ? 1 : 0); @endphp
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-sand-200"><h2 class="text-sm font-semibold text-sand-800">Rincian per artikel</h2></div>
            @if ($byProduct->isEmpty())
                <div class="p-12 text-center text-sand-500">Tidak ada penjualan pada periode ini.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">SKU</th>
                                <th class="px-5 py-3 font-semibold">Produk</th>
                                <th class="px-5 py-3 font-semibold text-center">Qty</th>
                                @if ($showTm)<th class="px-5 py-3 font-semibold text-right">Nilai TM420</th>@endif
                                @if ($showDiferd)<th class="px-5 py-3 font-semibold text-right">Nilai Diferd</th>@endif
                                @if ($showFee)<th class="px-5 py-3 font-semibold text-right">Fee 420F</th>@endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($byProduct as $row)
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3.5 text-sand-600 tnum">{{ $row['product']->sku_induk ?? '—' }}</td>
                                    <td class="px-5 py-3.5 font-medium text-sand-900">{{ $row['product']->nama_artikel }}
                                        <span class="text-sand-400 font-normal">· {{ $row['product']->brand->nama ?? '—' }}</span></td>
                                    <td class="px-5 py-3.5 text-center tnum text-sand-700">{{ number_format($row['qty'], 0, ',', '.') }}</td>
                                    @if ($showTm)<td class="px-5 py-3.5 text-right tnum text-sand-800">{{ $fmt($row['tm420']) }}</td>@endif
                                    @if ($showDiferd)<td class="px-5 py-3.5 text-right tnum text-sand-600">{{ $fmt($row['diferd']) }}</td>@endif
                                    @if ($showFee)<td class="px-5 py-3.5 text-right tnum font-medium text-brand-700">{{ $fmt($row['fee']) }}</td>@endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-sand-200 bg-sand-50 font-semibold text-sand-900">
                                <td class="px-5 py-3" colspan="2">TOTAL</td>
                                <td class="px-5 py-3 text-center tnum">{{ number_format($totalQty, 0, ',', '.') }}</td>
                                @if ($showTm)<td class="px-5 py-3 text-right tnum">{{ $fmt($totalTm420) }}</td>@endif
                                @if ($showDiferd)<td class="px-5 py-3 text-right tnum">{{ $fmt($totalDiferd) }}</td>@endif
                                @if ($showFee)<td class="px-5 py-3 text-right tnum text-brand-700">{{ $fmt($totalFee) }}</td>@endif
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>

        {{-- Channel --}}
        @if ($byMarketplace->isNotEmpty())
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-sand-200"><h2 class="text-sm font-semibold text-sand-800">Distribusi channel</h2></div>
                <div class="divide-y divide-sand-100">
                    @foreach ($byMarketplace as $mp)
                        <div class="px-5 py-3 flex items-center gap-4">
                            <span class="w-28 text-sm text-sand-700">{{ $mp['label'] }}</span>
                            <div class="flex-1 h-2.5 rounded-full bg-sand-100 overflow-hidden">
                                <div class="h-full rounded-full bg-brand-500" style="width: {{ $totalQty > 0 ? round($mp['qty'] / $totalQty * 100, 1) : 0 }}%"></div>
                            </div>
                            <span class="w-24 text-right text-sm tnum text-sand-700">{{ number_format($mp['qty'], 0, ',', '.') }} unit</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-app-layout>

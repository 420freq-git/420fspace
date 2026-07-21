<x-app-layout>
    @php $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-lg font-semibold text-sand-900">Produk Terjual per Bulan — by Kategori</h1>
                <p class="text-xs text-sand-500">Unit terjual (pesanan lunas) 6 bulan terakhir, dikelompokkan per kategori.</p>
            </div>
            <a href="{{ route('laporan.terjual-kategori.pdf') }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m0 0l-2.25-2.25M12 16.5l2.25-2.25M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"/></svg>
                Export PDF
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            @if ($rows->isEmpty())
                <div class="p-12 text-center text-sand-500">Belum ada penjualan lunas dalam 6 bulan terakhir.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Kategori</th>
                                @foreach ($labels as $l)
                                    <th class="px-3 py-3 font-semibold text-center">{{ $l }}</th>
                                @endforeach
                                <th class="px-5 py-3 font-semibold text-center">Total unit</th>
                                @if ($showTm)<th class="px-5 py-3 font-semibold text-right">Nilai TM420</th>@endif
                                @if ($showDiferd)<th class="px-5 py-3 font-semibold text-right">Nilai Diferd</th>@endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($rows as $kat => $r)
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3 font-medium text-sand-800">{{ $kat }}</td>
                                    @foreach ($r['per_bulan'] as $q)
                                        <td class="px-3 py-3 text-center tnum {{ $q > 0 ? 'text-sand-800' : 'text-sand-300' }}">{{ $q }}</td>
                                    @endforeach
                                    <td class="px-5 py-3 text-center tnum font-semibold text-sand-900">{{ number_format($r['qty'], 0, ',', '.') }}</td>
                                    @if ($showTm)<td class="px-5 py-3 text-right tnum text-sand-800">{{ $fmt($r['nilai_tm']) }}</td>@endif
                                    @if ($showDiferd)<td class="px-5 py-3 text-right tnum text-sand-600">{{ $fmt($r['nilai_diferd']) }}</td>@endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-sand-200 bg-sand-50 font-semibold text-sand-900">
                                <td class="px-5 py-3">TOTAL</td>
                                @foreach ($totals['per_bulan'] as $q)
                                    <td class="px-3 py-3 text-center tnum">{{ $q }}</td>
                                @endforeach
                                <td class="px-5 py-3 text-center tnum">{{ number_format($totals['qty'], 0, ',', '.') }}</td>
                                @if ($showTm)<td class="px-5 py-3 text-right tnum">{{ $fmt($totals['nilai_tm']) }}</td>@endif
                                @if ($showDiferd)<td class="px-5 py-3 text-right tnum">{{ $fmt($totals['nilai_diferd']) }}</td>@endif
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

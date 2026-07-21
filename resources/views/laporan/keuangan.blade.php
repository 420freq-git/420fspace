<x-app-layout>
    @php
        $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
        $cardCount = 1 + ($showTm ? 1 : 0) + ($showDiferd ? 1 : 0) + ($showFee ? 1 : 0);
    @endphp
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-lg font-semibold text-sand-900">Laporan Keuangan Bulanan</h1>
                <p class="text-xs text-sand-500">Ringkasan nilai per bulan (12 bulan terakhir).</p>
            </div>
            <a href="{{ route('laporan.keuangan.pdf') }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m0 0l-2.25-2.25M12 16.5l2.25-2.25M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"/></svg>
                Export PDF
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div class="grid grid-cols-2 lg:grid-cols-{{ min(4, $cardCount) }} gap-5">
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5"><p class="text-sm text-sand-500">Unit terjual (12 bln)</p><p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ number_format($totals['unit'], 0, ',', '.') }}</p></div>
            @if ($showTm)
                <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5"><p class="text-sm text-sand-500">Nilai jual (TM420)</p><p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ $fmt($totals['nilai_tm']) }}</p></div>
            @endif
            @if ($showDiferd)
                <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5"><p class="text-sm text-sand-500">Hak Diferd</p><p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ $fmt($totals['hak_diferd']) }}</p><p class="mt-1 text-xs text-sand-400">nilai barang terjual × harga Diferd</p></div>
            @endif
            @if ($showFee)
                <div class="rounded-xl border border-brand-200 bg-brand-50 shadow-sm p-5"><p class="text-sm text-brand-700">Fee 420F</p><p class="mt-1 text-2xl font-semibold text-brand-700 tnum">{{ $fmt($totals['fee']) }}</p></div>
            @endif
        </div>

        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                            <th class="px-5 py-3 font-semibold">Bulan</th>
                            <th class="px-5 py-3 font-semibold text-center">Unit</th>
                            @if ($showTm)<th class="px-5 py-3 font-semibold text-right">Nilai TM420</th>@endif
                            @if ($showDiferd)<th class="px-5 py-3 font-semibold text-right">Hak Diferd</th>@endif
                            @if ($showFee)<th class="px-5 py-3 font-semibold text-right">Fee 420F</th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @foreach ($rows as $r)
                            <tr class="hover:bg-sand-50/50 {{ $r['unit'] === 0 ? 'text-sand-400' : '' }}">
                                <td class="px-5 py-3 {{ $r['unit'] > 0 ? 'font-medium text-sand-800' : '' }}">{{ $r['bulan']->translatedFormat('M Y') }}</td>
                                <td class="px-5 py-3 text-center tnum">{{ number_format($r['unit'], 0, ',', '.') }}</td>
                                @if ($showTm)<td class="px-5 py-3 text-right tnum">{{ $fmt($r['nilai_tm']) }}</td>@endif
                                @if ($showDiferd)<td class="px-5 py-3 text-right tnum">{{ $fmt($r['hak_diferd']) }}</td>@endif
                                @if ($showFee)<td class="px-5 py-3 text-right tnum {{ $r['unit'] > 0 ? 'text-brand-700' : '' }}">{{ $fmt($r['fee']) }}</td>@endif
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-sand-200 bg-sand-50 font-semibold text-sand-900">
                            <td class="px-5 py-3">TOTAL</td>
                            <td class="px-5 py-3 text-center tnum">{{ number_format($totals['unit'], 0, ',', '.') }}</td>
                            @if ($showTm)<td class="px-5 py-3 text-right tnum">{{ $fmt($totals['nilai_tm']) }}</td>@endif
                            @if ($showDiferd)<td class="px-5 py-3 text-right tnum">{{ $fmt($totals['hak_diferd']) }}</td>@endif
                            @if ($showFee)<td class="px-5 py-3 text-right tnum text-brand-700">{{ $fmt($totals['fee']) }}</td>@endif
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

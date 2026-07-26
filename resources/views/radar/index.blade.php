<x-app-layout>
    @php $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-semibold text-sand-900">Radar deadline &amp; buy-out</h1>
            <p class="text-xs text-sand-500">Batch aktif dengan sisa stok, diurut dari yang paling dekat deadline pelunasan.</p>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <div class="grid grid-cols-2 lg:grid-cols-3 gap-5">
            <div class="rounded-xl border {{ $totalPaparan > 0 ? 'border-amber-200 bg-amber-50' : 'border-sand-200 bg-white' }} shadow-sm p-5">
                <p class="text-sm {{ $totalPaparan > 0 ? 'text-amber-700' : 'text-sand-500' }}">Total paparan buy-out</p>
                <p class="mt-1 text-2xl font-semibold {{ $totalPaparan > 0 ? 'text-amber-800' : 'text-sand-900' }} tnum">{{ $fmt($totalPaparan) }}</p>
                <p class="mt-1 text-xs text-sand-400">nilai stok sisa × harga Diferd</p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Sisa stok belum terjual</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ number_format($totalSisa, 0, ',', '.') }} <span class="text-sm font-normal">pcs</span></p>
                <p class="mt-1 text-xs text-sand-400">terjual {{ number_format($totalTerjual, 0, ',', '.') }} dari {{ number_format($totalDiterima, 0, ',', '.') }} diterima</p>
            </div>
            <div class="rounded-xl border {{ $mepetCount > 0 ? 'border-red-200 bg-red-50' : 'border-sand-200 bg-white' }} shadow-sm p-5">
                <p class="text-sm {{ $mepetCount > 0 ? 'text-red-700' : 'text-sand-500' }}">Perlu perhatian</p>
                <p class="mt-1 text-2xl font-semibold {{ $mepetCount > 0 ? 'text-red-800' : 'text-sand-900' }} tnum">{{ $mepetCount }}</p>
                <p class="mt-1 text-xs text-sand-400">batch ≤ {{ $ambang }} hari / lewat deadline</p>
            </div>
        </div>

        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            @if ($rows->isEmpty())
                <div class="p-12 text-center text-sand-500">Tidak ada batch aktif dengan sisa stok. 🎉</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Batch</th>
                                <th class="px-5 py-3 font-semibold">Deadline pelunasan</th>
                                <th class="px-5 py-3 font-semibold text-center">Sisa waktu</th>
                                <th class="px-5 py-3 font-semibold">Terjual / Diterima</th>
                                <th class="px-5 py-3 font-semibold text-center">Sisa stok</th>
                                <th class="px-5 py-3 font-semibold text-right">Paparan buy-out</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($rows as $r)
                                @php
                                    [$tone, $label] = match ($r['status']) {
                                        'lewat' => ['bg-red-100 text-red-700', 'Lewat deadline'],
                                        'mepet' => ['bg-amber-100 text-amber-800', 'Mepet'],
                                        default => ['bg-brand-100 text-brand-800', 'Aman'],
                                    };
                                @endphp
                                <tr class="hover:bg-sand-50/50 {{ $r['status'] === 'lewat' ? 'bg-red-50/40' : ($r['status'] === 'mepet' ? 'bg-amber-50/30' : '') }}">
                                    <td class="px-5 py-3.5">
                                        <a href="{{ route('settlement.show', $r['batch']) }}" class="font-medium text-sand-900 hover:text-brand-700 tnum">{{ $r['batch']->nomor_batch }}</a>
                                        <div class="text-xs text-sand-400">{{ $r['batch']->brand->nama }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-sand-600 tnum">{{ $r['deadline']->format('d/m/Y') }}</td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $tone }}">
                                            @if ($r['hari_lagi'] < 0)
                                                {{ abs($r['hari_lagi']) }} hari lewat
                                            @else
                                                {{ $r['hari_lagi'] }} hari lagi
                                            @endif
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 w-48">
                                        <div class="flex items-center justify-between text-xs mb-1">
                                            <span class="tnum text-sand-700">{{ number_format($r['terjual'], 0, ',', '.') }} / {{ number_format($r['diterima'], 0, ',', '.') }}</span>
                                            <span class="tnum text-sand-400">{{ $r['sell_through'] }}%</span>
                                        </div>
                                        <div class="h-1.5 rounded-full bg-sand-100 overflow-hidden">
                                            <div class="h-full rounded-full bg-brand-500" style="width: {{ $r['sell_through'] }}%"></div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-center tnum font-medium text-sand-900">{{ number_format($r['sisa_pcs'], 0, ',', '.') }}</td>
                                    <td class="px-5 py-3.5 text-right tnum font-semibold {{ $r['status'] === 'aman' ? 'text-sand-700' : 'text-amber-700' }}">{{ $fmt($r['paparan']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <p class="text-xs text-sand-400">
            Paparan buy-out = nilai stok yang belum terjual (× harga Diferd). Bila tak laku sampai deadline, stok ini di-buy-out —
            TM420 bayar ke 420F, lalu 420F teruskan ke Diferd. Dorong penjualan atau siapkan dana sebelum jatuh tempo.
        </p>
    </div>
</x-app-layout>

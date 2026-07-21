<x-app-layout>
    @php $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-semibold text-sand-900">Analisis per channel</h1>
            <p class="text-xs text-sand-500">Kinerja penjualan tiap marketplace — untuk memutuskan fokus channel.</p>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <div class="grid grid-cols-2 lg:grid-cols-3 gap-5">
            <div class="rounded-xl border border-brand-200 bg-brand-50 shadow-sm p-5">
                <p class="text-sm text-brand-700">Channel terbaik</p>
                <p class="mt-1 text-xl font-semibold text-brand-800">{{ $terbaik ?? '—' }}</p>
                <p class="mt-1 text-xs text-brand-600">terbanyak terjual</p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Total terjual (lunas)</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ number_format($totalQty, 0, ',', '.') }} <span class="text-sm font-normal">pcs</span></p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Total omzet</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ $fmt($totalOmzet) }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                            <th class="px-5 py-3 font-semibold">Channel</th>
                            <th class="px-5 py-3 font-semibold text-center">Pesanan</th>
                            <th class="px-5 py-3 font-semibold text-center">Terjual</th>
                            <th class="px-5 py-3 font-semibold">Porsi</th>
                            <th class="px-5 py-3 font-semibold text-center">Retur rusak</th>
                            <th class="px-5 py-3 font-semibold text-right">Omzet</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @foreach ($rows as $r)
                            <tr class="hover:bg-sand-50/50 {{ $r['qty'] === 0 ? 'opacity-50' : '' }}">
                                <td class="px-5 py-3.5 font-medium text-sand-900">{{ $r['label'] }}</td>
                                <td class="px-5 py-3.5 text-center tnum text-sand-600">{{ number_format($r['pesanan'], 0, ',', '.') }}</td>
                                <td class="px-5 py-3.5 text-center tnum font-medium text-sand-800">{{ number_format($r['qty'], 0, ',', '.') }}</td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 flex-1 min-w-16 rounded-full bg-sand-100 overflow-hidden">
                                            <div class="h-full rounded-full bg-brand-500" style="width: {{ max(2, $r['porsi']) }}%"></div>
                                        </div>
                                        <span class="text-xs tnum text-sand-500 w-10 text-right">{{ $r['porsi'] }}%</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-center tnum {{ $r['retur'] > 0 ? 'text-red-700' : 'text-sand-300' }}">{{ $r['retur'] ?: '—' }}</td>
                                <td class="px-5 py-3.5 text-right tnum text-sand-800">{{ $r['omzet'] > 0 ? $fmt($r['omzet']) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-xs text-sand-400">Hanya penjualan lunas dihitung. Porsi = bagian dari total pcs terjual. Channel yang belum dipakai ditampilkan pudar.</p>
    </div>
</x-app-layout>

<x-app-layout>
    @php $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-semibold text-sand-900">Rapor per artikel</h1>
            <p class="text-xs text-sand-500">Kinerja tiap produk — untuk memutuskan artikel mana yang dilanjut atau di-drop.</p>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <div class="grid grid-cols-2 {{ $showFee ? 'lg:grid-cols-3' : '' }} gap-5">
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Artikel pernah diproduksi</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ $aktifCount }}</p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Total omzet (nilai jual)</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ $fmt($totalOmzet) }}</p>
            </div>
            @if ($showFee)
                <div class="rounded-xl border border-brand-200 bg-brand-50 shadow-sm p-5">
                    <p class="text-sm text-brand-700">Total fee 420F</p>
                    <p class="mt-1 text-2xl font-semibold text-brand-800 tnum">{{ $fmt($totalFee) }}</p>
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                            <th class="px-5 py-3 font-semibold">Artikel</th>
                            <th class="px-5 py-3 font-semibold text-center">Produksi</th>
                            <th class="px-5 py-3 font-semibold text-center">Terjual</th>
                            <th class="px-5 py-3 font-semibold text-center">Sell-through</th>
                            <th class="px-5 py-3 font-semibold text-center">Sisa</th>
                            <th class="px-5 py-3 font-semibold text-center">Cacat</th>
                            <th class="px-5 py-3 font-semibold text-right">Omzet</th>
                            @if ($showFee)<th class="px-5 py-3 font-semibold text-right">Fee 420F</th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @foreach ($rows as $r)
                            <tr class="hover:bg-sand-50/50 {{ ! $r['pernah_produksi'] ? 'opacity-60' : '' }}">
                                <td class="px-5 py-3">
                                    <div class="font-medium text-sand-900">{{ $r['product']->nama_artikel }}</div>
                                    <div class="text-xs text-sand-400">{{ $r['kategori'] }}</div>
                                </td>
                                <td class="px-5 py-3 text-center tnum text-sand-600">{{ $r['pernah_produksi'] ? number_format($r['diproduksi'], 0, ',', '.') : '—' }}</td>
                                <td class="px-5 py-3 text-center tnum font-medium text-sand-800">{{ number_format($r['terjual'], 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-center">
                                    @if ($r['pernah_produksi'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $r['sell_through'] >= 80 ? 'bg-brand-100 text-brand-800' : ($r['sell_through'] >= 40 ? 'bg-sand-100 text-sand-700' : 'bg-amber-100 text-amber-800') }}">{{ $r['sell_through'] }}%</span>
                                    @else
                                        <span class="text-sand-300">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-center tnum text-sand-700">{{ $r['pernah_produksi'] ? $r['sisa'] : '—' }}</td>
                                <td class="px-5 py-3 text-center tnum {{ $r['cacat'] > 0 ? 'text-red-700' : 'text-sand-300' }}">{{ $r['cacat'] > 0 ? $r['cacat'].' ('.$r['cacat_persen'].'%)' : '—' }}</td>
                                <td class="px-5 py-3 text-right tnum text-sand-800">{{ $r['omzet'] > 0 ? $fmt($r['omzet']) : '—' }}</td>
                                @if ($showFee)<td class="px-5 py-3 text-right tnum text-brand-700">{{ $r['fee'] > 0 ? $fmt($r['fee']) : '—' }}</td>@endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-xs text-sand-400">Diurut dari paling laku. Sell-through = terjual ÷ diterima. Artikel yang belum pernah diproduksi ditampilkan pudar. Cacat = reject + kurang/cacat.</p>
    </div>
</x-app-layout>

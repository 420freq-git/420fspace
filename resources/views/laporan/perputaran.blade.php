<x-app-layout>
    @php
        $tone = [
            'brand' => 'bg-brand-100 text-brand-800', 'amber' => 'bg-amber-100 text-amber-800',
            'red' => 'bg-red-100 text-red-700', 'sand' => 'bg-sand-100 text-sand-500',
        ];
    @endphp
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-lg font-semibold text-sand-900">Laporan Perputaran Stok</h1>
                <p class="text-xs text-sand-500">Cepat / lambat / stok mati — berdasarkan 3 bulan terakhir (sejak {{ $sejak->translatedFormat('d M Y') }}).</p>
            </div>
            <a href="{{ route('laporan.perputaran.pdf') }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m0 0l-2.25-2.25M12 16.5l2.25-2.25M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"/></svg>
                Export PDF
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="rounded-xl border border-brand-200 bg-brand-50 shadow-sm p-5"><p class="text-sm text-brand-700">Cepat</p><p class="mt-1 text-2xl font-semibold text-brand-700 tnum">{{ $stats['cepat'] }}</p><p class="mt-1 text-xs text-brand-600">stok habis &lt; 2 bulan</p></div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 shadow-sm p-5"><p class="text-sm text-amber-700">Lambat</p><p class="mt-1 text-2xl font-semibold text-amber-700 tnum">{{ $stats['lambat'] }}</p><p class="mt-1 text-xs text-amber-600">butuh ≥ 2 bulan</p></div>
            <div class="rounded-xl border {{ $stats['mati'] > 0 ? 'border-red-200 bg-red-50' : 'border-sand-200 bg-white' }} shadow-sm p-5"><p class="text-sm {{ $stats['mati'] > 0 ? 'text-red-700' : 'text-sand-500' }}">Stok mati</p><p class="mt-1 text-2xl font-semibold {{ $stats['mati'] > 0 ? 'text-red-700' : 'text-sand-900' }} tnum">{{ $stats['mati'] }}</p><p class="mt-1 text-xs {{ $stats['mati'] > 0 ? 'text-red-600' : 'text-sand-400' }}">tak terjual 3 bulan</p></div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5"><p class="text-sm text-sand-500">Unit mengendap</p><p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ number_format($stats['sisaMati'], 0, ',', '.') }}</p><p class="mt-1 text-xs text-sand-400">pcs di stok mati</p></div>
        </div>

        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            @if ($rows->isEmpty())
                <div class="p-12 text-center text-sand-500">Belum ada artikel yang diproduksi.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Artikel</th>
                                <th class="px-5 py-3 font-semibold">Kategori</th>
                                <th class="px-5 py-3 font-semibold text-center">Stok</th>
                                <th class="px-5 py-3 font-semibold text-center">Keluar 3 bln</th>
                                <th class="px-5 py-3 font-semibold text-center">Rata/bln</th>
                                <th class="px-5 py-3 font-semibold text-center">Bulan stok</th>
                                <th class="px-5 py-3 font-semibold text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($rows as $r)
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3.5 font-medium text-sand-900">{{ $r['product']->nama_artikel }}
                                        <span class="text-sand-400 font-normal">· {{ $r['product']->brand->nama ?? '—' }}</span></td>
                                    <td class="px-5 py-3.5 text-sand-600">{{ $r['kategori'] }}</td>
                                    <td class="px-5 py-3.5 text-center tnum text-sand-800">{{ number_format($r['sisa'], 0, ',', '.') }}</td>
                                    <td class="px-5 py-3.5 text-center tnum text-sand-700">{{ number_format($r['keluar3'], 0, ',', '.') }}</td>
                                    <td class="px-5 py-3.5 text-center tnum text-sand-600">{{ $r['per_bulan'] }}</td>
                                    <td class="px-5 py-3.5 text-center tnum text-sand-600">{{ $r['bulan_stok'] !== null ? $r['bulan_stok'] : '∞' }}</td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $tone[$r['status']['tone']] }}">{{ $r['status']['label'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <p class="text-xs text-sand-400">"Bulan stok" = perkiraan berapa bulan stok sekarang bertahan dengan kecepatan keluar 3 bulan terakhir. ∞ = belum ada pergerakan.</p>
    </div>
</x-app-layout>

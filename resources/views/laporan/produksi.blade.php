<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-lg font-semibold text-sand-900">Laporan Produksi &amp; Performa Vendor</h1>
                <p class="text-xs text-sand-500">Progres batch, ketepatan deadline, &amp; kinerja Diferd.</p>
            </div>
            <a href="{{ route('laporan.produksi.pdf') }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m0 0l-2.25-2.25M12 16.5l2.25-2.25M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"/></svg>
                Export PDF
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-4"><p class="text-xs text-sand-500">Total batch</p><p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ $stats['total'] }}</p></div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 shadow-sm p-4"><p class="text-xs text-blue-700">Aktif</p><p class="mt-1 text-2xl font-semibold text-blue-700 tnum">{{ $stats['aktif'] }}</p></div>
            <div class="rounded-xl border border-brand-200 bg-brand-50 shadow-sm p-4"><p class="text-xs text-brand-700">Selesai</p><p class="mt-1 text-2xl font-semibold text-brand-700 tnum">{{ $stats['selesai'] }}</p></div>
            <div class="rounded-xl border {{ $stats['telat'] > 0 ? 'border-red-200 bg-red-50' : 'border-sand-200 bg-white' }} shadow-sm p-4"><p class="text-xs {{ $stats['telat'] > 0 ? 'text-red-700' : 'text-sand-500' }}">Telat deadline</p><p class="mt-1 text-2xl font-semibold {{ $stats['telat'] > 0 ? 'text-red-700' : 'text-sand-900' }} tnum">{{ $stats['telat'] }}</p></div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-4"><p class="text-xs text-sand-500">Rata progres aktif</p><p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ $stats['avgProgress'] }}%</p></div>
        </div>

        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            @if ($rows->isEmpty())
                <div class="p-12 text-center text-sand-500">Belum ada batch.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Batch</th>
                                <th class="px-5 py-3 font-semibold">Tgl order</th>
                                <th class="px-5 py-3 font-semibold">Deadline</th>
                                <th class="px-5 py-3 font-semibold">Progres</th>
                                <th class="px-5 py-3 font-semibold">Ketepatan</th>
                                <th class="px-5 py-3 font-semibold text-center">PO</th>
                                <th class="px-5 py-3 font-semibold text-center">Qty</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($rows as $r)
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3.5"><div class="font-medium text-sand-900 tnum">{{ $r['batch']->nomor_batch }}</div><div class="text-xs text-sand-400">{{ $r['batch']->brand->nama }}</div></td>
                                    <td class="px-5 py-3.5 text-sand-600 tnum">{{ $r['batch']->tanggal_order->format('d/m/Y') }}</td>
                                    <td class="px-5 py-3.5 text-sand-600 tnum">{{ $r['deadline']?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <div class="w-20 h-1.5 rounded-full bg-sand-100 overflow-hidden"><div class="h-full rounded-full {{ $r['selesai'] ? 'bg-brand-600' : ($r['telat'] ? 'bg-red-500' : 'bg-brand-500') }}" style="width: {{ $r['progress'] }}%"></div></div>
                                            <span class="text-xs tnum text-sand-500">{{ $r['progress'] }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-xs">
                                        @if ($r['selesai'])<span class="text-brand-700 font-medium">Selesai</span>
                                        @elseif ($r['sisa'] === null)<span class="text-sand-400">—</span>
                                        @elseif ($r['telat'])<span class="text-red-700 font-semibold">Telat {{ abs($r['sisa']) }} hari</span>
                                        @else<span class="text-sand-500">{{ $r['sisa'] }} hari lagi</span>@endif
                                    </td>
                                    <td class="px-5 py-3.5 text-center tnum text-sand-600">{{ $r['poCount'] }}</td>
                                    <td class="px-5 py-3.5 text-center tnum text-sand-700">{{ number_format($r['qty'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

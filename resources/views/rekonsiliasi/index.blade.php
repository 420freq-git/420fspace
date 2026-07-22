<x-app-layout>
    @php $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-semibold text-sand-900">Rekonsiliasi pembayaran TM</h1>
            <p class="text-xs text-sand-500">Per minggu: tagihan cair vs transfer diterima, dengan tunggakan berjalan.</p>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <div class="grid grid-cols-2 lg:grid-cols-3 gap-5">
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Total tagihan cair</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ $fmt($totalTagihan) }}</p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Total diterima</p>
                <p class="mt-1 text-2xl font-semibold text-brand-700 tnum">{{ $fmt($totalTransfer) }}</p>
            </div>
            <div class="rounded-xl border {{ $tunggakan > 0 ? 'border-amber-200 bg-amber-50' : 'border-brand-200 bg-brand-50' }} shadow-sm p-5">
                <p class="text-sm {{ $tunggakan > 0 ? 'text-amber-700' : 'text-brand-700' }}">{{ $tunggakan >= 0 ? 'Tunggakan' : 'Kelebihan bayar' }}</p>
                <p class="mt-1 text-2xl font-semibold {{ $tunggakan > 0 ? 'text-amber-800' : 'text-brand-800' }} tnum">{{ $fmt(abs($tunggakan)) }}</p>
                <p class="mt-1 text-xs {{ $tunggakan > 0 ? 'text-amber-600' : 'text-brand-600' }}">{{ $tunggakan > 0 ? 'belum dibayar TM' : ($tunggakan < 0 ? 'TM transfer lebih' : 'lunas') }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            @if (empty($rows))
                <div class="p-12 text-center text-sand-500">Belum ada tagihan cair atau transfer.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Minggu (Sen–Min)</th>
                                <th class="px-5 py-3 font-semibold text-right">Tagihan cair</th>
                                <th class="px-5 py-3 font-semibold text-right">Diterima</th>
                                <th class="px-5 py-3 font-semibold text-right">Selisih minggu</th>
                                <th class="px-5 py-3 font-semibold text-right">Tunggakan kumulatif</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($rows as $r)
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3 text-sand-700 tnum">{{ $r['label'] }}</td>
                                    <td class="px-5 py-3 text-right tnum text-sand-800">{{ $fmt($r['tagihan']) }}</td>
                                    <td class="px-5 py-3 text-right tnum text-brand-700">{{ $fmt($r['terima']) }}</td>
                                    <td class="px-5 py-3 text-right tnum {{ $r['selisih'] < 0 ? 'text-amber-700' : 'text-sand-400' }}">{{ $r['selisih'] < 0 ? $fmt($r['selisih']) : ($r['selisih'] > 0 ? '+'.$fmt($r['selisih']) : '—') }}</td>
                                    <td class="px-5 py-3 text-right tnum font-semibold {{ $r['tunggakan_kumulatif'] > 0 ? 'text-amber-700' : 'text-brand-700' }}">{{ $r['tunggakan_kumulatif'] != 0 ? $fmt($r['tunggakan_kumulatif']) : 'lunas' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <p class="text-xs text-sand-400">
            Tagihan cair = pesanan yang jadi lunas minggu itu (× harga ke 420F) + invoice buy-out (di minggu terbitnya).
            Cash batch (dibayar penuh di muka) tidak masuk hitungan ini.
            Karena TM sering bayar berselang (mis. Minggu untuk penjualan seminggu sebelumnya), lihat <span class="font-medium">tunggakan kumulatif</span> untuk posisi sebenarnya.
        </p>
    </div>
</x-app-layout>

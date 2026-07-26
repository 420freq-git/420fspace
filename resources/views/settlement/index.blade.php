<x-app-layout>
    @php $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-semibold text-sand-900">{{ $isAdmin ? 'Settlement / Saldo Vendor' : 'Settlement / Hak Saya' }}</h1>
            <p class="text-xs text-sand-500">
                {{ $isAdmin
                    ? 'Perhitungan kewajiban & pembayaran ke Diferd per batch.'
                    : 'Perhitungan hak Anda & pembayaran yang sudah Anda terima per batch.' }}
            </p>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Ringkasan — 4 kartu untuk 420F (+Fee), 3 untuk Diferd. Kartu deposit dihilangkan
             (deposit sudah tidak dipakai); logikanya tetap dorman di service. --}}
        <div class="grid grid-cols-2 {{ $isAdmin ? 'lg:grid-cols-4' : 'lg:grid-cols-3' }} gap-5">
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">{{ $isAdmin ? 'Hak Diferd' : 'Hak Anda' }}</p>
                <p class="mt-1 text-xl font-semibold text-sand-900 tnum">{{ $fmt($totals['kewajiban']) }}</p>
                <p class="mt-1 text-xs text-sand-400">dari barang terjual</p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">{{ $isAdmin ? 'Sudah dibayar' : 'Sudah Anda terima' }}</p>
                <p class="mt-1 text-xl font-semibold text-sand-900 tnum">{{ $fmt($totals['dibayar_total']) }}</p>
                <p class="mt-1 text-xs text-sand-400">termasuk penarikan {{ $fmt($totals['penarikan']) }}</p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">{{ $isAdmin ? 'Saldo ke Diferd' : 'Sisa hak Anda' }}</p>
                @if ($totals['saldo'] > 0)
                    <p class="mt-1 text-xl font-semibold text-amber-700 tnum">{{ $fmt($totals['saldo']) }}</p>
                    <p class="mt-1 text-xs text-amber-600">{{ $isAdmin ? 'sisa hak yang perlu dibayar' : 'sisa hak yang belum Anda terima' }}</p>
                @else
                    <p class="mt-1 text-xl font-semibold text-brand-700 tnum">{{ $fmt(0) }}</p>
                    <p class="mt-1 text-xs text-brand-600">hak sudah lunas</p>
                @endif
            </div>
            @if ($isAdmin)
                <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                    <p class="text-sm text-sand-500">Fee 420F</p>
                    <p class="mt-1 text-xl font-semibold text-brand-700 tnum">{{ $fmt($totals['fee']) }}</p>
                    <p class="mt-1 text-xs text-sand-400">markup dari penjualan</p>
                </div>
            @endif
        </div>

        {{-- Rekap stok semua batch --}}
        @if ($stokKategori->isNotEmpty())
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-sand-200 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-sand-800">Rekap stok seluruh batch</h2>
                    <div class="flex items-center gap-4 text-xs">
                        <span class="text-sand-500">Diproduksi <span class="font-semibold text-sand-800 tnum">{{ number_format($stokTotal['diproduksi'], 0, ',', '.') }}</span></span>
                        <span class="text-sand-500">Terjual <span class="font-semibold text-sand-800 tnum">{{ number_format($stokTotal['terjual'], 0, ',', '.') }}</span></span>
                        <span class="text-sand-500">Sisa <span class="font-semibold text-brand-700 tnum">{{ number_format($stokTotal['sisa'], 0, ',', '.') }}</span></span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Kategori</th>
                                <th class="px-5 py-3 font-semibold text-center">Diproduksi</th>
                                <th class="px-5 py-3 font-semibold text-center">Terjual</th>
                                <th class="px-5 py-3 font-semibold text-center">Sisa stok</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($stokKategori as $kat => $k)
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3 font-medium text-sand-800">{{ $kat }}</td>
                                    <td class="px-5 py-3 text-center tnum text-sand-600">{{ number_format($k['diproduksi'], 0, ',', '.') }}</td>
                                    <td class="px-5 py-3 text-center tnum text-sand-600">{{ number_format($k['terjual'], 0, ',', '.') }}</td>
                                    <td class="px-5 py-3 text-center tnum font-semibold text-sand-900">{{ number_format($k['sisa'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-sand-200 bg-sand-50 font-semibold text-sand-900">
                                <td class="px-5 py-3">TOTAL</td>
                                <td class="px-5 py-3 text-center tnum">{{ number_format($stokTotal['diproduksi'], 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-center tnum">{{ number_format($stokTotal['terjual'], 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-center tnum text-brand-700">{{ number_format($stokTotal['sisa'], 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        {{-- Per batch --}}
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            @if ($rows->isEmpty())
                <div class="p-12 text-center text-sand-500">Belum ada batch.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Batch</th>
                                <th class="px-5 py-3 font-semibold text-center">Terjual / Terkirim</th>
                                <th class="px-5 py-3 font-semibold text-right">Hak (terjual)</th>
                                <th class="px-5 py-3 font-semibold text-right">{{ $isAdmin ? 'Dibayar' : 'Diterima' }}</th>
                                <th class="px-5 py-3 font-semibold text-right">Saldo</th>
                                @if ($isAdmin)<th class="px-5 py-3 font-semibold text-right">Fee 420F</th>@endif
                                <th class="px-5 py-3 font-semibold text-center">Status</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($rows as $row)
                                @php $b = $row['batch']; $s = $row['sum']; $st = $row['stok']; @endphp
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3.5">
                                        <a href="{{ route('settlement.show', $b) }}" class="font-medium text-sand-900 hover:text-brand-700 tnum">{{ $b->nomor_batch }}</a>
                                        <div class="text-xs text-sand-400">{{ $b->brand->nama }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        @if ($st['is_cash'] ?? false)
                                            {{-- Cash = beli putus: tampilkan TERKIRIM ke TM, bukan terjual. --}}
                                            <div class="tnum text-sand-800">{{ number_format($st['diterima'], 0, ',', '.') }} <span class="text-sand-400">terkirim</span></div>
                                            <div class="text-xs text-sand-400">dari {{ number_format($st['diproduksi'], 0, ',', '.') }} pcs</div>
                                        @else
                                            <div class="tnum text-sand-800">{{ number_format($st['terjual'], 0, ',', '.') }} <span class="text-sand-400">/</span> <span class="font-medium {{ $st['sisa'] > 0 ? 'text-brand-700' : 'text-sand-400' }}">{{ number_format($st['sisa'], 0, ',', '.') }}</span></div>
                                            <div class="text-xs text-sand-400">dari {{ number_format($st['diproduksi'], 0, ',', '.') }} pcs</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-right tnum text-sand-800">{{ $fmt($s['kewajiban']) }}</td>
                                    <td class="px-5 py-3.5 text-right">
                                        <div class="tnum text-sand-800">{{ $fmt($s['terbayar']) }}</div>
                                        @if ($s['penarikan'] > 0)
                                            <div class="text-xs text-sand-400">via penarikan {{ $fmt($s['penarikan']) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-right tnum font-medium {{ $s['saldo'] > 0 ? 'text-amber-700' : 'text-brand-700' }}">
                                        {{ $s['saldo'] > 0 ? $fmt($s['saldo']) : '—' }}
                                    </td>
                                    @if ($isAdmin)<td class="px-5 py-3.5 text-right tnum text-brand-700">{{ $fmt($s['fee420f']) }}</td>@endif
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $b->status->badgeClasses() }}">{{ $b->status->label() }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <a href="{{ route('settlement.show', $b) }}" class="text-sm font-medium text-brand-700 hover:underline">Detail</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <div class="space-y-1 text-xs text-sand-400">
            <p>Diferd menarik dana tanpa memilih batch, tapi saat 420F menyetujui, jumlahnya langsung <span class="font-medium">dibagi ke batch dan dicatat permanen</span> — batch tertua yang haknya belum tertutup dibayar lebih dulu. Kolom <span class="font-medium">Saldo</span> karena itu bisa dipakai sebagai dasar pelunasan saat batch selesai, dan angkanya tidak bergeser lagi kalau ada penjualan susulan.@if ($totals['penarikan_pending'] > 0) Menunggu persetujuan {{ $fmt($totals['penarikan_pending']) }} (belum dibagi).@endif @if ($totals['penarikan_sisa'] > 0) Ada {{ $fmt($totals['penarikan_sisa']) }} yang tidak terserap batch mana pun — dari penjualan yang tidak terikat batch.@endif</p>
        </div>
    </div>
</x-app-layout>

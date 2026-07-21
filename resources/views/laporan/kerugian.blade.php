<x-app-layout>
    @php $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-lg font-semibold text-sand-900">Laporan Kerugian</h1>
                <p class="text-xs text-sand-500">Dipisah menurut pihak yang menanggung.</p>
            </div>
            <a href="{{ route('laporan.kerugian.pdf') }}" target="_blank"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m0 0l-2.25-2.25M12 16.5l2.25-2.25M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"/></svg>
                Export PDF
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Ringkasan per pihak --}}
        <div class="grid gap-5 {{ $showTm && $showDiferd ? 'sm:grid-cols-2' : '' }}">
            @if ($showTm)
                <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">TM420</span>
                        <p class="text-sm text-sand-500">Kerugian brand</p>
                    </div>
                    <p class="mt-2 text-2xl font-semibold text-sand-900 tnum">{{ $fmt($totalNilai) }}</p>
                    <p class="mt-1 text-xs text-sand-400">{{ number_format($totalQty, 0, ',', '.') }} pcs retur yang tidak bisa dijual lagi</p>
                </div>
            @endif
            @if ($showDiferd)
                <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Diferd</span>
                        <p class="text-sm text-sand-500">Kerugian vendor</p>
                    </div>
                    <p class="mt-2 text-2xl font-semibold text-sand-900 tnum">{{ $fmt($produksiNilai) }}</p>
                    <p class="mt-1 text-xs text-sand-400">{{ number_format($produksiQty, 0, ',', '.') }} pcs dari PO yang tidak sampai jadi stok jual</p>
                </div>
            @endif
        </div>

        {{-- Kerugian Diferd: qty PO yang tidak sampai jadi stok jual --}}
        @if ($showDiferd)
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-sand-200 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-sand-800">Kerugian Diferd — produk yang tidak bisa dikirim</h2>
                    <p class="text-xs text-sand-400">Selisih antara jumlah PO dan barang yang benar-benar sampai jadi stok jual. Tidak menambah tagihan ke brand.</p>
                </div>
                <span class="text-xs text-sand-500">Total <span class="font-semibold text-red-700 tnum">{{ $fmt($produksiNilai) }}</span></span>
            </div>
            @if ($produksi->isEmpty())
                <div class="p-8 text-center text-sm text-sand-400">Tidak ada reject maupun kekurangan penerimaan.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Jenis</th>
                                <th class="px-5 py-3 font-semibold">Batch</th>
                                <th class="px-5 py-3 font-semibold">Produk</th>
                                <th class="px-5 py-3 font-semibold text-center">UK</th>
                                <th class="px-5 py-3 font-semibold text-center">Qty</th>
                                <th class="px-5 py-3 font-semibold text-right">Harga Diferd</th>
                                <th class="px-5 py-3 font-semibold text-right">Kerugian</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($produksi as $r)
                                <tr class="hover:bg-sand-50/50 align-top">
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ str_contains($r['jenis'], 'Reject') ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800' }}">{{ $r['jenis'] }}</span>
                                        <div class="mt-1 text-xs text-sand-400">{{ $r['keterangan'] }}</div>
                                    </td>
                                    <td class="px-5 py-3 text-sand-600 tnum">{{ $r['batch'] }}</td>
                                    <td class="px-5 py-3 text-sand-800">{{ $r['produk'] }}</td>
                                    <td class="px-5 py-3 text-center text-sand-700">{{ $r['ukuran'] }}</td>
                                    <td class="px-5 py-3 text-center tnum text-sand-800">{{ $r['qty'] }}</td>
                                    <td class="px-5 py-3 text-right tnum text-sand-600">{{ $fmt($r['harga']) }}</td>
                                    <td class="px-5 py-3 text-right tnum font-medium text-red-700">{{ $fmt($r['nilai']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-sand-200 bg-sand-50 font-semibold text-sand-900">
                                <td class="px-5 py-3" colspan="4">TOTAL</td>
                                <td class="px-5 py-3 text-center tnum">{{ number_format($produksiQty, 0, ',', '.') }}</td>
                                <td class="px-5 py-3"></td>
                                <td class="px-5 py-3 text-right tnum text-red-700">{{ $fmt($produksiNilai) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
        @endif

        {{-- Kerugian TM420: produk retur yang tidak bisa dijual lagi --}}
        @if ($showTm)
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-sand-200 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-sand-800">Kerugian TM420 — retur yang tidak bisa dijual</h2>
                    <p class="text-xs text-sand-400">Barang kembali dari pembeli dalam kondisi rusak. Brand tetap membayar biaya produksinya.</p>
                </div>
                <span class="text-xs text-sand-500">Total <span class="font-semibold text-red-700 tnum">{{ $fmt($totalNilai) }}</span></span>
            </div>
            @if ($items->isEmpty())
                <div class="p-8 text-center text-sm text-sand-400">Belum ada retur rusak. 🎉</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Tgl retur diterima</th>
                                <th class="px-5 py-3 font-semibold">Order ID</th>
                                <th class="px-5 py-3 font-semibold">Produk</th>
                                <th class="px-5 py-3 font-semibold">Alasan</th>
                                <th class="px-5 py-3 font-semibold text-center">UK</th>
                                <th class="px-5 py-3 font-semibold text-center">Qty</th>
                                <th class="px-5 py-3 font-semibold text-right">Harga Diferd</th>
                                <th class="px-5 py-3 font-semibold text-right">Kerugian</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($items as $s)
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3.5 text-sand-600 tnum">{{ $s->order?->tgl_retur_diterima?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="px-5 py-3.5 text-sand-700 tnum">{{ $s->nomor_pesanan ?? '—' }}</td>
                                    <td class="px-5 py-3.5 font-medium text-sand-900">{{ $s->product->nama_artikel }}</td>
                                    <td class="px-5 py-3.5 text-sand-600">{{ $s->order?->alasan_rusak ?? '—' }}</td>
                                    <td class="px-5 py-3.5 text-center text-sand-700">{{ $s->ukuran->value }}</td>
                                    <td class="px-5 py-3.5 text-center tnum text-sand-700">{{ $s->qty }}</td>
                                    <td class="px-5 py-3.5 text-right tnum text-sand-800">{{ $fmt($s->harga_diferd) }}</td>
                                    <td class="px-5 py-3.5 text-right tnum font-medium text-red-700">{{ $fmt($s->qty * $s->harga_diferd) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @endif
    </div>
</x-app-layout>

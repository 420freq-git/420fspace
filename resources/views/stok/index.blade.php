<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-semibold text-sand-900">Stok</h1>
            <p class="text-xs text-sand-500">Sisa stok = diproduksi (Batch/PO) − terjual.</p>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-5">
        @php
            $canRec = auth()->user()->role !== \App\Enums\Role::Diferd;
            $toneBadge = [
                'red' => 'bg-red-100 text-red-700', 'amber' => 'bg-amber-100 text-amber-800',
                'brand' => 'bg-brand-100 text-brand-800', 'sand' => 'bg-sand-100 text-sand-500',
            ];
        @endphp

        @php $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp

        {{-- Ringkasan stok --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="rounded-xl border border-brand-200 bg-brand-50 shadow-sm p-5">
                <p class="text-sm text-brand-700">Total stok</p>
                <p class="mt-1 text-2xl font-semibold text-brand-700 tnum">{{ number_format($totalSisa, 0, ',', '.') }} <span class="text-sm font-normal">pcs</span></p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Nilai stok</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ $fmt($totalNilai) }}</p>
                <p class="mt-1 text-xs text-sand-400">{{ $basisHarga }}</p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Diproduksi</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ number_format($totalProduced, 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Terjual</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ number_format($totalSold, 0, ',', '.') }}</p>
            </div>
        </div>

        {{-- Stok di jalan: barang yang sudah ada tapi belum bisa dijual --}}
        @php $totalJalanSemua = $totalVendor + $totalJalan; @endphp
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-sand-200 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-sand-800">Stok di jalan</h2>
                    <p class="text-xs text-sand-400">Belum masuk “Total stok” karena belum bisa dijual.</p>
                </div>
                <span class="text-xs text-sand-500">Total di jalan
                    <span class="font-semibold text-sand-800 tnum">{{ number_format($totalJalanSemua, 0, ',', '.') }} pcs</span>
                </span>
            </div>

            <div class="grid sm:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-sand-200">
                <div class="p-5">
                    <p class="text-sm text-sand-500">Menunggu dikirim</p>
                    <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ number_format($totalVendor, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-sand-400">selesai produksi, surat jalan belum dibuat</p>
                </div>
                <div class="p-5 {{ $totalReject > 0 ? 'bg-red-50/60' : '' }}">
                    <p class="text-sm {{ $totalReject > 0 ? 'text-red-700' : 'text-sand-500' }}">Reject produksi</p>
                    <p class="mt-1 text-2xl font-semibold tnum {{ $totalReject > 0 ? 'text-red-800' : 'text-sand-900' }}">{{ number_format($totalReject, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs {{ $totalReject > 0 ? 'text-red-600' : 'text-sand-400' }}">batch berjalan · ditanggung vendor</p>
                    @if ($totalRejectArsip > 0)
                        @php $bolehLihatKerugian = in_array(auth()->user()->role, [\App\Enums\Role::Admin, \App\Enums\Role::Tm420], true); @endphp
                        <p class="mt-1 text-xs text-sand-400">
                            + {{ number_format($totalRejectArsip, 0, ',', '.') }} dari batch selesai
                            @if ($bolehLihatKerugian)
                                (<a href="{{ route('laporan.kerugian') }}" class="text-brand-700 hover:underline">arsip</a>)
                            @else
                                (arsip)
                            @endif
                        </p>
                    @endif
                </div>
                <div class="p-5 {{ $totalJalan > 0 ? 'bg-amber-50/60' : '' }}">
                    <p class="text-sm {{ $totalJalan > 0 ? 'text-amber-700' : 'text-sand-500' }}">Dikirim, belum diterima</p>
                    <p class="mt-1 text-2xl font-semibold tnum {{ $totalJalan > 0 ? 'text-amber-800' : 'text-sand-900' }}">{{ number_format($totalJalan, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs {{ $totalJalan > 0 ? 'text-amber-600' : 'text-sand-400' }}">di perjalanan dari Diferd</p>
                </div>
                <div class="p-5">
                    <p class="text-sm text-sand-500">Terjual belum cair</p>
                    <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ number_format($totalBelumCair, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-sand-400">sudah keluar ke pembeli, uang belum masuk</p>
                </div>
            </div>

            @if ($jalanRows->isNotEmpty())
                <div class="overflow-x-auto border-t border-sand-200">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Artikel</th>
                                <th class="px-5 py-3 font-semibold text-center">Menunggu kirim</th>
                                <th class="px-5 py-3 font-semibold text-center">Reject</th>
                                <th class="px-5 py-3 font-semibold text-center">Di jalan</th>
                                <th class="px-5 py-3 font-semibold text-center">Belum cair</th>
                                <th class="px-5 py-3 font-semibold text-center">Siap dijual</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($jalanRows as $r)
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3">
                                        <div class="font-medium text-sand-800">{{ $r['product']->nama_artikel }}</div>
                                        <div class="text-xs text-sand-400">{{ $r['kategori'] }}</div>
                                    </td>
                                    <td class="px-5 py-3 text-center tnum {{ $r['di_vendor'] > 0 ? 'text-sand-800' : 'text-sand-300' }}">{{ $r['di_vendor'] ?: '—' }}</td>
                                    <td class="px-5 py-3 text-center tnum {{ $r['reject'] > 0 ? 'font-medium text-red-700' : 'text-sand-300' }}">{{ $r['reject'] ?: '—' }}</td>
                                    <td class="px-5 py-3 text-center tnum {{ $r['di_jalan'] > 0 ? 'font-medium text-amber-700' : 'text-sand-300' }}">{{ $r['di_jalan'] ?: '—' }}</td>
                                    <td class="px-5 py-3 text-center tnum {{ $r['belum_cair'] > 0 ? 'text-sand-800' : 'text-sand-300' }}">{{ $r['belum_cair'] ?: '—' }}</td>
                                    <td class="px-5 py-3 text-center tnum font-semibold text-brand-700">{{ $r['sisa'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="px-5 py-6 text-center text-sm text-sand-400 border-t border-sand-200">Tidak ada stok di jalan — semua barang produksi sudah diterima dan seluruh penjualan sudah cair.</p>
            @endif
        </div>

        {{-- Per kategori --}}
        @if ($byKategori->isNotEmpty())
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-sand-200"><h2 class="text-sm font-semibold text-sand-800">Stok per kategori</h2></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Kategori</th>
                                <th class="px-5 py-3 font-semibold text-center">Artikel</th>
                                <th class="px-5 py-3 font-semibold text-center">Diproduksi</th>
                                <th class="px-5 py-3 font-semibold text-center">Terjual</th>
                                <th class="px-5 py-3 font-semibold text-center">Sisa stok</th>
                                <th class="px-5 py-3 font-semibold text-right">Nilai stok</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($byKategori as $kat => $k)
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3 font-medium text-sand-800">{{ $kat }}</td>
                                    <td class="px-5 py-3 text-center tnum text-sand-600">{{ $k['artikel'] }}</td>
                                    <td class="px-5 py-3 text-center tnum text-sand-600">{{ number_format($k['diproduksi'], 0, ',', '.') }}</td>
                                    <td class="px-5 py-3 text-center tnum text-sand-600">{{ number_format($k['terjual'], 0, ',', '.') }}</td>
                                    <td class="px-5 py-3 text-center tnum font-semibold text-sand-900">{{ number_format($k['sisa'], 0, ',', '.') }}</td>
                                    <td class="px-5 py-3 text-right tnum text-sand-800">{{ $fmt($k['nilai']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-sand-200 bg-sand-50 font-semibold text-sand-900">
                                <td class="px-5 py-3" colspan="4">TOTAL</td>
                                <td class="px-5 py-3 text-center tnum">{{ number_format($totalSisa, 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-right tnum">{{ $fmt($totalNilai) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        @if (($reorderCount ?? 0) > 0)
            <div class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                <svg class="h-5 w-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                <p class="text-sm text-amber-800"><span class="font-semibold">{{ $reorderCount }} produk</span> laris &amp; stoknya menipis — disarankan produksi ulang.</p>
            </div>
        @endif
        @if (($nullBatchCount ?? 0) > 0 && $canRec)
            <div class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                <svg class="h-5 w-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                <p class="text-sm text-amber-800"><span class="font-semibold">{{ $nullBatchCount }} unit</span> terjual belum tertaut batch produksi.</p>
                <a href="{{ route('stok.reconcile') }}" class="ms-auto text-sm font-medium text-amber-800 hover:underline whitespace-nowrap">Rekonsiliasi →</a>
            </div>
        @endif

        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            @if (empty($rows))
                <div class="p-12 text-center text-sand-500">Belum ada produk.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Artikel</th>
                                <th class="px-5 py-3 font-semibold">Brand</th>
                                @foreach ($ukurans as $u)
                                    <th class="px-4 py-3 font-semibold text-center">{{ $u->value }}</th>
                                @endforeach
                                <th class="px-5 py-3 font-semibold text-center">Sisa total</th>
                                <th class="px-5 py-3 font-semibold">Sell-through</th>
                                <th class="px-5 py-3 font-semibold text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($rows as $row)
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3.5 font-medium text-sand-900">{{ $row['product']->nama_artikel }}</td>
                                    <td class="px-5 py-3.5 text-sand-600">{{ $row['product']->brand->nama }}</td>
                                    @foreach ($ukurans as $u)
                                        @php $cell = $row['sizes'][$u->value]; $sisa = $cell['sisa']; @endphp
                                        <td class="px-4 py-3.5 text-center">
                                            <div class="tnum font-medium {{ $sisa <= 0 ? 'text-sand-300' : ($sisa <= 5 ? 'text-red-600' : 'text-sand-800') }}">{{ $sisa }}</div>
                                            @if ($cell['produced'] > 0)
                                                <div class="text-[10px] text-sand-400 tnum">dari {{ $cell['produced'] }}</div>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="px-5 py-3.5 text-center tnum font-semibold {{ $row['sisa'] <= 0 ? 'text-sand-300' : 'text-sand-900' }}">{{ number_format($row['sisa'], 0, ',', '.') }}</td>
                                    <td class="px-5 py-3.5">
                                        @if ($row['produced'] > 0)
                                            <div class="flex items-center gap-2">
                                                <div class="w-24 h-1.5 rounded-full bg-sand-100 overflow-hidden">
                                                    <div class="h-full rounded-full {{ $row['sell_through'] >= 60 ? 'bg-brand-500' : 'bg-sand-400' }}" style="width: {{ min(100, $row['sell_through']) }}%"></div>
                                                </div>
                                                <span class="text-xs tnum text-sand-500">{{ $row['sell_through'] }}%</span>
                                            </div>
                                            <div class="text-[10px] text-sand-400 tnum mt-0.5">{{ number_format($row['sold'], 0, ',', '.') }} terjual dari {{ number_format($row['produced'], 0, ',', '.') }}</div>
                                        @else
                                            <span class="text-xs text-sand-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $toneBadge[$row['status']['tone']] }}">{{ $row['status']['label'] }}</span>
                                        @if ($row['reorder'])
                                            <div class="mt-1 inline-flex items-center gap-1 text-[11px] font-medium text-amber-700" title="Stok tipis &amp; laris">
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/></svg>
                                                produksi ulang ~{{ number_format($row['saran_qty'], 0, ',', '.') }}
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <p class="mt-3 text-xs text-sand-400">Angka merah per ukuran = sisa ≤ 5. Sell-through = terjual ÷ diproduksi. Saran "produksi ulang" muncul bila stok ≤ 15 &amp; sell-through ≥ 60%.</p>
    </div>
</x-app-layout>

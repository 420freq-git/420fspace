<x-app-layout>
    @php
        $isAdmin = auth()->user()->isAdmin();
        $fmt = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
        $totalTm420 = $sales->sum(fn ($s) => $s->qty * ($s->harga_tm420 ?? 0));
    @endphp
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-sand-900">Penjualan</h1>
                <p class="text-xs text-sand-500">Catatan unit terjual (stok ditarik FIFO per batch).</p>
            </div>
            <a href="{{ route('sales.create') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-800 transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Catat penjualan
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Statistik --}}
        <div class="grid grid-cols-2 {{ $isAdmin ? 'lg:grid-cols-3' : 'lg:grid-cols-2' }} gap-5">
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Total unit terjual</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ number_format($totalQty, 0, ',', '.') }} pcs</p>
            </div>
            @if ($isAdmin)
                <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                    <p class="text-sm text-sand-500">Kewajiban ke Diferd</p>
                    <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ $fmt($totalDiferd) }}</p>
                </div>
                <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                    <p class="text-sm text-sand-500">Fee 420F</p>
                    <p class="mt-1 text-2xl font-semibold text-brand-700 tnum">{{ $fmt($totalFee) }}</p>
                </div>
            @else
                <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                    <p class="text-sm text-sand-500">Total tagihan ke 420F</p>
                    <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ $fmt($totalTm420) }}</p>
                </div>
            @endif
        </div>

        {{-- Filter --}}
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-sand-600">Marketplace</label>
                <select name="marketplace" class="mt-1 rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                    <option value="">Semua</option>
                    @foreach ($marketplaces as $mp)
                        <option value="{{ $mp->value }}" @selected(request('marketplace') === $mp->value)>{{ $mp->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-sand-600">Dari</label>
                <input type="date" name="dari" value="{{ request('dari') }}" class="mt-1 rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
            </div>
            <div>
                <label class="block text-xs font-medium text-sand-600">Sampai</label>
                <input type="date" name="sampai" value="{{ request('sampai') }}" class="mt-1 rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
            </div>
            <button type="submit" class="rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Filter</button>
            @if (request()->hasAny(['marketplace', 'dari', 'sampai']))
                <a href="{{ route('sales.index') }}" class="px-2 py-2 text-sm text-sand-500 hover:text-sand-800">Reset</a>
            @endif
        </form>

        {{-- Tabel --}}
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            @if ($sales->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-sand-500">Belum ada penjualan.</p>
                    <a href="{{ route('sales.create') }}" class="mt-3 inline-block text-sm font-medium text-brand-700 hover:underline">Catat penjualan pertama</a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Tanggal</th>
                                <th class="px-5 py-3 font-semibold">Artikel</th>
                                <th class="px-5 py-3 font-semibold text-center">UK</th>
                                <th class="px-5 py-3 font-semibold text-center">Qty</th>
                                <th class="px-5 py-3 font-semibold">Marketplace</th>
                                <th class="px-5 py-3 font-semibold">No. pesanan</th>
                                <th class="px-5 py-3 font-semibold">Batch</th>
                                @if ($isAdmin)
                                    <th class="px-5 py-3 font-semibold text-right">Nilai Diferd</th>
                                    <th class="px-5 py-3 font-semibold text-right">Fee 420F</th>
                                @else
                                    <th class="px-5 py-3 font-semibold text-right">Nilai TM420</th>
                                @endif
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($sales as $sale)
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3 text-sand-600 tnum">{{ $sale->tanggal_terjual->format('d/m/Y') }}</td>
                                    <td class="px-5 py-3 font-medium text-sand-900">{{ $sale->product->nama_artikel }}</td>
                                    <td class="px-5 py-3 text-center text-sand-700">{{ $sale->ukuran->value }}</td>
                                    <td class="px-5 py-3 text-center tnum text-sand-700">{{ $sale->qty }}</td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $sale->marketplace->badgeClasses() }}">{{ $sale->marketplace->label() }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-sand-500 tnum">{{ $sale->nomor_pesanan ?? '—' }}</td>
                                    <td class="px-5 py-3 text-sand-500 tnum">{{ $sale->batch?->nomor_batch ?? '—' }}</td>
                                    @if ($isAdmin)
                                        <td class="px-5 py-3 text-right tnum text-sand-800">{{ $fmt($sale->nilai_diferd) }}</td>
                                        <td class="px-5 py-3 text-right tnum font-medium text-brand-700">{{ $sale->fee_420f !== null ? $fmt($sale->fee_420f) : '—' }}</td>
                                    @else
                                        <td class="px-5 py-3 text-right tnum text-sand-800">{{ $sale->harga_tm420 !== null ? $fmt($sale->qty * $sale->harga_tm420) : '—' }}</td>
                                    @endif
                                    <td class="px-5 py-3 text-right">
                                        <form method="POST" action="{{ route('sales.destroy', $sale) }}" onsubmit="return confirm('Hapus penjualan ini? Stok akan dikembalikan.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-md p-1.5 text-sand-500 hover:bg-red-50 hover:text-red-700" title="Hapus">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    @php $canManage = auth()->user()->isAdmin() || auth()->user()->role === \App\Enums\Role::Diferd; @endphp
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-lg font-semibold text-sand-900">Pengiriman / Surat Jalan</h1>
                <p class="text-xs text-sand-500">Barang produksi dikirim Diferd → gudang 420F.</p>
            </div>
            @if ($canManage)
                <a href="{{ route('pengiriman.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Buat surat jalan
                </a>
            @endif
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            @if ($pengiriman->isEmpty())
                <div class="p-12 text-center text-sand-500">Belum ada surat jalan.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Nomor SJ</th>
                                <th class="px-5 py-3 font-semibold">Batch</th>
                                <th class="px-5 py-3 font-semibold">Tgl kirim</th>
                                <th class="px-5 py-3 font-semibold text-center">Qty</th>
                                <th class="px-5 py-3 font-semibold">Ekspedisi / resi</th>
                                <th class="px-5 py-3 font-semibold text-center">Status</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($pengiriman as $sj)
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3.5"><a href="{{ route('pengiriman.show', $sj) }}" class="font-medium text-sand-900 hover:text-brand-700 tnum">{{ $sj->nomor_sj }}</a></td>
                                    <td class="px-5 py-3.5 text-sand-600 tnum">{{ $sj->batch->nomor_batch }}<span class="text-sand-400"> · {{ $sj->batch->brand->nama }}</span></td>
                                    <td class="px-5 py-3.5 text-sand-600 tnum">{{ $sj->tanggal_kirim->format('d/m/Y') }}</td>
                                    <td class="px-5 py-3.5 text-center tnum text-sand-700">{{ number_format($sj->total_qty, 0, ',', '.') }}</td>
                                    <td class="px-5 py-3.5 text-sand-500">{{ $sj->ekspedisi ?? '—' }}<span class="text-sand-400 tnum">{{ $sj->resi ? ' · '.$sj->resi : '' }}</span></td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $sj->statusBadge() }}">{{ $sj->isDiterima() ? 'Diterima' : 'Dikirim' }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right"><a href="{{ route('pengiriman.show', $sj) }}" class="text-sm font-medium text-brand-700 hover:underline">Detail</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

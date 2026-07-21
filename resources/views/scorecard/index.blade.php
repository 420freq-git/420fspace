<x-app-layout>
    @php $d = fn ($detik) => \App\Services\TahapTimelineService::durasi((int) $detik); @endphp
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-semibold text-sand-900">Scorecard vendor — Diferd</h1>
            <p class="text-xs text-sand-500">Kinerja produksi all-time: mutu, kecepatan, ketepatan deadline.</p>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- KPI utama --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="rounded-xl border {{ $rejectRate > 5 ? 'border-red-200 bg-red-50' : 'border-sand-200 bg-white' }} shadow-sm p-5">
                <p class="text-sm {{ $rejectRate > 5 ? 'text-red-700' : 'text-sand-500' }}">Tingkat reject</p>
                <p class="mt-1 text-2xl font-semibold {{ $rejectRate > 5 ? 'text-red-800' : 'text-sand-900' }} tnum">{{ $rejectRate }}%</p>
                <p class="mt-1 text-xs text-sand-400">{{ number_format($totReject, 0, ',', '.') }} dari {{ number_format($totProduksi, 0, ',', '.') }} pcs</p>
            </div>
            <div class="rounded-xl border {{ $kurangRate > 5 ? 'border-amber-200 bg-amber-50' : 'border-sand-200 bg-white' }} shadow-sm p-5">
                <p class="text-sm {{ $kurangRate > 5 ? 'text-amber-700' : 'text-sand-500' }}">Kurang / cacat saat terima</p>
                <p class="mt-1 text-2xl font-semibold {{ $kurangRate > 5 ? 'text-amber-800' : 'text-sand-900' }} tnum">{{ $kurangRate }}%</p>
                <p class="mt-1 text-xs text-sand-400">{{ number_format($totKurang, 0, ',', '.') }} dari {{ number_format($totDikirim, 0, ',', '.') }} dikirim</p>
            </div>
            <div class="rounded-xl border border-brand-200 bg-brand-50 shadow-sm p-5">
                <p class="text-sm text-brand-700">Bebas cacat</p>
                <p class="mt-1 text-2xl font-semibold text-brand-800 tnum">{{ $lolosRate }}%</p>
                <p class="mt-1 text-xs text-brand-600">produksi tanpa reject/kurang</p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Ketepatan deadline</p>
                @if ($onTimeRate !== null)
                    <p class="mt-1 text-2xl font-semibold {{ $onTimeRate >= 80 ? 'text-brand-700' : 'text-amber-700' }} tnum">{{ $onTimeRate }}%</p>
                    <p class="mt-1 text-xs text-sand-400">{{ $onTime }} tepat · {{ $late }} telat (dari {{ $adaDeadline }})</p>
                @else
                    <p class="mt-1 text-2xl font-semibold text-sand-400">—</p>
                    <p class="mt-1 text-xs text-sand-400">belum ada PO selesai berdeadline</p>
                @endif
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            {{-- Durasi rata-rata tiap tahap --}}
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-sand-200">
                    <h2 class="text-sm font-semibold text-sand-800">Rata-rata durasi tiap tahap</h2>
                    <p class="text-xs text-sand-400">Dari audit log perpindahan tahap. Batang = relatif terhadap tahap terlama.</p>
                </div>
                @if (empty($tahapRata))
                    <div class="p-8 text-center text-sm text-sand-400">Belum ada riwayat tahap.</div>
                @else
                    <div class="p-5 space-y-3">
                        @foreach ($tahapRata as $t)
                            <div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $t['tahap']->badgeClasses() }}">{{ $t['tahap']->label() }}</span>
                                    <span class="tnum text-sand-700">{{ $d($t['rata_detik']) }} <span class="text-xs text-sand-400">· {{ $t['n'] }}×</span></span>
                                </div>
                                <div class="mt-1 h-1.5 w-full rounded-full bg-sand-100 overflow-hidden">
                                    <div class="h-full rounded-full bg-brand-500" style="width: {{ max(2, round($t['rata_detik'] / $terlamaDetik * 100)) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Produk paling banyak cacat --}}
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-sand-200">
                    <h2 class="text-sm font-semibold text-sand-800">Produk paling banyak cacat</h2>
                    <p class="text-xs text-sand-400">Reject + kurang/cacat, terbanyak di atas.</p>
                </div>
                @if ($rankCacat->isEmpty())
                    <div class="p-8 text-center text-sm text-sand-400">Tidak ada cacat tercatat. 🎉</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                    <th class="px-5 py-3 font-semibold">Produk</th>
                                    <th class="px-5 py-3 font-semibold text-center">Reject</th>
                                    <th class="px-5 py-3 font-semibold text-center">Kurang</th>
                                    <th class="px-5 py-3 font-semibold text-center">% cacat</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sand-100">
                                @foreach ($rankCacat as $p)
                                    <tr class="hover:bg-sand-50/50">
                                        <td class="px-5 py-3 text-sand-800">{{ $p['nama'] }}</td>
                                        <td class="px-5 py-3 text-center tnum {{ $p['reject'] > 0 ? 'text-red-700 font-medium' : 'text-sand-300' }}">{{ $p['reject'] ?: '—' }}</td>
                                        <td class="px-5 py-3 text-center tnum {{ $p['kurang'] > 0 ? 'text-amber-700 font-medium' : 'text-sand-300' }}">{{ $p['kurang'] ?: '—' }}</td>
                                        <td class="px-5 py-3 text-center tnum text-sand-700">{{ $p['produksi'] > 0 ? round($p['cacat'] / $p['produksi'] * 100, 1) : 0 }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <p class="text-xs text-sand-400">Semua kerugian mutu (reject &amp; kurang/cacat) ditanggung vendor — lihat rinciannya di Laporan Kerugian. Scorecard ini alat untuk membahas perbaikan dengan Diferd.</p>
    </div>
</x-app-layout>

<x-app-layout>
    @php $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-sand-900">Saldo 420F</h1>
                <p class="text-xs text-sand-500">Buku kas — mutasi uang masuk &amp; keluar dengan saldo berjalan.</p>
            </div>
            <a href="{{ route('cashflow.index') }}" class="inline-flex items-center rounded-lg border border-sand-300 bg-white px-3 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Ringkasan Cashflow</a>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Kartu ringkas --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="rounded-xl border {{ $saldo >= 0 ? 'border-brand-200 bg-brand-50' : 'border-red-200 bg-red-50' }} shadow-sm p-5">
                <p class="text-sm {{ $saldo >= 0 ? 'text-brand-700' : 'text-red-700' }}">Saldo kas 420F</p>
                <p class="mt-1 text-2xl font-semibold {{ $saldo >= 0 ? 'text-brand-700' : 'text-red-700' }} tnum">{{ $fmt($saldo) }}</p>
                <p class="mt-1 text-xs text-sand-400">uang yang dipegang saat ini</p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Total masuk</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ $fmt($totalMasuk) }}</p>
                <p class="mt-1 text-xs text-sand-400">transfer dari brand</p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Total keluar</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ $fmt($totalKeluar) }}</p>
                <p class="mt-1 text-xs text-sand-400">dibayar ke Diferd</p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Fee 420F (margin)</p>
                <p class="mt-1 text-2xl font-semibold text-brand-700 tnum">{{ $fmt($fee) }}</p>
                <p class="mt-1 text-xs text-sand-400">markup pesanan lunas</p>
            </div>
        </div>

        @if ($depositMengendap > 0)
            <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                <span class="font-medium">Catatan:</span> ada <span class="font-semibold tnum">{{ $fmt($depositMengendap) }}</span>
                modal (deposit) mengendap di Diferd — <span class="font-medium">di luar kas 420F</span> (TM menalangi langsung), jadi tidak masuk saldo di atas.
            </div>
        @endif

        {{-- Buku kas --}}
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-sand-200">
                <h2 class="text-sm font-semibold text-sand-800">Mutasi kas</h2>
                <p class="text-xs text-sand-400">Terbaru di atas. Saldo dihitung berjalan dari mutasi terlama.</p>
            </div>
            @if ($rows->isEmpty())
                <div class="p-12 text-center text-sand-500">Belum ada mutasi kas.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Tanggal</th>
                                <th class="px-5 py-3 font-semibold">Keterangan</th>
                                <th class="px-5 py-3 font-semibold text-right">Masuk</th>
                                <th class="px-5 py-3 font-semibold text-right">Keluar</th>
                                <th class="px-5 py-3 font-semibold text-right">Saldo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($rows as $r)
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3 text-sand-600 tnum whitespace-nowrap">{{ $r['tanggal']?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex h-1.5 w-1.5 rounded-full {{ $r['arah'] === 'masuk' ? 'bg-brand-500' : 'bg-amber-500' }}"></span>
                                            <span class="text-sand-800">{{ $r['label'] }}</span>
                                        </div>
                                        @if ($r['ket'])<div class="mt-0.5 pl-3.5 text-xs text-sand-400">{{ $r['ket'] }}</div>@endif
                                    </td>
                                    <td class="px-5 py-3 text-right tnum {{ $r['arah'] === 'masuk' ? 'text-brand-700 font-medium' : 'text-sand-300' }}">{{ $r['arah'] === 'masuk' ? $fmt($r['jumlah']) : '—' }}</td>
                                    <td class="px-5 py-3 text-right tnum {{ $r['arah'] === 'keluar' ? 'text-amber-700 font-medium' : 'text-sand-300' }}">{{ $r['arah'] === 'keluar' ? $fmt($r['jumlah']) : '—' }}</td>
                                    <td class="px-5 py-3 text-right tnum font-semibold {{ $r['saldo'] >= 0 ? 'text-sand-900' : 'text-red-700' }}">{{ $fmt($r['saldo']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

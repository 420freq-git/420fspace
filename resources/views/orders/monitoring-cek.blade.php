<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('orders.index') }}" class="text-xs text-brand-700 hover:underline">&larr; Kembali ke Kelola Pesanan</a>
            <h1 class="text-lg font-semibold text-sand-900">Monitoring Pesanan Perlu Dicek</h1>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <p class="text-sm text-sand-500 -mt-2">
            Pesanan marketplace yang <span class="font-medium text-sand-700">belum cair</span> (terkirim / nyangkut / hilang / settlement telat).
            Isi keterangan hasil cek lalu klik <span class="font-medium">"Sudah Dicek"</span> — pesanan tetap di sini sampai dana cair.
            "Perlu dicek sekarang" (jatuh tempo cek ulang) hanya untuk notifikasi.
        </p>

        {{-- Stats --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            @foreach ([
                ['Total dalam monitoring', $stats['total'], 'text-sand-900', false],
                ['Perlu dicek sekarang', $stats['perlu_sekarang'], 'text-amber-700', true],
                ['Belum pernah dicek', $stats['belum_pernah'], 'text-sand-900', false],
                ['TikTok', $stats['tiktok'], 'text-sand-900', false],
                ['Shopee', $stats['shopee'], 'text-sand-900', false],
            ] as [$label, $val, $color, $notif])
                <div class="rounded-xl border {{ $notif ? 'border-amber-200 bg-amber-50' : 'border-sand-200 bg-white' }} shadow-sm p-4">
                    <p class="text-xs text-sand-500">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold {{ $color }} tnum">{{ $val }}</p>
                </div>
            @endforeach
        </div>

        {{-- Tabel --}}
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            @if ($orders->isEmpty())
                <div class="p-12 text-center text-sand-500">Tidak ada pesanan yang perlu dicek. 🎉</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Pesanan</th>
                                <th class="px-5 py-3 font-semibold">Umur / Tgl</th>
                                <th class="px-5 py-3 font-semibold">Status cek</th>
                                <th class="px-5 py-3 font-semibold">Keterangan &amp; aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($orders as $order)
                                @php
                                    $umur = $order->umur_hari;
                                    $perlu = $order->jumlah_cek === 0 || ($order->tgl_cek_terakhir && $order->tgl_cek_terakhir->lte($batasCek));
                                @endphp
                                <tr class="{{ $perlu ? 'bg-red-50/40' : '' }} hover:bg-sand-50/50 align-top">
                                    <td class="px-5 py-3.5">
                                        <div class="font-medium text-sand-900 tnum">{{ $order->nomor_pesanan }}</div>
                                        <div class="text-xs text-sand-400">{{ $order->marketplace->label() }} &middot; <span class="tnum">{{ $order->resi ?? '—' }}</span></div>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="font-medium {{ $umur > 30 ? 'text-red-700' : 'text-sand-700' }} tnum">{{ $umur }} hari</div>
                                        <div class="text-xs text-sand-400 tnum">{{ $order->tanggal_pesanan->format('d/m/Y') }}</div>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        @if ($order->jumlah_cek === 0)
                                            <span class="text-xs font-medium text-sand-500">Belum dicek</span>
                                        @else
                                            <div class="text-xs font-medium text-brand-700">&#10003; {{ $order->jumlah_cek }}&times; &middot; {{ $order->tgl_cek_terakhir?->format('d/m/Y') }}</div>
                                            @if ($perlu)<div class="text-xs text-amber-600">perlu cek ulang</div>@endif
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <form method="POST" action="{{ route('monitoring.dicek', $order) }}" class="flex flex-1 items-center gap-2">
                                                @csrf
                                                <input type="text" name="keterangan" value="{{ $order->keterangan }}" placeholder="cth: masih ke pembeli / balik ke penjual"
                                                       class="flex-1 min-w-[180px] rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                                                <button type="submit" class="shrink-0 rounded-lg bg-brand-700 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-800" title="Sudah dicek">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('monitoring.tolak', $order) }}" onsubmit="return confirm('Tandai pesanan {{ $order->nomor_pesanan }} sebagai retur (paket ditolak pembeli)?');">
                                                @csrf
                                                <button type="submit" class="shrink-0 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700 hover:bg-amber-100" title="Paket ditolak → retur">Ditolak</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <p class="text-xs text-sand-400">Baris merah = perlu dicek sekarang (belum pernah dicek, atau &gt; {{ 2 }} hari sejak cek terakhir). Pesanan hilang dari sini otomatis saat statusnya jadi Lunas (cair).</p>
    </div>
</x-app-layout>

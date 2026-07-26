<x-app-layout>
    @php
        $toneMap = [
            'danger' => ['icon' => 'bg-red-50 text-red-700', 'val' => 'text-red-700'],
            'warn' => ['icon' => 'bg-amber-50 text-amber-700', 'val' => 'text-amber-700'],
            'brand' => ['icon' => 'bg-brand-50 text-brand-700', 'val' => 'text-brand-700'],
            'default' => ['icon' => 'bg-brand-50 text-brand-700', 'val' => 'text-sand-900'],
        ];
        $deadlineChip = function ($row) {
            if ($row['selesai'] ?? false) return ['selesai', 'text-brand-700'];
            $s = $row['sisaHari'];
            if ($s === null) return ['—', 'text-sand-400'];
            if ($s < 0) return ['telat '.abs($s).' hari', 'text-red-700 font-semibold'];
            if ($s === 0) return ['hari ini', 'text-amber-700 font-semibold'];
            return [$s.' hari lagi', ($row['mepet'] ?? false) ? 'text-amber-700 font-medium' : 'text-sand-500'];
        };
    @endphp

    <x-slot name="header">
        <h1 class="text-lg font-semibold text-sand-900">Dashboard</h1>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Welcome / identity --}}
        <div class="relative overflow-hidden rounded-xl border border-sand-200 bg-white shadow-sm">
            <div class="absolute inset-y-0 left-0 w-1.5 bg-brand-700"></div>
            <div class="p-6 sm:p-8 pl-8 sm:pl-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <p class="text-sm text-sand-500">Selamat datang,</p>
                    <h2 class="text-2xl font-semibold text-sand-900">{{ auth()->user()->name }}</h2>
                    <p class="mt-1 text-sm text-sand-500">Sistem produksi &amp; settlement 420Frequency</p>
                </div>
                <span class="self-start inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ auth()->user()->role->badgeClasses() }}">
                    {{ auth()->user()->role->label() }}
                </span>
            </div>
        </div>

        {{-- Metrik ringkas (menyesuaikan role) --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach ($cards as $card)
                @php $tone = $toneMap[$card['tone'] ?? 'default']; @endphp
                <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                    <div class="flex items-start justify-between">
                        <p class="text-sm text-sand-500">{{ $card['label'] }}</p>
                        <span class="inline-grid place-items-center h-9 w-9 rounded-lg {{ $tone['icon'] }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}"/></svg>
                        </span>
                    </div>
                    <p class="mt-3 text-2xl font-semibold {{ $tone['val'] }} tnum">{{ $card['value'] }}</p>
                    <p class="mt-1 text-xs text-sand-400">{{ $card['note'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Info: modal dari TM langsung ke Diferd (admin) --}}
        @if ($role === \App\Enums\Role::Admin && ($money['modal'] ?? 0) > 0)
            @php $rp = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp
            <div class="flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4">
                <svg class="h-5 w-5 shrink-0 text-blue-500 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                <p class="text-sm text-blue-800">
                    Ada <span class="font-medium">{{ $rp($money['modal']) }}</span> modal produksi yang <span class="font-medium">TM salurkan langsung ke Diferd</span> (kasus khusus).
                    Ini <span class="font-medium">di luar kas 420F</span> — tidak memengaruhi posisi kas, bukan hutang, dan direkonsiliasi saat batch selesai.
                </p>
            </div>
        @endif

        {{-- Grafik penjualan (monitoring) --}}
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                <div>
                    <h3 class="text-sm font-semibold text-sand-800">Grafik penjualan</h3>
                    <p class="text-xs text-sand-400">Unit terjual (lunas) 6 bulan terakhir{{ $chartShowNilai ? '' : ' · monitor volume' }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    @foreach ($chartSeries as $s)
                        <div class="flex items-center gap-2">
                            <span class="inline-block h-2.5 w-2.5 rounded-sm {{ $s['color'] }}"></span>
                            <span class="text-xs text-sand-600">{{ $s['brand'] }}</span>
                            <span class="text-xs font-semibold text-sand-800 tnum">{{ number_format($s['total'], 0, ',', '.') }}</span>
                            @if ($chartShowNilai && $s['nilai'] > 0)
                                <span class="text-xs text-sand-400 tnum">· {{ $rupiah($s['nilai']) }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            @php $chartTotal = collect($chartSeries)->sum('total'); @endphp
            @if ($chartTotal === 0)
                <div class="py-10 text-center text-sm text-sand-400">Belum ada penjualan lunas pada 6 bulan terakhir.</div>
            @else
                <div class="flex items-end gap-2 sm:gap-4 h-44">
                    @foreach ($chartLabels as $mi => $label)
                        <div class="flex-1 flex flex-col items-center min-w-0">
                            <div class="w-full flex items-end justify-center gap-1 h-36">
                                @foreach ($chartSeries as $s)
                                    @php $v = $s['data'][$mi]; $h = round($v / $chartMax * 100); @endphp
                                    <div class="w-3.5 sm:w-6 rounded-t {{ $s['color'] }}"
                                         style="height: {{ $v > 0 ? max(4, $h) : 0 }}%"
                                         title="{{ $s['brand'] }} · {{ $label }}: {{ $v }} pcs"></div>
                                @endforeach
                            </div>
                            <span class="mt-1.5 text-xs text-sand-400">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ============ VENDOR (Diferd) ============ --}}
        @if ($role === \App\Enums\Role::Diferd)
            <div class="grid lg:grid-cols-3 gap-6">
                {{-- Antrian produksi --}}
                <div class="lg:col-span-2 rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-sand-200 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-sand-800">Antrian &amp; deadline produksi</h3>
                        <a href="{{ route('monitoring-produksi.index') }}" class="text-xs font-medium text-brand-700 hover:text-brand-800">Buka monitoring →</a>
                    </div>
                    @forelse ($vendorQueue as $r)
                        @php [$dl, $dlClass] = $deadlineChip($r); $po = $r['po']; @endphp
                        <div class="px-5 py-3.5 border-b border-sand-100 last:border-0 flex items-center gap-4 {{ $r['telat'] ? 'bg-red-50/40' : '' }}">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-sand-800 truncate">{{ $po->product->nama_artikel }}</p>
                                <p class="text-xs text-sand-400 tnum">{{ $po->nomor_po }} · {{ $r['batch']->nomor_batch }}</p>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $po->tahap->badgeClasses() }}">{{ $po->tahap->step() }}. {{ $po->tahap->label() }}</span>
                            @if ($r['mandek'])<span class="text-xs text-amber-700 font-medium">mandek {{ $r['hari'] }}h</span>@endif
                            <span class="w-24 text-right text-xs {{ $dlClass }}">{{ $dl }}</span>
                        </div>
                    @empty
                        <div class="p-10 text-center text-sand-500 text-sm">Tidak ada PO yang perlu dikerjakan. 🎉</div>
                    @endforelse
                </div>

                {{-- Ringkasan settlement --}}
                <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-sand-200 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-sand-800">Ringkasan pembayaran</h3>
                        <a href="{{ route('settlement.index') }}" class="text-xs font-medium text-brand-700 hover:text-brand-800">Detail →</a>
                    </div>
                    <dl class="p-5 space-y-3 text-sm">
                        <div class="flex justify-between"><dt class="text-sand-600">Hak dari barang terjual</dt><dd class="tnum font-medium text-sand-900">{{ $rupiah($money['hak']) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-sand-500">Sudah dibayar</dt><dd class="tnum text-brand-700">{{ $rupiah($money['dibayar']) }}</dd></div>
                        <div class="flex justify-between border-t border-sand-200 pt-3">
                            <dt class="font-medium text-sand-800">{{ $money['sisa'] > 0 ? 'Akan diterima' : 'Hak lunas' }}</dt>
                            <dd class="tnum font-semibold {{ $money['sisa'] > 0 ? 'text-amber-700' : 'text-brand-700' }}">{{ $money['sisa'] > 0 ? $rupiah($money['sisa']) : '✓' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        @else
            {{-- ============ BRAND (TM420) & 420F ============ --}}
            <div class="grid lg:grid-cols-3 gap-6">
                {{-- Progres produksi --}}
                <div class="lg:col-span-2 rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-sand-200 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-sand-800">Progres produksi</h3>
                        <a href="{{ route('monitoring-produksi.index') }}" class="text-xs font-medium text-brand-700 hover:text-brand-800">Semua →</a>
                    </div>
                    @forelse ($batchRows as $r)
                        @php [$dl, $dlClass] = $deadlineChip($r); $b = $r['batch']; @endphp
                        <div class="px-5 py-4 border-b border-sand-100 last:border-0">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('batches.show', $b) }}" class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-sand-800 truncate hover:text-brand-700">{{ $b->nomor_batch }}
                                        <span class="text-sand-400 font-normal">· {{ $b->brand->nama }}</span></p>
                                </a>
                                @if ($r['catatanCount'] > 0)
                                    <span class="inline-flex items-center gap-1 text-xs text-amber-700" title="{{ $r['catatanCount'] }} catatan vendor">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.019z"/></svg>
                                        {{ $r['catatanCount'] }}
                                    </span>
                                @endif
                                <span class="w-24 text-right text-xs {{ $dlClass }}">{{ $dl }}</span>
                            </div>
                            <div class="mt-2 flex items-center gap-3">
                                <div class="flex-1 h-2 rounded-full bg-sand-100 overflow-hidden">
                                    <div class="h-full rounded-full {{ $r['telat'] ? 'bg-red-500' : 'bg-brand-500' }}" style="width: {{ $r['progress'] }}%"></div>
                                </div>
                                <span class="text-xs tnum text-sand-500 w-9 text-right">{{ $r['progress'] }}%</span>
                            </div>
                        </div>
                    @empty
                        <div class="p-10 text-center text-sand-500 text-sm">Belum ada batch aktif.</div>
                    @endforelse
                </div>

                <div class="space-y-6">
                    {{-- Perlu tindakan --}}
                    <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-sand-200"><h3 class="text-sm font-semibold text-sand-800">Perlu tindakan</h3></div>
                        <div class="divide-y divide-sand-100">
                            <a href="{{ route('monitoring.cek') }}" class="flex items-center justify-between px-5 py-3.5 hover:bg-sand-50/60">
                                <span class="text-sm text-sand-700">Pesanan perlu dicek</span>
                                <span class="inline-flex items-center gap-2">
                                    <span class="tnum text-sm font-semibold {{ $perluDicek > 0 ? 'text-amber-700' : 'text-sand-400' }}">{{ $perluDicek }}</span>
                                    <svg class="h-4 w-4 text-sand-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                </span>
                            </a>
                            <a href="{{ route('monitoring.kembali') }}" class="flex items-center justify-between px-5 py-3.5 hover:bg-sand-50/60">
                                <span class="text-sm text-sand-700">Barang kembali menunggu</span>
                                <span class="inline-flex items-center gap-2">
                                    <span class="tnum text-sm font-semibold {{ $returPending > 0 ? 'text-red-700' : 'text-sand-400' }}">{{ $returPending }}</span>
                                    <svg class="h-4 w-4 text-sand-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                </span>
                            </a>
                        </div>
                    </div>

                    {{-- Stok menipis --}}
                    <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-sand-200 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-sand-800">Stok menipis</h3>
                            <a href="{{ route('stok.index') }}" class="text-xs font-medium text-brand-700 hover:text-brand-800">Stok →</a>
                        </div>
                        @forelse ($stokMenipis as $s)
                            <div class="flex items-center justify-between px-5 py-3 border-b border-sand-100 last:border-0">
                                <span class="text-sm text-sand-700 truncate pr-3">{{ $s['product']->nama_artikel }}</span>
                                <span class="tnum text-sm font-semibold {{ $s['sisa'] == 0 ? 'text-red-700' : 'text-amber-700' }}">{{ $s['sisa'] }} pcs</span>
                            </div>
                        @empty
                            <div class="px-5 py-8 text-center text-sm text-sand-400">Stok aman semua. 👍</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-semibold text-sand-900">Monitoring Produksi</h1>
            <p class="text-xs text-sand-500">Pantau progres tiap PO menuju deadline — dari belanja bahan sampai terkirim.</p>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Ringkasan --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Batch aktif</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ $stats['batchAktif'] }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 shadow-sm p-5">
                <p class="text-sm text-blue-700">PO dalam produksi</p>
                <p class="mt-1 text-2xl font-semibold text-blue-700 tnum">{{ $stats['poProduksi'] }}</p>
            </div>
            <div class="rounded-xl border border-brand-200 bg-brand-50 shadow-sm p-5">
                <p class="text-sm text-brand-700">PO siap kirim / terkirim</p>
                <p class="mt-1 text-2xl font-semibold text-brand-700 tnum">{{ $stats['poReady'] }}</p>
            </div>
            <div class="rounded-xl border {{ $stats['batchTelat'] > 0 ? 'border-red-200 bg-red-50' : 'border-sand-200 bg-white' }} shadow-sm p-5">
                <p class="text-sm {{ $stats['batchTelat'] > 0 ? 'text-red-700' : 'text-sand-500' }}">Batch telat deadline</p>
                <p class="mt-1 text-2xl font-semibold {{ $stats['batchTelat'] > 0 ? 'text-red-700' : 'text-sand-900' }} tnum">{{ $stats['batchTelat'] }}</p>
            </div>
        </div>

        {{-- Funnel tahap --}}
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400 mb-4">Sebaran PO per tahap</h2>
            <div class="space-y-2">
                @foreach ($funnel as $f)
                    <div class="flex items-center gap-3">
                        <span class="w-32 shrink-0 text-xs text-sand-600">{{ $f['tahap']->step() }}. {{ $f['tahap']->label() }}</span>
                        <div class="flex-1 h-5 rounded bg-sand-100 overflow-hidden">
                            <div class="h-full {{ $f['tahap']->barClass() }}" style="width: {{ round($f['count'] / $maxFunnel * 100) }}%"></div>
                        </div>
                        <span class="w-8 shrink-0 text-right text-xs tnum {{ $f['count'] > 0 ? 'text-sand-800 font-medium' : 'text-sand-300' }}">{{ $f['count'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Per batch --}}
        @forelse ($rows as $row)
            @php
                $batch = $row['batch'];
                $sisa = $row['sisaHari'];
            @endphp
            <div class="rounded-xl border {{ $row['telat'] ? 'border-red-200' : ($row['mepet'] ? 'border-amber-200' : 'border-sand-200') }} bg-white shadow-sm overflow-hidden">
                {{-- Header batch --}}
                <div class="px-5 py-4 border-b border-sand-200 flex flex-wrap items-center gap-x-6 gap-y-3">
                    <div class="min-w-0">
                        <a href="{{ route('batches.show', $batch) }}" class="font-semibold text-sand-900 hover:text-brand-700">{{ $batch->nomor_batch }}</a>
                        <p class="text-xs text-sand-500">{{ $batch->brand->nama }} · {{ $row['posTotal'] }} PO · {{ number_format($batch->total_qty, 0, ',', '.') }} pcs</p>
                    </div>

                    {{-- Deadline --}}
                    <div class="text-right ml-auto">
                        <p class="text-[11px] uppercase tracking-wide text-sand-400">Deadline produksi</p>
                        <p class="text-sm">
                            <span class="text-sand-700">{{ $row['deadlineProd']?->translatedFormat('d M Y') ?? '—' }}</span>
                            @if ($row['selesai'])
                                <span class="ml-1 text-xs font-medium text-brand-700">· selesai</span>
                            @elseif ($sisa === null)
                            @elseif ($sisa < 0)
                                <span class="ml-1 text-xs font-semibold text-red-700">· telat {{ abs($sisa) }} hari</span>
                            @elseif ($sisa === 0)
                                <span class="ml-1 text-xs font-semibold text-amber-700">· hari ini</span>
                            @else
                                <span class="ml-1 text-xs font-medium {{ $row['mepet'] ? 'text-amber-700' : 'text-sand-500' }}">· {{ $sisa }} hari lagi</span>
                            @endif
                        </p>
                    </div>

                    {{-- Progress keseluruhan --}}
                    <div class="w-40 shrink-0">
                        <div class="flex justify-between text-[11px] mb-1">
                            <span class="text-sand-400">Progress</span>
                            <span class="font-semibold text-sand-700 tnum">{{ $row['progress'] }}%</span>
                        </div>
                        <div class="h-2 rounded-full bg-sand-100 overflow-hidden">
                            <div class="h-full rounded-full {{ $row['selesai'] ? 'bg-brand-600' : ($row['telat'] ? 'bg-red-500' : 'bg-brand-500') }}" style="width: {{ $row['progress'] }}%"></div>
                        </div>
                    </div>
                </div>

                {{-- Daftar PO --}}
                <div class="divide-y divide-sand-100">
                    @foreach ($batch->purchaseOrders as $po)
                        @php
                            $tahap = $po->tahap;
                            $hari = $po->hari_di_tahap;
                            $mandek = ! $tahap->isReady() && $hari !== null && $hari >= 5;
                        @endphp
                        <div class="px-5 py-4 flex flex-wrap items-center gap-x-5 gap-y-3">
                            <div class="w-56 min-w-0">
                                <p class="text-sm font-medium text-sand-800 truncate">{{ $po->product->nama_artikel }}</p>
                                <p class="text-xs text-sand-400 tnum">{{ $po->nomor_po }}</p>
                            </div>

                            {{-- Stepper 11 tahap --}}
                            <div class="flex-1 min-w-[200px]">
                                <div class="flex gap-0.5" title="{{ $tahap->step() }}/11 — {{ $tahap->label() }}">
                                    @foreach (\App\Enums\TahapProduksi::cases() as $t)
                                        <div class="h-1.5 flex-1 rounded-full {{ $t->step() <= $tahap->step() ? $tahap->barClass() : 'bg-sand-100' }}"></div>
                                    @endforeach
                                </div>
                                <div class="mt-1.5 flex items-center gap-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $tahap->badgeClasses() }}">{{ $tahap->step() }}. {{ $tahap->label() }}</span>
                                    @if ($tahap->isDone())
                                        <span class="text-xs text-brand-600">✓ tuntas</span>
                                    @elseif ($hari !== null)
                                        <span class="text-xs {{ $mandek ? 'text-amber-700 font-medium' : 'text-sand-400' }}">
                                            {{ $hari === 0 ? 'update hari ini' : $hari.' hari di tahap ini' }}{{ $mandek ? ' · mandek?' : '' }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Aksi majukan tahap --}}
                            @if ($canUpdate && ! $tahap->isDone())
                                <form method="POST" action="{{ route('purchase-orders.status', [$batch, $po]) }}" class="shrink-0">
                                    @csrf @method('PATCH')
                                    <select name="tahap" onchange="this.form.submit()"
                                            class="rounded-lg border-sand-300 text-xs py-1.5 pr-8 focus:border-brand-600 focus:ring-brand-600">
                                        @foreach (\App\Enums\TahapProduksi::cases() as $t)
                                            <option value="{{ $t->value }}" @selected($po->tahap === $t)>{{ $t->step() }}. {{ $t->label() }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            @endif

                            {{-- Catatan vendor --}}
                            @if ($po->catatan_vendor)
                                <div class="w-full flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-100 px-3 py-2">
                                    <svg class="h-4 w-4 shrink-0 text-amber-500 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.019z"/></svg>
                                    <p class="text-xs text-amber-800 whitespace-pre-line"><span class="font-medium">Catatan vendor:</span> {{ $po->catatan_vendor }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-12 text-center text-sand-500">
                Belum ada batch aktif untuk dimonitor.
            </div>
        @endforelse
    </div>
</x-app-layout>

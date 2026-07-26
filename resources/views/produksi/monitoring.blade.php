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
                <p class="text-sm text-sand-500">Batch berjalan</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ $stats['batchBerjalan'] }}</p>
                <p class="text-[11px] text-sand-400">dari {{ $stats['batchAktif'] }} batch aktif</p>
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

        {{-- Batch BERJALAN (prioritas di atas) --}}
        @php $adaBerjalan = $rows->firstWhere('selesai', false); $adaSelesai = $rows->firstWhere('selesai', true); @endphp
        @forelse ($rows->where('selesai', false) as $row)
            @php $batch = $row['batch']; $sisa = $row['sisaHari']; @endphp
            <div class="rounded-xl border {{ $row['telat'] ? 'border-red-200' : ($row['mepet'] ? 'border-amber-200' : 'border-sand-200') }} bg-white shadow-sm overflow-hidden"
                 data-batch-selesai="{{ $batch->id }}" data-selesai-nilai="0">
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
                            @if ($sisa === null)
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
                            <span class="font-semibold text-sand-700 tnum" data-batch-progress-text="{{ $batch->id }}">{{ $row['progress'] }}%</span>
                        </div>
                        <div class="h-2 rounded-full bg-sand-100 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-300 {{ $row['telat'] ? 'bg-red-500' : 'bg-brand-500' }}"
                                 style="width: {{ $row['progress'] }}%" data-batch-progress="{{ $batch->id }}"></div>
                        </div>
                    </div>
                </div>

                @include('produksi._po_list', ['batch' => $batch, 'canUpdate' => $canUpdate])
            </div>
        @empty
            @unless ($adaSelesai)
                <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-12 text-center text-sand-500">
                    Belum ada batch aktif untuk dimonitor.
                </div>
            @else
                <div class="rounded-xl border border-brand-200 bg-brand-50 shadow-sm p-6 text-center text-brand-700">
                    Semua batch aktif sudah selesai produksi & pengiriman. 🎉
                </div>
            @endunless
        @endforelse

        {{-- Batch SELESAI (collapse — detail produk disembunyikan, cukup info final) --}}
        @if ($adaSelesai)
            <div class="pt-2">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400 mb-3">Batch selesai</h2>
                <div class="space-y-3">
                    @foreach ($rows->where('selesai', true) as $row)
                        @php $batch = $row['batch']; $f = $row['final']; @endphp
                        <div x-data="{ open: false }" class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden"
                             data-batch-selesai="{{ $batch->id }}" data-selesai-nilai="1">
                            <div class="px-5 py-4 flex flex-wrap items-center gap-x-5 gap-y-2">
                                <div class="min-w-0">
                                    <a href="{{ route('batches.show', $batch) }}" class="font-semibold text-sand-900 hover:text-brand-700">{{ $batch->nomor_batch }}</a>
                                    <p class="text-xs text-sand-500">{{ $batch->brand->nama }} · {{ $row['posTotal'] }} PO · {{ number_format($batch->total_qty, 0, ',', '.') }} pcs</p>
                                </div>

                                <div class="ml-auto flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-brand-100 text-brand-800">✓ Selesai</span>
                                    <span class="text-xs text-sand-500">{{ $f['durasi'] }}</span>
                                    @if ($f['lewatDeadline'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">lewat deadline</span>
                                    @elseif ($row['deadlineProd'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">tepat waktu</span>
                                    @endif
                                    @if ($f['rejectPcs'] > 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">{{ $f['rejectPcs'] }} pcs reject</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sand-100 text-sand-600">tanpa reject</span>
                                    @endif
                                    <button type="button" @click="open = !open"
                                            class="inline-flex items-center gap-1 rounded-lg border border-sand-300 bg-white px-3 py-1.5 text-xs font-medium text-sand-700 hover:bg-sand-50">
                                        <span x-text="open ? 'Sembunyikan' : 'Detail produk'"></span>
                                        <span x-text="open ? '▲' : '▼'" class="text-[10px]"></span>
                                    </button>
                                </div>
                            </div>

                            {{-- Info final + detail produk (toggle) --}}
                            <div x-show="open" x-cloak class="border-t border-sand-100">
                                <div class="px-5 py-3 bg-sand-50/60 grid sm:grid-cols-3 gap-3 text-xs">
                                    <div>
                                        <dt class="text-sand-400 uppercase tracking-wide text-[10px]">Selesai pada</dt>
                                        <dd class="text-sand-700">{{ $f['selesaiPada']?->translatedFormat('d M Y') ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sand-400 uppercase tracking-wide text-[10px]">Lama pengerjaan</dt>
                                        <dd class="text-sand-700">{{ $f['durasi'] }}{{ $f['lewatDeadline'] ? ' · lewat deadline' : '' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sand-400 uppercase tracking-wide text-[10px]">Reject / kurang</dt>
                                        <dd class="text-sand-700">
                                            @if ($f['rejectPcs'] > 0)
                                                {{ $f['rejectPcs'] }} pcs —
                                                @foreach ($f['rejectProduk'] as $nama => $pcs)<span class="text-amber-700">{{ $nama }} ({{ $pcs }})</span>@if (! $loop->last), @endif @endforeach
                                            @else
                                                tidak ada
                                            @endif
                                        </dd>
                                    </div>
                                </div>
                                @include('produksi._po_list', ['batch' => $batch, 'canUpdate' => $canUpdate])
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Ganti tahap ditangani tanpa reload di resources/js/app.js — tak perlu lagi menambal
         posisi scroll lewat sessionStorage. --}}
</x-app-layout>

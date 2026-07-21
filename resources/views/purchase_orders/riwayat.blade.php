<x-app-layout>
    @php
        $d = fn ($detik) => \App\Services\TahapTimelineService::durasi($detik);
        $terlama = collect($timeline['baris'])->max('detik') ?: 1;
    @endphp

    <x-slot name="header">
        <div class="flex items-center gap-2 min-w-0">
            <x-back-link :href="route('batches.show', $batch)" />
            <div class="min-w-0">
                <h1 class="text-lg font-semibold text-sand-900 truncate">Riwayat produksi · {{ $po->product->nama_artikel }}</h1>
                <p class="text-xs text-sand-500 tnum">{{ $po->nomor_po }} · batch {{ $batch->nomor_batch }}</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Ringkasan --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-5">
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Total waktu</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900">{{ $d($timeline['total_detik']) }}</p>
                <p class="mt-1 text-xs text-sand-400">sejak PO dibuat {{ $timeline['mulai']?->format('d/m/Y') }}</p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Tahap dilalui</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ count($timeline['baris']) }}</p>
                <p class="mt-1 text-xs text-sand-400">dari {{ count(\App\Enums\TahapProduksi::cases()) }} tahap</p>
            </div>
            <div class="rounded-xl border {{ $timeline['selesai'] ? 'border-brand-200 bg-brand-50' : 'border-amber-200 bg-amber-50' }} shadow-sm p-5">
                <p class="text-sm {{ $timeline['selesai'] ? 'text-brand-700' : 'text-amber-700' }}">Status</p>
                <p class="mt-1 text-lg font-semibold {{ $timeline['selesai'] ? 'text-brand-800' : 'text-amber-800' }}">
                    {{ $timeline['selesai'] ? 'Selesai' : $po->tahap->label() }}
                </p>
                @if ($timeline['selesai'] && $timeline['tuntas_pada'])
                    <p class="mt-1 text-xs text-brand-600">tuntas {{ $timeline['tuntas_pada']->format('d/m/Y H:i') }}</p>
                @elseif ($batch->deadline_produksi)
                    <p class="mt-1 text-xs text-amber-600">deadline {{ $batch->deadline_produksi->format('d/m/Y') }}</p>
                @endif
            </div>
        </div>

        {{-- Timeline per tahap --}}
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-sand-200">
                <h2 class="text-sm font-semibold text-sand-800">Durasi tiap tahap</h2>
                <p class="text-xs text-sand-400">Diolah dari audit log perpindahan tahap. Tahap yang dilewati tidak muncul.</p>
            </div>

            @if (empty($timeline['baris']))
                <div class="p-10 text-center text-sm text-sand-400">Belum ada riwayat tahap.</div>
            @else
                <ol class="divide-y divide-sand-100">
                    @foreach ($timeline['baris'] as $i => $b)
                        <li class="px-5 py-4">
                            <div class="flex items-start gap-4">
                                <div class="flex flex-col items-center pt-0.5">
                                    <span class="grid place-items-center h-7 w-7 rounded-full text-xs font-semibold tnum
                                                 {{ $b['berjalan'] ? 'bg-amber-100 text-amber-800 ring-2 ring-amber-300' : 'bg-brand-100 text-brand-800' }}">
                                        {{ $i + 1 }}
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $b['tahap']->badgeClasses() }}">{{ $b['tahap']->label() }}</span>
                                        @if ($b['berjalan'])
                                            <span class="text-xs font-medium text-amber-700">sedang berjalan</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-xs text-sand-500 tnum">
                                        {{ $b['mulai']->format('d/m/Y H:i') }}
                                        @if ($b['selesai']) &rarr; {{ $b['selesai']->format('d/m/Y H:i') }} @endif
                                        @if ($b['oleh']) <span class="text-sand-400">· dipindahkan {{ $b['oleh'] }}</span> @endif
                                    </p>
                                    <div class="mt-2 h-1.5 w-full rounded-full bg-sand-100 overflow-hidden">
                                        <div class="h-full rounded-full {{ $b['berjalan'] ? 'bg-amber-400' : 'bg-brand-500' }}"
                                             style="width: {{ max(2, round($b['detik'] / $terlama * 100)) }}%"></div>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-sm font-semibold text-sand-900">{{ $d($b['detik']) }}</p>
                                    <p class="text-xs text-sand-400">{{ $timeline['total_detik'] > 0 ? round($b['detik'] / $timeline['total_detik'] * 100) : 0 }}%</p>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </div>
</x-app-layout>

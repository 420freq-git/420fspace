<x-app-layout>
    @php
        $isAdmin = auth()->user()->isAdmin();
        $canStatus = $isAdmin || auth()->user()->role === \App\Enums\Role::Diferd;
        // TM420 menyusun daftar artikel selama batch belum disetujui — justru itu yang direview
        // 420F. Setelah disetujui, daftar dibekukan supaya persetujuannya berarti.
        $bolehKelolaPo = $isAdmin
            || (auth()->user()->role === \App\Enums\Role::Tm420 && $batch->status->belumDisetujui());
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 min-w-0">
                <x-back-link :href="route('batches.index')" />
                <div class="min-w-0">
                    <h1 class="text-lg font-semibold text-sand-900 truncate tnum">{{ $batch->nomor_batch }}</h1>
                    <p class="text-xs text-sand-500">{{ $batch->brand->nama }} &middot; vendor {{ $batch->vendor }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('batches.pdf', $batch) }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H7.5a2.25 2.25 0 00-2.25 2.25v9a2.25 2.25 0 002.25 2.25h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25H15m0-3l-3-3m0 0l-3 3m3-3V15"/></svg>
                    Export PDF
                </a>
                @if ($bolehKelolaPo)
                    <a href="{{ route('batches.edit', $batch) }}" class="inline-flex items-center rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Ubah</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Persetujuan batch: TM mengajukan → 420F menyetujui → diteruskan ke vendor --}}
        @if ($batch->status === \App\Enums\BatchStatus::Menunggu)
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-5" x-data="{ tolak: false }">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 shrink-0 text-blue-600 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div class="text-sm">
                            <p class="font-semibold text-blue-900">Menunggu persetujuan 420F</p>
                            <p class="mt-1 text-blue-700">
                                Diajukan {{ $batch->pengaju?->name ?? '—' }} · {{ $batch->created_at->format('d/m/Y H:i') }}.
                                Vendor belum bisa melihat batch ini sampai disetujui.
                            </p>
                        </div>
                    </div>
                    @if ($isAdmin)
                        <div class="flex items-center gap-2">
                            <button type="button" @click="tolak = ! tolak" class="rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Tolak</button>
                            <form method="POST" action="{{ route('batches.approve', $batch) }}"
                                  onsubmit="return confirm('Setujui batch ini? Vendor akan langsung bisa melihat & mengerjakannya.');">
                                @csrf @method('PATCH')
                                <button type="submit" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Setujui &amp; teruskan ke vendor</button>
                            </form>
                        </div>
                    @endif
                </div>
                @if ($isAdmin)
                    <form x-show="tolak" x-cloak method="POST" action="{{ route('batches.reject', $batch) }}" class="mt-4 flex flex-wrap items-end gap-3 border-t border-blue-200 pt-4">
                        @csrf @method('PATCH')
                        <div class="flex-1 min-w-64">
                            <label class="block text-xs font-medium text-blue-900">Alasan penolakan</label>
                            <input type="text" name="catatan_approval" required maxlength="255"
                                   class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600"
                                   placeholder="mis. qty XXL kebanyakan, kurangi dulu">
                        </div>
                        <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Tolak batch</button>
                    </form>
                @endif
            </div>
        @elseif ($batch->status === \App\Enums\BatchStatus::Ditolak)
            <div class="rounded-xl border border-red-200 bg-red-50 p-5 flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <svg class="h-5 w-5 shrink-0 text-red-600 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                    <div class="text-sm">
                        <p class="font-semibold text-red-900">Ditolak 420F</p>
                        <p class="mt-1 text-red-700">{{ $batch->catatan_approval }}</p>
                        <p class="mt-1 text-xs text-red-600">{{ $batch->penyetuju?->name }} · {{ $batch->tgl_approval?->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('batches.reajukan', $batch) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Ajukan ulang</button>
                </form>
            </div>
        @elseif ($batch->tgl_approval && $batch->diajukan_oleh !== $batch->disetujui_oleh)
            <p class="text-xs text-sand-400">
                Diajukan {{ $batch->pengaju?->name ?? '—' }}, disetujui {{ $batch->penyetuju?->name ?? '—' }} pada {{ $batch->tgl_approval->format('d/m/Y H:i') }}.
            </p>
        @endif

        {{-- Ringkasan batch --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            @php
                $sp = $batch->sisa_produksi;
                $cards = [
                    ['Tanggal order', $batch->tanggal_order->format('d/m/Y')],
                    ['Deadline produksi', $batch->deadline_produksi ? $batch->deadline_produksi->format('d/m/Y') : 'belum diset'],
                    ['Deadline pelunasan', $batch->deadline->format('d/m/Y')],
                    ['Jenis order', $batch->jenis_order->label()],
                    ['Type payment', $batch->type_payment->label()],
                    ['Total qty', number_format($batch->total_qty, 0, ',', '.').' pcs'],
                ];
            @endphp
            @foreach ($cards as [$label, $val])
                <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-4">
                    <p class="text-xs text-sand-500">{{ $label }}</p>
                    <p class="mt-1 text-sm font-semibold text-sand-900 tnum">{{ $val }}</p>
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $batch->status->badgeClasses() }}">Batch {{ $batch->status->label() }}</span>
            @if ($batch->status->value !== 'lunas' && $batch->deadline_produksi)
                @if ($sp < 0)
                    <span class="text-sm font-medium text-red-700">Produksi lewat deadline {{ abs($sp) }} hari</span>
                @else
                    <span class="text-sm {{ $sp <= 14 ? 'font-medium text-red-700' : 'text-sand-500' }}">Sisa {{ $sp }} hari menuju deadline produksi</span>
                @endif
            @endif
        </div>

        {{-- Daftar PO --}}
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-sand-200">
                <h2 class="text-sm font-semibold text-sand-800">PO per artikel ({{ $batch->purchaseOrders->count() }})</h2>
                @if ($bolehKelolaPo)
                    <a href="{{ route('purchase-orders.create', $batch) }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        Tambah PO
                    </a>
                @endif
            </div>

            {{-- Filter tahapan + unduh semua desain --}}
            @if ($batch->purchaseOrders->isNotEmpty())
                <div class="px-5 py-3 border-b border-sand-200 bg-sand-50/50 flex flex-wrap items-center gap-3">
                    <form method="GET" class="flex items-center gap-2">
                        <label class="text-xs font-medium text-sand-600">Tahapan</label>
                        <select name="tahap" onchange="this.form.submit()" class="rounded-lg border-sand-300 text-xs py-1.5 pr-8 focus:border-brand-600 focus:ring-brand-600">
                            <option value="">Semua tahapan</option>
                            @foreach (\App\Enums\TahapProduksi::cases() as $t)
                                <option value="{{ $t->value }}" @selected($tahapFilter === $t->value)>{{ $t->step() }}. {{ $t->label() }}</option>
                            @endforeach
                        </select>
                    </form>
                    @if ($tahapFilter)
                        <a href="{{ route('batches.show', $batch) }}" class="text-xs text-sand-500 hover:text-sand-800">Reset</a>
                        <span class="text-xs text-sand-400">{{ $pos->count() }} dari {{ $batch->purchaseOrders->count() }} PO</span>
                    @endif
                    @if ($adaFile)
                        <a href="{{ route('batches.designs', $batch) }}"
                           onclick="return confirm('Unduh semua file desain artikel dalam batch ini sebagai ZIP?');"
                           class="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-sand-300 bg-white px-3 py-1.5 text-xs font-medium text-sand-700 hover:bg-sand-100">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                            Unduh semua desain (ZIP)
                        </a>
                    @endif
                </div>
            @endif

            @if ($pos->isEmpty())
                <div class="p-10 text-center">
                    <p class="text-sand-500">{{ $tahapFilter ? 'Tidak ada PO di tahapan ini.' : 'Belum ada PO di batch ini.' }}</p>
                    @if ($bolehKelolaPo && ! $tahapFilter)
                        <a href="{{ route('purchase-orders.create', $batch) }}" class="mt-3 inline-block text-sm font-medium text-brand-700 hover:underline">Tambah PO pertama</a>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Nomor PO</th>
                                <th class="px-5 py-3 font-semibold">Kategori</th>
                                <th class="px-5 py-3 font-semibold">Artikel</th>
                                <th class="px-5 py-3 font-semibold text-center">Qty</th>
                                <th class="px-5 py-3 font-semibold">Tahap produksi</th>
                                <th class="px-5 py-3 font-semibold">Catatan vendor</th>
                                <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($pos as $po)
                                @php
                                    $mockups = $po->product->files->where('tipe', \App\Enums\ProductFileType::Mockup->value);
                                    $desains = $po->product->files->whereIn('tipe', [\App\Enums\ProductFileType::Desain->value, \App\Enums\ProductFileType::Mentahan->value]);
                                @endphp
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3.5 font-medium text-sand-900 tnum">{{ $po->nomor_po }}</td>
                                    <td class="px-5 py-3.5 text-sand-600">{{ $po->product->category->nama ?? '—' }}</td>
                                    <td class="px-5 py-3.5" x-data="{ preview: false }">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sand-700">{{ $po->product->nama_artikel }}</span>
                                            @if ($po->product->files->isNotEmpty())
                                                <button type="button" @click="preview = true" title="Preview mockup &amp; detail desain"
                                                        class="rounded-md p-1 text-brand-700 hover:bg-brand-50">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                </button>
                                                <a href="{{ route('products.download-zip', $po->product) }}" title="Unduh file desain artikel ini"
                                                   class="rounded-md p-1 text-sand-500 hover:bg-sand-100 hover:text-sand-800">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                                </a>
                                            @else
                                                {{-- Tanpa penanda ini, baris artikel tanpa file terlihat seolah fiturnya tidak ada. --}}
                                                @if ($bolehKelolaPo)
                                                    <a href="{{ route('products.edit', $po->product) }}" title="Belum ada mockup/desain — unggah di data produk"
                                                       class="rounded-md p-1 text-sand-300 hover:bg-amber-50 hover:text-amber-600">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"/></svg>
                                                    </a>
                                                @else
                                                    <span title="Belum ada mockup/desain untuk artikel ini" class="p-1 text-sand-300">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/></svg>
                                                    </span>
                                                @endif
                                            @endif
                                        </div>

                                        {{-- Modal preview mockup & detail desain --}}
                                        <div x-show="preview" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                                            <div class="absolute inset-0 bg-sand-900/50" @click="preview = false"></div>
                                            <div class="relative w-full max-w-3xl max-h-[85vh] overflow-y-auto rounded-xl border border-sand-200 bg-white shadow-xl">
                                                <div class="sticky top-0 bg-white px-5 py-4 border-b border-sand-200 flex items-start justify-between gap-3">
                                                    <div>
                                                        <h3 class="text-base font-semibold text-sand-900">{{ $po->product->nama_artikel }}</h3>
                                                        <p class="text-xs text-sand-500">{{ $po->product->category->nama ?? '—' }} · {{ $po->nomor_po }}{{ $po->product->sku_induk ? ' · '.$po->product->sku_induk : '' }}</p>
                                                    </div>
                                                    <button type="button" @click="preview = false" class="rounded-md p-1.5 text-sand-400 hover:bg-sand-100">
                                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    </button>
                                                </div>

                                                <div class="p-5 space-y-5">
                                                    {{-- Mockup --}}
                                                    @if ($mockups->isNotEmpty())
                                                        <div>
                                                            <p class="text-xs font-semibold uppercase tracking-wider text-sand-400 mb-2">Mockup</p>
                                                            <div class="grid grid-cols-2 gap-3">
                                                                @foreach ($mockups as $m)
                                                                    <a href="{{ asset('storage/'.$m->path) }}" target="_blank" class="block rounded-lg border border-sand-200 overflow-hidden hover:border-brand-400">
                                                                        <img src="{{ asset('storage/'.$m->path) }}" alt="{{ $m->nama_asli }}" class="w-full h-48 object-contain bg-sand-50">
                                                                        <p class="px-2 py-1.5 text-[11px] text-sand-500 truncate">{{ $m->nama_asli }}</p>
                                                                    </a>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif

                                                    {{-- Detail desain (snapshot PO) --}}
                                                    <div>
                                                        <p class="text-xs font-semibold uppercase tracking-wider text-sand-400 mb-2">Detail desain</p>
                                                        <dl class="grid sm:grid-cols-3 gap-3 text-sm">
                                                            @foreach (['Depan' => $po->desain_depan, 'Belakang' => $po->desain_belakang, 'Lengan' => $po->desain_lengan] as $lbl => $val)
                                                                <div class="rounded-lg border border-sand-200 p-3">
                                                                    <dt class="text-[11px] uppercase tracking-wide text-sand-400">{{ $lbl }}</dt>
                                                                    <dd class="mt-0.5 text-sand-800">{{ $val ?: '—' }}</dd>
                                                                </div>
                                                            @endforeach
                                                        </dl>
                                                    </div>

                                                    {{-- Spesifikasi ringkas --}}
                                                    <div>
                                                        <p class="text-xs font-semibold uppercase tracking-wider text-sand-400 mb-2">Spesifikasi</p>
                                                        <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-1.5 text-sm">
                                                            @foreach (['Bahan' => $po->jenis_bahan, 'Warna bahan' => $po->warna_bahan, 'Patrun' => $po->patrun, 'Rib' => $po->ukuran_rib, 'Cat sablon' => $po->cat_sablon, 'Finishing' => $po->finishing, 'Warna benang' => $po->warna_benang, 'Supplier bahan' => $po->supp_bahan] as $lbl => $val)
                                                                @if ($val)
                                                                    <div class="flex justify-between gap-3 border-b border-sand-100 pb-1">
                                                                        <dt class="text-sand-500">{{ $lbl }}</dt>
                                                                        <dd class="text-sand-800 text-right">{{ $val }}</dd>
                                                                    </div>
                                                                @endif
                                                            @endforeach
                                                        </dl>
                                                        @if ($po->note)
                                                            <p class="mt-2 text-sm text-sand-600"><span class="font-medium">Catatan:</span> {{ $po->note }}</p>
                                                        @endif
                                                    </div>

                                                    {{-- File desain --}}
                                                    @if ($desains->isNotEmpty())
                                                        <div>
                                                            <p class="text-xs font-semibold uppercase tracking-wider text-sand-400 mb-2">File desain / mentahan</p>
                                                            <div class="space-y-1.5">
                                                                @foreach ($desains as $f)
                                                                    <a href="{{ route('product-files.download', $f) }}" class="flex items-center gap-2 rounded-lg border border-sand-200 px-3 py-2 text-sm text-sand-700 hover:border-brand-300 hover:bg-brand-50/40">
                                                                        <svg class="h-4 w-4 text-sand-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                                                        <span class="flex-1 truncate">{{ $f->nama_asli }}</span>
                                                                        <span class="text-[11px] uppercase text-sand-400">{{ $f->ext }}</span>
                                                                    </a>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>

                                                <div class="sticky bottom-0 bg-white px-5 py-3 border-t border-sand-200 flex justify-end gap-2">
                                                    <a href="{{ route('purchase-orders.pdf', [$batch, $po]) }}" target="_blank" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Export PDF artikel</a>
                                                    <button type="button" @click="preview = false" class="rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Tutup</button>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-center tnum text-sand-700">{{ number_format($po->total_qty, 0, ',', '.') }}</td>
                                    <td class="px-5 py-3.5">
                                        @if ($canStatus)
                                            <form method="POST" action="{{ route('purchase-orders.status', [$batch, $po]) }}">
                                                @csrf @method('PATCH')
                                                <select name="tahap" onchange="this.form.submit()"
                                                        class="rounded-lg border-sand-300 text-xs py-1.5 pr-8 focus:border-brand-600 focus:ring-brand-600">
                                                    @foreach (\App\Enums\TahapProduksi::cases() as $st)
                                                        <option value="{{ $st->value }}" @selected($po->tahap === $st)>{{ $st->step() }}. {{ $st->label() }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $po->tahap->badgeClasses() }}">{{ $po->tahap->label() }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 align-top">
                                        @if ($canStatus)
                                            <form method="POST" action="{{ route('purchase-orders.catatan', [$batch, $po]) }}" class="flex flex-col gap-1.5 w-56">
                                                @csrf @method('PATCH')
                                                <textarea name="catatan_vendor" rows="2" placeholder="Kendala teknis / info ke brand…"
                                                          class="rounded-lg border-sand-300 text-xs focus:border-brand-600 focus:ring-brand-600">{{ $po->catatan_vendor }}</textarea>
                                                <button type="submit" class="self-start rounded-md bg-sand-100 px-2.5 py-1 text-xs font-medium text-sand-700 hover:bg-sand-200">Simpan</button>
                                            </form>
                                        @else
                                            <p class="max-w-[14rem] whitespace-pre-line text-xs {{ $po->catatan_vendor ? 'text-sand-700' : 'text-sand-400' }}">{{ $po->catatan_vendor ?: '—' }}</p>
                                        @endif
                                    </td>
                                        <td class="px-5 py-3.5">
                                            <div class="flex items-center justify-end gap-1">
                                                <a href="{{ route('purchase-orders.riwayat', [$batch, $po]) }}" class="rounded-md p-1.5 text-sand-500 hover:bg-sand-100 hover:text-sand-800" title="Riwayat durasi tahap produksi">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                </a>
                                                <a href="{{ route('purchase-orders.pdf', [$batch, $po]) }}" target="_blank" class="rounded-md p-1.5 text-sand-500 hover:bg-brand-50 hover:text-brand-700" title="Export PDF artikel ini">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m0 0l-2.25-2.25M12 16.5l2.25-2.25M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"/></svg>
                                                </a>
                                            @if ($bolehKelolaPo)
                                                <a href="{{ route('purchase-orders.edit', [$batch, $po]) }}" class="rounded-md p-1.5 text-sand-500 hover:bg-sand-100 hover:text-sand-800" title="Ubah">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                                                </a>
                                                <form method="POST" action="{{ route('purchase-orders.destroy', [$batch, $po]) }}" onsubmit="return confirm('Hapus PO {{ $po->nomor_po }}?');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="rounded-md p-1.5 text-sand-500 hover:bg-red-50 hover:text-red-700" title="Hapus">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                                    </button>
                                                </form>
                                            @endif
                                            </div>
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

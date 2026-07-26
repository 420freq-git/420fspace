<x-app-layout>
    @php
        $isAdmin = auth()->user()->isAdmin();
        $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
    @endphp
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 min-w-0">
                <x-back-link :href="route('settlement.index')" />
                <div class="min-w-0">
                    <h1 class="text-lg font-semibold text-sand-900 truncate tnum">{{ $batch->nomor_batch }}</h1>
                    <p class="text-xs text-sand-500">{{ $batch->brand->nama }} &middot; settlement ke {{ $batch->vendor }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if ($isAdmin)
                    <form method="POST" action="{{ route('settlement.status', $batch) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ $batch->status->value === 'lunas' ? 'aktif' : 'lunas' }}">
                        <button type="submit" class="inline-flex items-center rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">
                            {{ $batch->status->value === 'lunas' ? 'Set Aktif' : 'Tandai Lunas' }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Batch CASH (beli putus di muka) --}}
        @if (! empty($summary['cash']))
            @php $cs = $summary['cash_status']; @endphp
            <div class="rounded-xl border {{ $cs['lunas'] ? 'border-indigo-200 bg-indigo-50' : 'border-amber-200 bg-amber-50' }} shadow-sm p-6">
                <div>
                    <h2 class="text-sm font-semibold text-indigo-900">Batch pembayaran CASH (beli putus)@if ($cs['pakai_dp']) · DP {{ $cs['persen'] }}%@endif</h2>
                    <p class="mt-1 text-sm {{ $cs['lunas'] ? 'text-indigo-700' : 'text-amber-800' }}">
                        {{ $cs['lunas'] ? 'Lunas penuh — stok keluar sistem (milik TM420).' : 'Ditagih lewat invoice; uang masuk saat invoice ditandai lunas + bukti.' }}
                    </p>
                </div>

                <div class="mt-4 grid md:grid-cols-2 gap-4">
                    {{-- SISI TM — tagihan via invoice --}}
                    <div class="rounded-lg border border-sand-200 bg-white p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-sand-400 mb-2">Tagihan ke TM</p>
                        @foreach (array_filter([$cs['dp_inv'], $cs['sisa_inv']]) as $inv)
                            <div class="flex items-center justify-between text-sm py-1">
                                <a href="{{ route('invoices.show', $inv) }}" class="text-brand-700 hover:underline tnum">{{ $inv->nomor }}</a>
                                <span class="flex items-center gap-2">
                                    <span class="tnum text-sand-700">{{ $fmt($inv->total) }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $inv->status === 'lunas' ? 'bg-brand-100 text-brand-800' : 'bg-amber-100 text-amber-800' }}">{{ $inv->status === 'lunas' ? 'lunas' : 'belum' }}</span>
                                </span>
                            </div>
                        @endforeach
                        @if ($cs['jumlah_invoice'] === 0)
                            <p class="text-sm text-sand-400">Belum ada invoice terbit.</p>
                        @endif
                        @if ($isAdmin && $cs['jumlah_invoice'] === 0)
                            <form method="POST" action="{{ route('settlement.bayar-cash', $batch) }}" class="mt-2">
                                @csrf @method('PATCH')
                                <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">Terbitkan invoice tagihan</button>
                            </form>
                        @endif
                        @if ($isAdmin && $cs['bisa_terbit_sisa'])
                            <form method="POST" action="{{ route('settlement.lunasi-sisa-cash', $batch) }}" class="mt-2"
                                  onsubmit="return confirm('Terbitkan invoice pelunasan sisa ke TM?');">
                                @csrf @method('PATCH')
                                <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">Terbitkan invoice sisa (siap kirim)</button>
                            </form>
                        @endif
                    </div>

                    {{-- SISI DIFERD — 420F bayar modal, bertahap + bukti --}}
                    <div class="rounded-lg border border-sand-200 bg-white p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-sand-400 mb-2">{{ $isAdmin ? '420F bayar Diferd (modal)' : 'Pembayaran ke Anda (modal)' }}</p>
                        <dl class="space-y-1 text-sm">
                            <div class="flex justify-between"><dt class="text-sand-500">Total modal</dt><dd class="tnum text-sand-800">{{ $fmt($cs['diferd_total']) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-sand-500">Sudah dibayar</dt><dd class="tnum text-sand-800">{{ $fmt($cs['diferd_dibayar']) }}</dd></div>
                            <div class="flex justify-between border-t border-sand-100 pt-1"><dt class="font-medium text-sand-700">Sisa</dt><dd class="tnum font-semibold {{ $cs['diferd_sisa'] > 0 ? 'text-amber-700' : 'text-brand-700' }}">{{ $cs['diferd_sisa'] > 0 ? $fmt($cs['diferd_sisa']) : 'Lunas' }}</dd></div>
                        </dl>
                        @if ($isAdmin && $cs['bisa_bayar_diferd'])
                            <form method="POST" action="{{ route('settlement.bayar-diferd-cash', $batch) }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                                @csrf
                                <input type="file" name="bukti_transfer" accept="image/*,application/pdf" class="block w-full text-xs text-sand-600 file:mr-2 file:rounded file:border-0 file:bg-sand-100 file:px-2 file:py-1 file:text-xs">
                                <button class="rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-800">
                                    Bayar Diferd {{ $cs['tahap_diferd'] === 'dp' ? '(DP-modal)' : '(pelunasan)' }} + bukti
                                </button>
                            </form>
                        @elseif ($isAdmin && $cs['diferd_sisa'] > 0)
                            <p class="mt-2 text-xs text-sand-500">Pelunasan modal dibuka saat semua PO siap kirim.</p>
                        @endif
                    </div>
                </div>

                {{-- Reject di batch cash. DP: otomatis dipotong dari pelunasan. Non-DP: ganti barang/refund. --}}
                @if (($summary['ganti_obligasi_pcs'] ?? 0) > 0 && ($cs['pakai_dp'] ?? false))
                    <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <h3 class="text-sm font-semibold text-amber-900">Reject {{ $summary['ganti_obligasi_pcs'] }} pcs — dipotong otomatis dari pelunasan</h3>
                        <p class="mt-1 text-xs text-amber-700">
                            Batch DP: {{ $summary['ganti_obligasi_pcs'] }} pcs yang tak sampai otomatis dikurangi dari invoice
                            pelunasan (TM bayar lebih sedikit) dan dari pembayaran modal ke Diferd. Tak ada refund manual.
                        </p>
                    </div>
                @elseif (($summary['ganti_obligasi_pcs'] ?? 0) > 0)
                    <div class="mt-5 rounded-lg border border-rose-200 bg-rose-50 p-4">
                        <h3 class="text-sm font-semibold text-rose-900">Reject di batch cash — kewajiban ganti Diferd</h3>
                        <p class="mt-1 text-xs text-rose-700">
                            {{ $summary['ganti_obligasi_pcs'] }} pcs qty PO tidak sampai diterima (reject/kurang). Karena sudah
                            dibayar penuh di muka, Diferd wajib mengganti — barang (re-produksi) atau refund uang.
                        </p>
                        <dl class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                            <div><dt class="text-xs text-sand-500">Obligasi</dt><dd class="tnum font-semibold text-sand-900">{{ $summary['ganti_obligasi_pcs'] }} pcs · {{ $fmt($summary['ganti_obligasi_diferd']) }}</dd></div>
                            <div><dt class="text-xs text-sand-500">Diganti barang</dt><dd class="tnum font-semibold text-emerald-700">{{ $summary['ganti_barang_pcs'] }} pcs</dd></div>
                            <div><dt class="text-xs text-sand-500">Direfund</dt><dd class="tnum font-semibold text-emerald-700">{{ $summary['ganti_refund_pcs'] }} pcs · {{ $fmt($summary['ganti_refund_diferd']) }}</dd></div>
                            <div><dt class="text-xs text-sand-500">Belum ditangani</dt><dd class="tnum font-semibold {{ ($summary['ganti_sisa_pcs'] ?? 0) > 0 ? 'text-rose-700' : 'text-sand-500' }}">{{ $summary['ganti_sisa_pcs'] }} pcs · {{ $fmt($summary['ganti_sisa_diferd']) }}</dd></div>
                        </dl>

                        @if ($isAdmin && ($summary['ganti_sisa_pcs'] ?? 0) > 0)
                            <form method="POST" action="{{ route('settlement.ganti-cash', $batch) }}" class="mt-4 flex flex-wrap items-end gap-3 border-t border-rose-200 pt-4">
                                @csrf
                                <div>
                                    <label class="block text-xs font-medium text-sand-600">Cara ganti</label>
                                    <select name="metode" class="mt-1 rounded-lg border-sand-300 text-sm">
                                        <option value="barang">Sudah diganti barang (re-produksi)</option>
                                        <option value="refund">Refund uang (Diferd → 420F → TM)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-sand-600">Jumlah pcs</label>
                                    <input type="number" name="pcs" min="1" max="{{ $summary['ganti_sisa_pcs'] }}" value="{{ $summary['ganti_sisa_pcs'] }}"
                                           class="mt-1 w-28 rounded-lg border-sand-300 text-sm tnum">
                                </div>
                                <div class="flex-1 min-w-[10rem]">
                                    <label class="block text-xs font-medium text-sand-600">Catatan (opsional)</label>
                                    <input type="text" name="keterangan" maxlength="255" class="mt-1 w-full rounded-lg border-sand-300 text-sm">
                                </div>
                                <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Catat ganti</button>
                            </form>
                            <p class="mt-2 text-xs text-sand-500">Refund: nilai diprorata dari sisa ({{ $fmt($summary['ganti_sisa_tm420']) }} ke TM bila seluruh {{ $summary['ganti_sisa_pcs'] }} pcs direfund). Barang: tak ada uang bergerak.</p>
                        @endif

                        {{-- Refund dideklarasikan → 2 langkah transfer ber-bukti (Diferd→420F, 420F→TM). --}}
                        @if (($refundGanti ?? collect())->isNotEmpty())
                            <div class="mt-4 border-t border-rose-200 pt-4 space-y-3">
                                <p class="text-xs font-semibold uppercase tracking-wider text-rose-500">Pelaksanaan refund (ber-bukti)</p>
                                @foreach ($refundGanti as $rg)
                                    <div class="rounded-lg border border-sand-200 bg-white p-3">
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="text-sand-700">{{ $rg->pcs }} pcs · Diferd {{ $fmt($rg->nilai_diferd) }} · ke TM {{ $fmt($rg->nilai_tm420) }}</span>
                                            @if ($rg->refundTuntas())<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-brand-100 text-brand-800">tuntas</span>@endif
                                        </div>
                                        <div class="mt-2 grid sm:grid-cols-2 gap-3">
                                            {{-- Langkah 1: Diferd kembalikan ke 420F --}}
                                            <div>
                                                <p class="text-xs text-sand-500">1. Diferd → 420F ({{ $fmt($rg->nilai_diferd) }})</p>
                                                @if ($rg->diferdSudahKembalikan())
                                                    <p class="text-xs text-brand-700">✓ dicatat {{ $rg->tgl_diferd?->format('d/m/Y') }} @if ($rg->bukti_diferd)· <a href="{{ route('penarikan.index') }}" class="hover:underline">bukti</a>@endif</p>
                                                @elseif ($isAdmin)
                                                    <form method="POST" action="{{ route('settlement.refund-diferd', $rg) }}" enctype="multipart/form-data" class="mt-1 space-y-1">
                                                        @csrf
                                                        <input type="file" name="bukti_transfer" accept="image/*,application/pdf" class="block w-full text-xs file:mr-2 file:rounded file:border-0 file:bg-sand-100 file:px-2 file:py-1">
                                                        <button class="rounded bg-rose-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-rose-700">Catat + bukti</button>
                                                    </form>
                                                @else
                                                    <p class="text-xs text-sand-400">menunggu</p>
                                                @endif
                                            </div>
                                            {{-- Langkah 2: 420F teruskan ke TM --}}
                                            <div>
                                                <p class="text-xs text-sand-500">2. 420F → TM ({{ $fmt($rg->nilai_tm420) }})</p>
                                                @if ($rg->sudahDiteruskanTm())
                                                    <p class="text-xs text-brand-700">✓ dicatat {{ $rg->tgl_tm?->format('d/m/Y') }}</p>
                                                @elseif ($isAdmin && $rg->diferdSudahKembalikan())
                                                    <form method="POST" action="{{ route('settlement.refund-teruskan', $rg) }}" enctype="multipart/form-data" class="mt-1 space-y-1">
                                                        @csrf
                                                        <input type="file" name="bukti_transfer" accept="image/*,application/pdf" class="block w-full text-xs file:mr-2 file:rounded file:border-0 file:bg-sand-100 file:px-2 file:py-1">
                                                        <button class="rounded bg-rose-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-rose-700">Catat + bukti</button>
                                                    </form>
                                                @else
                                                    <p class="text-xs text-sand-400">menunggu langkah 1</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if (($summary['ganti_sisa_pcs'] ?? 0) <= 0 && ($refundGanti ?? collect())->isEmpty())
                            <p class="mt-3 text-xs font-medium text-emerald-700">Semua kewajiban ganti sudah ditangani.</p>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        {{-- Rincian saldo (konsinyasi) --}}
        <div class="grid md:grid-cols-2 gap-6 {{ ! empty($summary['cash']) ? 'hidden' : '' }}">
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400 mb-4">{{ $isAdmin ? 'Hak vendor — barang terjual' : 'Hak Anda — barang terjual' }}</h2>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between"><dt class="text-sand-600">Hak (barang terjual)</dt><dd class="tnum font-medium text-sand-900">{{ $fmt($summary['hak_jual']) }}</dd></div>
                    @if ($summary['buyout'] > 0)
                        <div class="flex justify-between"><dt class="text-sand-600">Hak buy-out sisa stok</dt><dd class="tnum font-medium text-sand-900">{{ $fmt($summary['buyout']) }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-sand-500">{{ $isAdmin ? 'Dibayar' : 'Diterima' }} (dicatat ke batch)</dt><dd class="tnum text-sand-700">− {{ $fmt($summary['pembayaran']) }}</dd></div>
                    @if ($summary['penarikan'] > 0)
                        <div class="flex justify-between">
                            <dt class="text-sand-500">{{ $isAdmin ? 'Dibayar' : 'Diterima' }} via penarikan
                                <a href="{{ route('penarikan.index') }}" class="text-xs text-brand-700 hover:underline">lihat</a>
                            </dt>
                            <dd class="tnum text-sand-700">− {{ $fmt($summary['penarikan']) }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between border-t border-sand-200 pt-2.5">
                        <dt class="font-medium text-sand-800">Saldo</dt>
                        @if ($summary['saldo'] > 0)
                            <dd class="tnum font-semibold text-amber-700">{{ $fmt($summary['saldo']) }} <span class="text-xs font-normal">{{ $isAdmin ? 'perlu dibayar' : 'belum Anda terima' }}</span></dd>
                        @else
                            <dd class="tnum font-semibold text-brand-700">Lunas</dd>
                        @endif
                    </div>
                </dl>
                @if ($summary['buyout'] > 0)
                    <p class="mt-3 text-xs text-sand-400">Buy-out menambah hak Diferd {{ $fmt($summary['buyout']) }}; TM ditagih lewat invoice (lihat menu Invoice).</p>
                @endif
            </div>

            <div class="space-y-4">
                {{-- Modal produksi (deposit) — TERPISAH dari hak sampai direkonsiliasi --}}
                @if ($summary['deposit'] > 0)
                    @if ($summary['rekonsiliasi'])
                        <div class="rounded-xl border border-brand-200 bg-brand-50 shadow-sm p-5">
                            <p class="text-sm text-brand-700">Deposit sudah direkonsiliasi</p>
                            <p class="mt-1 text-2xl font-semibold text-brand-800 tnum">{{ $fmt($summary['deposit']) }}</p>
                            <p class="mt-1 text-xs text-brand-600">Di-offset ke hak Diferd &amp; tagihan TM{{ $batch->tgl_rekonsiliasi ? ' · '.$batch->tgl_rekonsiliasi->format('d/m/Y') : '' }}.</p>
                        </div>
                    @else
                        <div class="rounded-xl border border-blue-200 bg-blue-50 shadow-sm p-5">
                            <p class="text-sm text-blue-700">Modal produksi (deposit)</p>
                            <p class="mt-1 text-2xl font-semibold text-blue-800 tnum">{{ $fmt($summary['modal']) }}</p>
                            <p class="mt-1 text-xs text-blue-600">Uang muka untuk Diferd — di luar kas 420F, terpisah dari hak sampai direkonsiliasi saat batch selesai.</p>
                            @if ($isAdmin)
                                <form method="POST" action="{{ route('settlement.rekonsiliasi', $batch) }}" class="mt-3"
                                      onsubmit="return confirm('Rekonsiliasi deposit {{ $fmt($summary['deposit']) }}? Deposit akan di-offset ke hak Diferd & tagihan TM. Lakukan saat batch benar-benar selesai.');">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="w-full rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Rekonsiliasi deposit (offset)</button>
                                </form>
                            @endif
                        </div>
                    @endif
                @endif
                <div class="grid {{ $isAdmin ? 'grid-cols-2' : 'grid-cols-1' }} gap-4">
                    @if ($isAdmin)
                        {{-- Fee 420F = margin 420F; tak ditampilkan ke Diferd. --}}
                        <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                            <p class="text-sm text-sand-500">Fee 420F</p>
                            <p class="mt-1 text-xl font-semibold text-brand-700 tnum">{{ $fmt($summary['fee420f']) }}</p>
                        </div>
                    @endif
                    <div class="rounded-xl border {{ $batch->dibuyout ? 'border-sand-200 bg-sand-50' : 'border-sand-200 bg-white' }} shadow-sm p-5">
                        @if ($batch->dibuyout)
                            <p class="text-sm text-sand-500">Sisa stok</p>
                            <p class="mt-1 text-lg font-semibold text-sand-700">Sudah di-buy-out</p>
                            <p class="mt-1 text-xs text-sand-400">jadi milik TM420 · {{ $batch->tgl_buyout?->format('d/m/Y') }} · ditagih via invoice</p>
                        @else
                            <p class="text-sm text-sand-500">Nilai sisa stok (hak Diferd)</p>
                            <p class="mt-1 text-xl font-semibold text-sand-900 tnum">{{ $fmt($summary['sisa_stok_value']) }}</p>
                            <p class="mt-1 text-xs text-sand-400">dasar buy-out · ditagih ke TM di harga jual</p>
                            @if ($isAdmin && $summary['sisa_stok_value'] > 0)
                                <form method="POST" action="{{ route('settlement.buyout', $batch) }}" class="mt-3"
                                      onsubmit="return confirm('Buy-out seluruh sisa stok? Stok jadi milik TM420 & keluar dari stok jual. Terbit INVOICE ke TM (harga jual), hak Diferd +{{ $fmt($summary['sisa_stok_value']) }} (dibayar via penarikan). 420F ambil margin saat invoice lunas.');">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="w-full rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100">Buy-out sisa stok</button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Rincian stok batch --}}
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-sand-200 flex flex-wrap items-center justify-between gap-3">
                @php
                    // Cash = beli putus: barang tak "terjual" lewat konsinyasi, tapi TERKIRIM ke TM.
                    $cashStok = $stok['is_cash'] ?? false;
                    $lblKol = $cashStok ? 'Terkirim' : 'Terjual';
                    $lblSisa = $cashStok ? 'Belum terkirim' : 'Belum terjual';
                    $val = fn ($r) => $cashStok ? $r['diterima'] : $r['terjual'];
                    $valSisa = fn ($r) => $cashStok ? max(0, $r['diproduksi'] - $r['diterima']) : $r['sisa'];
                @endphp
                <h2 class="text-sm font-semibold text-sand-800">Rincian stok batch ini</h2>
                <div class="flex items-center gap-4 text-xs">
                    <span class="text-sand-500">Diproduksi <span class="font-semibold text-sand-800 tnum">{{ number_format($stok['diproduksi'], 0, ',', '.') }}</span></span>
                    <span class="text-sand-500">{{ $lblKol }} <span class="font-semibold text-sand-800 tnum">{{ number_format($val($stok), 0, ',', '.') }}</span></span>
                    <span class="text-sand-500">{{ $lblSisa }} <span class="font-semibold text-brand-700 tnum">{{ number_format($valSisa($stok), 0, ',', '.') }}</span></span>
                </div>
            </div>
            @if ($stok['byKategori']->isEmpty())
                <div class="p-8 text-center text-sm text-sand-400">Belum ada PO/produksi di batch ini.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Kategori / Artikel</th>
                                <th class="px-5 py-3 font-semibold text-center">Diproduksi</th>
                                <th class="px-5 py-3 font-semibold text-center">{{ $lblKol }}</th>
                                <th class="px-5 py-3 font-semibold text-center">{{ $lblSisa }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($stok['byKategori'] as $kat => $k)
                                <tr class="bg-sand-50/70">
                                    <td class="px-5 py-2.5 font-semibold text-sand-800">{{ $kat }}</td>
                                    <td class="px-5 py-2.5 text-center tnum font-semibold text-sand-800">{{ number_format($k['diproduksi'], 0, ',', '.') }}</td>
                                    <td class="px-5 py-2.5 text-center tnum font-semibold text-sand-800">{{ number_format($val($k), 0, ',', '.') }}</td>
                                    <td class="px-5 py-2.5 text-center tnum font-semibold text-brand-700">{{ number_format($valSisa($k), 0, ',', '.') }}</td>
                                </tr>
                                @foreach ($k['artikels'] as $a)
                                    <tr class="hover:bg-sand-50/40">
                                        <td class="px-5 py-2 pl-10 text-sand-600">{{ $a['nama'] }}</td>
                                        <td class="px-5 py-2 text-center tnum text-sand-600">{{ number_format($a['diproduksi'], 0, ',', '.') }}</td>
                                        <td class="px-5 py-2 text-center tnum text-sand-600">{{ number_format($val($a), 0, ',', '.') }}</td>
                                        <td class="px-5 py-2 text-center tnum text-sand-700">{{ number_format($valSisa($a), 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-sand-200 bg-sand-50 font-semibold text-sand-900">
                                <td class="px-5 py-3">TOTAL</td>
                                <td class="px-5 py-3 text-center tnum">{{ number_format($stok['diproduksi'], 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-center tnum">{{ number_format($val($stok), 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-center tnum text-brand-700">{{ number_format($valSisa($stok), 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>

        {{-- Form catat (admin) --}}
        @if ($isAdmin)
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6"
                 x-data="{ tipe: 'pembayaran', jumlah: '' }">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400 mb-4">Catat pembayaran ke Diferd</h2>
                <form method="POST" action="{{ route('settlement.ledger.store', $batch) }}" class="grid sm:grid-cols-4 gap-4 items-end">
                    @csrf
                    <input type="hidden" name="tipe" value="pembayaran">
                    <div>
                        <label class="block text-xs font-medium text-sand-600">Tipe</label>
                        <input type="text" value="Pembayaran" disabled class="mt-1 block w-full rounded-lg border-sand-200 bg-sand-50 text-sm text-sand-500">
                        <p class="mt-1 text-[11px] text-sand-400">Buy-out &amp; deposit lewat tombolnya masing-masing.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-sand-600">Jumlah</label>
                        <div class="mt-1 relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-sand-400">Rp</span>
                            <input type="number" min="1" name="jumlah" x-model="jumlah" required class="block w-full rounded-lg border-sand-300 pl-9 text-sm focus:border-brand-600 focus:ring-brand-600 tnum">
                        </div>
                        <p class="mt-1 text-xs text-sand-400" x-show="tipe === 'buyout'" x-cloak>Saran = nilai sisa stok.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-sand-600">Tanggal</label>
                        <input type="date" name="tanggal" value="{{ now()->format('Y-m-d') }}" required class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                    </div>
                    <div>
                        <button type="submit" class="w-full inline-flex items-center justify-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">Catat</button>
                    </div>
                    <div class="sm:col-span-4">
                        <input type="text" name="keterangan" placeholder="Keterangan (opsional)" class="block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                    </div>
                    @error('jumlah') <p class="sm:col-span-4 text-sm text-red-600">{{ $message }}</p> @enderror
                </form>
            </div>
        @endif

        {{-- Riwayat buku --}}
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-sand-200"><h2 class="text-sm font-semibold text-sand-800">Riwayat pembayaran</h2></div>
            @if ($ledger->isEmpty() && $summary['deposit'] == 0)
                <div class="p-10 text-center text-sand-500">Belum ada pembayaran.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Tanggal</th>
                                <th class="px-5 py-3 font-semibold">Tipe</th>
                                <th class="px-5 py-3 font-semibold text-right">Jumlah</th>
                                <th class="px-5 py-3 font-semibold">Keterangan</th>
                                @if ($isAdmin)<th class="px-5 py-3"></th>@endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @if ($summary['deposit'] > 0)
                                <tr class="bg-sand-50/40">
                                    <td class="px-5 py-3 text-sand-500 tnum">{{ $batch->tanggal_order->format('d/m/Y') }}</td>
                                    <td class="px-5 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Deposit awal</span></td>
                                    <td class="px-5 py-3 text-right tnum text-sand-800">{{ $fmt($summary['deposit']) }}</td>
                                    <td class="px-5 py-3 text-sand-400">dari data batch</td>
                                    @if ($isAdmin)<td></td>@endif
                                </tr>
                            @endif
                            @foreach ($ledger as $entry)
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3 text-sand-600 tnum">{{ $entry->tanggal->format('d/m/Y') }}</td>
                                    <td class="px-5 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $entry->tipe->badgeClasses() }}">{{ $entry->tipe->label() }}</span></td>
                                    <td class="px-5 py-3 text-right tnum text-sand-800">{{ $fmt($entry->jumlah) }}</td>
                                    <td class="px-5 py-3 text-sand-500">{{ $entry->keterangan ?? '—' }}</td>
                                    @if ($isAdmin)
                                        <td class="px-5 py-3 text-right">
                                            <form method="POST" action="{{ route('settlement.ledger.destroy', $entry) }}" onsubmit="return confirm('Hapus entri ini?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="rounded-md p-1.5 text-sand-500 hover:bg-red-50 hover:text-red-700" title="Hapus">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

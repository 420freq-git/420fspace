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

        {{-- Rincian saldo --}}
        <div class="grid md:grid-cols-2 gap-6">
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400 mb-4">Hak vendor — barang terjual</h2>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between"><dt class="text-sand-600">Hak (barang terjual)</dt><dd class="tnum font-medium text-sand-900">{{ $fmt($summary['kewajiban']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-sand-500">Dibayar (dicatat ke batch)</dt><dd class="tnum text-sand-700">− {{ $fmt($summary['pembayaran']) }}</dd></div>
                    @if ($summary['penarikan'] > 0)
                        <div class="flex justify-between">
                            <dt class="text-sand-500">Dibayar via penarikan
                                <a href="{{ route('penarikan.index') }}" class="text-xs text-brand-700 hover:underline">lihat</a>
                            </dt>
                            <dd class="tnum text-sand-700">− {{ $fmt($summary['penarikan']) }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between border-t border-sand-200 pt-2.5">
                        <dt class="font-medium text-sand-800">Saldo</dt>
                        @if ($summary['saldo'] > 0)
                            <dd class="tnum font-semibold text-amber-700">{{ $fmt($summary['saldo']) }} <span class="text-xs font-normal">perlu dibayar</span></dd>
                        @else
                            <dd class="tnum font-semibold text-brand-700">Lunas</dd>
                        @endif
                    </div>
                </dl>
                @if ($summary['buyout'] > 0)
                    <p class="mt-3 text-xs text-sand-400">Buy-out stok sisa: {{ $fmt($summary['buyout']) }} (tercatat di riwayat).</p>
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
                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                        <p class="text-sm text-sand-500">Fee 420F</p>
                        <p class="mt-1 text-xl font-semibold text-brand-700 tnum">{{ $fmt($summary['fee420f']) }}</p>
                    </div>
                    <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                        <p class="text-sm text-sand-500">Nilai sisa stok</p>
                        <p class="mt-1 text-xl font-semibold text-sand-900 tnum">{{ $fmt($summary['sisa_stok_value']) }}</p>
                        <p class="mt-1 text-xs text-sand-400">dasar buy-out</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Rincian stok batch --}}
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-sand-200 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-sand-800">Rincian stok batch ini</h2>
                <div class="flex items-center gap-4 text-xs">
                    <span class="text-sand-500">Diproduksi <span class="font-semibold text-sand-800 tnum">{{ number_format($stok['diproduksi'], 0, ',', '.') }}</span></span>
                    <span class="text-sand-500">Terjual <span class="font-semibold text-sand-800 tnum">{{ number_format($stok['terjual'], 0, ',', '.') }}</span></span>
                    <span class="text-sand-500">Belum terjual <span class="font-semibold text-brand-700 tnum">{{ number_format($stok['sisa'], 0, ',', '.') }}</span></span>
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
                                <th class="px-5 py-3 font-semibold text-center">Terjual</th>
                                <th class="px-5 py-3 font-semibold text-center">Belum terjual</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($stok['byKategori'] as $kat => $k)
                                <tr class="bg-sand-50/70">
                                    <td class="px-5 py-2.5 font-semibold text-sand-800">{{ $kat }}</td>
                                    <td class="px-5 py-2.5 text-center tnum font-semibold text-sand-800">{{ number_format($k['diproduksi'], 0, ',', '.') }}</td>
                                    <td class="px-5 py-2.5 text-center tnum font-semibold text-sand-800">{{ number_format($k['terjual'], 0, ',', '.') }}</td>
                                    <td class="px-5 py-2.5 text-center tnum font-semibold text-brand-700">{{ number_format($k['sisa'], 0, ',', '.') }}</td>
                                </tr>
                                @foreach ($k['artikels'] as $a)
                                    <tr class="hover:bg-sand-50/40">
                                        <td class="px-5 py-2 pl-10 text-sand-600">{{ $a['nama'] }}</td>
                                        <td class="px-5 py-2 text-center tnum text-sand-600">{{ number_format($a['diproduksi'], 0, ',', '.') }}</td>
                                        <td class="px-5 py-2 text-center tnum text-sand-600">{{ number_format($a['terjual'], 0, ',', '.') }}</td>
                                        <td class="px-5 py-2 text-center tnum text-sand-700">{{ number_format($a['sisa'], 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-sand-200 bg-sand-50 font-semibold text-sand-900">
                                <td class="px-5 py-3">TOTAL</td>
                                <td class="px-5 py-3 text-center tnum">{{ number_format($stok['diproduksi'], 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-center tnum">{{ number_format($stok['terjual'], 0, ',', '.') }}</td>
                                <td class="px-5 py-3 text-center tnum text-brand-700">{{ number_format($stok['sisa'], 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>

        {{-- Form catat (admin) --}}
        @if ($isAdmin)
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6"
                 x-data="{ tipe: 'pembayaran', jumlah: '', sisaStok: {{ (int) $summary['sisa_stok_value'] }},
                           onTipe() { if (this.tipe === 'buyout') this.jumlah = this.sisaStok; } }">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400 mb-4">Catat pembayaran / buy-out</h2>
                <form method="POST" action="{{ route('settlement.ledger.store', $batch) }}" class="grid sm:grid-cols-4 gap-4 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-sand-600">Tipe</label>
                        <select name="tipe" x-model="tipe" @change="onTipe()" class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                            @foreach (\App\Enums\LedgerTipe::cases() as $t)
                                <option value="{{ $t->value }}">{{ $t->label() }}</option>
                            @endforeach
                        </select>
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

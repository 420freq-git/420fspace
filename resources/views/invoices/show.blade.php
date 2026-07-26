<x-app-layout>
    @php $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 min-w-0">
                <x-back-link :href="route('invoices.index')" />
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg font-semibold text-sand-900 truncate tnum">{{ $invoice->nomor }}</h1>
                        @if ($invoice->isCash())
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">{{ $invoice->labelJenis() }}</span>
                        @elseif ($invoice->isBuyout())
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Buy-out</span>
                        @endif
                    </div>
                    <p class="text-xs text-sand-500">Tagihan ke {{ $invoice->brand->nama }} · terbit {{ $invoice->tanggal_terbit->format('d/m/Y') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('invoices.pdf', $invoice) }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m0 0l-2.25-2.25M12 16.5l2.25-2.25M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"/></svg>
                    Export PDF
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        <div class="grid sm:grid-cols-3 gap-5">
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Ditagih ke</p>
                <p class="mt-1 text-lg font-semibold text-sand-900">{{ $invoice->brand->nama }}</p>
                <p class="text-xs text-sand-400">{{ $invoice->isManual() ? $invoice->labelJenis() : $invoice->orders->count().' pesanan' }} · {{ number_format($invoice->total_qty, 0, ',', '.') }} pcs</p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Total tagihan</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ $fmt($invoice->total) }}</p>
            </div>
            <div class="rounded-xl border {{ $invoice->isLunas() ? 'border-brand-200 bg-brand-50' : 'border-amber-200 bg-amber-50' }} shadow-sm p-5">
                <p class="text-sm {{ $invoice->isLunas() ? 'text-brand-700' : 'text-amber-700' }}">Status</p>
                <p class="mt-1 text-lg font-semibold {{ $invoice->isLunas() ? 'text-brand-700' : 'text-amber-700' }}">{{ $invoice->isLunas() ? 'Lunas' : 'Belum dibayar' }}</p>
                @if ($invoice->isLunas())<p class="text-xs text-brand-600">dibayar {{ $invoice->tanggal_bayar->format('d/m/Y') }}</p>@endif
            </div>
        </div>

        {{-- Tandai lunas (admin) --}}
        @if ($isAdmin && ! $invoice->isLunas())
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400 mb-3">Catat penerimaan (tandai lunas)</h2>
                <form method="POST" action="{{ route('invoices.paid', $invoice) }}" class="flex flex-wrap items-end gap-4">
                    @csrf @method('PATCH')
                    <div>
                        <label class="block text-xs font-medium text-sand-600">Tanggal bayar</label>
                        <input type="date" name="tanggal_bayar" value="{{ now()->format('Y-m-d') }}" required class="mt-1 rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                    </div>
                    <button type="submit" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Tandai lunas · {{ $fmt($invoice->total) }}</button>
                    <span class="text-xs text-sand-400">Penerimaan akan tercatat di Cashflow.</span>
                </form>
            </div>
        @endif

        {{-- Bukti transfer — TM unggah sebagai konfirmasi pembayaran; semua yang bisa lihat invoice bisa membukanya --}}
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5" x-data="{ open: false }">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400">Bukti transfer</h2>
                    @if ($invoice->bukti_transfer)
                        <a href="{{ route('invoices.bukti', $invoice) }}" target="_blank" class="mt-1 inline-flex items-center gap-1.5 text-sm text-brand-700 hover:underline">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                            Lihat bukti yang diunggah
                        </a>
                    @else
                        <p class="mt-1 text-sm text-sand-400">Belum ada bukti transfer.</p>
                    @endif
                </div>
                <button type="button" @click="open = !open" class="rounded-lg border border-sand-300 bg-white px-3 py-1.5 text-xs font-medium text-sand-700 hover:bg-sand-50">{{ $invoice->bukti_transfer ? 'Ganti bukti' : 'Unggah bukti' }}</button>
            </div>
            <form x-show="open" x-cloak method="POST" action="{{ route('invoices.bukti.upload', $invoice) }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-3 border-t border-sand-100 pt-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-sand-600">File bukti (JPG/PNG/PDF, maks 5MB)</label>
                    <input type="file" name="bukti_transfer" accept=".jpg,.jpeg,.png,.pdf" required class="mt-1 block text-sm text-sand-600 file:mr-2 file:rounded file:border-0 file:bg-sand-100 file:px-3 file:py-1.5 file:text-sm file:font-medium hover:file:bg-sand-200">
                </div>
                <button type="submit" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Simpan bukti</button>
                @error('bukti_transfer') <p class="w-full text-xs text-red-600">{{ $message }}</p> @enderror
            </form>
        </div>

        {{-- Daftar pesanan --}}
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-sand-200 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-sand-800">Pesanan dalam invoice</h2>
                @if ($isAdmin && ! $invoice->isLunas())
                    <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" onsubmit="return confirm('Hapus invoice ini? Pesanan akan dilepas kembali.');">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline">Hapus invoice</button>
                    </form>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                            <th class="px-5 py-3 font-semibold">Order ID</th>
                            <th class="px-5 py-3 font-semibold">Tanggal</th>
                            <th class="px-5 py-3 font-semibold">Channel</th>
                            <th class="px-5 py-3 font-semibold text-center">Qty</th>
                            <th class="px-5 py-3 font-semibold text-right">Nilai tagihan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @foreach ($invoice->orders as $o)
                            <tr class="hover:bg-sand-50/50 align-top">
                                <td class="px-5 py-3">
                                    <div class="text-sand-700 tnum">{{ $o->nomor_pesanan }}</div>
                                    <div class="mt-0.5 space-y-0.5">
                                        @foreach ($o->items as $it)
                                            <div class="text-xs text-sand-500">{{ $it->product->nama_artikel }} <span class="text-sand-400">· {{ $it->ukuran->value }} × {{ $it->qty }}</span></div>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-sand-600 tnum">{{ $o->tanggal_pesanan->format('d/m/Y') }}</td>
                                <td class="px-5 py-3 text-sand-600">{{ $o->marketplace->label() }}</td>
                                <td class="px-5 py-3 text-center tnum text-sand-700">{{ $o->total_qty }}</td>
                                <td class="px-5 py-3 text-right tnum text-sand-800">{{ $fmt($o->nilai_tm) }}</td>
                            </tr>
                        @endforeach
                        @if ($invoice->isBuyout())
                            <tr class="hover:bg-sand-50/50 align-top">
                                <td class="px-5 py-3">
                                    <div class="text-sand-700">Buy-out sisa stok</div>
                                    @if ($invoice->catatan)
                                        <div class="mt-0.5 text-xs text-sand-500">{{ $invoice->catatan }}</div>
                                    @endif

                                    {{-- Rincian artikel: direkonstruksi dari (diterima − terjual) batch,
                                         karena stok batch buy-out sudah keluar dari pool jual. --}}
                                    @if (! empty($rincianBuyout['baris']))
                                        <div class="mt-1.5 space-y-0.5">
                                            @foreach ($rincianBuyout['baris'] as $b)
                                                <div class="text-xs text-sand-500">
                                                    {{ $b['product']->nama_artikel ?? 'Artikel dihapus' }}
                                                    <span class="text-sand-400">·
                                                        @foreach ($b['sizes'] as $s){{ $s['ukuran'] }} × {{ $s['qty'] }}@if (! $loop->last), @endif @endforeach
                                                        ({{ number_format($b['pcs'], 0, ',', '.') }} pcs · {{ $fmt($b['nilai']) }})
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>

                                        @if ($rincianBuyout['pcs'] !== (int) $invoice->pcs_manual || $rincianBuyout['nilai'] !== (int) $invoice->jumlah_manual)
                                            <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                                Rincian di atas dihitung ulang dari data stok terkini dan berbeda dengan nilai
                                                yang tercatat saat buy-out ({{ number_format($invoice->pcs_manual, 0, ',', '.') }} pcs ·
                                                {{ $fmt($invoice->jumlah_manual) }}). <span class="font-medium">Yang mengikat adalah nilai
                                                tercatat</span>; selisih biasanya karena ada retur setelah buy-out.
                                            </div>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-sand-600 tnum">{{ $invoice->tanggal_terbit->format('d/m/Y') }}</td>
                                <td class="px-5 py-3 text-sand-600">Buy-out</td>
                                <td class="px-5 py-3 text-center tnum text-sand-700">{{ $invoice->pcs_manual }}</td>
                                <td class="px-5 py-3 text-right tnum text-sand-800">{{ $fmt($invoice->jumlah_manual) }}</td>
                            </tr>
                        @elseif ($invoice->isCash())
                            {{-- Tagihan batch cash (DP atau pelunasan) — beli putus, dibayar bertahap. --}}
                            <tr class="hover:bg-sand-50/50 align-top">
                                <td class="px-5 py-3">
                                    <div class="font-medium text-sand-800">{{ $invoice->labelJenis() }}</div>
                                    <div class="mt-0.5 text-xs text-sand-500">Pembayaran cash (beli putus){{ $invoice->batch ? ' · batch '.$invoice->batch->nomor_batch : '' }}</div>
                                </td>
                                <td class="px-5 py-3 text-sand-600 tnum">{{ $invoice->tanggal_terbit->format('d/m/Y') }}</td>
                                <td class="px-5 py-3 text-sand-600">Cash</td>
                                <td class="px-5 py-3 text-center tnum text-sand-700">{{ $invoice->pcs_manual ?: '—' }}</td>
                                <td class="px-5 py-3 text-right tnum text-sand-800">{{ $fmt($invoice->jumlah_manual) }}</td>
                            </tr>
                        @endif
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-sand-200 bg-sand-50 font-semibold text-sand-900">
                            <td class="px-5 py-3" colspan="3">TOTAL</td>
                            <td class="px-5 py-3 text-center tnum">{{ number_format($invoice->total_qty, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right tnum">{{ $fmt($invoice->total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

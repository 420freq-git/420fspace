<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 min-w-0">
                <x-back-link :href="route('pengiriman.index')" />
                <div class="min-w-0">
                    <h1 class="text-lg font-semibold text-sand-900 truncate tnum">{{ $sj->nomor_sj }}</h1>
                    <p class="text-xs text-sand-500">Batch {{ $sj->batch->nomor_batch }} · {{ $sj->batch->brand->nama }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('pengiriman.pdf', $sj) }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m0 0l-2.25-2.25M12 16.5l2.25-2.25M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"/></svg>
                    Export PDF
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        <div class="grid sm:grid-cols-3 gap-5">
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Tanggal kirim</p>
                <p class="mt-1 text-lg font-semibold text-sand-900 tnum">{{ $sj->tanggal_kirim->format('d/m/Y') }}</p>
                <p class="text-xs text-sand-400">{{ $sj->ekspedisi ?? '—' }}{{ $sj->resi ? ' · '.$sj->resi : '' }}</p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">{{ $sj->isDiterima() ? 'Dikirim / diterima' : 'Total kirim' }}</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">
                    {{ number_format($sj->total_qty, 0, ',', '.') }}@if ($sj->isDiterima())<span class="text-sand-400"> / </span>{{ number_format($sj->total_diterima, 0, ',', '.') }}@endif
                    <span class="text-sm font-normal text-sand-400">pcs</span>
                </p>
                @if ($sj->isDiterima() && $sj->adaSelisih())<p class="text-xs text-red-600">ada selisih</p>@endif
            </div>
            <div class="rounded-xl border {{ $sj->isDiterima() ? 'border-brand-200 bg-brand-50' : 'border-amber-200 bg-amber-50' }} shadow-sm p-5">
                <p class="text-sm {{ $sj->isDiterima() ? 'text-brand-700' : 'text-amber-700' }}">Status</p>
                <p class="mt-1 text-lg font-semibold {{ $sj->isDiterima() ? 'text-brand-700' : 'text-amber-700' }}">{{ $sj->isDiterima() ? 'Diterima' : 'Dikirim' }}</p>
                @if ($sj->isDiterima())<p class="text-xs text-brand-600">{{ $sj->tgl_diterima?->format('d/m/Y') }}</p>@endif
            </div>
        </div>

        @if ($canReceive && ! $sj->isDiterima())
            {{-- Form konfirmasi penerimaan --}}
            <form method="POST" action="{{ route('pengiriman.terima', $sj) }}"
                  class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden"
                  x-data="{
                      alasan: '',
                      konfirmasi: false,
                      dikirim: {{ (int) $sj->total_qty }},
                      diterima() {
                          return [...$el.querySelectorAll('input[data-dikirim]')]
                              .reduce((t, el) => t + (parseInt(el.value) || 0), 0);
                      },
                      kurang() { return Math.max(0, this.dikirim - this.diterima()); },
                      buka() { this.kurang() > 0 ? this.konfirmasi = true : $el.submit(); },
                  }"
                  @submit.prevent="buka()">
                @csrf @method('PATCH')
                <div class="px-5 py-4 border-b border-sand-200 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-sand-800">Konfirmasi penerimaan barang</h2>
                        <p class="text-xs text-sand-400">Isi jumlah yang diterima <span class="font-medium">dalam kondisi baik</span> (kurang/cacat dikurangi). Kalau semua sesuai, klik <span class="font-medium">Klop &amp; terima</span>. Selisih otomatis mengurangi stok &amp; jadi tanggungan vendor.</p>
                    </div>
                    <button type="button" onclick="this.closest('form').querySelectorAll('input[data-dikirim]').forEach(el => el.value = el.dataset.dikirim)"
                            class="shrink-0 text-xs font-medium text-sand-500 hover:text-sand-800">↺ Samakan semua</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Artikel</th>
                                <th class="px-5 py-3 font-semibold text-center">Ukuran</th>
                                <th class="px-5 py-3 font-semibold text-right">Dikirim</th>
                                <th class="px-5 py-3 font-semibold text-right">Diterima</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($sj->items as $it)
                                <tr>
                                    <td class="px-5 py-2.5 text-sand-800">{{ $it->product->nama_artikel }}</td>
                                    <td class="px-5 py-2.5 text-center text-sand-700">{{ $it->ukuran->value }}</td>
                                    <td class="px-5 py-2.5 text-right tnum text-sand-600">{{ $it->qty }}</td>
                                    <td class="px-5 py-2.5 text-right">
                                        <input type="number" name="diterima[{{ $it->id }}]" value="{{ $it->qty }}" data-dikirim="{{ $it->qty }}" min="0" required
                                               class="w-24 text-right rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600 tnum">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-4 border-t border-sand-200 flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Klop &amp; terima
                    </button>
                </div>

                {{-- Terima kurang dari yang dikirim → wajib pilih sebabnya --}}
                <div x-show="konfirmasi" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-sand-900/40" @click="konfirmasi = false"></div>
                    <div class="relative w-full max-w-md rounded-xl border border-sand-200 bg-white shadow-xl">
                        <div class="px-5 py-4 border-b border-sand-200">
                            <h3 class="text-base font-semibold text-sand-900">Jumlah tidak sesuai surat jalan</h3>
                            <p class="mt-1 text-sm text-sand-500">
                                Diterima <span class="font-medium tnum" x-text="diterima()"></span> dari
                                <span class="font-medium tnum" x-text="dikirim"></span> pcs —
                                kurang <span class="font-medium text-red-600 tnum" x-text="kurang()"></span> pcs.
                            </p>
                        </div>
                        <div class="px-5 py-4 space-y-3">
                            <p class="text-sm text-sand-600">Kenapa kurang?</p>
                            @foreach (\App\Enums\AlasanSelisih::untukTerima() as $a)
                                <label class="flex items-start gap-2.5 rounded-lg border p-3 cursor-pointer transition"
                                       :class="alasan === '{{ $a->value }}' ? 'border-brand-500 ring-1 ring-brand-500' : 'border-sand-200 hover:bg-sand-50'">
                                    <input type="radio" name="alasan_kurang_terima" value="{{ $a->value }}" x-model="alasan"
                                           class="mt-0.5 border-sand-300 text-brand-700 focus:ring-brand-600">
                                    <span>
                                        <span class="block text-sm font-medium text-sand-800">{{ $a->label() }}</span>
                                        <span class="block text-xs text-sand-500">{{ $a->keterangan() }}</span>
                                    </span>
                                </label>
                            @endforeach
                            <div>
                                <label class="block text-xs font-medium text-sand-600">Catatan <span class="font-normal text-sand-400">(opsional)</span></label>
                                <input type="text" name="catatan_selisih_terima" maxlength="255"
                                       class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600"
                                       placeholder="mis. 2 pcs jahitan lepas">
                            </div>
                            <p class="text-xs text-sand-400">Kerugiannya ditanggung vendor — tagihan ke brand ikut berkurang.</p>
                        </div>
                        <div class="px-5 py-4 border-t border-sand-200 flex justify-end gap-3">
                            <button type="button" @click="konfirmasi = false" class="rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Periksa lagi</button>
                            <button type="button" @click="$el.closest('form').submit()" :disabled="alasan === ''"
                                    class="rounded-lg px-4 py-2 text-sm font-semibold text-white"
                                    :class="alasan !== '' ? 'bg-brand-700 hover:bg-brand-800' : 'bg-sand-300 cursor-not-allowed'">Konfirmasi terima</button>
                        </div>
                    </div>
                </div>
            </form>
        @else
            {{-- Isi (read-only) --}}
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-sand-200 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-sand-800">Isi kiriman</h2>
                    @if ($canManage && ! $sj->isDiterima())
                        <form method="POST" action="{{ route('pengiriman.destroy', $sj) }}" onsubmit="return confirm('Hapus surat jalan ini?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs font-medium text-red-600 hover:underline">Hapus</button>
                        </form>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Artikel</th>
                                <th class="px-5 py-3 font-semibold text-center">Ukuran</th>
                                <th class="px-5 py-3 font-semibold text-right">Dikirim</th>
                                @if ($sj->isDiterima())
                                    <th class="px-5 py-3 font-semibold text-right">Diterima</th>
                                    <th class="px-5 py-3 font-semibold text-right">Selisih</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($sj->items as $it)
                                <tr>
                                    <td class="px-5 py-3 text-sand-800">{{ $it->product->nama_artikel }}</td>
                                    <td class="px-5 py-3 text-center text-sand-700">{{ $it->ukuran->value }}</td>
                                    <td class="px-5 py-3 text-right tnum text-sand-700">{{ number_format($it->qty, 0, ',', '.') }}</td>
                                    @if ($sj->isDiterima())
                                        <td class="px-5 py-3 text-right tnum text-sand-800">{{ number_format($it->qty_diterima, 0, ',', '.') }}</td>
                                        <td class="px-5 py-3 text-right tnum {{ $it->selisih < 0 ? 'text-red-600 font-medium' : ($it->selisih > 0 ? 'text-amber-600' : 'text-sand-300') }}">{{ $it->selisih > 0 ? '+' : '' }}{{ $it->selisih }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-sand-200 bg-sand-50 font-semibold text-sand-900">
                                <td class="px-5 py-3" colspan="2">TOTAL</td>
                                <td class="px-5 py-3 text-right tnum">{{ number_format($sj->total_qty, 0, ',', '.') }}</td>
                                @if ($sj->isDiterima())
                                    <td class="px-5 py-3 text-right tnum">{{ number_format($sj->total_diterima, 0, ',', '.') }}</td>
                                    <td class="px-5 py-3 text-right tnum {{ $sj->total_diterima - $sj->total_qty < 0 ? 'text-red-600' : 'text-sand-300' }}">{{ $sj->total_diterima - $sj->total_qty > 0 ? '+' : '' }}{{ $sj->total_diterima - $sj->total_qty }}</td>
                                @endif
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @if ($sj->catatan)
                    <div class="px-5 py-3 border-t border-sand-100 text-sm text-sand-600"><span class="font-medium">Catatan:</span> {{ $sj->catatan }}</div>
                @endif
                @if ($sj->alasan_kurang_kirim)
                    <div class="px-5 py-3 border-t border-sand-100 text-sm text-sand-600">
                        <span class="font-medium">Kirim kurang dari PO:</span>
                        <span class="ms-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $sj->alasan_kurang_kirim->badgeClasses() }}">{{ $sj->alasan_kurang_kirim->label() }}</span>
                    </div>
                @endif
                @if ($sj->alasan_kurang_terima)
                    <div class="px-5 py-3 border-t border-sand-100 text-sm text-sand-600">
                        <span class="font-medium">Selisih saat diterima:</span>
                        <span class="ms-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $sj->alasan_kurang_terima->badgeClasses() }}">{{ $sj->alasan_kurang_terima->label() }}</span>
                        @if ($sj->catatan_selisih_terima)<span class="text-sand-500"> · {{ $sj->catatan_selisih_terima }}</span>@endif
                    </div>
                @endif
            </div>

            @if ($sj->isDiterima() && $sj->adaSelisih())
                <div class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-5 py-4">
                    <svg class="h-5 w-5 shrink-0 text-red-500 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    <p class="text-sm text-red-800">
                        Ada <span class="font-medium">{{ number_format($sj->total_qty - $sj->total_diterima, 0, ',', '.') }} pcs</span> kurang/cacat — stok yang bisa dijual sudah otomatis berkurang, sehingga tagihan ke brand ikut turun.
                        <span class="font-medium">Kerugian ditanggung vendor: Rp {{ number_format($kerugianVendor, 0, ',', '.') }}</span> (Diferd tidak dibayar untuk unit ini).
                    </p>
                </div>
            @endif
        @endif
    </div>
</x-app-layout>

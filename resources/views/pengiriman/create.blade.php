<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-sand-900">Buat surat jalan</h1>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6 sm:p-8">
            @if (session('error'))
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
            @endif

            <form method="POST" action="{{ route('pengiriman.store') }}" class="space-y-5"
                  x-data="{
                      batchId: '',
                      map: @js($producedMap),
                      artikels: [],
                      konfirmasi: false,
                      alasan: '',
                      onBatch() {
                          this.alasan = '';
                          this.artikels = (this.map[this.batchId] || []).map(a => ({
                              product_id: a.product_id, nama: a.nama, kategori: a.kategori,
                              open: false,
                              sizes: a.sizes.map(s => ({ ukuran: s.ukuran, qty: s.qty, max: s.qty })),
                          }));
                      },
                      totalArtikel() { return this.artikels.filter(a => this.subtotal(a) > 0).length; },
                      subtotal(a) { return a.sizes.reduce((t, s) => t + (parseInt(s.qty) || 0), 0); },
                      totalQty() { return this.artikels.reduce((t, a) => t + this.subtotal(a), 0); },
                      totalPo() { return this.artikels.reduce((t, a) => t + a.sizes.reduce((x, s) => x + (parseInt(s.max) || 0), 0), 0); },
                      kurang() { return Math.max(0, this.totalPo() - this.totalQty()); },
                      bolehKirim() { return this.totalQty() > 0 && (this.kurang() === 0 || this.alasan !== ''); },
                      buka() { if (this.totalQty() > 0) this.konfirmasi = true; },
                  }">
                @csrf

                <div class="grid sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-sand-700">Batch produksi</label>
                        <select name="batch_id" x-model="batchId" @change="onBatch()" required class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                            <option value="">— pilih batch —</option>
                            @foreach ($batches as $b)<option value="{{ $b->id }}">{{ $b->nomor_batch }} · {{ $b->brand->nama }}</option>@endforeach
                        </select>
                        @error('batch_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-sand-700">Tanggal kirim</label>
                        <input type="date" name="tanggal_kirim" value="{{ old('tanggal_kirim', now()->format('Y-m-d')) }}" required
                               class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-sand-700">Ekspedisi <span class="text-sand-400 font-normal">(opsional)</span></label>
                        <input type="text" name="ekspedisi" value="{{ old('ekspedisi') }}" placeholder="mis. JNE / diantar sendiri"
                               class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-sand-700">Resi <span class="text-sand-400 font-normal">(opsional)</span></label>
                        <input type="text" name="resi" value="{{ old('resi') }}"
                               class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600 tnum">
                    </div>
                </div>

                {{-- Isi kiriman: list per artikel + toggle qty per size --}}
                <div class="rounded-lg border border-sand-200 overflow-hidden">
                    <div class="px-4 py-3 border-b border-sand-200 bg-sand-50/60 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-sand-500">Isi kiriman</p>
                        <p class="text-xs text-sand-500" x-show="artikels.length > 0" x-cloak>
                            <span x-text="totalArtikel()"></span> artikel · <span class="font-semibold text-sand-800" x-text="totalQty()"></span> pcs
                        </p>
                    </div>

                    <template x-if="artikels.length === 0">
                        <p class="px-4 py-6 text-sm text-sand-400">Pilih batch dulu — daftar artikel muncul otomatis dari hasil produksinya.</p>
                    </template>

                    <div class="divide-y divide-sand-100">
                        <template x-for="(a, ai) in artikels" :key="a.product_id">
                            <div>
                                {{-- Baris artikel (toggle) --}}
                                <button type="button" @click="a.open = !a.open"
                                        class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-sand-50/60">
                                    <svg class="h-4 w-4 shrink-0 text-sand-400 transition-transform" :class="a.open && 'rotate-90'"
                                         fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-sand-800 truncate" x-text="a.nama"></p>
                                        <p class="text-xs text-sand-400" x-text="a.kategori"></p>
                                    </div>
                                    <span class="text-sm tnum font-semibold shrink-0"
                                          :class="subtotal(a) > 0 ? 'text-brand-700' : 'text-sand-300'"
                                          x-text="subtotal(a) + ' pcs'"></span>
                                </button>

                                {{-- Qty per ukuran --}}
                                <div x-show="a.open" x-cloak class="px-4 pb-4 pt-1 bg-sand-50/40">
                                    <div class="grid grid-cols-3 sm:grid-cols-5 gap-3">
                                        <template x-for="(s, si) in a.sizes" :key="s.ukuran">
                                            <div>
                                                <label class="block text-xs font-medium text-sand-500" x-text="s.ukuran"></label>
                                                <input type="number" min="0" x-model="s.qty"
                                                       :name="`items[${a.product_id}-${s.ukuran}][qty]`"
                                                       class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600 tnum">
                                                <input type="hidden" :name="`items[${a.product_id}-${s.ukuran}][product_id]`" :value="a.product_id">
                                                <input type="hidden" :name="`items[${a.product_id}-${s.ukuran}][ukuran]`" :value="s.ukuran">
                                            </div>
                                        </template>
                                    </div>
                                    <p class="mt-2 text-xs text-sand-400">Qty terisi = jumlah diproduksi. Isi 0 untuk ukuran yang tidak dikirim.</p>
                                </div>
                            </div>
                        </template>
                    </div>
                    @error('items') <p class="px-4 py-2 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-sand-700">Catatan <span class="text-sand-400 font-normal">(opsional)</span></label>
                    <input type="text" name="catatan" value="{{ old('catatan') }}" class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('pengiriman.index') }}" class="inline-flex items-center rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Batal</a>
                    <button type="button" @click="buka()" :disabled="totalQty() === 0"
                            class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold text-white"
                            :class="totalQty() > 0 ? 'bg-brand-700 hover:bg-brand-800' : 'bg-sand-300 cursor-not-allowed'">Buat surat jalan</button>
                </div>

                {{-- Popup konfirmasi --}}
                <div x-show="konfirmasi" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-sand-900/40" @click="konfirmasi = false"></div>
                    <div class="relative w-full max-w-md rounded-xl border border-sand-200 bg-white shadow-xl">
                        <div class="px-5 py-4 border-b border-sand-200">
                            <h3 class="text-base font-semibold text-sand-900">Konfirmasi surat jalan</h3>
                            <p class="mt-1 text-sm text-sand-500">Pastikan data sudah benar sebelum dibuat.</p>
                        </div>
                        <div class="px-5 py-4 space-y-2 max-h-72 overflow-y-auto">
                            <div class="flex justify-between text-sm">
                                <span class="text-sand-500">Total artikel</span>
                                <span class="font-semibold text-sand-800 tnum" x-text="totalArtikel()"></span>
                            </div>
                            <div class="flex justify-between text-sm border-b border-sand-100 pb-2">
                                <span class="text-sand-500">Total qty</span>
                                <span class="font-semibold text-sand-800 tnum" x-text="totalQty() + ' pcs'"></span>
                            </div>
                            <template x-for="a in artikels.filter(a => subtotal(a) > 0)" :key="a.product_id">
                                <div class="flex justify-between text-sm">
                                    <span class="text-sand-600 truncate pr-3" x-text="a.nama"></span>
                                    <span class="tnum text-sand-700 shrink-0" x-text="subtotal(a) + ' pcs'"></span>
                                </div>
                            </template>

                            {{-- Kirim kurang dari PO → wajib pilih sebabnya --}}
                            <div x-show="kurang() > 0" x-cloak class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
                                <p class="text-sm font-medium text-amber-900">
                                    Kiriman kurang <span class="tnum" x-text="kurang()"></span> pcs dari PO
                                    (<span class="tnum" x-text="totalQty()"></span> dari <span class="tnum" x-text="totalPo()"></span> pcs).
                                </p>
                                <p class="mt-1 text-xs text-amber-700">Kenapa jumlahnya kurang? Sisanya tidak akan bisa dikirim lagi setelah surat jalan ini diterima.</p>
                                <div class="mt-3 space-y-2">
                                    @foreach (\App\Enums\AlasanSelisih::untukKirim() as $a)
                                        <label class="flex items-start gap-2.5 rounded-lg border bg-white p-3 cursor-pointer transition"
                                               :class="alasan === '{{ $a->value }}' ? 'border-brand-500 ring-1 ring-brand-500' : 'border-sand-200 hover:bg-sand-50'">
                                            <input type="radio" name="alasan_kurang_kirim" value="{{ $a->value }}" x-model="alasan"
                                                   class="mt-0.5 border-sand-300 text-brand-700 focus:ring-brand-600">
                                            <span>
                                                <span class="block text-sm font-medium text-sand-800">{{ $a->label() }}</span>
                                                <span class="block text-xs text-sand-500">{{ $a->keterangan() }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="px-5 py-4 border-t border-sand-200 flex justify-end gap-3">
                            <button type="button" @click="konfirmasi = false" class="rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Periksa lagi</button>
                            <button type="submit" :disabled="! bolehKirim()"
                                    class="rounded-lg px-4 py-2 text-sm font-semibold text-white"
                                    :class="bolehKirim() ? 'bg-brand-700 hover:bg-brand-800' : 'bg-sand-300 cursor-not-allowed'">Ya, buat surat jalan</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

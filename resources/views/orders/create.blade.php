<x-app-layout>
    @php
        $isAdmin = auth()->user()->isAdmin();
        $productsJson = $products->map(fn ($p) => ['id' => $p->id, 'label' => $p->nama_artikel.($isAdmin ? ' · '.$p->brand->nama : '')])->values();
        $ukuranVals = array_map(fn ($u) => $u->value, $ukurans);
        $oldItems = old('items', [['product_id' => '', 'ukuran' => '', 'qty' => 1]]);
    @endphp
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-sand-900">Input pesanan manual</h1>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6 sm:p-8">
            <form method="POST" action="{{ route('orders.store') }}" class="space-y-5"
                  @submit="errProduk = rows.some(r => ! r.product_id); if (errProduk) $event.preventDefault()"
                  x-data="{
                      errProduk: false,
                      channel: @js(old('marketplace', 'whatsapp')),
                      products: @js($productsJson),
                      ukurans: @js($ukuranVals),
                      rows: @js($oldItems),
                      get langsung() { return ['whatsapp', 'web'].includes(this.channel); },
                      add() { this.rows.push({ product_id: '', ukuran: '', qty: 1 }); },
                      remove(i) { if (this.rows.length > 1) this.rows.splice(i, 1); },
                      labelOf(id) { return this.products.find(p => String(p.id) === String(id))?.label ?? ''; },
                      cari(q) {
                          const k = q.trim().toLowerCase();
                          if (! k) return this.products;
                          return this.products.filter(p => p.label.toLowerCase().includes(k));
                      },
                  }">
                @csrf

                <div class="grid sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-sand-700">Order ID / No. pesanan</label>
                        <input type="text" name="nomor_pesanan" value="{{ old('nomor_pesanan') }}" required
                               class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600 tnum" placeholder="mis. OFFLINE-001 / WEB-001">
                        @error('nomor_pesanan') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-sand-700">Resi <span class="text-sand-400 font-normal">(opsional)</span></label>
                        <input type="text" name="resi" value="{{ old('resi') }}"
                               class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600 tnum">
                    </div>
                </div>

                <div class="grid sm:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-sand-700">Channel</label>
                        <select name="marketplace" x-model="channel" required class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600">
                            @foreach ($marketplaces as $mp)<option value="{{ $mp->value }}">{{ $mp->label() }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-sand-700">Tanggal</label>
                        <input type="date" name="tanggal_pesanan" value="{{ old('tanggal_pesanan', now()->format('Y-m-d')) }}" required
                               class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-sand-700">Status</label>
                        <div x-show="!langsung">
                            <select name="status" :disabled="langsung" class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600">
                                @foreach (\App\Enums\OrderStatus::cases() as $st)<option value="{{ $st->value }}" @selected(old('status', 'dipesan') === $st->value)>{{ $st->label() }}</option>@endforeach
                            </select>
                        </div>
                        <div x-show="langsung" x-cloak class="mt-1">
                            <input type="hidden" name="status" value="lunas" :disabled="!langsung">
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-brand-50 border border-brand-200 px-3 py-2 text-sm font-medium text-brand-700">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Langsung lunas
                            </span>
                        </div>
                    </div>
                </div>
                @error('status') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                {{-- Barang: multi-artikel --}}
                <div class="rounded-lg border border-sand-200 p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-sand-400">Barang</p>
                        <button type="button" @click="add()" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:text-brand-800">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            Tambah artikel
                        </button>
                    </div>

                    <template x-for="(row, i) in rows" :key="i">
                        <div class="grid grid-cols-12 gap-3 items-end">
                            {{-- Pemilih produk dengan pencarian: daftar artikel sudah puluhan baris,
                                 dropdown biasa memaksa scroll panjang tiap tambah baris. --}}
                            <div class="col-span-6 relative"
                                 x-data="{ open: false, q: '', sorot: 0 }"
                                 x-init="q = labelOf(row.product_id)"
                                 @click.outside="open = false; q = labelOf(row.product_id)"
                                 @keydown.escape.stop="open = false; q = labelOf(row.product_id); $refs.cari.blur()">
                                <label class="block text-xs font-medium text-sand-600" x-show="i === 0">Produk</label>
                                <input type="hidden" :name="`items[${i}][product_id]`" :value="row.product_id">
                                <input type="text" x-ref="cari" x-model="q" autocomplete="off"
                                       placeholder="ketik untuk cari artikel…"
                                       @focus="open = true; sorot = 0" @input="open = true; sorot = 0"
                                       @keydown.down.prevent="sorot = Math.min(sorot + 1, cari(q).length - 1)"
                                       @keydown.up.prevent="sorot = Math.max(sorot - 1, 0)"
                                       @keydown.enter.prevent="const h = cari(q)[sorot]; if (h) { row.product_id = h.id; q = h.label; open = false }"
                                       class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600"
                                       :class="row.product_id ? '' : 'text-sand-500'">
                                <div x-show="open" x-cloak
                                     class="absolute z-20 mt-1 w-full max-h-56 overflow-y-auto rounded-lg border border-sand-200 bg-white shadow-lg">
                                    <template x-for="(p, k) in cari(q)" :key="p.id">
                                        <button type="button"
                                                @click="row.product_id = p.id; q = p.label; open = false"
                                                @mouseenter="sorot = k"
                                                class="block w-full px-3 py-2 text-left text-sm"
                                                :class="k === sorot ? 'bg-brand-50 text-brand-800' : 'text-sand-700 hover:bg-sand-50'"
                                                x-text="p.label"></button>
                                    </template>
                                    <p x-show="cari(q).length === 0" class="px-3 py-2 text-sm text-sand-400">
                                        Artikel tidak ditemukan.
                                    </p>
                                </div>
                            </div>
                            <div class="col-span-3">
                                <label class="block text-xs font-medium text-sand-600" x-show="i === 0">Ukuran</label>
                                <select :name="`items[${i}][ukuran]`" x-model="row.ukuran" required
                                        class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                                    <option value="">—</option>
                                    <template x-for="u in ukurans" :key="u"><option :value="u" x-text="u"></option></template>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-sand-600" x-show="i === 0">Qty</label>
                                <input type="number" :name="`items[${i}][qty]`" x-model="row.qty" min="1" required
                                       class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600 tnum">
                            </div>
                            <div class="col-span-1">
                                <button type="button" @click="remove(i)" x-show="rows.length > 1"
                                        class="rounded-md p-1.5 text-sand-400 hover:bg-red-50 hover:text-red-700" title="Hapus baris">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    </template>
                    <p x-show="errProduk" x-cloak class="text-sm text-red-600">Masih ada baris yang produknya belum dipilih.</p>
                    @error('items') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <p class="text-xs text-sand-400">Semua artikel harus dari brand yang sama. Stok ditarik FIFO dari batch.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-sand-700">Keterangan <span class="text-sand-400 font-normal">(opsional)</span></label>
                    <input type="text" name="keterangan" value="{{ old('keterangan') }}" class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600">
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('orders.index') }}" class="inline-flex items-center rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Batal</a>
                    <button type="submit" class="inline-flex items-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">Simpan pesanan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

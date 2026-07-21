<x-app-layout>
    @php $isAdmin = auth()->user()->isAdmin(); @endphp
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-sand-900">Catat penjualan</h1>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6 sm:p-8">
            <form method="POST" action="{{ route('sales.store') }}"
                  x-data="{
                    productId: '{{ old('product_id') }}',
                    ukuran: '{{ old('ukuran') }}',
                    stockMap: @js($stockMap),
                    avail() { const m = this.stockMap[this.productId]; return (m && this.ukuran !== '') ? m[this.ukuran] : null; }
                  }">
                @csrf

                <div class="space-y-5">
                    <div>
                        <label for="product_id" class="block text-sm font-medium text-sand-700">Produk</label>
                        <select name="product_id" id="product_id" x-model="productId" required class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600">
                            <option value="">— pilih produk —</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}">{{ $p->nama_artikel }}@if($isAdmin) · {{ $p->brand->nama }}@endif</option>
                            @endforeach
                        </select>
                        @error('product_id') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label for="ukuran" class="block text-sm font-medium text-sand-700">Ukuran</label>
                            <select name="ukuran" id="ukuran" x-model="ukuran" required class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600">
                                <option value="">— pilih —</option>
                                @foreach (\App\Enums\Ukuran::cases() as $u)
                                    <option value="{{ $u->value }}">{{ $u->value }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1.5 text-xs" x-show="avail() !== null" x-cloak>
                                <span class="text-sand-500">Stok tersedia:</span>
                                <span class="font-medium" :class="avail() > 0 ? 'text-brand-700' : 'text-red-600'" x-text="(avail() ?? 0) + ' pcs'"></span>
                            </p>
                            @error('ukuran') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="qty" class="block text-sm font-medium text-sand-700">Qty</label>
                            <input type="number" name="qty" id="qty" min="1" value="{{ old('qty', 1) }}" required
                                   class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600 tnum">
                            @error('qty') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label for="marketplace" class="block text-sm font-medium text-sand-700">Marketplace</label>
                            <select name="marketplace" id="marketplace" required class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600">
                                @foreach ($marketplaces as $mp)
                                    <option value="{{ $mp->value }}" @selected(old('marketplace') === $mp->value)>{{ $mp->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="tanggal_terjual" class="block text-sm font-medium text-sand-700">Tanggal terjual</label>
                            <input type="date" name="tanggal_terjual" id="tanggal_terjual" value="{{ old('tanggal_terjual', now()->format('Y-m-d')) }}" required
                                   class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600">
                        </div>
                    </div>

                    <div>
                        <label for="nomor_pesanan" class="block text-sm font-medium text-sand-700">Nomor pesanan <span class="text-sand-400 font-normal">(opsional)</span></label>
                        <input type="text" name="nomor_pesanan" id="nomor_pesanan" value="{{ old('nomor_pesanan') }}"
                               class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600 tnum" placeholder="mis. 260710AC2TK5EE">
                    </div>

                    <div>
                        <label for="keterangan" class="block text-sm font-medium text-sand-700">Keterangan <span class="text-sand-400 font-normal">(opsional)</span></label>
                        <input type="text" name="keterangan" id="keterangan" value="{{ old('keterangan') }}"
                               class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600">
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-end gap-3">
                    <a href="{{ route('sales.index') }}" class="inline-flex items-center rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Batal</a>
                    <button type="submit" class="inline-flex items-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">Simpan penjualan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

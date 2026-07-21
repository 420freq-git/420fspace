<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-sand-900">Upload pesanan marketplace</h1>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        @isset($summary)
            {{-- Hasil import --}}
            <div class="rounded-xl border border-brand-200 bg-brand-50 p-6">
                <h2 class="text-base font-semibold text-brand-800">Import selesai — {{ $summary['marketplace'] }}</h2>
                <div class="mt-4 grid grid-cols-3 gap-3">
                    <div class="rounded-lg bg-white border border-sand-200 p-3 text-center">
                        <div class="text-2xl font-semibold text-brand-700 tnum">{{ $summary['imported_orders'] }}</div>
                        <div class="text-xs text-sand-500">pesanan masuk</div>
                    </div>
                    <div class="rounded-lg bg-white border border-sand-200 p-3 text-center">
                        <div class="text-2xl font-semibold text-sand-800 tnum">{{ $summary['imported_items'] }}</div>
                        <div class="text-xs text-sand-500">baris item</div>
                    </div>
                    <div class="rounded-lg bg-white border border-sand-200 p-3 text-center">
                        <div class="text-2xl font-semibold {{ $summary['items_tanpa_stok'] > 0 ? 'text-amber-700' : 'text-sand-400' }} tnum">{{ $summary['items_tanpa_stok'] }}</div>
                        <div class="text-xs text-sand-500">item tanpa stok/batch</div>
                    </div>
                </div>
                <ul class="mt-4 space-y-1.5 text-sm text-sand-700">
                    <li class="flex justify-between"><span>Dilewati — bukan produk kita (SKU tak dikenal)</span><span class="tnum font-medium">{{ $summary['skip_sku_tak_dikenal'] }}</span></li>
                    <li class="flex justify-between"><span>Dilewati — dibatalkan</span><span class="tnum font-medium">{{ $summary['skip_dibatalkan'] }}</span></li>
                    <li class="flex justify-between"><span>Dilewati — sudah pernah diimpor</span><span class="tnum font-medium">{{ $summary['skip_sudah_ada'] }}</span></li>
                </ul>
                @if (! empty($summary['sku_tak_dikenal']))
                    <details class="mt-3">
                        <summary class="text-xs text-sand-500 cursor-pointer">Lihat contoh SKU yang dilewati ({{ count($summary['sku_tak_dikenal']) }})</summary>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($summary['sku_tak_dikenal'] as $sku)
                                <span class="text-[11px] font-mono bg-white border border-sand-200 rounded px-1.5 py-0.5 text-sand-500">{{ $sku }}</span>
                            @endforeach
                        </div>
                    </details>
                @endif
                <div class="mt-5 flex gap-3">
                    <a href="{{ route('orders.index') }}" class="inline-flex items-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Lihat pesanan</a>
                    <a href="{{ route('orders.import.form') }}" class="inline-flex items-center rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Import lagi</a>
                </div>
            </div>
        @else
            @isset($dupWarning)
                <div class="rounded-xl border border-amber-300 bg-amber-50 p-5">
                    <p class="font-semibold text-amber-800">File ini sepertinya sudah pernah diupload</p>
                    <p class="mt-1 text-sm text-amber-700">Diimpor pada <span class="font-medium">{{ $dupWarning['tanggal'] }}</span> oleh <span class="font-medium">{{ $dupWarning['oleh'] }}</span> (file: {{ $dupWarning['nama_file'] }}). Untuk mencegah dobel input, upload dihentikan. Kalau memang mau memproses ulang, upload lagi &amp; centang override.</p>
                </div>
            @endisset
            {{-- Form upload --}}
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6 sm:p-8">
                <form method="POST" action="{{ route('orders.import.store') }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    <div>
                        <label for="file" class="block text-sm font-medium text-sand-700">File export pesanan</label>
                        <input type="file" name="file" id="file" accept=".csv,.xlsx,.xls" required
                               class="mt-1 block w-full text-sm text-sand-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100">
                        @error('file') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="rounded-lg bg-sand-50 border border-sand-200 p-4 text-xs text-sand-500 space-y-1">
                        <p>&bull; Format terdeteksi otomatis: <span class="font-medium">TikTok</span> (.csv) atau <span class="font-medium">Shopee</span> (.xlsx).</p>
                        <p>&bull; Hanya baris dengan <span class="font-medium">SKU yang cocok</span> (produk Diferd) yang diimpor jadi pesanan <span class="font-medium">Dipesan</span>. Sisanya dilewati.</p>
                        <p>&bull; Pesanan dibatalkan &amp; yang sudah pernah diimpor otomatis dilewati.</p>
                    </div>
                    @isset($dupWarning)
                        <label class="flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-50/60 p-3">
                            <input type="checkbox" name="paksa" value="1" required class="mt-0.5 rounded border-amber-300 text-amber-600 focus:ring-amber-500">
                            <span class="text-sm text-amber-800">Saya paham file ini sudah pernah diimpor — tetap proses ulang.</span>
                        </label>
                    @endisset
                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('orders.index') }}" class="inline-flex items-center rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Batal</a>
                        <button type="submit" class="inline-flex items-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">Upload &amp; import</button>
                    </div>
                </form>
            </div>
        @endisset
    </div>
</x-app-layout>

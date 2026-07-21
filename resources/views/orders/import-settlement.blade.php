<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-sand-900">Upload settlement / income</h1>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        @isset($summary)
            <div class="rounded-xl border border-brand-200 bg-brand-50 p-6">
                <h2 class="text-base font-semibold text-brand-800">Settlement diproses — {{ $summary['marketplace'] }}</h2>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-lg bg-white border border-sand-200 p-3 text-center">
                        <div class="text-2xl font-semibold text-brand-700 tnum">{{ $summary['cair'] }}</div>
                        <div class="text-xs text-sand-500">pesanan → Lunas (cair)</div>
                    </div>
                    <div class="rounded-lg bg-white border border-sand-200 p-3 text-center">
                        <div class="text-2xl font-semibold text-sand-500 tnum">{{ $summary['sudah_lunas'] }}</div>
                        <div class="text-xs text-sand-500">sudah lunas sebelumnya</div>
                    </div>
                </div>
                <ul class="mt-4 space-y-1.5 text-sm text-sand-700">
                    <li class="flex justify-between"><span>Order ID tak ditemukan di sistem</span><span class="tnum font-medium">{{ $summary['tak_ditemukan'] }}</span></li>
                    <li class="flex justify-between"><span>Dilewati (status batal/retur)</span><span class="tnum font-medium">{{ $summary['dilewati'] }}</span></li>
                </ul>
                <div class="mt-5 flex gap-3">
                    <a href="{{ route('orders.index') }}" class="inline-flex items-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Lihat pesanan</a>
                    <a href="{{ route('orders.settlement.form') }}" class="inline-flex items-center rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Upload lagi</a>
                </div>
            </div>
        @else
            @isset($dupWarning)
                <div class="rounded-xl border border-amber-300 bg-amber-50 p-5">
                    <p class="font-semibold text-amber-800">File ini sepertinya sudah pernah diupload</p>
                    <p class="mt-1 text-sm text-amber-700">Diproses pada <span class="font-medium">{{ $dupWarning['tanggal'] }}</span> oleh <span class="font-medium">{{ $dupWarning['oleh'] }}</span> (file: {{ $dupWarning['nama_file'] }}). Untuk mencegah dobel input, upload dihentikan. Kalau memang mau memproses ulang, upload lagi &amp; centang override.</p>
                </div>
            @endisset
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6 sm:p-8">
                <form method="POST" action="{{ route('orders.settlement.store') }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    <div>
                        <label for="file" class="block text-sm font-medium text-sand-700">File settlement / income</label>
                        <input type="file" name="file" id="file" accept=".csv,.xlsx,.xls" required
                               class="mt-1 block w-full text-sm text-sand-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100">
                        @error('file') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="rounded-lg bg-sand-50 border border-sand-200 p-4 text-xs text-sand-500 space-y-1">
                        <p>&bull; Format terdeteksi otomatis: income <span class="font-medium">TikTok</span> atau <span class="font-medium">Shopee</span> (.xlsx).</p>
                        <p>&bull; Pesanan yang <span class="font-medium">sudah cair</span> otomatis berubah status → <span class="font-medium">Lunas</span> (terjual final). Dicocokkan lewat Order ID.</p>
                        <p>&bull; Uang/fee tidak diimpor — hanya konfirmasi terjual.</p>
                    </div>
                    @isset($dupWarning)
                        <label class="flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-50/60 p-3">
                            <input type="checkbox" name="paksa" value="1" required class="mt-0.5 rounded border-amber-300 text-amber-600 focus:ring-amber-500">
                            <span class="text-sm text-amber-800">Saya paham file ini sudah pernah diupload — tetap proses ulang.</span>
                        </label>
                    @endisset
                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('orders.index') }}" class="inline-flex items-center rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Batal</a>
                        <button type="submit" class="inline-flex items-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">Upload &amp; proses</button>
                    </div>
                </form>
            </div>
        @endisset
    </div>
</x-app-layout>

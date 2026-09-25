<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-sand-900">Tarik Penjualan TM dari ERP TM420</h1>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <div class="rounded-xl border p-4 {{ $status['ok'] ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50' }}">
            <div class="flex items-center gap-2 text-sm">
                <span class="inline-block h-2 w-2 rounded-full {{ $status['ok'] ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                <span class="font-semibold {{ $status['ok'] ? 'text-emerald-800' : 'text-rose-800' }}">
                    {{ $status['ok'] ? 'Terhubung ke ERP TM420' : 'Tidak terhubung' }}
                </span>
                @if ($status['pesan'])<span class="text-sand-500">· {{ $status['pesan'] }}</span>@endif
            </div>
        </div>

        {{--
            Yang ditarik hanya penjualan yang UANGNYA SUDAH CAIR, dan hanya SKU
            brand TM420 yang ada di katalog sini. Barang titipan VOOJAH sengaja
            tidak ikut: ia sudah masuk lewat ERP 420F, dan menariknya dua kali
            berarti satu penjualan tercatat dua kali.
        --}}
        <p class="text-sm text-sand-600">
            Menarik pesanan <strong>brand TM420</strong> untuk artikel yang diproduksi di sistem ini —
            termasuk yang <strong>belum cair</strong>, supaya bisa dipantau sejak masuk. Statusnya ikut
            diperbarui tiap kali ditarik, dan yang sudah cair bisa ditagihkan lewat
            <a href="{{ route('invoices.index') }}" class="text-brand-700 hover:underline">Invoice</a> seperti brand lain.
        </p>

        @if (session('error'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ session('error') }}</div>
        @endif

        @isset($ringkas)
            <div class="rounded-xl border border-brand-200 bg-brand-50 p-6">
                <h2 class="text-base font-semibold text-brand-800">
                    Tarik selesai — {{ $ringkas['periode']['dari'] }} s/d {{ $ringkas['periode']['sampai'] }}
                </h2>

                <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                    <div class="rounded-lg bg-white p-3">
                        <div class="text-sand-500">Baris dari ERP TM</div>
                        <div class="text-lg font-bold text-sand-900">{{ $ringkas['baris_erp'] }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-3">
                        <div class="text-sand-500">Produksi kita</div>
                        <div class="text-lg font-bold text-emerald-700">{{ $ringkas['dipakai'] }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-3">
                        <div class="text-sand-500">Pesanan dibuat</div>
                        <div class="text-lg font-bold text-emerald-700">{{ $ringkas['import']['imported_orders'] }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-3">
                        <div class="text-sand-500">Sudah ada (dilewati)</div>
                        <div class="text-lg font-bold text-sand-500">{{ $ringkas['import']['skip_sudah_ada'] }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-3">
                        <div class="text-sand-500">Status diperbarui</div>
                        <div class="text-lg font-bold text-sand-900">{{ $ringkas['status_diperbarui'] }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-3">
                        <div class="text-sand-500">Bukan produksi kita</div>
                        <div class="text-lg font-bold text-sand-500">{{ $ringkas['bukan_produksi_kita'] }}</div>
                    </div>
                </div>

                @if ($ringkas['sebelum_cutoff'] > 0)
                    {{-- Periode yang tagihannya sudah diselesaikan di luar sistem. Barisnya
                         nyata, tapi menagihnya lagi tidak akan memunculkan galat apa pun. --}}
                    <p class="mt-4 rounded-lg bg-white px-3 py-2 text-xs text-sand-600">
                        {{ $ringkas['sebelum_cutoff'] }} baris dilewati karena cair pada/sebelum cut-off
                        <strong>{{ $ringkas['cutoff'] }}</strong> — periode itu sudah diselesaikan di luar sistem.
                    </p>
                @endif

                @if ($ringkas['sku_tak_dikenal'])
                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        <p class="font-semibold">SKU TM yang belum ada di katalog sini — tidak ikut tertagih:</p>
                        <p class="mt-1">{{ implode(', ', $ringkas['sku_tak_dikenal']) }}</p>
                    </div>
                @endif

                @if ($ringkas['selisih_harga'])
                    {{-- Harga kita vs ongkos yang tercatat di ERP TM. Keduanya seharusnya sama:
                         angka di sana berasal dari surat jalan yang dikirim sistem ini. --}}
                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        <p class="font-semibold">Harga kita berbeda dari catatan ERP TM — periksa sebelum menagih:</p>
                        <ul class="mt-1 space-y-0.5">
                            @foreach ($ringkas['selisih_harga'] as $s)
                                <li>{{ $s['sku'] }}: kita Rp {{ number_format($s['kita'], 0, ',', '.') }} ·
                                    ERP TM Rp {{ number_format($s['erp_tm'], 0, ',', '.') }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <a href="{{ route('invoices.index') }}"
                   class="mt-5 inline-block rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                    Lanjut ke Invoice
                </a>
            </div>
        @endisset

        <form method="POST" action="{{ route('orders.tarik-tm.jalankan') }}"
              class="rounded-xl border border-sand-200 bg-white p-6 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-sand-700">Dari (tanggal cair)</label>
                    <input type="date" name="dari" value="{{ old('dari', $dariDefault) }}" required
                           class="mt-1 block w-full rounded-lg border-sand-300 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-sand-700">Sampai</label>
                    <input type="date" name="sampai" value="{{ old('sampai', $sampaiDefault) }}" required
                           class="mt-1 block w-full rounded-lg border-sand-300 text-sm">
                </div>
            </div>

            <p class="text-xs text-sand-500">
                Yang sudah cair disaring menurut <strong>tanggal cair</strong> (itulah yang menentukan kapan
                boleh ditagih); yang belum cair menurut <strong>tanggal pesanan</strong>.
            </p>

            <button class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                Tarik penjualan TM
            </button>
        </form>
    </div>
</x-app-layout>

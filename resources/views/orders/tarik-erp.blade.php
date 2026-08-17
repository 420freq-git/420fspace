<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-sand-900">Tarik Pesanan dari ERP 420F</h1>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Status koneksi ERP --}}
        <div class="rounded-xl border p-4 {{ $status['ok'] ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50' }}">
            <div class="flex items-center gap-2 text-sm">
                <span class="inline-block h-2 w-2 rounded-full {{ $status['ok'] ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                <span class="font-semibold {{ $status['ok'] ? 'text-emerald-800' : 'text-rose-800' }}">
                    {{ $status['ok'] ? 'Terhubung ke ERP 420F' : 'Tidak terhubung' }}
                </span>
                @if ($status['pesan'])<span class="text-sand-500">· {{ $status['pesan'] }}</span>@endif
            </div>
        </div>

        @if (session('error'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ session('error') }}</div>
        @endif

        @isset($summary)
            <div class="rounded-xl border border-brand-200 bg-brand-50 p-6">
                <h2 class="text-base font-semibold text-brand-800">Tarik selesai — sumber {{ $summary['sumber'] }}</h2>
                <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                    <div class="rounded-lg bg-white p-3"><div class="text-sand-500">Pesanan diterima</div><div class="text-lg font-bold text-sand-900">{{ $summary['pesanan_diterima'] }}</div></div>
                    <div class="rounded-lg bg-white p-3"><div class="text-sand-500">Order dibuat</div><div class="text-lg font-bold text-emerald-700">{{ $summary['imported_orders'] }}</div></div>
                    <div class="rounded-lg bg-white p-3"><div class="text-sand-500">Item terjual</div><div class="text-lg font-bold text-sand-900">{{ $summary['imported_items'] }}</div></div>
                    <div class="rounded-lg bg-white p-3"><div class="text-sand-500">Sudah ada (dilewati)</div><div class="text-lg font-bold text-sand-500">{{ $summary['skip_sudah_ada'] }}</div></div>
                    <div class="rounded-lg bg-white p-3"><div class="text-sand-500">SKU tak dikenal</div><div class="text-lg font-bold text-amber-600">{{ $summary['skip_sku_tak_dikenal'] }}</div></div>
                    <div class="rounded-lg bg-white p-3"><div class="text-sand-500">Stok 0 (dilewati)</div><div class="text-lg font-bold text-amber-600">{{ $summary['skip_stok0'] + $summary['skip_order_stok0'] }}</div></div>
                </div>
                @if (! empty($summary['sku_tak_dikenal']))
                    <p class="mt-3 text-xs text-sand-500">SKU tak dikenal: {{ implode(', ', array_slice($summary['sku_tak_dikenal'], 0, 10)) }}{{ count($summary['sku_tak_dikenal']) > 10 ? '…' : '' }}</p>
                @endif
                <a href="{{ route('orders.index') }}" class="mt-4 inline-block text-sm font-medium text-brand-700 hover:text-brand-800">Lihat daftar pesanan →</a>
            </div>
        @endisset

        <div class="rounded-xl border border-sand-200 bg-white p-6">
            <h2 class="text-base font-semibold text-sand-900">Tarik pesanan</h2>
            <p class="mt-1 text-sm text-sand-500">
                Menarik pesanan VOOJAH &amp; 420F yang di-import di ERP → membuat Order &amp; memotong stok di sini.
                Aman ditarik ulang: pesanan yang nomornya sudah ada otomatis dilewati.
            </p>
            <form method="POST" action="{{ route('orders.tarik-erp.jalankan') }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-sand-600">Dari (opsional)</label>
                    <input type="date" name="dari" value="{{ old('dari', $dariDefault) }}"
                           class="mt-1 rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                </div>
                <div>
                    <label class="block text-xs font-medium text-sand-600">Sampai (opsional)</label>
                    <input type="date" name="sampai" value="{{ old('sampai', $sampaiDefault) }}"
                           class="mt-1 rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                </div>
                <button {{ $status['ok'] ? '' : 'disabled' }}
                        class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800 disabled:opacity-40">
                    Tarik dari ERP
                </button>
            </form>
        </div>
    </div>
</x-app-layout>

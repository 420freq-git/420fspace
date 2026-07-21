<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('orders.index') }}" class="text-xs text-brand-700 hover:underline">&larr; Kembali ke Kelola Pesanan</a>
            <h1 class="text-lg font-semibold text-sand-900">Monitoring Barang Kembali</h1>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <p class="text-sm text-sand-500 -mt-2">
            Pesanan yang sudah <span class="font-medium text-sand-700">ditolak/retur</span> tetapi barang fisiknya <span class="font-medium">masih dalam perjalanan balik</span>.
            Saat barang sampai, konfirmasi kondisinya: <span class="font-medium text-brand-700">Layak jual</span> &rarr; masuk stok jadi;
            <span class="font-medium text-red-700">Rusak</span> &rarr; tidak masuk stok (dicatat sebagai kerugian, brand tetap bayar produksi).
        </p>

        <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5 inline-block">
            <p class="text-xs text-sand-500">Total menunggu barang kembali</p>
            <p class="mt-1 text-2xl font-semibold text-blue-700 tnum">{{ $total }}</p>
        </div>

        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            @if ($orders->isEmpty())
                <div class="p-12 text-center text-sand-500">Tidak ada barang retur yang menunggu. 🎉</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Order ID</th>
                                <th class="px-5 py-3 font-semibold">Channel</th>
                                <th class="px-5 py-3 font-semibold">Tgl pesanan</th>
                                <th class="px-5 py-3 font-semibold">Umur</th>
                                <th class="px-5 py-3 font-semibold">Alasan batal</th>
                                <th class="px-5 py-3 font-semibold text-right">Konfirmasi barang diterima</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($orders as $order)
                                @php $umur = $order->umur_hari; @endphp
                                <tr class="{{ $umur > 30 ? 'bg-red-50/40' : '' }} hover:bg-sand-50/50 align-top">
                                    <td class="px-5 py-3.5">
                                        <div class="font-medium text-sand-900 tnum">{{ $order->nomor_pesanan }}</div>
                                        <div class="text-xs text-sand-400 tnum">{{ $order->resi ?? '—' }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-sand-600">{{ $order->marketplace->label() }}</td>
                                    <td class="px-5 py-3.5 text-sand-600 tnum">{{ $order->tanggal_pesanan->format('d/m/Y') }}</td>
                                    <td class="px-5 py-3.5 tnum {{ $umur > 30 ? 'font-medium text-red-700' : 'text-sand-600' }}">{{ $umur }} hari</td>
                                    <td class="px-5 py-3.5 text-sand-500">{{ $order->alasan_batal ?? '—' }}</td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center justify-end gap-2">
                                            <form method="POST" action="{{ route('monitoring.terima', $order) }}" onsubmit="return confirm('Barang diterima LAYAK JUAL — masuk kembali ke stok?');">
                                                @csrf <input type="hidden" name="kondisi" value="layak">
                                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                    Layak (masuk stok)
                                                </button>
                                            </form>
                                            <div x-data="{ open: false }" class="relative">
                                                <button type="button" @click="open = true" class="inline-flex items-center rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100">Rusak (kerugian)</button>

                                                {{-- Wajib isi alasan sebelum dicatat sebagai kerugian --}}
                                                <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                                                    <div class="absolute inset-0 bg-sand-900/50" @click="open = false"></div>
                                                    <form method="POST" action="{{ route('monitoring.terima', $order) }}" class="relative w-full max-w-md rounded-xl border border-sand-200 bg-white shadow-xl text-left">
                                                        @csrf <input type="hidden" name="kondisi" value="rusak">
                                                        <div class="px-5 py-4 border-b border-sand-200">
                                                            <h3 class="text-base font-semibold text-sand-900">Barang rusak/hilang</h3>
                                                            <p class="mt-1 text-sm text-sand-500">Pesanan {{ $order->nomor_pesanan }} — stok berkurang &amp; dicatat sebagai kerugian.</p>
                                                        </div>
                                                        <div class="px-5 py-4">
                                                            <label class="block text-sm font-medium text-sand-700">Alasan <span class="text-red-600">*</span></label>
                                                            <input type="text" name="alasan_rusak" required maxlength="255" placeholder="mis. sobek saat pengiriman / basah / hilang di ekspedisi"
                                                                   class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                                                            <p class="mt-1 text-xs text-sand-400">Alasan wajib diisi supaya kerugian bisa ditelusuri.</p>
                                                        </div>
                                                        <div class="px-5 py-4 border-t border-sand-200 flex justify-end gap-2">
                                                            <button type="button" @click="open = false" class="rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Batal</button>
                                                            <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Catat kerugian</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <p class="text-xs text-sand-400">Baris merah = umur &gt; 30 hari (barang mungkin tidak akan kembali). Setelah dikonfirmasi, pesanan hilang dari sini.</p>
    </div>
</x-app-layout>

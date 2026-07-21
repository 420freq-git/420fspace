<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-semibold text-sand-900">Pengaturan monitoring</h1>
            <p class="text-xs text-sand-500">Atur ambang hari untuk pengecekan pesanan &amp; deadline produksi.</p>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6 sm:p-8">
            <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
                @csrf @method('PATCH')

                <div>
                    <label class="block text-sm font-medium text-sand-700">Pesanan perlu dicek setelah</label>
                    <div class="mt-1 flex items-center gap-2">
                        <input type="number" name="monitor_hari" value="{{ old('monitor_hari', $monitor_hari) }}" min="1" max="90" required
                               class="w-24 rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600 tnum">
                        <span class="text-sm text-sand-500">hari belum cair</span>
                    </div>
                    <p class="mt-1 text-xs text-sand-400">Pesanan yang belum lunas melewati ini masuk daftar "Perlu dicek".</p>
                    @error('monitor_hari') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-sand-700">Jeda cek ulang</label>
                    <div class="mt-1 flex items-center gap-2">
                        <input type="number" name="cek_ulang_hari" value="{{ old('cek_ulang_hari', $cek_ulang_hari) }}" min="1" max="30" required
                               class="w-24 rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600 tnum">
                        <span class="text-sm text-sand-500">hari</span>
                    </div>
                    <p class="mt-1 text-xs text-sand-400">Setelah dicek, notifikasi "perlu cek ulang" muncul lagi setelah jeda ini.</p>
                    @error('cek_ulang_hari') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-sand-700">Ambang "mepet" deadline produksi</label>
                    <div class="mt-1 flex items-center gap-2">
                        <input type="number" name="mepet_hari" value="{{ old('mepet_hari', $mepet_hari) }}" min="0" max="30" required
                               class="w-24 rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600 tnum">
                        <span class="text-sm text-sand-500">hari sebelum deadline</span>
                    </div>
                    <p class="mt-1 text-xs text-sand-400">Batch/PO dengan sisa ≤ ini ditandai "mepet" di monitoring produksi &amp; dashboard.</p>
                    @error('mepet_hari') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end pt-2 border-t border-sand-100">
                    <button type="submit" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Simpan pengaturan</button>
                </div>
            </form>
        </div>

        {{-- Zona berbahaya: reset transaksi --}}
        <div class="mt-8 rounded-xl border border-red-200 bg-red-50/50 shadow-sm p-6 sm:p-8"
             x-data="{ buka: false, konfirmasi: '' }">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 shrink-0 text-red-500 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                <div class="flex-1">
                    <h2 class="text-sm font-semibold text-red-800">Reset transaksi</h2>
                    <p class="mt-1 text-sm text-red-700">Menghapus <span class="font-medium">semua data transaksi</span>: batch, PO, pesanan, penjualan, pengiriman, invoice, penarikan, ledger, audit log.</p>
                    <p class="mt-1 text-xs text-red-600">Produk, kategori, harga, brand, dan pengguna <span class="font-medium">TIDAK dihapus</span>. Sistem membuat backup <code>.sql</code> otomatis lebih dulu. Tindakan ini tidak bisa dibatalkan dari aplikasi.</p>

                    @error('konfirmasi') <p class="mt-2 text-xs font-medium text-red-700">{{ $message }}</p> @enderror

                    <div x-show="! buka" x-cloak>
                        <button type="button" @click="buka = true" class="mt-4 rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-100">Reset transaksi…</button>
                    </div>

                    <form x-show="buka" x-cloak method="POST" action="{{ route('settings.reset') }}" class="mt-4 space-y-3"
                          @submit="if (konfirmasi !== 'RESET') { $event.preventDefault(); }">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-red-800">Ketik <span class="font-mono font-semibold">RESET</span> untuk mengonfirmasi</label>
                            <input type="text" name="konfirmasi" x-model="konfirmasi" autocomplete="off"
                                   class="mt-1 block w-48 rounded-lg border-red-300 text-sm focus:border-red-500 focus:ring-red-500"
                                   placeholder="RESET">
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="submit" :disabled="konfirmasi !== 'RESET'"
                                    class="rounded-lg px-4 py-2 text-sm font-semibold text-white"
                                    :class="konfirmasi === 'RESET' ? 'bg-red-600 hover:bg-red-700' : 'bg-red-300 cursor-not-allowed'">
                                Hapus semua transaksi sekarang
                            </button>
                            <button type="button" @click="buka = false; konfirmasi = ''" class="rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

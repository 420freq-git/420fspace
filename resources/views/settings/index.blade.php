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
    </div>
</x-app-layout>

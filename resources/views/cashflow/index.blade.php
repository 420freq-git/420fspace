<x-app-layout>
    @php $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-semibold text-sand-900">Cashflow 420Frequency</h1>
            <p class="text-xs text-sand-500">Pantau uang masuk dari TM420 &amp; uang keluar ke Diferd.</p>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Posisi kas + fee --}}
        <div class="grid sm:grid-cols-2 gap-5">
            <div class="rounded-xl border {{ $posisiKas >= 0 ? 'border-brand-200 bg-brand-50' : 'border-red-200 bg-red-50' }} shadow-sm p-6">
                <p class="text-sm {{ $posisiKas >= 0 ? 'text-brand-700' : 'text-red-700' }}">Posisi kas 420F</p>
                <p class="mt-1 text-3xl font-semibold {{ $posisiKas >= 0 ? 'text-brand-700' : 'text-red-700' }} tnum">{{ $fmt($posisiKas) }}</p>
                <p class="mt-1 text-xs text-sand-500">ditransfer TM − dibayar Diferd (kas yang dipegang 420F)</p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6">
                <p class="text-sm text-sand-500">Fee 420F (margin)</p>
                <p class="mt-1 text-3xl font-semibold text-brand-700 tnum">{{ $fmt($fee) }}</p>
                <p class="mt-1 text-xs text-sand-400">markup dari pesanan lunas</p>
            </div>
        </div>

        {{-- Dua sisi arus --}}
        <div class="grid md:grid-cols-2 gap-6">
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400 mb-4">Uang masuk — dari TM420</h2>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between"><dt class="text-sand-600">Tagihan (pesanan lunas)</dt><dd class="tnum font-medium text-sand-900">{{ $fmt($tagihanTM) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-sand-500">Sudah ditransfer</dt><dd class="tnum text-brand-700">{{ $fmt($ditransferTM) }}</dd></div>
                    <div class="flex justify-between border-t border-sand-200 pt-2.5"><dt class="font-medium text-sand-800">Sisa tagihan ke TM</dt>
                        <dd class="tnum font-semibold {{ $sisaTagihanTM > 0 ? 'text-amber-700' : 'text-brand-700' }}">{{ $fmt($sisaTagihanTM) }}</dd></div>
                </dl>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400 mb-4">Uang keluar — ke Diferd</h2>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between"><dt class="text-sand-600">Hak Diferd (barang terjual)</dt><dd class="tnum font-medium text-sand-900">{{ $fmt($kewajibanDiferd) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-sand-500">Sudah dibayar (pembayaran)</dt><dd class="tnum text-brand-700">{{ $fmt($pembayaranDiferd) }}</dd></div>
                    <div class="flex justify-between border-t border-sand-200 pt-2.5"><dt class="font-medium text-sand-800">Sisa hak ke Diferd</dt>
                        <dd class="tnum font-semibold {{ $sisaBayarDiferd > 0 ? 'text-amber-700' : 'text-brand-700' }}">{{ $sisaBayarDiferd > 0 ? $fmt($sisaBayarDiferd) : 'Lunas' }}</dd></div>
                    @if ($buyoutDiferd > 0)
                        <div class="flex justify-between"><dt class="text-sand-500">Buy-out stok sisa</dt><dd class="tnum text-sand-700">{{ $fmt($buyoutDiferd) }}</dd></div>
                    @endif
                </dl>
                @if ($modalDiferd > 0)
                    <div class="mt-4 rounded-lg bg-blue-50 border border-blue-100 px-3 py-2.5">
                        <div class="flex justify-between text-sm">
                            <span class="text-blue-700">Modal produksi (dari TM langsung ke Diferd)</span>
                            <span class="tnum font-medium text-blue-800">{{ $fmt($modalDiferd) }}</span>
                        </div>
                        <p class="mt-1 text-xs text-blue-600">Di luar kas 420F — TM menalangi modal langsung ke Diferd (kasus khusus). Tidak memengaruhi posisi kas 420F.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Catat transfer TM --}}
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400 mb-4">Catat transfer dari TM420</h2>
            <form method="POST" action="{{ route('cashflow.transfer.store') }}" class="grid sm:grid-cols-4 gap-4 items-end">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-sand-600">Jumlah</label>
                    <div class="mt-1 relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-sand-400">Rp</span>
                        <input type="number" min="1" name="jumlah" required class="block w-full rounded-lg border-sand-300 pl-9 text-sm focus:border-brand-600 focus:ring-brand-600 tnum">
                    </div>
                    @error('jumlah') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-sand-600">Tanggal</label>
                    <input type="date" name="tanggal" value="{{ now()->format('Y-m-d') }}" required class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                </div>
                <div class="sm:col-span-1">
                    <label class="block text-xs font-medium text-sand-600">Keterangan</label>
                    <input type="text" name="keterangan" class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                </div>
                <button type="submit" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Catat</button>
            </form>
        </div>

        {{-- Riwayat transfer --}}
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-sand-200"><h2 class="text-sm font-semibold text-sand-800">Riwayat transfer TM420</h2></div>
            @if ($ledger->isEmpty())
                <div class="p-10 text-center text-sand-500">Belum ada transfer tercatat.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Tanggal</th>
                                <th class="px-5 py-3 font-semibold text-right">Jumlah</th>
                                <th class="px-5 py-3 font-semibold">Keterangan</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($ledger as $e)
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3 text-sand-600 tnum">{{ $e->tanggal->format('d/m/Y') }}</td>
                                    <td class="px-5 py-3 text-right tnum text-sand-800">{{ $fmt($e->jumlah) }}</td>
                                    <td class="px-5 py-3 text-sand-500">{{ $e->keterangan ?? '—' }}</td>
                                    <td class="px-5 py-3 text-right">
                                        <form method="POST" action="{{ route('cashflow.transfer.destroy', $e) }}" onsubmit="return confirm('Hapus entri transfer ini?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-md p-1.5 text-sand-500 hover:bg-red-50 hover:text-red-700" title="Hapus">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

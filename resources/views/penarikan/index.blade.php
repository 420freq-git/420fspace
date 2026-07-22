<x-app-layout>
    @php $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-semibold text-sand-900">Penarikan Diferd</h1>
            <p class="text-xs text-sand-500">Tarik saldo hak dari barang terjual — kapan saja, selama saldo cukup.</p>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        {{-- Saldo --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Total hak</p>
                <p class="mt-1 text-xl font-semibold text-sand-900 tnum">{{ $fmt($hak) }}</p>
                <p class="mt-1 text-xs text-sand-400">barang terjual</p>
            </div>
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Sudah ditarik</p>
                <p class="mt-1 text-xl font-semibold text-sand-900 tnum">{{ $fmt($dibayar) }}</p>
            </div>
            <div class="rounded-xl border {{ $pending > 0 ? 'border-amber-200 bg-amber-50' : 'border-sand-200 bg-white' }} shadow-sm p-5">
                <p class="text-sm {{ $pending > 0 ? 'text-amber-700' : 'text-sand-500' }}">Menunggu persetujuan</p>
                <p class="mt-1 text-xl font-semibold {{ $pending > 0 ? 'text-amber-700' : 'text-sand-900' }} tnum">{{ $fmt($pending) }}</p>
            </div>
            <div class="rounded-xl border border-brand-200 bg-brand-50 shadow-sm p-5">
                <p class="text-sm text-brand-700">Saldo tersedia</p>
                <p class="mt-1 text-xl font-semibold text-brand-700 tnum">{{ $fmt($tersedia) }}</p>
                <p class="mt-1 text-xs text-brand-600">bisa ditarik sekarang</p>
            </div>
        </div>

        {{-- Ajukan penarikan --}}
        @if ($tersedia > 0)
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400 mb-4">{{ $isAdmin ? 'Catat penarikan' : 'Ajukan penarikan' }}</h2>
                <form method="POST" action="{{ route('penarikan.store') }}" class="grid sm:grid-cols-4 gap-4 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-sand-600">Jumlah</label>
                        <div class="mt-1 relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-sand-400">Rp</span>
                            <input type="number" min="1" max="{{ $tersedia }}" name="jumlah" required class="block w-full rounded-lg border-sand-300 pl-9 text-sm focus:border-brand-600 focus:ring-brand-600 tnum">
                        </div>
                        @error('jumlah') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-sand-600">Catatan (opsional)</label>
                        <input type="text" name="catatan" class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                    </div>
                    <button type="submit" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">{{ $isAdmin ? 'Catat' : 'Ajukan' }}</button>
                </form>
            </div>
        @else
            <div class="rounded-lg border border-sand-200 bg-sand-50 px-4 py-3 text-sm text-sand-500">Saldo belum tersedia untuk ditarik.</div>
        @endif

        {{-- Riwayat --}}
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-sand-200"><h2 class="text-sm font-semibold text-sand-800">Riwayat penarikan</h2></div>
            @if ($riwayat->isEmpty())
                <div class="p-10 text-center text-sand-500">Belum ada penarikan.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Tgl ajuan</th>
                                <th class="px-5 py-3 font-semibold text-right">Jumlah</th>
                                <th class="px-5 py-3 font-semibold text-center">Status</th>
                                <th class="px-5 py-3 font-semibold">Tgl cair</th>
                                <th class="px-5 py-3 font-semibold">Catatan</th>
                                <th class="px-5 py-3 font-semibold">Bukti / invoice</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($riwayat as $p)
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3 text-sand-600 tnum">{{ $p->tanggal_ajuan->format('d/m/Y') }}</td>
                                    <td class="px-5 py-3 text-right">
                                        <div class="tnum font-medium text-sand-800">{{ $fmt($p->jumlah) }}</div>
                                        @if ($p->status === 'disetujui' && $p->alokasi->isNotEmpty())
                                            <div class="mt-1 space-y-0.5 text-[11px] text-sand-400">
                                                @foreach ($p->alokasi as $a)
                                                    <div class="tnum">{{ $a->batch?->nomor_batch ?? 'tanpa batch' }} · {{ $fmt($a->jumlah) }}</div>
                                                @endforeach
                                            </div>
                                        @elseif ($p->status === 'diajukan' && isset($rencana[$p->id]))
                                            <div class="mt-1 space-y-0.5 text-[11px] text-sand-400">
                                                <div class="italic">rencana pembagian:</div>
                                                @foreach ($rencana[$p->id]['baris'] as $nomor => $jml)
                                                    <div class="tnum">{{ $nomor }} · {{ $fmt($jml) }}</div>
                                                @endforeach
                                                @if ($rencana[$p->id]['sisa'] > 0)
                                                    <div class="tnum text-amber-600">tanpa batch · {{ $fmt($rencana[$p->id]['sisa']) }}</div>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-center"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $p->badgeClasses() }}">{{ $p->statusLabel() }}</span></td>
                                    <td class="px-5 py-3 text-sand-600 tnum">{{ $p->tanggal_cair?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="px-5 py-3 text-sand-500">{{ $p->catatan ?? '—' }}</td>
                                    <td class="px-5 py-3" x-data="{ open: false }">
                                        <div class="flex flex-col items-start gap-1 text-xs">
                                            @if ($p->bukti_transfer)
                                                <a href="{{ route('penarikan.bukti', [$p, 'transfer']) }}" class="inline-flex items-center gap-1 text-brand-700 hover:underline">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>Bukti transfer</a>
                                            @endif
                                            @if ($p->bukti_invoice)
                                                <a href="{{ route('penarikan.bukti', [$p, 'invoice']) }}" class="inline-flex items-center gap-1 text-brand-700 hover:underline">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>Invoice</a>
                                            @endif
                                            @if (! $p->bukti_transfer && ! $p->bukti_invoice)
                                                <span class="text-sand-300">belum ada</span>
                                            @endif
                                            {{-- Unggah bukti hanya untuk 420F; Diferd cukup melihat. --}}
                                            @if ($isAdmin)
                                                <button type="button" @click="open=!open" class="text-sand-500 hover:text-sand-800">{{ ($p->bukti_transfer || $p->bukti_invoice) ? 'Ubah…' : 'Unggah…' }}</button>
                                            @endif
                                        </div>
                                        @if ($isAdmin)
                                            <form x-show="open" x-cloak method="POST" action="{{ route('penarikan.bukti.upload', $p) }}" enctype="multipart/form-data" class="mt-2 space-y-1.5">
                                                @csrf
                                                <div>
                                                    <label class="block text-[11px] text-sand-500">Bukti transfer</label>
                                                    <input type="file" name="bukti_transfer" accept=".jpg,.jpeg,.png,.pdf" class="block w-full text-xs text-sand-600 file:mr-2 file:rounded file:border-0 file:bg-sand-100 file:px-2 file:py-1 file:text-xs">
                                                </div>
                                                <div>
                                                    <label class="block text-[11px] text-sand-500">Invoice</label>
                                                    <input type="file" name="bukti_invoice" accept=".jpg,.jpeg,.png,.pdf" class="block w-full text-xs text-sand-600 file:mr-2 file:rounded file:border-0 file:bg-sand-100 file:px-2 file:py-1 file:text-xs">
                                                </div>
                                                <button type="submit" class="rounded-md bg-brand-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-brand-700">Simpan</button>
                                                @error('bukti_transfer') <p class="text-[11px] text-red-600">{{ $message }}</p> @enderror
                                                @error('bukti_invoice') <p class="text-[11px] text-red-600">{{ $message }}</p> @enderror
                                            </form>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        @if ($p->status === 'diajukan')
                                            <div class="flex items-center justify-end gap-2">
                                                @if ($isAdmin)
                                                    <form method="POST" action="{{ route('penarikan.approve', $p) }}"
                                                          onsubmit="return confirm('Setujui penarikan {{ $fmt($p->jumlah) }}? Pembagian ke batch akan dicatat permanen dan tidak bisa diubah dari halaman settlement.');">
                                                        @csrf @method('PATCH')
                                                        <button type="submit" class="rounded-md bg-brand-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-brand-700">Setujui</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('penarikan.reject', $p) }}">
                                                        @csrf @method('PATCH')
                                                        <button type="submit" class="rounded-md border border-sand-300 px-2.5 py-1 text-xs font-medium text-sand-600 hover:bg-sand-100">Tolak</button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('penarikan.destroy', $p) }}" onsubmit="return confirm('Batalkan permintaan ini?');">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="rounded-md p-1.5 text-sand-500 hover:bg-red-50 hover:text-red-700" title="Batalkan">
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        @endif
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

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-sand-900">Kategori &amp; harga</h1>
                <p class="text-xs text-sand-500">Master harga per kategori &times; tier ukuran.</p>
            </div>
            <a href="{{ route('categories.create') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-800 transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Tambah kategori
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-4">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-xs text-sand-500">
            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-amber-400"></span> Diferd = 420F bayar ke vendor</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-blue-400"></span> TM420 = brand bayar ke 420F</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-brand-500"></span> Markup = untung 420F</span>
        </div>

        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            @if ($categories->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-sand-500">Belum ada kategori.</p>
                    <a href="{{ route('categories.create') }}" class="mt-3 inline-block text-sm font-medium text-brand-700 hover:underline">Tambah kategori pertama</a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Kategori</th>
                                <th class="px-5 py-3 font-semibold">Tier</th>
                                <th class="px-5 py-3 font-semibold text-right">Diferd</th>
                                <th class="px-5 py-3 font-semibold text-right">TM420</th>
                                <th class="px-5 py-3 font-semibold text-right">Markup 420F</th>
                                <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $cat)
                                @php
                                    $sxl = $cat->priceFor(\App\Enums\SizeTier::SXL);
                                    $xxl = $cat->priceFor(\App\Enums\SizeTier::XXL);
                                @endphp
                                @foreach ([['tier' => \App\Enums\SizeTier::SXL, 'p' => $sxl], ['tier' => \App\Enums\SizeTier::XXL, 'p' => $xxl]] as $i => $line)
                                    <tr class="{{ $loop->first ? 'border-t-2 border-sand-200' : '' }} hover:bg-sand-50/40">
                                        @if ($loop->first)
                                            <td rowspan="2" class="align-top px-5 py-3.5 border-e border-sand-100">
                                                <div class="font-medium text-sand-900">{{ $cat->nama }}</div>
                                                @if ($cat->aktif)
                                                    <span class="mt-1 inline-flex items-center gap-1.5 text-xs font-medium text-brand-700"><span class="h-1.5 w-1.5 rounded-full bg-brand-600"></span> Aktif</span>
                                                @else
                                                    <span class="mt-1 inline-flex items-center gap-1.5 text-xs font-medium text-sand-400"><span class="h-1.5 w-1.5 rounded-full bg-sand-300"></span> Nonaktif</span>
                                                @endif
                                            </td>
                                        @endif
                                        <td class="px-5 py-3 text-sand-600">{{ $line['tier']->label() }}</td>
                                        <td class="px-5 py-3 text-right tnum text-sand-800">{{ $line['p'] ? 'Rp '.number_format($line['p']->harga_diferd, 0, ',', '.') : '—' }}</td>
                                        <td class="px-5 py-3 text-right tnum text-sand-800">{{ $line['p'] && $line['p']->harga_tm420 !== null ? 'Rp '.number_format($line['p']->harga_tm420, 0, ',', '.') : '—' }}</td>
                                        <td class="px-5 py-3 text-right tnum">
                                            @if ($line['p'] && $line['p']->markup !== null)
                                                <span class="font-medium text-brand-700">+Rp {{ number_format($line['p']->markup, 0, ',', '.') }}</span>
                                            @else
                                                <span class="text-sand-400">—</span>
                                            @endif
                                        </td>
                                        @if ($loop->first)
                                            <td rowspan="2" class="align-top px-5 py-3.5 border-s border-sand-100">
                                                <div class="flex items-center justify-end gap-1">
                                                    <a href="{{ route('categories.edit', $cat) }}" class="rounded-md p-1.5 text-sand-500 hover:bg-sand-100 hover:text-sand-800" title="Ubah">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                                                    </a>
                                                    <form method="POST" action="{{ route('categories.destroy', $cat) }}" onsubmit="return confirm('Hapus kategori {{ $cat->nama }}? Harga terkait ikut terhapus.');">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="rounded-md p-1.5 text-sand-500 hover:bg-red-50 hover:text-red-700" title="Hapus">
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

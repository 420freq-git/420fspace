<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-sand-900">Brand</h1>
                <p class="text-xs text-sand-500">Kelola brand yang diproduksi lewat Diferd.</p>
            </div>
            <a href="{{ route('brands.create') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-800 transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Tambah brand
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            @if ($brands->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-sand-500">Belum ada brand.</p>
                    <a href="{{ route('brands.create') }}" class="mt-3 inline-block text-sm font-medium text-brand-700 hover:underline">Tambah brand pertama</a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Brand</th>
                                <th class="px-5 py-3 font-semibold">Tipe</th>
                                <th class="px-5 py-3 font-semibold">Kode</th>
                                <th class="px-5 py-3 font-semibold">Status</th>
                                <th class="px-5 py-3 font-semibold">Akun</th>
                                <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($brands as $brand)
                                <tr class="hover:bg-sand-50/60">
                                    <td class="px-5 py-3.5 font-medium text-sand-900">{{ $brand->nama }}</td>
                                    <td class="px-5 py-3.5">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $brand->tipe->badgeClasses() }}">
                                            {{ $brand->tipe->label() }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-sand-600 tnum">{{ $brand->kode ?? '—' }}</td>
                                    <td class="px-5 py-3.5">
                                        @if ($brand->aktif)
                                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-brand-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-brand-600"></span> Aktif
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-sand-400">
                                                <span class="h-1.5 w-1.5 rounded-full bg-sand-300"></span> Nonaktif
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-sand-600 tnum">{{ $brand->users_count }}</td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center justify-end gap-1">
                                            <a href="{{ route('brands.edit', $brand) }}"
                                               class="rounded-md p-1.5 text-sand-500 hover:bg-sand-100 hover:text-sand-800" title="Ubah">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                                            </a>
                                            <form method="POST" action="{{ route('brands.destroy', $brand) }}"
                                                  onsubmit="return confirm('Hapus brand {{ $brand->nama }}?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="rounded-md p-1.5 text-sand-500 hover:bg-red-50 hover:text-red-700" title="Hapus">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                                </button>
                                            </form>
                                        </div>
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

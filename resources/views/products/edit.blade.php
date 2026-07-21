<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-sand-900">Ubah produk</h1>
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6 sm:p-8">
            <form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('products._fields')

                <div class="mt-8 flex items-center justify-end gap-3">
                    <a href="{{ route('products.index') }}" class="inline-flex items-center rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Batal</a>
                    <button type="submit" class="inline-flex items-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">Simpan perubahan</button>
                </div>
            </form>
        </div>

        {{-- File tersimpan (di luar form utama agar tombol hapus tidak nested) --}}
        @if ($product->files->isNotEmpty())
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6 sm:p-8">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400 mb-4">File tersimpan</h2>
                @foreach ([\App\Enums\ProductFileType::Mockup, \App\Enums\ProductFileType::Desain, \App\Enums\ProductFileType::Mentahan] as $type)
                    @php $files = $product->filesOfType($type); @endphp
                    @if ($files->isNotEmpty())
                        <div class="mb-5 last:mb-0">
                            <p class="text-xs font-medium text-sand-600 mb-2">{{ $type->label() }}</p>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                @foreach ($files as $file)
                                    <div class="group relative rounded-lg border border-sand-200 overflow-hidden">
                                        @if ($file->is_image)
                                            <img src="{{ $file->url }}" alt="{{ $file->nama_asli }}" class="h-28 w-full object-cover bg-sand-100">
                                        @else
                                            <div class="h-28 w-full grid place-items-center bg-sand-50 text-sand-400">
                                                <div class="text-center">
                                                    <svg class="h-8 w-8 mx-auto" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                                    <span class="mt-1 block text-[11px] font-semibold tnum">{{ $file->ext }}</span>
                                                </div>
                                            </div>
                                        @endif
                                        <div class="px-2 py-1.5 flex items-center gap-1.5">
                                            <span class="min-w-0 flex-1 truncate text-[11px] text-sand-500" title="{{ $file->nama_asli }}">{{ $file->nama_asli }}</span>
                                            <a href="{{ route('product-files.download', $file) }}" class="shrink-0 text-brand-700 hover:text-brand-800" title="Download">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-6L12 15m0 0l4.5-4.5M12 15V3"/></svg>
                                            </a>
                                        </div>
                                        <form method="POST" action="{{ route('product-files.destroy', $file) }}"
                                              onsubmit="return confirm('Hapus file ini?');"
                                              class="absolute top-1.5 right-1.5">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-md bg-white/90 p-1 text-sand-500 shadow-sm hover:text-red-700" title="Hapus file">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>

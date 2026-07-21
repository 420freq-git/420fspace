@use('App\Enums\BrandType')

<div class="space-y-5">
    {{-- Nama --}}
    <div>
        <label for="nama" class="block text-sm font-medium text-sand-700">Nama brand</label>
        <input type="text" name="nama" id="nama" value="{{ old('nama', $brand->nama) }}" required autofocus
               class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600"
               placeholder="mis. TM420">
        @error('nama') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    {{-- Kode --}}
    <div>
        <label for="kode" class="block text-sm font-medium text-sand-700">Kode singkat <span class="text-sand-400 font-normal">(opsional)</span></label>
        <input type="text" name="kode" id="kode" value="{{ old('kode', $brand->kode) }}" maxlength="10"
               class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600"
               placeholder="mis. TM">
        <p class="mt-1.5 text-xs text-sand-400">Dipakai untuk nomor PO, mis. <span class="font-medium">PO.TM.04.26.01</span>.</p>
        @error('kode') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    {{-- Tipe --}}
    <div>
        <span class="block text-sm font-medium text-sand-700 mb-2">Tipe brand</span>
        <div class="grid sm:grid-cols-2 gap-3">
            @foreach (BrandType::cases() as $type)
                <label class="cursor-pointer">
                    <input type="radio" name="tipe" value="{{ $type->value }}" class="peer sr-only"
                           @checked(old('tipe', $brand->tipe?->value) === $type->value)>
                    <div class="h-full rounded-lg border border-sand-300 bg-white p-4 transition
                                peer-checked:border-brand-600 peer-checked:ring-2 peer-checked:ring-brand-600/25 hover:border-sand-400">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $type->badgeClasses() }}">{{ $type->label() }}</span>
                        </div>
                        <p class="mt-2 text-xs text-sand-500 leading-relaxed">{{ $type->description() }}</p>
                    </div>
                </label>
            @endforeach
        </div>
        @error('tipe') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    {{-- Aktif --}}
    <div>
        <label class="inline-flex items-center gap-2.5">
            <input type="checkbox" name="aktif" value="1" @checked(old('aktif', $brand->aktif ?? true))
                   class="rounded border-sand-300 text-brand-700 focus:ring-brand-600">
            <span class="text-sm text-sand-700">Brand aktif</span>
        </label>
    </div>
</div>

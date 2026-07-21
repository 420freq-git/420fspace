@php $tm = \App\Enums\Role::Tm420->value; @endphp
<div x-data="{ role: '{{ old('role', $user->role->value ?? '') }}' }" class="space-y-5">
    <div>
        <label class="block text-sm font-medium text-sand-700">Nama</label>
        <input type="text" name="name" value="{{ old('name', $user->name) }}" required
               class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-sand-700">Email</label>
        <input type="email" name="email" value="{{ old('email', $user->email) }}" required
               class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-sand-700">No. HP (WhatsApp) <span class="text-sand-400 font-normal">(opsional)</span></label>
        <input type="text" name="no_hp" value="{{ old('no_hp', $user->no_hp) }}" placeholder="08xxxxxxxxxx"
               class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600 tnum">
        <p class="mt-1 text-xs text-sand-400">Untuk pengingat WhatsApp. Kosongkan bila tak ingin dikirimi.</p>
        @error('no_hp') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="grid sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-medium text-sand-700">Peran</label>
            <select name="role" x-model="role" required class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                <option value="">— pilih peran —</option>
                @foreach (\App\Enums\Role::cases() as $r)
                    <option value="{{ $r->value }}">{{ $r->label() }}</option>
                @endforeach
            </select>
            @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div x-show="role === '{{ $tm }}'" x-cloak>
            <label class="block text-sm font-medium text-sand-700">Brand (untuk TM420)</label>
            <select name="brand_id" class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                <option value="">— pilih brand —</option>
                @foreach ($brands as $b)
                    <option value="{{ $b->id }}" @selected(old('brand_id', $user->brand_id) == $b->id)>{{ $b->nama }}</option>
                @endforeach
            </select>
            @error('brand_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>
</div>

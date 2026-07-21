<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-sand-900">Ubah akun — {{ $user->name }}</h1>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6 sm:p-8">
            <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-5">
                @csrf @method('PUT')
                @include('users._fields')
                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('users.index') }}" class="inline-flex items-center rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Batal</a>
                    <button type="submit" class="inline-flex items-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Simpan</button>
                </div>
            </form>
        </div>

        {{-- Reset password --}}
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6 sm:p-8">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400 mb-4">Reset password</h2>
            <form method="POST" action="{{ route('users.reset', $user) }}" class="grid sm:grid-cols-3 gap-4 items-end">
                @csrf @method('PATCH')
                <div>
                    <label class="block text-xs font-medium text-sand-600">Password baru</label>
                    <input type="password" name="password" required autocomplete="new-password"
                           class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                    @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-sand-600">Ulangi</label>
                    <input type="password" name="password_confirmation" required autocomplete="new-password"
                           class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                </div>
                <button type="submit" class="rounded-lg bg-sand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-sand-900">Reset password</button>
            </form>
            <p class="mt-2 text-xs text-sand-400">Untuk mengganti password sendiri, gunakan menu Profil.</p>
        </div>
    </div>
</x-app-layout>

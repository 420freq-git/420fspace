<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-sand-900">Tambah akun</h1>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6 sm:p-8">
            <form method="POST" action="{{ route('users.store') }}" class="space-y-5">
                @csrf
                @include('users._fields')

                <div class="grid sm:grid-cols-2 gap-5 border-t border-sand-100 pt-5">
                    <div>
                        <label class="block text-sm font-medium text-sand-700">Password</label>
                        <input type="password" name="password" required autocomplete="new-password"
                               class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-sand-700">Ulangi password</label>
                        <input type="password" name="password_confirmation" required autocomplete="new-password"
                               class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('users.index') }}" class="inline-flex items-center rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Batal</a>
                    <button type="submit" class="inline-flex items-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Buat akun</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

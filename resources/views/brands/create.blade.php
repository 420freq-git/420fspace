<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-sand-900">Tambah brand</h1>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-6 sm:p-8">
            <form method="POST" action="{{ route('brands.store') }}">
                @csrf
                @include('brands._fields')

                <div class="mt-8 flex items-center justify-end gap-3">
                    <a href="{{ route('brands.index') }}" class="inline-flex items-center rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100">Batal</a>
                    <button type="submit" class="inline-flex items-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-800">Simpan brand</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-lg font-semibold text-sand-900">Pengguna</h1>
                <p class="text-xs text-sand-500">Kelola akun staf, atur peran &amp; reset password.</p>
            </div>
            <a href="{{ route('users.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Tambah akun
            </a>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                            <th class="px-5 py-3 font-semibold">Nama</th>
                            <th class="px-5 py-3 font-semibold">Email</th>
                            <th class="px-5 py-3 font-semibold">Peran</th>
                            <th class="px-5 py-3 font-semibold">Brand</th>
                            <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @foreach ($users as $u)
                            <tr class="hover:bg-sand-50/50">
                                <td class="px-5 py-3.5 font-medium text-sand-900">{{ $u->name }}
                                    @if ($u->id === auth()->id())<span class="ml-1 text-xs text-sand-400">(Anda)</span>@endif
                                </td>
                                <td class="px-5 py-3.5 text-sand-600">{{ $u->email }}</td>
                                <td class="px-5 py-3.5"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $u->role->badgeClasses() }}">{{ $u->role->label() }}</span></td>
                                <td class="px-5 py-3.5 text-sand-600">{{ $u->brand->nama ?? '—' }}</td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('users.edit', $u) }}" class="rounded-md p-1.5 text-sand-500 hover:bg-sand-100 hover:text-sand-800" title="Ubah / reset password">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                                        </a>
                                        @if ($u->id !== auth()->id())
                                            <form method="POST" action="{{ route('users.destroy', $u) }}" onsubmit="return confirm('Hapus akun {{ $u->name }}?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="rounded-md p-1.5 text-sand-500 hover:bg-red-50 hover:text-red-700" title="Hapus">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

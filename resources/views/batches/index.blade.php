<x-app-layout>
    @php
        $isAdmin = auth()->user()->isAdmin();
        $bolehBuat = $isAdmin || in_array(auth()->user()->role, [\App\Enums\Role::Tm420, \App\Enums\Role::Voojah], true);
    @endphp
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-sand-900">Batch / PO</h1>
                <p class="text-xs text-sand-500">Master PO produksi ke Diferd.</p>
            </div>
            @if ($bolehBuat)
                <a href="{{ route('batches.create') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-800 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    {{ $isAdmin ? 'Buat batch' : 'Ajukan batch' }}
                </a>
            @endif
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            @if ($batches->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-sand-500">Belum ada batch.</p>
                    @if ($bolehBuat)
                        <a href="{{ route('batches.create') }}" class="mt-3 inline-block text-sm font-medium text-brand-700 hover:underline">{{ $isAdmin ? 'Buat batch pertama' : 'Ajukan batch pertama' }}</a>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Nomor batch</th>
                                <th class="px-5 py-3 font-semibold">Brand</th>
                                <th class="px-5 py-3 font-semibold">Tgl order</th>
                                <th class="px-5 py-3 font-semibold">Deadline produksi</th>
                                <th class="px-5 py-3 font-semibold text-center">PO</th>
                                <th class="px-5 py-3 font-semibold text-center">Terkirim</th>
                                <th class="px-5 py-3 font-semibold text-center">Status</th>
                                <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($batches as $batch)
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3.5">
                                        <a href="{{ route('batches.show', $batch) }}" class="font-medium text-sand-900 hover:text-brand-700 tnum">{{ $batch->nomor_batch }}</a>
                                        <div class="text-xs text-sand-400">{{ $batch->jenis_order->label() }} &middot; {{ $batch->type_payment->label() }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-sand-700">{{ $batch->brand->nama }}</td>
                                    <td class="px-5 py-3.5 text-sand-600 tnum">{{ $batch->tanggal_order->format('d/m/Y') }}</td>
                                    <td class="px-5 py-3.5">
                                        @if ($batch->deadline_produksi)
                                            <div class="text-sand-600 tnum">{{ $batch->deadline_produksi->format('d/m/Y') }}</div>
                                            @if ($batch->status->value !== 'lunas')
                                                @php $sp = $batch->sisa_produksi; @endphp
                                                @if ($sp < 0)
                                                    <span class="text-xs font-medium text-red-700">lewat {{ abs($sp) }} hari</span>
                                                @elseif ($sp <= 14)
                                                    <span class="text-xs font-medium text-red-700">{{ $sp }} hari lagi</span>
                                                @else
                                                    <span class="text-xs text-sand-400">{{ $sp }} hari lagi</span>
                                                @endif
                                            @endif
                                        @else
                                            <div class="text-xs text-sand-400">belum diset</div>
                                        @endif
                                        <div class="text-[11px] text-sand-400 mt-0.5">Pelunasan: {{ $batch->deadline->format('d/m/Y') }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-center tnum text-sand-700">{{ $batch->purchaseOrders->count() }}</td>
                                    <td class="px-5 py-3.5 text-center">
                                        @php $pg = $progres[$batch->id] ?? ['terkirim' => 0, 'diproduksi' => 0]; @endphp
                                        <div class="tnum text-sand-800">{{ number_format($pg['terkirim'], 0, ',', '.') }}</div>
                                        <div class="text-xs text-sand-400">dari {{ number_format($pg['diproduksi'], 0, ',', '.') }} pcs</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $batch->status->badgeClasses() }}">{{ $batch->status->label() }}</span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center justify-end gap-1">
                                            <a href="{{ route('batches.show', $batch) }}" class="rounded-md p-1.5 text-sand-500 hover:bg-sand-100 hover:text-sand-800" title="Detail">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            </a>
                                            <a href="{{ route('batches.pdf', $batch) }}" target="_blank" class="rounded-md p-1.5 text-sand-500 hover:bg-sand-100 hover:text-sand-800" title="Export PDF">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                            </a>
                                            @if ($isAdmin)
                                                <a href="{{ route('batches.edit', $batch) }}" class="rounded-md p-1.5 text-sand-500 hover:bg-sand-100 hover:text-sand-800" title="Ubah">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                                                </a>
                                                <form method="POST" action="{{ route('batches.destroy', $batch) }}" onsubmit="return confirm('Hapus batch {{ $batch->nomor_batch }} beserta seluruh PO-nya?');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="rounded-md p-1.5 text-sand-500 hover:bg-red-50 hover:text-red-700" title="Hapus">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
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
            @endif
        </div>
    </div>
</x-app-layout>

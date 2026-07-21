<x-app-layout>
    @php
        $typeLabels = [
            'Order' => 'Pesanan', 'PurchaseOrder' => 'PO produksi', 'Batch' => 'Batch',
            'VendorLedger' => 'Pembayaran vendor', 'BrandLedger' => 'Penerimaan TM',
            'Penarikan' => 'Penarikan', 'Invoice' => 'Invoice', 'Product' => 'Produk',
        ];
        $fmtVal = fn ($v) => $v === null || $v === '' ? '—' : \Illuminate\Support\Str::limit((string) $v, 40);
    @endphp
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-semibold text-sand-900">Audit log</h1>
            <p class="text-xs text-sand-500">Riwayat perubahan — siapa mengubah apa &amp; kapan.</p>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-4">
        {{-- Filter --}}
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div class="relative flex-1 min-w-[200px] max-w-xs">
                <input type="search" name="cari" value="{{ $filters['cari'] ?? '' }}" placeholder="Cari objek atau pelaku…"
                       class="w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
            </div>
            <select name="tipe" class="rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                <option value="">Semua objek</option>
                @foreach ($types as $t)
                    <option value="{{ $t }}" @selected(($filters['tipe'] ?? '') === $t)>{{ $typeLabels[$t] ?? $t }}</option>
                @endforeach
            </select>
            <select name="event" class="rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                <option value="">Semua aksi</option>
                @foreach (['created' => 'Dibuat', 'updated' => 'Diubah', 'deleted' => 'Dihapus'] as $v => $l)
                    <option value="{{ $v }}" @selected(($filters['event'] ?? '') === $v)>{{ $l }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-sand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-sand-900">Filter</button>
            @if (array_filter($filters))
                <a href="{{ route('audit.index') }}" class="text-sm text-sand-500 hover:text-sand-800">Reset</a>
            @endif
        </form>

        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            @if ($logs->isEmpty())
                <div class="p-12 text-center text-sand-500">Belum ada catatan perubahan.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Waktu</th>
                                <th class="px-5 py-3 font-semibold">Pelaku</th>
                                <th class="px-5 py-3 font-semibold">Aksi</th>
                                <th class="px-5 py-3 font-semibold">Objek</th>
                                <th class="px-5 py-3 font-semibold">Perubahan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($logs as $log)
                                @php $role = \App\Enums\Role::tryFrom($log->user_role); @endphp
                                <tr class="hover:bg-sand-50/50 align-top">
                                    <td class="px-5 py-3.5 text-sand-500 tnum whitespace-nowrap">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="px-5 py-3.5">
                                        <div class="font-medium text-sand-800">{{ $log->user_name }}</div>
                                        @if ($role)<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $role->badgeClasses() }}">{{ $role->label() }}</span>@endif
                                    </td>
                                    <td class="px-5 py-3.5"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $log->eventClasses() }}">{{ $log->eventLabel() }}</span></td>
                                    <td class="px-5 py-3.5">
                                        <div class="text-sand-800">{{ $log->label }}</div>
                                        <div class="text-[11px] text-sand-400">{{ $typeLabels[$log->auditable_type] ?? $log->auditable_type }}</div>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        @if ($log->event === 'updated' && $log->changes)
                                            <div class="space-y-0.5">
                                                @foreach ($log->changes as $field => $pair)
                                                    <div class="text-xs">
                                                        <span class="font-medium text-sand-600">{{ $field }}:</span>
                                                        <span class="text-sand-400 line-through">{{ $fmtVal($pair[0]) }}</span>
                                                        <span class="text-sand-300">→</span>
                                                        <span class="text-sand-800">{{ $fmtVal($pair[1]) }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-xs text-sand-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($logs->hasPages())
            <div>{{ $logs->links() }}</div>
        @endif
    </div>
</x-app-layout>

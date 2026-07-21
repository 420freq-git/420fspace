<x-app-layout>
    @php $isAdmin = auth()->user()->isAdmin(); @endphp
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-sand-900">Kelola Pesanan</h1>
                <p class="text-xs text-sand-500">Pesanan marketplace &amp; manual — siklus dipesan → dikirim → lunas.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('orders.import.form') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-800 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Upload marketplace
                </a>
                <a href="{{ route('orders.settlement.form') }}"
                   class="inline-flex items-center gap-2 rounded-lg border border-brand-300 bg-white px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                    Upload settlement
                </a>
                <a href="{{ route('orders.create') }}"
                   class="inline-flex items-center gap-2 rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-sand-700 hover:bg-sand-100 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Input manual
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-5"
         x-data="{
            sel: [],
            applyBulk(status) {
                if (!this.sel.length) { alert('Pilih pesanan dulu.'); return; }
                const f = document.createElement('form');
                f.method = 'POST'; f.action = '{{ route('orders.bulk-status') }}';
                const add = (n, v) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; f.appendChild(i); };
                add('_token', '{{ csrf_token() }}');
                add('status', status);
                this.sel.forEach(id => add('order_ids[]', id));
                document.body.appendChild(f); f.submit();
            }
         }">

        {{-- Banner monitoring (hanya bila ada) --}}
        @if ($perluDicek > 0)
            <div class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                <svg class="h-5 w-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                <p class="text-sm text-amber-800"><span class="font-semibold">{{ $perluDicek }} pesanan</span> belum cair &gt; {{ 12 }} hari — perlu dicek di Seller Center.</p>
                <a href="{{ route('monitoring.cek') }}" class="ms-auto text-sm font-medium text-amber-800 hover:underline whitespace-nowrap">Buka monitoring →</a>
            </div>
        @endif
        @if ($returPending > 0)
            <div class="flex items-center gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">
                <svg class="h-5 w-5 shrink-0 text-blue-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                <p class="text-sm text-blue-800"><span class="font-semibold">{{ $returPending }} pesanan retur</span> menunggu barang fisik kembali.</p>
                <a href="{{ route('monitoring.kembali') }}" class="ms-auto text-sm font-medium text-blue-800 hover:underline whitespace-nowrap">Buka monitoring →</a>
            </div>
        @endif

        {{-- Filter --}}
        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border border-sand-200 bg-white p-4">
            <div class="flex-1 min-w-[180px]">
                <label class="block text-xs font-medium text-sand-600">Cari Order ID / Resi</label>
                <input type="text" name="cari" value="{{ request('cari') }}" class="mt-1 block w-full rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
            </div>
            <div>
                <label class="block text-xs font-medium text-sand-600">Channel</label>
                <select name="channel" class="mt-1 rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                    <option value="">Semua</option>
                    @foreach ($marketplaces as $mp)<option value="{{ $mp->value }}" @selected(request('channel') === $mp->value)>{{ $mp->label() }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-sand-600">Status</label>
                <select name="status" class="mt-1 rounded-lg border-sand-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                    <option value="">Semua</option>
                    @foreach ($statuses as $st)<option value="{{ $st->value }}" @selected(request('status') === $st->value)>{{ $st->label() }}</option>@endforeach
                </select>
            </div>
            <label class="inline-flex items-center gap-2 pb-2">
                <input type="checkbox" name="belum_cair" value="1" @checked(request()->boolean('belum_cair')) class="rounded border-sand-300 text-brand-700 focus:ring-brand-600">
                <span class="text-sm text-sand-700">Hanya belum cair</span>
            </label>
            <button type="submit" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Filter</button>
            @if (request()->hasAny(['cari', 'channel', 'status', 'belum_cair', 'dari', 'sampai']))
                <a href="{{ route('orders.index') }}" class="px-2 py-2 text-sm text-sand-500 hover:text-sand-800">Reset</a>
            @endif
        </form>

        {{-- Bulk action bar --}}
        <div x-show="sel.length" x-cloak class="flex items-center gap-3 rounded-xl border border-brand-300 bg-brand-50 px-4 py-3">
            <span class="text-sm font-medium text-brand-800"><span x-text="sel.length"></span> pesanan terpilih</span>
            <div class="ms-auto flex items-center gap-2">
                <button type="button" @click="applyBulk('dikirim')" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.125-.504 1.09-1.124M14.25 6H2.25m12 0v9.75M14.25 6l4.5 6h-4.5"/></svg>
                    Tandai Dikirim
                </button>
                <button type="button" @click="sel = []" class="text-sm text-sand-500 hover:text-sand-800">Batal</button>
            </div>
        </div>

        {{-- Tabel --}}
        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            @if ($orders->isEmpty())
                <div class="p-12 text-center text-sand-500">Tidak ada pesanan.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="ps-5 py-3 w-8">
                                    <input type="checkbox" class="rounded border-sand-300 text-brand-700 focus:ring-brand-600"
                                           @change="sel = $event.target.checked ? [...$root.querySelectorAll('.rowcheck')].map(c => c.value) : []">
                                </th>
                                <th class="px-5 py-3 font-semibold">Tgl</th>
                                <th class="px-5 py-3 font-semibold">Order ID / Resi</th>
                                <th class="px-5 py-3 font-semibold">Produk</th>
                                <th class="px-5 py-3 font-semibold">Channel</th>
                                <th class="px-5 py-3 font-semibold">Status</th>
                                <th class="px-5 py-3 font-semibold text-center">Dana</th>
                                <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($orders as $order)
                                <tr class="hover:bg-sand-50/50 align-top">
                                    <td class="ps-5 py-3.5">
                                        <input type="checkbox" class="rowcheck rounded border-sand-300 text-brand-700 focus:ring-brand-600" x-model="sel" value="{{ $order->id }}">
                                    </td>
                                    <td class="px-5 py-3.5 text-sand-600 tnum whitespace-nowrap">{{ $order->tanggal_pesanan->format('d/m/Y') }}</td>
                                    <td class="px-5 py-3.5">
                                        <div class="font-medium text-sand-900 tnum">{{ $order->nomor_pesanan }}</div>
                                        <div class="text-xs text-sand-400 tnum">{{ $order->resi ?? '—' }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-sand-700">
                                        @foreach ($order->items as $it)
                                            <div class="text-xs">{{ $it->qty }}× {{ $it->product->nama_artikel }} <span class="text-sand-400">({{ $it->ukuran->value }})</span></div>
                                        @endforeach
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $order->marketplace->badgeClasses() }}">{{ $order->marketplace->label() }}</span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $order->status->badgeClasses() }}">{{ $order->status->label() }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        @if ($order->status->value === 'lunas')
                                            <span class="text-xs font-medium text-brand-700">Cair</span>
                                        @elseif ($order->status->value === 'batal')
                                            <span class="text-xs text-sand-400">—</span>
                                        @else
                                            <span class="text-xs text-amber-700">Belum cair</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <form method="POST" action="{{ route('orders.destroy', $order) }}" onsubmit="return confirm('Hapus pesanan {{ $order->nomor_pesanan }}? Stok dikembalikan.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-md p-1.5 text-sand-500 hover:bg-red-50 hover:text-red-700" title="Hapus">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-4 border-t border-sand-100">{{ $orders->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>

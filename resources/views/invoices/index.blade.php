<x-app-layout>
    @php $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-lg font-semibold text-sand-900">Invoice TM</h1>
                <p class="text-xs text-sand-500">Tagihan ke TM420 atas pesanan yang sudah cair.</p>
            </div>
            @if ($isAdmin)
                <form method="POST" action="{{ route('invoices.generate') }}">
                    @csrf
                    <button type="submit" {{ $belumDitagih === 0 ? 'disabled' : '' }}
                            class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm {{ $belumDitagih > 0 ? 'bg-brand-700 hover:bg-brand-800' : 'bg-sand-300 cursor-not-allowed' }}">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        Buat invoice ({{ $belumDitagih }} pesanan)
                    </button>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                <p class="text-sm text-sand-500">Total ditagih</p>
                <p class="mt-1 text-2xl font-semibold text-sand-900 tnum">{{ $fmt($totalDitagih) }}</p>
            </div>
            <div class="rounded-xl border border-brand-200 bg-brand-50 shadow-sm p-5">
                <p class="text-sm text-brand-700">Sudah dibayar</p>
                <p class="mt-1 text-2xl font-semibold text-brand-700 tnum">{{ $fmt($totalDibayar) }}</p>
            </div>
            <div class="rounded-xl border {{ $totalBelum > 0 ? 'border-amber-200 bg-amber-50' : 'border-sand-200 bg-white' }} shadow-sm p-5">
                <p class="text-sm {{ $totalBelum > 0 ? 'text-amber-700' : 'text-sand-500' }}">Belum dibayar</p>
                <p class="mt-1 text-2xl font-semibold {{ $totalBelum > 0 ? 'text-amber-700' : 'text-sand-900' }} tnum">{{ $fmt($totalBelum) }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-sand-200 bg-white shadow-sm overflow-hidden">
            @if ($invoices->isEmpty())
                <div class="p-12 text-center text-sand-500">
                    Belum ada invoice.
                    @if ($isAdmin && $belumDitagih > 0)<br>Ada {{ $belumDitagih }} pesanan cair siap ditagihkan — klik "Buat invoice".@endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-sand-500">
                                <th class="px-5 py-3 font-semibold">Nomor</th>
                                <th class="px-5 py-3 font-semibold">Brand</th>
                                <th class="px-5 py-3 font-semibold">Terbit</th>
                                <th class="px-5 py-3 font-semibold text-center">Pesanan</th>
                                <th class="px-5 py-3 font-semibold text-right">Total</th>
                                <th class="px-5 py-3 font-semibold text-center">Status</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach ($invoices as $inv)
                                <tr class="hover:bg-sand-50/50">
                                    <td class="px-5 py-3.5"><a href="{{ route('invoices.show', $inv) }}" class="font-medium text-sand-900 hover:text-brand-700 tnum">{{ $inv->nomor }}</a></td>
                                    <td class="px-5 py-3.5 text-sand-600">{{ $inv->brand->nama }}</td>
                                    <td class="px-5 py-3.5 text-sand-600 tnum">{{ $inv->tanggal_terbit->format('d/m/Y') }}</td>
                                    <td class="px-5 py-3.5 text-center tnum text-sand-700">{{ $inv->orders->count() }}</td>
                                    <td class="px-5 py-3.5 text-right tnum text-sand-800">{{ $fmt($inv->total) }}</td>
                                    <td class="px-5 py-3.5 text-center">
                                        @if ($inv->isLunas())
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-brand-100 text-brand-800">Lunas</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Belum dibayar</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-right"><a href="{{ route('invoices.show', $inv) }}" class="text-sm font-medium text-brand-700 hover:underline">Detail</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

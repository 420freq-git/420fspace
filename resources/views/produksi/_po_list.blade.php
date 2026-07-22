{{-- Daftar PO satu batch (dipakai kartu berjalan & detail batch selesai). --}}
<div class="divide-y divide-sand-100">
    @foreach ($batch->purchaseOrders as $po)
        @php
            $tahap = $po->tahap;
            $hari = $po->hari_di_tahap;
            $mandek = ! $tahap->isReady() && $hari !== null && $hari >= 5;
        @endphp
        <div class="px-5 py-4 flex flex-wrap items-center gap-x-5 gap-y-3">
            <div class="w-56 min-w-0">
                <p class="text-sm font-medium text-sand-800 truncate">{{ $po->product->nama_artikel }}</p>
                <a href="{{ route('purchase-orders.riwayat', [$batch, $po]) }}"
                   class="text-xs text-brand-700 hover:underline tnum inline-flex items-center gap-1">
                    {{ $po->nomor_po }}
                    <svg class="h-3 w-3 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </a>
            </div>

            {{-- Stepper 14 tahap --}}
            <div class="flex-1 min-w-[200px]">
                <div class="flex gap-0.5" title="{{ $tahap->step() }}/{{ count(\App\Enums\TahapProduksi::cases()) }} — {{ $tahap->label() }}">
                    @foreach (\App\Enums\TahapProduksi::cases() as $t)
                        <div class="h-1.5 flex-1 rounded-full {{ $t->step() <= $tahap->step() ? $tahap->barClass() : 'bg-sand-100' }}"></div>
                    @endforeach
                </div>
                <div class="mt-1.5 flex items-center gap-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $tahap->badgeClasses() }}">{{ $tahap->step() }}. {{ $tahap->label() }}</span>
                    @if ($tahap->isDone())
                        <span class="text-xs text-brand-600">✓ tuntas</span>
                    @elseif ($hari !== null)
                        <span class="text-xs {{ $mandek ? 'text-amber-700 font-medium' : 'text-sand-400' }}">
                            {{ $hari === 0 ? 'update hari ini' : $hari.' hari di tahap ini' }}{{ $mandek ? ' · mandek?' : '' }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- Aksi majukan tahap --}}
            @if ($canUpdate && ! $tahap->isDone())
                <form method="POST" action="{{ route('purchase-orders.status', [$batch, $po]) }}" class="shrink-0">
                    @csrf @method('PATCH')
                    <select name="tahap" onchange="try{sessionStorage.setItem('poScroll',window.scrollY);}catch(e){} this.form.submit()"
                            class="rounded-lg border-sand-300 text-xs py-1.5 pr-8 focus:border-brand-600 focus:ring-brand-600">
                        @foreach (\App\Enums\TahapProduksi::cases() as $t)
                            <option value="{{ $t->value }}" @selected($po->tahap === $t)>{{ $t->step() }}. {{ $t->label() }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            {{-- Catatan vendor --}}
            @if ($po->catatan_vendor)
                <div class="w-full flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-100 px-3 py-2">
                    <svg class="h-4 w-4 shrink-0 text-amber-500 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.019z"/></svg>
                    <p class="text-xs text-amber-800 whitespace-pre-line"><span class="font-medium">Catatan vendor:</span> {{ $po->catatan_vendor }}</p>
                </div>
            @endif
        </div>
    @endforeach
</div>

@php
    use App\Enums\JenisOrder;
    use App\Enums\TypePayment;
    use App\Enums\BatchStatus;
    $tglVal = old('tanggal_order', optional($batch->tanggal_order)->format('Y-m-d'));
@endphp

<div class="space-y-5" x-data="{
        tgl: '{{ $tglVal }}',
        payment: '{{ old('type_payment', $batch->type_payment?->value ?? 'termin') }}',
        deadline(){
            if(!this.tgl) return '—';
            const d = new Date(this.tgl); d.setFullYear(d.getFullYear()+1);
            return d.toLocaleDateString('id-ID', {day:'2-digit', month:'long', year:'numeric'});
        }
     }">

    <div class="grid sm:grid-cols-2 gap-5">
        <div>
            <label for="brand_id" class="block text-sm font-medium text-sand-700">Brand</label>
            <select name="brand_id" id="brand_id" required class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600">
                <option value="">— pilih brand —</option>
                @foreach ($brands as $b)
                    <option value="{{ $b->id }}" @selected(old('brand_id', $batch->brand_id) == $b->id)>{{ $b->nama }}</option>
                @endforeach
            </select>
            @error('brand_id') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="tanggal_order" class="block text-sm font-medium text-sand-700">Tanggal order</label>
            <input type="date" name="tanggal_order" id="tanggal_order" x-model="tgl" required
                   class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600">
            <p class="mt-1.5 text-xs text-sand-500">Deadline pelunasan (+1 tahun): <span class="font-medium text-sand-700" x-text="deadline()"></span></p>
            @error('tanggal_order') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="deadline_produksi" class="block text-sm font-medium text-sand-700">Deadline produksi <span class="text-sand-400 font-normal">(opsional)</span></label>
            <input type="date" name="deadline_produksi" id="deadline_produksi" value="{{ old('deadline_produksi', optional($batch->deadline_produksi)->format('Y-m-d')) }}"
                   class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600">
            <p class="mt-1.5 text-xs text-sand-500">Batas waktu vendor menyelesaikan produksi.</p>
            @error('deadline_produksi') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="jenis_order" class="block text-sm font-medium text-sand-700">Jenis order</label>
            <select name="jenis_order" id="jenis_order" required class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600">
                @foreach (JenisOrder::cases() as $jo)
                    <option value="{{ $jo->value }}" @selected(old('jenis_order', $batch->jenis_order?->value ?? 'full_order') === $jo->value)>{{ $jo->label() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="type_payment" class="block text-sm font-medium text-sand-700">Type payment</label>
            <select name="type_payment" id="type_payment" x-model="payment" required class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600">
                @foreach (TypePayment::cases() as $tp)
                    <option value="{{ $tp->value }}" @selected(old('type_payment', $batch->type_payment?->value ?? 'termin') === $tp->value)>{{ $tp->label() }}</option>
                @endforeach
            </select>
        </div>

        {{-- DP hanya untuk cash: brand bayar sebagian di muka saat disetujui, sisa saat siap kirim.
             Kosong = cash penuh di muka (perilaku biasa). --}}
        <div x-show="payment === 'cash'" x-cloak>
            <label for="dp_nominal" class="block text-sm font-medium text-sand-700">Down payment — nominal (opsional)</label>
            <div class="mt-1 relative">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-sand-400">Rp</span>
                <input type="number" name="dp_nominal" id="dp_nominal" min="1" step="1000"
                       value="{{ old('dp_nominal', $batch->dp_nominal) }}" placeholder="mis. 5000000"
                       class="block w-full rounded-lg border-sand-300 pl-9 focus:border-brand-600 focus:ring-brand-600 tnum">
            </div>
            <p class="mt-1 text-xs text-sand-500">Jumlah (Rp) yang ditagih ke brand di muka saat batch disetujui; sisanya saat semua PO siap kirim. Harus lebih kecil dari total tagihan. Kosongkan untuk bayar penuh di muka.</p>
            @error('dp_nominal') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Deposit tidak lagi diinput per batch: modal produksi terjadi sekali di awal kerja
             sama dan dikelola global di halaman Settlement (kartu "Modal di vendor"). --}}

        {{-- Status hanya boleh diubah 420F: kalau TM bisa menyetel sendiri, alur persetujuan
             jadi tidak berarti (TM tinggal pilih "Aktif" tanpa lewat 420F). --}}
        @if ($batch->exists && auth()->user()->isAdmin())
            <div>
                <label for="status" class="block text-sm font-medium text-sand-700">Status batch</label>
                <select name="status" id="status" class="mt-1 block w-full rounded-lg border-sand-300 focus:border-brand-600 focus:ring-brand-600">
                    @foreach (BatchStatus::cases() as $st)
                        <option value="{{ $st->value }}" @selected(old('status', $batch->status?->value) === $st->value)>{{ $st->label() }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>
</div>

{{-- Daftar PO satu batch (dipakai kartu berjalan & detail batch selesai). --}}
<div class="divide-y divide-sand-100" data-po-list="{{ $batch->id }}">
    @foreach ($batch->purchaseOrders as $po)
        @include('produksi._po_row', ['batch' => $batch, 'po' => $po, 'canUpdate' => $canUpdate])
    @endforeach
</div>

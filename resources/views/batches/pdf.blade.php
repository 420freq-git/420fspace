<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #1b1d19; margin: 0; }
    .po { padding: 16px 30px; page-break-after: always; }
    .po:last-child { page-break-after: auto; }
    table { width: 100%; border-collapse: collapse; }
    .lbl { font-size: 7px; letter-spacing: 0.5px; text-transform: uppercase; color: #6e7267; font-weight: bold; }
    .val { font-size: 11px; color: #1b1d19; font-weight: bold; }
    .mono { font-family: DejaVu Sans Mono, monospace; }

    /* header */
    .hdr td { vertical-align: top; border-bottom: 2px solid #1b1d19; padding-bottom: 12px; }
    .wordmark { font-size: 23px; font-weight: bold; color: #1b1d19; }
    .wordmark .g { color: #2e5a22; }
    .doctitle { font-size: 8px; letter-spacing: 2px; text-transform: uppercase; color: #6e7267; font-weight: bold; margin-top: 3px; }
    .hdr .right { text-align: right; }
    .ponum { font-family: DejaVu Sans Mono, monospace; font-size: 16px; font-weight: bold; color: #2e5a22; margin-top: 3px; }
    .vend { display: inline-block; margin-top: 6px; font-size: 8px; font-weight: bold; letter-spacing: 0.5px;
            color: #2e5a22; background: #ebf1e3; padding: 3px 9px; border-radius: 9px; }

    /* meta */
    .meta { margin-top: 8px; border: 0.75px solid #e4e2d8; }
    .meta td { border: 0.75px solid #e4e2d8; padding: 6px 9px; width: 25%; }
    .meta .val { font-size: 10.5px; }

    /* section heading */
    .sec-h { font-size: 8.5px; letter-spacing: 1.5px; text-transform: uppercase; font-weight: bold; color: #1b1d19;
             border-bottom: 1px solid #e4e2d8; padding-bottom: 4px; margin: 10px 0 6px; }

    /* spec two-col */
    .spec td { padding: 5px 2px; border-bottom: 1px dotted #c7c5b9; font-size: 10px; }
    .spec .k { color: #6e7267; width: 20%; }
    .spec .v { text-align: right; font-weight: bold; width: 30%; }
    .spec .gap { width: 6%; border: 0; }

    /* ukuran desain */
    .design td { border: 0.75px solid #e4e2d8; padding: 8px 10px; width: 33.33%; vertical-align: top; }
    .design .big { font-size: 15px; font-weight: bold; }
    .design .off { color: #9a9e92; }

    /* label & aksesoris — pil terisi (ADA) vs redup (TIDAK) agar jelas terbaca */
    .chips td { padding: 4px 2px; }
    .chip { display: inline-block; border-radius: 9px; padding: 4px 11px; font-size: 9px; margin: 0 5px 6px 0; }
    .chip.yes { background: #2e5a22; color: #ffffff; font-weight: bold; }
    .chip.no { background: #f4f2eb; color: #a7aa9f; border: 0.75px solid #dcd9cc; }

    /* size grid */
    .grid th { background: #f4f2eb; color: #6e7267; border: 0.75px solid #e4e2d8; padding: 5px 6px;
               font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.5px; }
    .grid td { border: 0.75px solid #e4e2d8; padding: 5px 6px; text-align: center; font-size: 10px; }
    .grid .sz { text-align: left; font-weight: bold; }
    .grid .sku { text-align: left; font-family: DejaVu Sans Mono, monospace; font-size: 8.5px; color: #6e7267; }
    .grid .z { color: #b9b6a9; }
    .grid .total td { background: #ebf1e3; font-weight: bold; border-top: 1.5px solid #1b1d19; }
    .grid .total .g { color: #2e5a22; }

    /* mockups & desain — gambar mengisi penuh lebar cell (upload sesuai rasio cell) */
    .mock { table-layout: fixed; }
    .mock td { border: 1px dashed #c7c5b9; text-align: center; vertical-align: middle; padding: 6px; height: 252px; }
    .mock img { display: block; width: 100%; height: auto; max-height: 220px; margin: 0 auto; }
    .mock .twin img { display: inline-block; width: 48.5%; }   /* dua mockup berdampingan */
    .cap { font-size: 7px; color: #6e7267; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }

    /* note & footer */
    .note { border-left: 3px solid #2e5a22; background: #ebf1e3; padding: 7px 11px; font-size: 9.5px; margin-top: 6px; }
    .note b { text-transform: uppercase; font-size: 7.5px; color: #4a6a3f; }
    .madeby { margin-top: 16px; text-align: center; font-size: 7px; color: #9a9e92; letter-spacing: 0.5px; }
</style>
</head>
<body>
@php
    $img = function ($file) {
        $abs = \Storage::disk('public')->path($file->path);
        if (! is_file($abs)) return null;
        return 'data:'.mime_content_type($abs).';base64,'.base64_encode(file_get_contents($abs));
    };
    $chip = fn ($label, $on) => $on
        ? '<span class="chip yes">&#10003; '.$label.'</span>'
        : '<span class="chip no">&#10007; '.$label.'</span>';
    $ukurans = \App\Enums\Ukuran::cases();
    $jenises = \App\Enums\JenisProduksi::cases();
@endphp

@forelse ($batch->purchaseOrders as $po)
    @php
        $p = $po->product;
        $mockups = $p ? $p->filesOfType(\App\Enums\ProductFileType::Mockup)->filter(fn ($f) => $f->is_image)->values() : collect();
        $desain = $p ? $p->filesOfType(\App\Enums\ProductFileType::Desain)->filter(fn ($f) => $f->is_image)->first() : null;
        $skuFor = fn ($u) => $p?->sizes->firstWhere('ukuran', $u->value)?->sku_turunan;
    @endphp
    <div class="po">

        {{-- Header --}}
        <table class="hdr">
            <tr>
                <td>
                    <div class="wordmark">{{ $batch->brand->nama }}</div>
                    <div class="doctitle">Master Production Order</div>
                </td>
                <td class="right">
                    <span class="lbl">Nomor PO</span>
                    <div class="ponum">{{ $po->nomor_po }}</div>
                    <div><span class="vend">Vendor &middot; {{ strtoupper($batch->vendor) }}</span></div>
                </td>
            </tr>
        </table>

        {{-- Meta --}}
        <table class="meta">
            <tr>
                <td><span class="lbl">Tanggal order</span><br><span class="val mono">{{ $batch->tanggal_order->format('d/m/Y') }}</span></td>
                <td><span class="lbl">Deadline produksi</span><br><span class="val mono">{{ $batch->deadline_produksi?->format('d/m/Y') ?? '—' }}</span></td>
                <td><span class="lbl">Jenis order</span><br><span class="val">{{ $batch->jenis_order->label() }}</span></td>
                <td><span class="lbl">Type payment</span><br><span class="val">{{ $batch->type_payment->label() }}</span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="lbl">Nama artikel</span><br><span class="val">{{ $p->nama_artikel ?? '—' }}</span></td>
                <td><span class="lbl">Kategori</span><br><span class="val">{{ $p?->category?->nama ?? '—' }}</span></td>
                <td><span class="lbl">SKU induk</span><br><span class="val mono">{{ $p?->sku_induk ?? '—' }}</span></td>
            </tr>
        </table>

        {{-- Spesifikasi & sablon --}}
        <div class="sec-h">Spesifikasi &amp; sablon</div>
        <table class="spec">
            <tr>
                <td class="k">Patrun</td><td class="v">{{ $po->patrun ?: '—' }}</td><td class="gap"></td>
                <td class="k">Cat sablon</td><td class="v">{{ $po->cat_sablon ?: '—' }}</td>
            </tr>
            <tr>
                <td class="k">Jenis bahan</td><td class="v">{{ $po->jenis_bahan ?: '—' }}</td><td class="gap"></td>
                <td class="k">Finishing</td><td class="v">{{ $po->finishing ?: '—' }}</td>
            </tr>
            <tr>
                <td class="k">Supp bahan</td><td class="v">{{ $po->supp_bahan ?: '—' }}</td><td class="gap"></td>
                <td class="k">Ukuran RIB</td><td class="v">{{ $po->ukuran_rib ?: '—' }}</td>
            </tr>
            <tr>
                <td class="k">Warna benang</td><td class="v">{{ $po->warna_benang ?: '—' }}</td><td class="gap"></td>
                <td class="k">Warna bahan</td><td class="v">{{ $po->warna_bahan ?: '—' }}</td>
            </tr>
        </table>

        {{-- Ukuran desain --}}
        <div class="sec-h">Ukuran desain</div>
        <table class="design">
            <tr>
                <td><span class="lbl">Desain depan</span><br><span class="big {{ $po->desain_depan ? '' : 'off' }}">{{ $po->desain_depan ?: '0 × 0' }}</span></td>
                <td><span class="lbl">Desain belakang</span><br><span class="big {{ $po->desain_belakang ? '' : 'off' }}">{{ $po->desain_belakang ?: '0 × 0' }}</span></td>
                <td><span class="lbl">Desain lengan</span><br><span class="big {{ $po->desain_lengan ? '' : 'off' }}">{{ $po->desain_lengan ?: '0 × 0' }}</span></td>
            </tr>
        </table>

        {{-- Label & aksesoris --}}
        <div class="sec-h">Label &amp; aksesoris</div>
        <table class="chips">
            <tr>
                <td>
                    {!! $chip('Label leher', $po->label_leher) !!}
                    {!! $chip('Label bawah', $po->label_bawah) !!}
                    {!! $chip('Slip label', $po->slip_label) !!}
                    {!! $chip('Aksesoris', $po->aksesoris) !!}
                    {!! $chip('Care label', $po->care_label) !!}
                    {!! $chip('Hangtag', $po->hangtag) !!}
                    {!! $chip('Plastik', $po->plastik) !!}
                </td>
            </tr>
        </table>

        {{-- Rincian ukuran --}}
        <div class="sec-h">Rincian ukuran</div>
        <table class="grid">
            <thead>
                <tr>
                    <th style="text-align:left">Size</th>
                    <th style="text-align:left">SKU turunan</th>
                    @foreach ($jenises as $j)<th>{{ $j->label() }}</th>@endforeach
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ukurans as $u)
                    <tr>
                        <td class="sz">{{ $u->value }}</td>
                        <td class="sku">{{ $skuFor($u) ?: '—' }}</td>
                        @foreach ($jenises as $j)
                            @php $q = $po->qtyFor($u, $j); @endphp
                            <td class="{{ $q ? '' : 'z' }}">{{ $q ?: 0 }}</td>
                        @endforeach
                        <td>{{ $po->totalPerUkuran($u) ?: 0 }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td class="sz" colspan="2">TOTAL</td>
                    @foreach ($jenises as $j)
                        @php $sum = 0; foreach ($ukurans as $u) { $sum += $po->qtyFor($u, $j); } @endphp
                        <td>{{ $sum ?: 0 }}</td>
                    @endforeach
                    <td class="g">{{ $po->total_qty }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Mockups & desain — jaga satu blok agar tak terpotong antar-halaman --}}
        <div style="page-break-inside: avoid;">
            <div class="sec-h">Mockups &amp; desain</div>
            <table class="mock">
                <tr>
                    <td style="width:55%" class="{{ $mockups->count() > 1 ? 'twin' : '' }}">
                        @if ($mockups->count())
                            @foreach ($mockups->take(2) as $mk)<img src="{{ $img($mk) }}">@endforeach
                            <div class="cap">Mockup{{ $mockups->count() > 1 ? ' — tampak depan &amp; belakang' : '' }}</div>
                        @else
                            <div class="cap">Mockup belum ada</div>
                        @endif
                    </td>
                    <td style="width:45%">
                        @if ($desain)
                            <img src="{{ $img($desain) }}">
                            <div class="cap">Detail desain</div>
                        @else
                            <div class="cap">Detail desain belum ada</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        {{-- Catatan --}}
        <div class="note"><b>Catatan produksi</b><br>{{ $po->note ?: '—' }}</div>

        <div class="madeby">managed by 420Frequency</div>
    </div>
@empty
    <div class="po">
        <div class="wordmark">{{ $batch->brand->nama }}</div>
        <div class="doctitle">Master Production Order</div>
        <p style="margin-top:20px; color:#6e7267;">Batch {{ $batch->nomor_batch }} belum memiliki PO.</p>
    </div>
@endforelse
</body>
</html>

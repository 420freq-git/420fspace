<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    @include('laporan.pdf._css')
</head>
<body>
@php $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp
<div class="page">

    <table class="hdr">
        <tr>
            <td>
                <div class="wordmark"><span class="g">420</span>Frequency</div>
                <div class="doctitle">Sistem produksi &amp; settlement</div>
            </td>
            <td class="right">
                <div class="rtitle">INVOICE</div>
                <div class="rmeta">{{ $invoice->nomor }}</div>
                <div class="rmeta">Terbit {{ $invoice->tanggal_terbit->translatedFormat('d M Y') }}</div>
            </td>
        </tr>
    </table>

    <table class="cards">
        <tr>
            <td style="width:34%">
                <div class="lbl">Ditagih kepada</div>
                <div class="val" style="font-size:13px">{{ $invoice->brand->nama }}</div>
            </td>
            <td style="width:33%">
                <div class="lbl">Status</div>
                <div class="val {{ $invoice->isLunas() ? 'g' : '' }}" style="font-size:13px">{{ $invoice->isLunas() ? 'LUNAS' : 'BELUM DIBAYAR' }}</div>
                @if ($invoice->isLunas())<div class="lbl" style="margin-top:2px">dibayar {{ $invoice->tanggal_bayar->translatedFormat('d M Y') }}</div>@endif
            </td>
            <td style="width:33%">
                <div class="lbl">Total tagihan</div>
                <div class="val g">{{ $fmt($invoice->total) }}</div>
            </td>
        </tr>
    </table>

    <div class="sec-h">Rincian pesanan</div>
    <table class="grid">
        <thead>
            <tr>
                <th style="width:26%">Order ID</th>
                <th style="width:16%">Tanggal</th>
                <th>Channel</th>
                <th class="c" style="width:10%">Qty</th>
                <th class="r" style="width:20%">Nilai</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->orders as $o)
                <tr>
                    <td>
                        {{ $o->nomor_pesanan }}
                        @foreach ($o->items as $it)
                            <div class="muted" style="font-size:8px;">{{ $it->product->nama_artikel }} · {{ $it->ukuran->value }} × {{ $it->qty }}</div>
                        @endforeach
                    </td>
                    <td>{{ $o->tanggal_pesanan->translatedFormat('d M Y') }}</td>
                    <td>{{ $o->marketplace->label() }}</td>
                    <td class="c">{{ $o->total_qty }}</td>
                    <td class="r">{{ $fmt($o->nilai_tm) }}</td>
                </tr>
            @endforeach
            @if ($invoice->isBuyout())
                <tr>
                    <td>Buy-out sisa stok<div class="muted" style="font-size:8px;">{{ $invoice->catatan }}</div></td>
                    <td>{{ $invoice->tanggal_terbit->translatedFormat('d M Y') }}</td>
                    <td>Buy-out</td>
                    <td class="c">{{ $invoice->pcs_manual }}</td>
                    <td class="r">{{ $fmt($invoice->jumlah_manual) }}</td>
                </tr>
            @endif
        </tbody>
        <tfoot>
            <tr class="total">
                <td colspan="3">TOTAL</td>
                <td class="c">{{ number_format($invoice->total_qty, 0, ',', '.') }}</td>
                <td class="r g">{{ $fmt($invoice->total) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="madeby">managed by 420Frequency</div>
</div>
</body>
</html>

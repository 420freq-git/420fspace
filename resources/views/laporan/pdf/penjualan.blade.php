<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    @include('laporan.pdf._css')
</head>
<body>
@php
    $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
    $cols = 3 + ($showTm ? 1 : 0) + ($showDiferd ? 1 : 0) + ($showFee ? 1 : 0);
    $cardW = (int) round(100 / (2 + ($showTm ? 1 : 0) + ($showDiferd ? 1 : 0) + ($showFee ? 1 : 0)));
@endphp
<div class="page">

    <table class="hdr">
        <tr>
            <td>
                <div class="wordmark"><span class="g">420</span>Frequency</div>
                <div class="doctitle">Sistem produksi &amp; settlement</div>
            </td>
            <td class="right">
                <div class="rtitle">Laporan Penjualan</div>
                <div class="rmeta">Periode {{ $dari->translatedFormat('d M Y') }} — {{ $sampai->translatedFormat('d M Y') }}</div>
                <div class="rmeta">Dicetak {{ now()->translatedFormat('d M Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <table class="cards">
        <tr>
            <td style="width:{{ $cardW }}%">
                <div class="lbl">Unit terjual</div>
                <div class="val g">{{ number_format($totalQty, 0, ',', '.') }}</div>
            </td>
            <td style="width:{{ $cardW }}%">
                <div class="lbl">Artikel</div>
                <div class="val">{{ $jumlahArtikel }}</div>
            </td>
            @if ($showTm)
                <td style="width:{{ $cardW }}%"><div class="lbl">Nilai jual (TM420)</div><div class="val">{{ $fmt($totalTm420) }}</div></td>
            @endif
            @if ($showDiferd)
                <td style="width:{{ $cardW }}%"><div class="lbl">Nilai Diferd</div><div class="val">{{ $fmt($totalDiferd) }}</div></td>
            @endif
            @if ($showFee)
                <td style="width:{{ $cardW }}%"><div class="lbl">Fee 420F</div><div class="val g">{{ $fmt($totalFee) }}</div></td>
            @endif
        </tr>
    </table>

    <div class="sec-h">Rincian per artikel</div>
    <table class="grid">
        <thead>
            <tr>
                <th style="width:14%">SKU</th>
                <th>Produk</th>
                <th class="c" style="width:9%">Qty</th>
                @if ($showTm)<th class="r" style="width:16%">Nilai TM420</th>@endif
                @if ($showDiferd)<th class="r" style="width:16%">Nilai Diferd</th>@endif
                @if ($showFee)<th class="r" style="width:15%">Fee 420F</th>@endif
            </tr>
        </thead>
        <tbody>
            @forelse ($byProduct as $row)
                <tr>
                    <td>{{ $row['product']->sku_induk ?? '—' }}</td>
                    <td>{{ $row['product']->nama_artikel }}<span class="muted"> · {{ $row['product']->brand->nama ?? '—' }}</span></td>
                    <td class="c">{{ number_format($row['qty'], 0, ',', '.') }}</td>
                    @if ($showTm)<td class="r">{{ $fmt($row['tm420']) }}</td>@endif
                    @if ($showDiferd)<td class="r">{{ $fmt($row['diferd']) }}</td>@endif
                    @if ($showFee)<td class="r">{{ $fmt($row['fee']) }}</td>@endif
                </tr>
            @empty
                <tr><td colspan="{{ $cols }}" class="c muted" style="padding:14px">Tidak ada penjualan pada periode ini.</td></tr>
            @endforelse
        </tbody>
        @if ($byProduct->isNotEmpty())
            <tfoot>
                <tr class="total">
                    <td colspan="2">TOTAL</td>
                    <td class="c">{{ number_format($totalQty, 0, ',', '.') }}</td>
                    @if ($showTm)<td class="r">{{ $fmt($totalTm420) }}</td>@endif
                    @if ($showDiferd)<td class="r">{{ $fmt($totalDiferd) }}</td>@endif
                    @if ($showFee)<td class="r g">{{ $fmt($totalFee) }}</td>@endif
                </tr>
            </tfoot>
        @endif
    </table>

    @if ($byMarketplace->isNotEmpty())
        <div class="sec-h">Distribusi channel</div>
        <table class="grid">
            <thead>
                <tr><th>Channel</th><th class="r" style="width:20%">Unit</th><th class="r" style="width:20%">Porsi</th></tr>
            </thead>
            <tbody>
                @foreach ($byMarketplace as $mp)
                    <tr>
                        <td>{{ $mp['label'] }}</td>
                        <td class="r">{{ number_format($mp['qty'], 0, ',', '.') }}</td>
                        <td class="r">{{ $totalQty > 0 ? number_format($mp['qty'] / $totalQty * 100, 1) : 0 }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="madeby">managed by 420Frequency</div>
</div>
</body>
</html>

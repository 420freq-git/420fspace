<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8">@include('laporan.pdf._css')</head>
<body>
@php
    $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
    $cardCount = 1 + ($showTm ? 1 : 0) + ($showDiferd ? 1 : 0) + ($showFee ? 1 : 0);
    $cardW = (int) round(100 / max(1, $cardCount));
@endphp
<div class="page">
    <table class="hdr">
        <tr>
            <td><div class="wordmark"><span class="g">420</span>Frequency</div><div class="doctitle">Sistem produksi &amp; settlement</div></td>
            <td class="right"><div class="rtitle">Laporan Keuangan Bulanan</div><div class="rmeta">12 bulan terakhir · dicetak {{ now()->translatedFormat('d M Y H:i') }}</div></td>
        </tr>
    </table>

    <table class="cards">
        <tr>
            <td style="width:{{ $cardW }}%"><div class="lbl">Unit terjual (12 bln)</div><div class="val">{{ number_format($totals['unit'], 0, ',', '.') }}</div></td>
            @if ($showTm)<td style="width:{{ $cardW }}%"><div class="lbl">Nilai jual ({{ $labelJual }})</div><div class="val">{{ $fmt($totals['nilai_tm']) }}</div></td>@endif
            @if ($showDiferd)<td style="width:{{ $cardW }}%"><div class="lbl">Hak Diferd</div><div class="val">{{ $fmt($totals['hak_diferd']) }}</div></td>@endif
            @if ($showFee)<td style="width:{{ $cardW }}%"><div class="lbl">Fee 420F</div><div class="val g">{{ $fmt($totals['fee']) }}</div></td>@endif
        </tr>
    </table>

    <div class="sec-h">Rekap per bulan</div>
    <table class="grid">
        <thead>
            <tr>
                <th>Bulan</th><th class="c" style="width:12%">Unit</th>
                @if ($showTm)<th class="r" style="width:22%">Nilai {{ $labelJual }}</th>@endif
                @if ($showDiferd)<th class="r" style="width:22%">Hak Diferd</th>@endif
                @if ($showFee)<th class="r" style="width:22%">Fee 420F</th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td>{{ $r['bulan']->translatedFormat('M Y') }}</td>
                    <td class="c">{{ number_format($r['unit'], 0, ',', '.') }}</td>
                    @if ($showTm)<td class="r">{{ $fmt($r['nilai_tm']) }}</td>@endif
                    @if ($showDiferd)<td class="r">{{ $fmt($r['hak_diferd']) }}</td>@endif
                    @if ($showFee)<td class="r">{{ $fmt($r['fee']) }}</td>@endif
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total">
                <td>TOTAL</td>
                <td class="c">{{ number_format($totals['unit'], 0, ',', '.') }}</td>
                @if ($showTm)<td class="r">{{ $fmt($totals['nilai_tm']) }}</td>@endif
                @if ($showDiferd)<td class="r">{{ $fmt($totals['hak_diferd']) }}</td>@endif
                @if ($showFee)<td class="r g">{{ $fmt($totals['fee']) }}</td>@endif
            </tr>
        </tfoot>
    </table>

    <div class="madeby">managed by 420Frequency</div>
</div>
</body>
</html>

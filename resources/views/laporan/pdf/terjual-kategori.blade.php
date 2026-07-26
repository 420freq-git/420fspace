<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8">@include('laporan.pdf._css')</head>
<body>
@php
    $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
    $cols = 2 + count($labels) + ($showTm ? 1 : 0) + ($showDiferd ? 1 : 0);
@endphp
<div class="page">
    <table class="hdr">
        <tr>
            <td><div class="wordmark"><span class="g">420</span>Frequency</div><div class="doctitle">Sistem produksi &amp; settlement</div></td>
            <td class="right"><div class="rtitle">Produk Terjual per Bulan</div><div class="rmeta">By kategori · 6 bulan terakhir</div><div class="rmeta">Dicetak {{ now()->translatedFormat('d M Y H:i') }}</div></td>
        </tr>
    </table>

    <div class="sec-h">Unit terjual per kategori</div>
    <table class="grid">
        <thead>
            <tr>
                <th>Kategori</th>
                @foreach ($labels as $l)<th class="c" style="width:8%">{{ $l }}</th>@endforeach
                <th class="c" style="width:10%">Total</th>
                @if ($showTm)<th class="r" style="width:16%">Nilai {{ $labelJual }}</th>@endif
                @if ($showDiferd)<th class="r" style="width:16%">Nilai Diferd</th>@endif
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $kat => $r)
                <tr>
                    <td>{{ $kat }}</td>
                    @foreach ($r['per_bulan'] as $q)<td class="c">{{ $q }}</td>@endforeach
                    <td class="c">{{ number_format($r['qty'], 0, ',', '.') }}</td>
                    @if ($showTm)<td class="r">{{ $fmt($r['nilai_tm']) }}</td>@endif
                    @if ($showDiferd)<td class="r">{{ $fmt($r['nilai_diferd']) }}</td>@endif
                </tr>
            @empty
                <tr><td colspan="{{ $cols }}" class="c muted" style="padding:14px">Belum ada penjualan lunas 6 bulan terakhir.</td></tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr class="total">
                    <td>TOTAL</td>
                    @foreach ($totals['per_bulan'] as $q)<td class="c">{{ $q }}</td>@endforeach
                    <td class="c">{{ number_format($totals['qty'], 0, ',', '.') }}</td>
                    @if ($showTm)<td class="r">{{ $fmt($totals['nilai_tm']) }}</td>@endif
                    @if ($showDiferd)<td class="r g">{{ $fmt($totals['nilai_diferd']) }}</td>@endif
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="madeby">managed by 420Frequency</div>
</div>
</body>
</html>

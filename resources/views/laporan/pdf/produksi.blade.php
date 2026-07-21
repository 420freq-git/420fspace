<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8">@include('laporan.pdf._css')</head>
<body>
<div class="page">
    <table class="hdr">
        <tr>
            <td><div class="wordmark"><span class="g">420</span>Frequency</div><div class="doctitle">Sistem produksi &amp; settlement</div></td>
            <td class="right"><div class="rtitle">Laporan Produksi</div><div class="rmeta">Performa vendor · dicetak {{ now()->translatedFormat('d M Y H:i') }}</div></td>
        </tr>
    </table>

    <table class="cards">
        <tr>
            <td style="width:20%"><div class="lbl">Total batch</div><div class="val">{{ $stats['total'] }}</div></td>
            <td style="width:20%"><div class="lbl">Aktif</div><div class="val">{{ $stats['aktif'] }}</div></td>
            <td style="width:20%"><div class="lbl">Selesai</div><div class="val g">{{ $stats['selesai'] }}</div></td>
            <td style="width:20%"><div class="lbl">Telat deadline</div><div class="val {{ $stats['telat'] > 0 ? 'r' : '' }}">{{ $stats['telat'] }}</div></td>
            <td style="width:20%"><div class="lbl">Rata progres</div><div class="val">{{ $stats['avgProgress'] }}%</div></td>
        </tr>
    </table>

    <div class="sec-h">Rincian batch</div>
    <table class="grid">
        <thead>
            <tr>
                <th style="width:20%">Batch</th><th>Brand</th>
                <th style="width:13%">Tgl order</th><th style="width:13%">Deadline</th>
                <th class="c" style="width:10%">Progres</th><th style="width:15%">Ketepatan</th>
                <th class="c" style="width:8%">Qty</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r['batch']->nomor_batch }}</td>
                    <td>{{ $r['batch']->brand->nama }}</td>
                    <td>{{ $r['batch']->tanggal_order->format('d/m/Y') }}</td>
                    <td>{{ $r['deadline']?->format('d/m/Y') ?? '—' }}</td>
                    <td class="c">{{ $r['progress'] }}%</td>
                    <td>@if ($r['selesai'])Selesai @elseif ($r['sisa'] === null)— @elseif ($r['telat'])Telat {{ abs($r['sisa']) }} hari @else {{ $r['sisa'] }} hari lagi @endif</td>
                    <td class="c">{{ number_format($r['qty'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="c muted" style="padding:14px">Belum ada batch.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="madeby">managed by 420Frequency</div>
</div>
</body>
</html>

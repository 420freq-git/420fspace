<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8">@include('laporan.pdf._css')</head>
<body>
<div class="page">
    <table class="hdr">
        <tr>
            <td><div class="wordmark"><span class="g">420</span>Frequency</div><div class="doctitle">Sistem produksi &amp; settlement</div></td>
            <td class="right"><div class="rtitle">Laporan Perputaran Stok</div><div class="rmeta">3 bulan terakhir (sejak {{ $sejak->translatedFormat('d M Y') }})</div><div class="rmeta">Dicetak {{ now()->translatedFormat('d M Y H:i') }}</div></td>
        </tr>
    </table>

    <table class="cards">
        <tr>
            <td style="width:25%"><div class="lbl">Cepat</div><div class="val g">{{ $stats['cepat'] }}</div></td>
            <td style="width:25%"><div class="lbl">Lambat</div><div class="val">{{ $stats['lambat'] }}</div></td>
            <td style="width:25%"><div class="lbl">Stok mati</div><div class="val {{ $stats['mati'] > 0 ? 'r' : '' }}">{{ $stats['mati'] }}</div></td>
            <td style="width:25%"><div class="lbl">Unit mengendap</div><div class="val">{{ number_format($stats['sisaMati'], 0, ',', '.') }}</div></td>
        </tr>
    </table>

    <div class="sec-h">Rincian per artikel</div>
    <table class="grid">
        <thead>
            <tr>
                <th>Artikel</th><th style="width:18%">Kategori</th>
                <th class="c" style="width:9%">Stok</th><th class="c" style="width:11%">Keluar 3bln</th>
                <th class="c" style="width:10%">Rata/bln</th><th class="c" style="width:10%">Bln stok</th>
                <th class="c" style="width:12%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r['product']->nama_artikel }}<span class="muted"> · {{ $r['product']->brand->nama ?? '—' }}</span></td>
                    <td>{{ $r['kategori'] }}</td>
                    <td class="c">{{ number_format($r['sisa'], 0, ',', '.') }}</td>
                    <td class="c">{{ number_format($r['keluar3'], 0, ',', '.') }}</td>
                    <td class="c">{{ $r['per_bulan'] }}</td>
                    <td class="c">{{ $r['bulan_stok'] !== null ? $r['bulan_stok'] : '~' }}</td>
                    <td class="c">{{ $r['status']['label'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="c muted" style="padding:14px">Belum ada artikel yang diproduksi.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p style="margin-top:10px; font-size:8px; color:#6e7267;">"Bln stok" = perkiraan berapa bulan stok bertahan dengan kecepatan keluar 3 bulan terakhir.</p>
    <div class="madeby">managed by 420Frequency</div>
</div>
</body>
</html>

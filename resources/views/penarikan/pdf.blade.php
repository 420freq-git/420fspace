<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8">@include('laporan.pdf._css')</head>
<body>
@php $fmt = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.'); @endphp
<div class="page">
    <table class="hdr">
        <tr>
            <td><div class="wordmark"><span class="g">420</span>Frequency</div><div class="doctitle">Sistem produksi &amp; settlement</div></td>
            <td class="right">
                <div class="rtitle">Bukti Pembayaran Diferd</div>
                <div class="rmeta">Penarikan #{{ $p->id }} · cair {{ ($p->tanggal_cair ?? $p->tanggal_ajuan)->translatedFormat('d M Y') }}</div>
            </td>
        </tr>
    </table>

    <table class="cards">
        <tr>
            <td style="width:34%"><div class="lbl">Dibayar ke</div><div class="val">Diferd</div><div class="lbl" style="margin-top:3px;">{{ $p->brand ? 'untuk hak '.$p->brand->nama : 'semua brand (batch tertua dulu)' }}</div></td>
            <td style="width:33%"><div class="lbl">Tanggal ajuan</div><div class="val">{{ $p->tanggal_ajuan->translatedFormat('d M Y') }}</div></td>
            <td style="width:33%"><div class="lbl">Jumlah dibayar</div><div class="val g">{{ $fmt($p->jumlah) }}</div></td>
        </tr>
    </table>
    @if ($p->catatan)
        <p class="muted" style="margin-top:6px;">Catatan: {{ $p->catatan }}</p>
    @endif

    <div class="sec-h">Pembagian pembayaran</div>
    <table class="grid">
        <thead><tr><th>Brand</th><th>Batch</th><th class="r" style="width:28%">Dibayar dari penarikan ini</th></tr></thead>
        <tbody>
            @foreach ($batches as $r)
                <tr>
                    <td>{{ $r['batch']->brand?->nama ?? '—' }}</td>
                    <td>{{ $r['batch']->nomor_batch }}</td>
                    <td class="r">{{ $fmt($r['dibayar']) }}</td>
                </tr>
            @endforeach
            @if ($tanpaBatch > 0)
                <tr><td colspan="2" class="muted">Tidak terbagi ke batch (hak dari penjualan tanpa batch)</td><td class="r">{{ $fmt($tanpaBatch) }}</td></tr>
            @endif
        </tbody>
        <tfoot>
            @foreach ($perBrand as $nama => $total)
                <tr><td colspan="2">Subtotal {{ $nama }}</td><td class="r">{{ $fmt($total) }}</td></tr>
            @endforeach
            <tr class="total"><td colspan="2">TOTAL</td><td class="r g">{{ $fmt($p->jumlah) }}</td></tr>
        </tfoot>
    </table>

    <div class="sec-h">Asal hak per batch (artikel terjual)</div>
    <p class="muted" style="margin:0 0 8px;">
        Rincian hak Diferd tiap batch di atas, per tanggal cetak. Pembayaran dibagi ke batch secara FIFO (batch tertua dulu),
        tidak per artikel — bila sebuah batch hanya terbayar sebagian, rincian ini menunjukkan asal hak, bukan artikel yang dilunasi transfer ini.
    </p>
    @foreach ($batches as $r)
        <table class="grid" style="margin-bottom:10px;">
            <thead>
                <tr><th colspan="3">{{ $r['batch']->nomor_batch }} · {{ $r['batch']->brand?->nama ?? '—' }}</th></tr>
                <tr><th>Artikel</th><th class="c" style="width:14%">Pcs terjual</th><th class="r" style="width:26%">Hak Diferd</th></tr>
            </thead>
            <tbody>
                @forelse ($r['artikel'] as $a)
                    <tr>
                        <td>{{ $a->product?->nama_artikel ?? 'Produk #'.$a->product_id }} @if ($a->product?->sku_induk)<span class="muted">{{ $a->product->sku_induk }}</span>@endif</td>
                        <td class="c">{{ number_format((int) $a->pcs, 0, ',', '.') }}</td>
                        <td class="r">{{ $fmt($a->hak) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Tidak ada penjualan konsinyasi.</td></tr>
                @endforelse
                @if ($r['buyout'] !== 0)
                    <tr><td class="muted">Buy-out sisa stok</td><td></td><td class="r">{{ $fmt($r['buyout']) }}</td></tr>
                @endif
            </tbody>
            <tfoot>
                <tr class="total"><td colspan="2">Total hak batch (per tanggal cetak)</td><td class="r">{{ $fmt($r['hak_batch']) }}</td></tr>
            </tfoot>
        </table>
    @endforeach

    <div class="madeby">dicetak {{ now()->translatedFormat('d M Y H:i') }} · managed by 420Frequency</div>
</div>
</body>
</html>

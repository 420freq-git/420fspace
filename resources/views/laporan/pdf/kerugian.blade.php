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
                <div class="rtitle">Laporan Kerugian</div>
                <div class="rmeta">Barang retur diterima rusak/hilang</div>
                <div class="rmeta">Dicetak {{ now()->translatedFormat('d M Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <table class="cards">
        <tr>
            @if ($showTm)
                <td style="width:50%">
                    <div class="lbl">Kerugian {{ $labelJual }} — retur tidak bisa dijual</div>
                    <div class="val r">{{ $fmt($totalNilai) }}</div>
                    <div class="lbl">{{ number_format($totalQty, 0, ',', '.') }} pcs</div>
                </td>
            @endif
            @if ($showDiferd)
                <td style="width:50%">
                    <div class="lbl">Kerugian Diferd — tidak bisa dikirim dari PO</div>
                    <div class="val r">{{ $fmt($produksiNilai) }}</div>
                    <div class="lbl">{{ number_format($produksiQty, 0, ',', '.') }} pcs</div>
                </td>
            @endif
        </tr>
    </table>

    @if ($showDiferd)
    <div class="sec-h">Kerugian Diferd — produk yang tidak bisa dikirim dari jumlah PO</div>
    <table class="grid">
        <thead>
            <tr>
                <th style="width:18%">Jenis</th>
                <th style="width:16%">Batch</th>
                <th>Produk</th>
                <th style="width:22%">Keterangan</th>
                <th class="c" style="width:7%">UK</th>
                <th class="c" style="width:8%">Qty</th>
                <th class="r" style="width:15%">Kerugian</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($produksi as $r)
                <tr>
                    <td>{{ $r['jenis'] }}</td>
                    <td>{{ $r['batch'] }}</td>
                    <td>{{ $r['produk'] }}</td>
                    <td class="muted">{{ $r['keterangan'] }}</td>
                    <td class="c">{{ $r['ukuran'] }}</td>
                    <td class="c">{{ $r['qty'] }}</td>
                    <td class="r">{{ $fmt($r['nilai']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="c muted" style="padding:14px">Tidak ada reject maupun kekurangan penerimaan.</td></tr>
            @endforelse
        </tbody>
        @if ($produksi->isNotEmpty())
            <tfoot>
                <tr class="total">
                    <td colspan="5">TOTAL</td>
                    <td class="c">{{ number_format($produksiQty, 0, ',', '.') }}</td>
                    <td class="r g">{{ $fmt($produksiNilai) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    @endif

    @if ($showTm)
    <div class="sec-h">Kerugian {{ $labelJual }} — retur yang tidak bisa dijual</div>
    <table class="grid">
        <thead>
            <tr>
                <th style="width:15%">Tgl diterima</th>
                <th style="width:16%">Order ID</th>
                <th>Produk</th>
                <th style="width:20%">Alasan</th>
                <th class="c" style="width:7%">UK</th>
                <th class="c" style="width:8%">Qty</th>
                <th class="r" style="width:15%">Harga Diferd</th>
                <th class="r" style="width:15%">Kerugian</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $s)
                <tr>
                    <td>{{ $s->order?->tgl_retur_diterima?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $s->nomor_pesanan ?? '—' }}</td>
                    <td>{{ $s->product->nama_artikel }}<span class="muted"> · {{ $s->brand->nama ?? '—' }}</span></td>
                    <td>{{ $s->order?->alasan_rusak ?? '—' }}</td>
                    <td class="c">{{ $s->ukuran->value }}</td>
                    <td class="c">{{ $s->qty }}</td>
                    <td class="r">{{ $fmt($s->harga_diferd) }}</td>
                    <td class="r">{{ $fmt($s->qty * $s->harga_diferd) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="c muted" style="padding:14px">Belum ada kerugian barang rusak.</td></tr>
            @endforelse
        </tbody>
        @if ($items->isNotEmpty())
            <tfoot>
                <tr class="total">
                    <td colspan="5">TOTAL</td>
                    <td class="c">{{ number_format($totalQty, 0, ',', '.') }}</td>
                    <td></td>
                    <td class="r g">{{ $fmt($totalNilai) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    @endif

    <div class="madeby">managed by 420Frequency</div>
</div>
</body>
</html>

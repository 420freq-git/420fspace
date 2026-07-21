<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    @include('laporan.pdf._css')
    <style>
        .art { border: 0.75px solid #d8d5c8; margin-bottom: 10px; }
        .art-h { background: #f4f2eb; padding: 6px 9px; border-bottom: 0.75px solid #d8d5c8; }
        .art-h .nm { font-size: 11px; font-weight: bold; color: #1b1d19; }
        .art-h .kat { font-size: 8px; color: #6e7267; }
        .art-h .tot { font-size: 11px; font-weight: bold; color: #2e5a22; }
        .art table { border-collapse: collapse; width: 100%; }
        .art td, .art th { border-top: 0.5px solid #e4e2d8; padding: 4px 9px; font-size: 9.5px; }
        .art th { background: #fbfaf6; color: #6e7267; font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; }
        .kat-h { background: #ebf1e3; padding: 5px 9px; font-size: 9.5px; font-weight: bold; color: #2e5a22;
                 border: 0.75px solid #cfe0bf; margin-top: 10px; }
        .ttd td { font-size: 9px; color: #6e7267; padding-top: 6px; text-align: center; }
    </style>
</head>
<body>
<div class="page">

    {{-- ============ LEMBAR 1..n : RINCIAN PER ARTIKEL ============ --}}
    <table class="hdr">
        <tr>
            <td>
                <div class="wordmark"><span class="g">420</span>Frequency</div>
                <div class="doctitle">Sistem produksi &amp; settlement</div>
            </td>
            <td class="right">
                <div class="rtitle">SURAT JALAN</div>
                <div class="rmeta">{{ $sj->nomor_sj }}</div>
                <div class="rmeta">{{ $sj->tanggal_kirim->translatedFormat('d M Y') }}</div>
            </td>
        </tr>
    </table>

    <table class="cards">
        <tr>
            <td style="width:34%">
                <div class="lbl">Dari (vendor)</div>
                <div class="val" style="font-size:13px">{{ $sj->batch->vendor ?? 'Diferd' }}</div>
                <div class="lbl" style="margin-top:2px">Batch {{ $sj->batch->nomor_batch }}</div>
            </td>
            <td style="width:33%">
                <div class="lbl">Untuk brand</div>
                <div class="val" style="font-size:13px">{{ $sj->batch->brand->nama }}</div>
                @if ($sj->ekspedisi)<div class="lbl" style="margin-top:2px">{{ $sj->ekspedisi }}{{ $sj->resi ? ' · '.$sj->resi : '' }}</div>@endif
            </td>
            <td style="width:33%">
                <div class="lbl">Total kirim</div>
                <div class="val g">{{ number_format($sj->total_qty, 0, ',', '.') }} pcs</div>
                <div class="lbl" style="margin-top:2px">{{ $sj->isDiterima() ? 'DITERIMA '.$sj->tgl_diterima?->translatedFormat('d M Y') : 'DIKIRIM' }}</div>
            </td>
        </tr>
    </table>

    <div class="sec-h">Rincian per artikel &amp; ukuran</div>

    @foreach ($byArtikel as $a)
        <div class="art">
            <div class="art-h">
                <table>
                    <tr>
                        <td style="border:0; padding:0;">
                            <div class="nm">{{ $loop->iteration }}. {{ $a['product']->nama_artikel }}</div>
                            <div class="kat">{{ $a['kategori'] }}{{ $a['product']->sku_induk ? ' · '.$a['product']->sku_induk : '' }}</div>
                        </td>
                        <td style="border:0; padding:0; text-align:right;">
                            <span class="tot">{{ number_format($a['total'], 0, ',', '.') }} pcs</span>
                        </td>
                    </tr>
                </table>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width:60%">Ukuran</th>
                        <th style="width:20%; text-align:right;">Dikirim</th>
                        @if ($sj->isDiterima())<th style="width:20%; text-align:right;">Diterima</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($a['sizes'] as $s)
                        <tr>
                            <td>{{ $s->ukuran->value }}</td>
                            <td style="text-align:right;">{{ number_format($s->qty, 0, ',', '.') }}</td>
                            @if ($sj->isDiterima())
                                <td style="text-align:right; {{ ($s->selisih ?? 0) < 0 ? 'color:#a32d2d; font-weight:bold;' : '' }}">{{ number_format((int) $s->qty_diterima, 0, ',', '.') }}</td>
                            @endif
                        </tr>
                    @endforeach
                    <tr>
                        <td style="font-weight:bold;">Subtotal</td>
                        <td style="text-align:right; font-weight:bold;">{{ number_format($a['total'], 0, ',', '.') }}</td>
                        @if ($sj->isDiterima())<td style="text-align:right; font-weight:bold;">{{ number_format($a['total_diterima'], 0, ',', '.') }}</td>@endif
                    </tr>
                </tbody>
            </table>
        </div>
    @endforeach

    {{-- ============ LEMBAR TERAKHIR : REKAP PER KATEGORI + TTD ============ --}}
    <div style="page-break-before: always;">
        <table class="hdr">
            <tr>
                <td>
                    <div class="wordmark"><span class="g">420</span>Frequency</div>
                    <div class="doctitle">Rekap kiriman</div>
                </td>
                <td class="right">
                    <div class="rtitle">REKAP PER KATEGORI</div>
                    <div class="rmeta">{{ $sj->nomor_sj }} · {{ $sj->tanggal_kirim->translatedFormat('d M Y') }}</div>
                    <div class="rmeta">{{ $sj->batch->brand->nama }} · Batch {{ $sj->batch->nomor_batch }}</div>
                </td>
            </tr>
        </table>

        @foreach ($byKategori as $kategori => $data)
            <div class="kat-h">
                <table>
                    <tr>
                        <td style="border:0; padding:0;">{{ $kategori }}</td>
                        <td style="border:0; padding:0; text-align:right;">{{ number_format($data['total'], 0, ',', '.') }} pcs</td>
                    </tr>
                </table>
            </div>
            <table class="grid">
                <thead>
                    <tr>
                        <th style="width:8%">No</th>
                        <th>Artikel</th>
                        <th class="r" style="width:20%">Total qty</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['artikels'] as $a)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $a['product']->nama_artikel }}</td>
                            <td class="r">{{ number_format($a['total'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach

        <table class="grid" style="margin-top:12px;">
            <tr class="total">
                <td style="font-weight:bold;">TOTAL KESELURUHAN</td>
                <td class="r g" style="width:20%; font-weight:bold;">{{ number_format($sj->total_qty, 0, ',', '.') }} pcs</td>
            </tr>
        </table>

        @if ($sj->catatan)
            <p style="margin-top:10px; font-size:9.5px; color:#6e7267;"><span style="font-weight:bold;">Catatan:</span> {{ $sj->catatan }}</p>
        @endif

        <table class="ttd" style="margin-top:44px;">
            <tr>
                <td style="width:50%">
                    Pengirim<br><span style="font-weight:bold; color:#1b1d19;">{{ $sj->batch->vendor ?? 'Diferd' }}</span>
                    <div style="height:52px;"></div>( ................................ )
                </td>
                <td style="width:50%">
                    Penerima<br><span style="font-weight:bold; color:#1b1d19;">{{ $penerima }}</span>
                    <div style="height:52px;"></div>( ................................ )
                </td>
            </tr>
        </table>

        <div class="madeby">managed by 420Frequency</div>
    </div>
</div>
</body>
</html>

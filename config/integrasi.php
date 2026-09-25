<?php

return [
    /*
    | Token statis (Bearer) untuk endpoint BACA read-only yang dikonsumsi ERP 420F.
    | Cocokkan nilai ini dengan PRODUKSI_TOKEN di .env ERP 420F.
    */
    'erp_token' => env('ERP_INTEGRASI_TOKEN'),

    /*
    | Token statis (Bearer) TERPISAH untuk ERP TM420 (endpoint /api/tm420/*).
    | WAJIB berbeda dari ERP_INTEGRASI_TOKEN — jangan pakai token ERP 420F.
    */
    'tm_token' => env('ERP_TM_INTEGRASI_TOKEN'),

    /*
    | Arah balik: produksi MENARIK pesanan marketplace dari ERP 420F (yang meng-import file).
    | Cocokkan erp_api_token dengan ERP_API_TOKEN di .env ERP.
    */
    'erp_base_url' => rtrim((string) env('ERP_API_BASE_URL', 'http://127.0.0.1:8420/api/v1'), '/'),
    'erp_api_token' => env('ERP_API_TOKEN'),

    /*
    | Arah balik ke ERP TM420: produksi MENARIK penjualan TM atas barang yang
    | dibuat di sistem ini — dasar tagihan ongkos produksi 420F ke TM.
    |
    | Token BERBEDA dari dua token di atas. Yang di atas menjaga pintu MASUK
    | (ERP membaca dari sini); yang ini kunci untuk pintu KELUAR, dan nilainya
    | dipegang ERP TM sebagai API_420F_PRODUKSI_TOKEN.
    */
    'tm_api_base_url' => rtrim((string) env('ERP_TM_API_BASE_URL', 'https://erp.tm420.id/api/420f'), '/'),
    'tm_api_token' => env('ERP_TM_API_TOKEN'),
];

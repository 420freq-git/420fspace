<?php

return [
    /*
    | Token statis (Bearer) untuk endpoint BACA read-only yang dikonsumsi ERP 420F.
    | Cocokkan nilai ini dengan PRODUKSI_TOKEN di .env ERP 420F.
    */
    'erp_token' => env('ERP_INTEGRASI_TOKEN'),

    /*
    | Arah balik: produksi MENARIK pesanan marketplace dari ERP 420F (yang meng-import file).
    | Cocokkan erp_api_token dengan ERP_API_TOKEN di .env ERP.
    */
    'erp_base_url' => rtrim((string) env('ERP_API_BASE_URL', 'http://127.0.0.1:8420/api/v1'), '/'),
    'erp_api_token' => env('ERP_API_TOKEN'),
];

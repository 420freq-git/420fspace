<?php

return [
    /*
    | Token statis (Bearer) untuk endpoint BACA read-only yang dikonsumsi ERP 420F.
    | Cocokkan nilai ini dengan PRODUKSI_TOKEN di .env ERP 420F.
    */
    'erp_token' => env('ERP_INTEGRASI_TOKEN'),
];

<?php

use App\Http\Controllers\Api\IntegrasiProduksiController;
use App\Http\Controllers\Api\IntegrasiTmController;
use Illuminate\Support\Facades\Route;

/*
| API BACA read-only untuk ERP 420F. Dijaga token statis (middleware erp.token).
| Sumber kebenaran tetap di app ini; ERP hanya menarik angka jadi.
*/
Route::prefix('v1')->middleware('erp.token')->group(function () {
    Route::get('/ping', [IntegrasiProduksiController::class, 'ping']);
    Route::get('/produksi/komisi-diferd', [IntegrasiProduksiController::class, 'komisiDiferd']);
    Route::get('/produksi/penarikan-diferd', [IntegrasiProduksiController::class, 'penarikanDiferd']);
    Route::get('/produk', [IntegrasiProduksiController::class, 'produk']);
});

/*
| API BACA read-only untuk ERP TM420 — token TERPISAH (erp.token:tm). Read-only, informatif;
| tak menerbitkan kewajiban & bukan dorongan stok (penerimaan/reject diputus di ERP TM).
*/
Route::prefix('tm420')->middleware('erp.token:tm')->group(function () {
    Route::get('/produksi-berjalan', [IntegrasiTmController::class, 'produksiBerjalan']);
});

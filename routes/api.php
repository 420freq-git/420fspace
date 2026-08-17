<?php

use App\Http\Controllers\Api\IntegrasiProduksiController;
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

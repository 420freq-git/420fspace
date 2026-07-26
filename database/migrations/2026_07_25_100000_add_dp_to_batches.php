<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Down payment untuk batch CASH: brand bayar sebagian di muka (Brand→420F→Diferd) saat batch
 * disetujui, sisanya dilunasi saat semua PO siap kirim. `dp_persen` null/0 = cash penuh di muka
 * (perilaku lama, tidak berubah).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            if (! Schema::hasColumn('batches', 'dp_persen')) {
                $table->unsignedTinyInteger('dp_persen')->nullable()->after('tgl_cash');
                $table->boolean('dp_dibayar')->default(false)->after('dp_persen');
                $table->timestamp('tgl_dp')->nullable()->after('dp_dibayar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn(['dp_persen', 'dp_dibayar', 'tgl_dp']);
        });
    }
};

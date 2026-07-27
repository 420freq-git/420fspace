<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DP batch cash kini diisi sebagai NOMINAL (Rp), bukan persen.
 * `dp_nominal` = jumlah yang ditagih ke brand di muka (sisi tagihan); sisi Diferd (modal)
 * diturunkan proporsional. Kolom lama `dp_persen` dibiarkan (tak dipakai) demi keamanan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            if (! Schema::hasColumn('batches', 'dp_nominal')) {
                $table->unsignedBigInteger('dp_nominal')->nullable()->after('dp_persen');
            }
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            if (Schema::hasColumn('batches', 'dp_nominal')) {
                $table->dropColumn('dp_nominal');
            }
        });
    }
};

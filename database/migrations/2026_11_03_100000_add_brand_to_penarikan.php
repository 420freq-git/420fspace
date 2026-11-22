<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penarikan Diferd boleh ditujukan ke satu BRAND (mis. "bayar hak VOOJAH"). Bila diisi, pembagian
 * FIFO saat disetujui hanya ke batch brand itu; bila kosong, tetap FIFO global seperti semula.
 * Sebelumnya penarikan selalu global, sehingga pembayaran yang dimaksud untuk VOOJAH terserap
 * lebih dulu oleh batch TM yang lebih tua.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penarikan', function (Blueprint $table) {
            if (! Schema::hasColumn('penarikan', 'brand_id')) {
                $table->foreignId('brand_id')->nullable()->after('jumlah')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('penarikan', function (Blueprint $table) {
            if (Schema::hasColumn('penarikan', 'brand_id')) {
                $table->dropConstrainedForeignId('brand_id');
            }
        });
    }
};

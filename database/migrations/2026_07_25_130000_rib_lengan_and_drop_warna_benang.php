<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spesifikasi produksi: pisah RIB leher & RIB lengan, buang "warna benang".
 * `ukuran_rib` kini bermakna RIB leher; tambah `ukuran_rib_lengan`.
 */
return new class extends Migration
{
    private array $tables = ['product_specs', 'purchase_orders'];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                if (! Schema::hasColumn($t, 'ukuran_rib_lengan')) {
                    $table->string('ukuran_rib_lengan')->nullable()->after('ukuran_rib');
                }
                if (Schema::hasColumn($t, 'warna_benang')) {
                    $table->dropColumn('warna_benang');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                if (Schema::hasColumn($t, 'ukuran_rib_lengan')) {
                    $table->dropColumn('ukuran_rib_lengan');
                }
                if (! Schema::hasColumn($t, 'warna_benang')) {
                    $table->string('warna_benang')->nullable();
                }
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Catatan dari vendor (Diferd) ke brand — kendala teknis, dsb.
            $table->text('catatan_vendor')->nullable()->after('tahap_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('catatan_vendor');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('tahap')->default('belanja_bahan')->after('status_produksi');
            $table->timestamp('tahap_updated_at')->nullable()->after('tahap');
        });

        // Backfill dari status_produksi lama (antri|produksi|selesai|dikirim).
        $map = [
            'antri' => 'belanja_bahan',
            'produksi' => 'sablon_massal',
            'selesai' => 'packing',
            'dikirim' => 'terkirim',
        ];
        foreach ($map as $old => $new) {
            DB::table('purchase_orders')
                ->where('status_produksi', $old)
                ->update(['tahap' => $new, 'tahap_updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['tahap', 'tahap_updated_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Invoice buy-out tidak bersumber dari pesanan marketplace, jadi butuh baris manual + tautan batch.
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('brand_id')->constrained('batches')->nullOnDelete();
            $table->unsignedBigInteger('jumlah_manual')->default(0)->after('status'); // nilai tagihan non-pesanan (buy-out)
            $table->unsignedInteger('pcs_manual')->default(0)->after('jumlah_manual'); // jumlah pcs buy-out (info)
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
            $table->dropColumn(['jumlah_manual', 'pcs_manual']);
        });
    }
};

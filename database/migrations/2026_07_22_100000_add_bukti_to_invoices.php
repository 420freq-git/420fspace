<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Bukti transfer dari TM (diunggah TM sebagai konfirmasi pembayaran invoice).
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('bukti_transfer')->nullable()->after('tanggal_bayar');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('bukti_transfer');
        });
    }
};

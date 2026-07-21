<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Alasan barang retur dinyatakan rusak/hilang (dasar kerugian).
            $table->string('alasan_rusak')->nullable()->after('alasan_batal');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('alasan_rusak');
        });
    }
};

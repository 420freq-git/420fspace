<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengiriman_items', function (Blueprint $table) {
            // qty yang benar-benar diterima brand (null = belum dikonfirmasi).
            $table->unsignedInteger('qty_diterima')->nullable()->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('pengiriman_items', function (Blueprint $table) {
            $table->dropColumn('qty_diterima');
        });
    }
};

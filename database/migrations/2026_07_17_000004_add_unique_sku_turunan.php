<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_sizes', function (Blueprint $table) {
            // Nullable → NULL boleh berulang; nilai terisi wajib unik (kunci pencocokan import).
            $table->unique('sku_turunan');
        });
    }

    public function down(): void
    {
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->dropUnique(['sku_turunan']);
        });
    }
};

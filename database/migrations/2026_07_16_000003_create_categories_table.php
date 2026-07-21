<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('category_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('size_tier'); // s_xl | xxl
            $table->unsignedInteger('harga_diferd')->default(0); // 420F bayar ke vendor
            $table->unsignedInteger('harga_tm420')->nullable();  // TM420 bayar ke 420F (brand eksternal)
            $table->timestamps();

            $table->unique(['category_id', 'size_tier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_prices');
        Schema::dropIfExists('categories');
    }
};

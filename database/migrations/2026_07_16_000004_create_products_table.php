<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('sku_induk')->nullable();
            $table->string('nama_artikel');
            // Harga khusus (override) per tier — null berarti ikut harga kategori
            $table->unsignedInteger('harga_diferd_sxl_override')->nullable();
            $table->unsignedInteger('harga_diferd_xxl_override')->nullable();
            $table->unsignedInteger('harga_tm420_sxl_override')->nullable();
            $table->unsignedInteger('harga_tm420_xxl_override')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(['brand_id', 'nama_artikel']);
        });

        Schema::create('product_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('ukuran'); // S,M,L,XL,XXL
            $table->string('sku_turunan')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'ukuran']);
        });

        Schema::create('product_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('tipe'); // mockup | desain
            $table->string('path');
            $table->string('nama_asli');
            $table->timestamps();
        });

        Schema::create('product_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete()->unique();
            // Spesifikasi & sablon
            $table->string('patrun')->nullable();
            $table->string('ukuran_rib')->nullable();
            $table->string('warna_bahan')->nullable();
            $table->string('jenis_bahan')->nullable();
            $table->string('supp_bahan')->nullable();
            $table->string('warna_benang')->nullable();
            $table->string('cat_sablon')->nullable();
            $table->string('finishing')->nullable();
            // Ukuran desain
            $table->string('desain_depan')->nullable();
            $table->string('desain_belakang')->nullable();
            $table->string('desain_lengan')->nullable();
            // Label & aksesoris
            $table->boolean('label_leher')->default(false);
            $table->boolean('label_bawah')->default(false);
            $table->boolean('slip_label')->default(false);
            $table->boolean('aksesoris')->default(false);
            $table->boolean('care_label')->default(false);
            $table->boolean('hangtag')->default(false);
            $table->boolean('plastik')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_specs');
        Schema::dropIfExists('product_files');
        Schema::dropIfExists('product_sizes');
        Schema::dropIfExists('products');
    }
};

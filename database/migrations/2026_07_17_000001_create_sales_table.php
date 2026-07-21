<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ukuran');
            $table->unsignedInteger('qty');
            $table->date('tanggal_terjual');
            $table->string('marketplace'); // shopee|tiktokshop|offline
            $table->string('nomor_pesanan')->nullable();
            $table->unsignedInteger('harga_diferd')->default(0); // snapshot: 420F bayar vendor
            $table->unsignedInteger('harga_tm420')->nullable();  // snapshot: brand bayar 420F (eksternal)
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'ukuran']);
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};

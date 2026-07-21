<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengiriman', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->string('nomor_sj')->unique();
            $table->date('tanggal_kirim');
            $table->string('ekspedisi')->nullable();
            $table->string('resi')->nullable();
            $table->string('status')->default('dikirim'); // dikirim | diterima
            $table->date('tgl_diterima')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('pengiriman_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengiriman_id')->constrained('pengiriman')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('ukuran');
            $table->unsignedInteger('qty')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengiriman_items');
        Schema::dropIfExists('pengiriman');
    }
};

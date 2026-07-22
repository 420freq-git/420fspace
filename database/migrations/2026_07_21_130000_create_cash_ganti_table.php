<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Penyelesaian kewajiban ganti Diferd atas reject di batch CASH (dibayar penuh di muka).
        // Tiap baris = satu tindakan ganti: re-produksi barang, atau refund uang.
        Schema::create('cash_ganti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained('brands');
            $table->string('metode');                // 'barang' (re-produksi) | 'refund' (uang)
            $table->unsignedInteger('pcs');          // jumlah pcs reject yang diselesaikan di baris ini
            $table->unsignedBigInteger('nilai_diferd')->default(0); // nilai yang direfund Diferd (0 utk barang)
            $table->unsignedBigInteger('nilai_tm420')->default(0);  // nilai yang direfund ke TM (0 utk barang)
            $table->date('tanggal');
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_ganti');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->string('nomor_batch')->unique();
            $table->string('vendor')->default('Diferd');
            $table->date('tanggal_order');
            $table->date('deadline');
            $table->string('jenis_order')->default('full_order');
            $table->string('type_payment')->default('termin');
            $table->unsignedBigInteger('deposit_awal')->default(0);
            $table->string('status')->default('aktif'); // aktif | lunas
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('nomor_po');
            // Snapshot spesifikasi & sablon (dari master produk, boleh diubah per PO)
            $table->string('patrun')->nullable();
            $table->string('ukuran_rib')->nullable();
            $table->string('warna_bahan')->nullable();
            $table->string('jenis_bahan')->nullable();
            $table->string('supp_bahan')->nullable();
            $table->string('warna_benang')->nullable();
            $table->string('cat_sablon')->nullable();
            $table->string('finishing')->nullable();
            $table->string('desain_depan')->nullable();
            $table->string('desain_belakang')->nullable();
            $table->string('desain_lengan')->nullable();
            $table->boolean('label_leher')->default(false);
            $table->boolean('label_bawah')->default(false);
            $table->boolean('slip_label')->default(false);
            $table->boolean('aksesoris')->default(false);
            $table->boolean('care_label')->default(false);
            $table->boolean('hangtag')->default(false);
            $table->boolean('plastik')->default(false);
            $table->text('note')->nullable();
            $table->string('status_produksi')->default('antri'); // antri|produksi|selesai|dikirim
            $table->timestamps();
        });

        Schema::create('po_size_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('ukuran'); // S..XXL
            $table->string('jenis');  // pendek|panjang|raglan_34|lekbong
            $table->unsignedInteger('qty')->default(0);
            $table->timestamps();

            $table->unique(['purchase_order_id', 'ukuran', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_size_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('batches');
    }
};

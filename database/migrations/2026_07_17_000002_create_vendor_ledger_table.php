<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('tipe'); // deposit | pembayaran | buyout
            $table->unsignedBigInteger('jumlah');
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_ledger');
    }
};

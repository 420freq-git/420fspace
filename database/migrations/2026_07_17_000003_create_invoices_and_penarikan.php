<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->string('nomor')->unique();
            $table->date('tanggal_terbit');
            $table->string('status')->default('belum_bayar'); // belum_bayar | lunas
            $table->date('tanggal_bayar')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('brand_id')->constrained()->nullOnDelete();
        });

        // Penarikan (withdrawal) Diferd — saldo global.
        Schema::create('penarikan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jumlah');
            $table->string('status')->default('diajukan'); // diajukan | disetujui | ditolak
            $table->date('tanggal_ajuan');
            $table->date('tanggal_cair')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
        Schema::dropIfExists('penarikan');
        Schema::dropIfExists('invoices');
    }
};

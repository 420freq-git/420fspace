<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice, tautan invoice_id di orders, dan tabel penarikan.
 * Nama file TANPA prefix "create_" DISENGAJA: agar sort SETELAH
 * `2026_07_17_000003_create_orders_table` (mengubah `orders` butuh tabelnya ada).
 * Guard hasTable/hasColumn membuatnya idempoten & aman untuk DB yang sudah termigrasi
 * di bawah nama lama `..._create_invoices_and_penarikan`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
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
        }

        if (! Schema::hasColumn('orders', 'invoice_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('invoice_id')->nullable()->after('brand_id')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasTable('penarikan')) {
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
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'invoice_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('invoice_id');
            });
        }
        Schema::dropIfExists('penarikan');
        Schema::dropIfExists('invoices');
    }
};

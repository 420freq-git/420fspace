<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Ledger perlu menampung entri pembalikan (refund reject cash batch) bernilai negatif:
    // Diferd mengembalikan uang & 420F meneruskan refund ke TM. Kolom unsigned menolaknya.
    public function up(): void
    {
        Schema::table('vendor_ledger', function (Blueprint $table) {
            $table->bigInteger('jumlah')->change();
        });
        Schema::table('brand_ledger', function (Blueprint $table) {
            $table->bigInteger('jumlah')->change();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_ledger', function (Blueprint $table) {
            $table->unsignedBigInteger('jumlah')->change();
        });
        Schema::table('brand_ledger', function (Blueprint $table) {
            $table->unsignedBigInteger('jumlah')->change();
        });
    }
};

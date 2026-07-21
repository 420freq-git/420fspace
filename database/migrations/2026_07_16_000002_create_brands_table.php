<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->string('tipe')->default('eksternal'); // eksternal | milik_sendiri
            $table->string('kode')->nullable();           // kode singkat, mis. "TM" untuk nomor PO
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });

        Schema::dropIfExists('brands');
    }
};

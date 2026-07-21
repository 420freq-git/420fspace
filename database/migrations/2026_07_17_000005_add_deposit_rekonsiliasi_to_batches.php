<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->boolean('deposit_rekonsiliasi')->default(false)->after('deposit_awal');
            $table->date('tgl_rekonsiliasi')->nullable()->after('deposit_rekonsiliasi');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn(['deposit_rekonsiliasi', 'tgl_rekonsiliasi']);
        });
    }
};

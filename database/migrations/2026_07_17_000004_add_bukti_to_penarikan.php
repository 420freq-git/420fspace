<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penarikan', function (Blueprint $table) {
            $table->string('bukti_transfer')->nullable()->after('catatan');
            $table->string('bukti_invoice')->nullable()->after('bukti_transfer');
        });
    }

    public function down(): void
    {
        Schema::table('penarikan', function (Blueprint $table) {
            $table->dropColumn(['bukti_transfer', 'bukti_invoice']);
        });
    }
};

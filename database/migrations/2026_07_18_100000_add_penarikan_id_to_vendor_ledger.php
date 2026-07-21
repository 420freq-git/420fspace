<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_ledger', function (Blueprint $table) {
            $table->foreignId('penarikan_id')->nullable()->after('batch_id')
                ->constrained('penarikan')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_ledger', function (Blueprint $table) {
            $table->dropConstrainedForeignId('penarikan_id');
        });
    }
};

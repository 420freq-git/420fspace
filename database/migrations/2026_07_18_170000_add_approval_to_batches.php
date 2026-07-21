<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->foreignId('diajukan_oleh')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('disetujui_oleh')->nullable()->after('diajukan_oleh')->constrained('users')->nullOnDelete();
            $table->timestamp('tgl_approval')->nullable()->after('disetujui_oleh');
            $table->string('catatan_approval')->nullable()->after('tgl_approval');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diajukan_oleh');
            $table->dropConstrainedForeignId('disetujui_oleh');
            $table->dropColumn(['tgl_approval', 'catatan_approval']);
        });
    }
};

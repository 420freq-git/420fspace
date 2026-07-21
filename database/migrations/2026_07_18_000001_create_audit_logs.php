<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();  // snapshot nama pelaku
            $table->string('user_role')->nullable();
            $table->string('event');                  // created | updated | deleted
            $table->string('auditable_type');         // basename model, mis. "Order"
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('label')->nullable();       // deskripsi manusiawi
            $table->json('changes')->nullable();       // {field: [old, new]}
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

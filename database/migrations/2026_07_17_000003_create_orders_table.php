<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->string('nomor_pesanan')->unique();
            $table->string('resi')->nullable();
            $table->string('marketplace'); // shopee | tiktokshop | offline
            $table->date('tanggal_pesanan');
            $table->string('status')->default('dipesan'); // dipesan|dikirim|lunas|retur|batal
            $table->date('tgl_kirim')->nullable();
            $table->date('tgl_cair')->nullable();
            $table->date('tgl_retur')->nullable();
            $table->date('tgl_retur_diterima')->nullable();
            $table->unsignedInteger('jumlah_cek')->default(0);
            $table->date('tgl_cek_terakhir')->nullable();
            $table->string('alasan_batal')->nullable();
            $table->string('sumber')->default('manual'); // manual | import
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('marketplace');
        });

        // Setiap "sale" jadi baris item milik sebuah order
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('kondisi_retur')->nullable()->after('keterangan'); // layak | rusak
        });

        // Migrasikan data penjualan lama menjadi orders (status lunas = sudah terjual)
        $sales = DB::table('sales')->get();
        foreach ($sales->groupBy(fn ($s) => $s->nomor_pesanan ?: ('LEGACY-'.$s->id)) as $nomor => $group) {
            $first = $group->first();
            $orderId = DB::table('orders')->insertGetId([
                'brand_id' => $first->brand_id,
                'nomor_pesanan' => $nomor,
                'marketplace' => $first->marketplace,
                'tanggal_pesanan' => $first->tanggal_terjual,
                'status' => 'lunas',
                'tgl_cair' => $first->tanggal_terjual,
                'sumber' => 'manual',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('sales')->whereIn('id', $group->pluck('id'))->update(['order_id' => $orderId]);
        }
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn('kondisi_retur');
        });
        Schema::dropIfExists('orders');
    }
};

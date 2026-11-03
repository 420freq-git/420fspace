<?php
namespace Tests\Feature\Erp;
use Tests\ErpTestCase;
use App\Models\Sale; use App\Models\VendorLedger; use App\Models\Penarikan; use App\Models\Batch;
use Illuminate\Support\Facades\DB;

class KonsistensiAngkaTest extends ErpTestCase {
  public function test_hak_diferd_konsisten_lintas_halaman(): void {
    // Skenario: 2 batch TM + 1 VOOJAH, sebagian terjual, 1 penarikan disetujui.
    $b1=$this->batchAktif($this->produkTm,['M'=>10]); $this->produksiTerima($b1); $this->jual($this->produkTm,'M',5);
    $b2=$this->batchAktif($this->produkTm2,['M'=>10]); $this->produksiTerima($b2); $this->jual($this->produkTm2,'M',3);
    $bv=$this->batchAktif($this->produkVoojah,['M'=>10]); $this->produksiTerima($bv); $this->jual($this->produkVoojah,'M',4);

    // Diferd ajukan penarikan sebagian, 420F setujui
    $pc=app(\App\Http\Controllers\PenarikanController::class);
    $pc->store($this->req($this->diferd,['jumlah'=>100000]));
    $pen=Penarikan::latest('id')->first();
    $pc->approve($this->req($this->admin),$pen);

    // (1) hak global − dibayar, ala PenarikanController
    $hakGlobal=(int)Sale::sold()->consignment()->sum(DB::raw('qty*harga_diferd'))
      + (int)VendorLedger::where('tipe','buyout')->sum('jumlah');
    $penarikanCair=(int)Penarikan::where('status','disetujui')->sum('jumlah');
    $pembayaran=(int)VendorLedger::where('tipe','pembayaran')->whereNull('penarikan_id')->sum('jumlah')+$penarikanCair;
    $sisaPenarikan=$hakGlobal-$pembayaran;

    // (2) jumlah saldo per batch, ala Settlement
    $s=app(\App\Services\SettlementService::class);
    $sisaSettlement=(int)Batch::all()->sum(fn($b)=>max(0,$s->batchSummary($b)['saldo']));

    $this->assertSame($sisaPenarikan,$sisaSettlement,"Sisa hak Diferd harus sama di Penarikan & Settlement");
  }

  /**
   * Fee 420F di Laporan Keuangan dulu hanya margin penjualan (tanpa margin cash & buy-out, dan
   * ikut menghitung penjualan batch cash) — beda dengan Cashflow. Kini harus sama persis.
   */
  public function test_fee_laporan_keuangan_sama_dengan_cashflow(): void {
    // Konsinyasi: jual 3 (margin 3×10.000) lalu buy-out sisa 7 (margin 7×10.000).
    $t=$this->batchAktif($this->produkTm,['M'=>10]); $this->produksiTerima($t); $this->jual($this->produkTm,'M',3);
    app(\App\Http\Controllers\SettlementController::class)->buyout($this->req($this->admin,[],'PATCH'),$t->fresh());

    // Cash: tagihan 10×70.000, TM lunas, 420F bayar modal 10×60.000 → margin 100.000.
    $c=$this->batchAktif($this->produkTm2,['M'=>10],'cash');
    $inv=\App\Models\Invoice::where('batch_id',$c->id)->where('jenis','cash')->firstOrFail();
    app(\App\Http\Controllers\InvoiceController::class)->markPaid($this->req($this->admin,['tanggal_bayar'=>now()->toDateString()],'PATCH'),$inv);
    app(\App\Http\Controllers\SettlementController::class)->bayarDiferdCash($this->req($this->admin,['nominal'=>600000],'POST'),$c);

    $feeCashflow=app(\App\Http\Controllers\CashflowController::class)->index()->getData()['fee'];

    $rc=app(\App\Http\Controllers\ReportController::class);
    $m=new \ReflectionMethod($rc,'keuanganData');
    $lap=$m->invoke($rc,$this->req($this->admin,[],'GET'));

    $this->assertSame(30000+70000+100000,$feeCashflow);
    $this->assertSame($feeCashflow,(int)$lap['totals']['fee'],'Fee Laporan Keuangan harus = Fee 420F di Cashflow');
    $this->assertSame(30000,(int)$lap['totals']['fee_jual']);
    $this->assertSame(170000,(int)$lap['totals']['fee_cash_buyout']);
  }
}

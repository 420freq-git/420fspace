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
}

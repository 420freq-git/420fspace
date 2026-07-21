<?php

namespace App\Enums;

enum LedgerTipe: string
{
    case Deposit = 'deposit';                   // setoran modal di muka (mengendap di vendor)
    case DepositSelesai = 'deposit_selesai';    // penyelesaian deposit di akhir kerja sama (offset/dikembalikan)
    case Pembayaran = 'pembayaran';             // pembayaran hak barang terjual ke vendor
    case Buyout = 'buyout';                     // pelunasan sisa stok di deadline

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Deposit',
            self::DepositSelesai => 'Deposit selesai',
            self::Pembayaran => 'Pembayaran',
            self::Buyout => 'Buy-out',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Deposit => 'bg-blue-100 text-blue-800',
            self::DepositSelesai => 'bg-sand-200 text-sand-800',
            self::Pembayaran => 'bg-emerald-100 text-emerald-800',
            self::Buyout => 'bg-amber-100 text-amber-800',
        };
    }
}

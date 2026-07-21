<?php

namespace App\Enums;

enum TahapProduksi: string
{
    case BelanjaBahan = 'belanja_bahan';
    case PengirimanBahan = 'pengiriman_bahan';
    case Cutting = 'cutting';
    case SettingDesain = 'setting_desain';
    case PembuatanFilm = 'pembuatan_film';
    case Proofing = 'proofing';
    case Revisi = 'revisi';
    case SablonMassal = 'sablon_massal';
    case Sewing = 'sewing';
    case Setrika = 'setrika';
    case Qc = 'qc';
    case Packing = 'packing';
    case SiapKirim = 'siap_kirim';
    case Terkirim = 'terkirim';

    public function label(): string
    {
        return match ($this) {
            self::BelanjaBahan => 'Belanja bahan',
            self::PengirimanBahan => 'Pengiriman Bahan',
            self::Cutting => 'Cutting',
            self::SettingDesain => 'Setting Desain',
            self::PembuatanFilm => 'Pembuatan Film',
            self::Proofing => 'Proofing',
            self::Revisi => 'Revisi',
            self::SablonMassal => 'Sablon Massal',
            self::Sewing => 'Sewing',
            self::Setrika => 'Setrika',
            self::Qc => 'QC',
            self::Packing => 'Packing',
            self::SiapKirim => 'Siap Kirim',
            self::Terkirim => 'Terkirim',
        };
    }

    /** Urutan 1..11. */
    public function step(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    /** Progress 0..100 (terkirim = 100%). */
    public function progress(): int
    {
        return (int) round($this->step() / count(self::cases()) * 100);
    }

    /** Fase besar untuk pewarnaan/pengelompokan. */
    public function fase(): string
    {
        return match ($this) {
            self::BelanjaBahan, self::PengirimanBahan, self::Cutting => 'Persiapan',
            self::SettingDesain, self::PembuatanFilm, self::Proofing, self::Revisi => 'Desain',
            self::SablonMassal, self::Sewing => 'Produksi',
            self::Setrika, self::Qc, self::Packing => 'Finishing',
            self::SiapKirim, self::Terkirim => 'Pengiriman',
        };
    }

    public function isReady(): bool
    {
        return in_array($this, [self::SiapKirim, self::Terkirim], true);
    }

    public function isDone(): bool
    {
        return $this === self::Terkirim;
    }

    public function badgeClasses(): string
    {
        return match ($this->fase()) {
            'Persiapan' => 'bg-sand-100 text-sand-700',
            'Desain' => 'bg-amber-100 text-amber-800',
            'Produksi' => 'bg-blue-100 text-blue-800',
            'Finishing' => 'bg-violet-100 text-violet-800',
            default => $this === self::Terkirim ? 'bg-brand-100 text-brand-800' : 'bg-emerald-100 text-emerald-800',
        };
    }

    /** Warna isi progress bar. */
    public function barClass(): string
    {
        return match ($this->fase()) {
            'Persiapan' => 'bg-sand-400',
            'Desain' => 'bg-amber-400',
            'Produksi' => 'bg-blue-500',
            'Finishing' => 'bg-violet-500',
            default => 'bg-brand-500',
        };
    }
}

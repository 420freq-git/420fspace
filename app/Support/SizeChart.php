<?php

namespace App\Support;

use App\Models\Category;

/**
 * Size chart (tabel ukuran badan) per kategori — dipakai di Master PO PDF.
 *
 * Sumber angka & template gambar: "Dokumen Spek/Template Size Chart" (disalin ke
 * resources/size-charts). Patokan PER KATEGORI. Kategori yang tak punya template
 * sendiri jatuh ke template pendek (reguler) / panjang (longsleeve) — kecuali
 * Hoodie & Double Layer yang selalu template-nya sendiri.
 */
class SizeChart
{
    /** Definisi chart per key. `img` = file di resources/size-charts. */
    private const CHARTS = [
        'reguler' => [
            'img' => 'reguler.png',
            'kolom' => ['Panjang', 'Lebar', 'Lengan'],
            'baris' => [
                'S' => [69, 48, 21],
                'M' => [71, 51, 22],
                'L' => [74, 53, 24],
                'XL' => [77, 56, 26],
                'XXL' => [80, 58, 28],
            ],
        ],
        'longsleeve' => [
            'img' => 'longsleeve.png',
            'kolom' => ['Panjang', 'Lebar', 'Lengan'],
            'baris' => [
                'S' => [69, 48, 61],
                'M' => [71, 51, 63],
                'L' => [74, 53, 65],
                'XL' => [77, 56, 67],
                'XXL' => [80, 58, 69],
            ],
        ],
        'double_layer' => [
            'img' => 'double_layer.png',
            'kolom' => ['Panjang', 'Lebar', 'Lengan pdk', 'Lengan pjg'],
            'baris' => [
                'S' => [69, 48, 21, 61],
                'M' => [71, 51, 22, 63],
                'L' => [74, 53, 24, 65],
                'XL' => [77, 56, 26, 67],
                'XXL' => [80, 58, 28, 69],
            ],
        ],
        'oversized' => [
            'img' => 'oversized.png',
            'kolom' => ['Panjang', 'Lebar', 'Bahu', 'Lengan'],
            'baris' => [
                'S' => [63, 51, 15, 23],
                'M' => [69, 55, 17, 25],
                'L' => [74, 57, 18, 27],
                'XL' => [76, 60, 19, 29],
                'XXL' => [81, 63, 20, 31],
            ],
        ],
        'hoodie' => [
            'img' => 'hoodie.png',
            'kolom' => ['Panjang', 'Lebar', 'Lengan'],
            'baris' => [
                'S' => [70, 49, 61],
                'M' => [72, 51, 63],
                'L' => [74, 53, 64],
                'XL' => [76, 55, 66],
                'XXL' => [78, 56, 68],
            ],
        ],
    ];

    /** Chart untuk sebuah kategori. Selalu mengembalikan array (fallback aman). */
    public static function forCategory(?Category $category): array
    {
        return self::CHARTS[self::keyFor($category)];
    }

    /** Path absolut file gambar template (untuk embed base64 di dompdf). */
    public static function imagePath(?Category $category): ?string
    {
        $abs = resource_path('size-charts/'.self::forCategory($category)['img']);

        return is_file($abs) ? $abs : null;
    }

    private static function keyFor(?Category $category): string
    {
        $n = strtolower($category?->nama ?? '');

        return match (true) {
            str_contains($n, 'double layer') => 'double_layer',
            str_contains($n, 'hoodie') => 'hoodie',
            str_contains($n, 'longsleeve') => 'longsleeve',
            str_contains($n, 'oversiz') => 'oversized',
            str_contains($n, 'reguler') => 'reguler',
            // Kategori tanpa template sendiri: ikut jenis (panjang → longsleeve, selain itu → reguler).
            default => $category && $category->jenisProduksi() === \App\Enums\JenisProduksi::Panjang
                ? 'longsleeve'
                : 'reguler',
        };
    }
}

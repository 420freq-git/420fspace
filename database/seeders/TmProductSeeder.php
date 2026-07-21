<?php

namespace Database\Seeders;

use App\Enums\Ukuran;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class TmProductSeeder extends Seeder
{
    /**
     * Input daftar produk TM420 dari "skulist produk TM.xlsx".
     * Format: [nama_artikel, kategori, base_sku]. SKU turunan = base_sku . '-' . ukuran.
     */
    public function run(): void
    {
        $tm420 = Brand::where('nama', 'TM420')->firstOrFail();
        $categories = Category::pluck('id', 'nama');

        $data = [
            ['Peace Of God', 'Longsleeve 24s', 'TS-POG'],
            ['Keep it Green', 'Double Layer 24s', 'TSDL-KEEPITGREEN'],
            ['Smile Leaf', 'Double Layer 24s', 'TSDL-SOL'],
            ['Legalize Maryjane', 'Double Layer 24s', 'TSDL-LGLZMRJN'],
            ['Enjoy Coffee', 'Double Layer 24s', 'TSDL-ENJOYCOFFEE'],
            ['YinYang', 'Longsleeve 24s', 'TSLS-YINYANG'],
            ['Everyday Is Good Day', 'Oversized 20s', 'TSOV-GOODDDAY'],
            ['Relax Oversized', 'Oversized 20s', 'TSOV-RELAX'],
            ['Legalized Maryjane Misty', 'Reguler 24s', 'TS-LGLZMRJN'],
            ['Circle', 'Reguler 24s', 'TS-CIRCLE'],
            ['Champions', 'Reguler 24s', 'TS-CANNACHAMP'],
            ['Enjoy Today', 'Reguler 24s', 'TS-ENJOYTODAY'],
            ["Let's Grow", 'Reguler 24s', 'TS-LETSGROW'],
            ['Marley Raglan', 'Reguler 24s', 'TS-RAGBOB'],
            ['Good Life', 'Reguler 24s', 'TS-GOODLIFE'],
            ['Mandala', 'Reguler 24s', 'TS-MANDALA'],
            ['Marley Grey', 'Reguler 24s', 'TS-MARGREY'],
            ['Smiley', 'Reguler 24s', 'TS-SMILEY'],
            ['Chichen Itza', 'Reguler 24s', 'TS-ITZA'],
            ['Stoned Icon', 'Reguler 24s', 'TS-STONEDICONS'],
            ['Organic Black', 'Reguler 24s', 'TS-ORGANIC-BLACK'],
            ['Organic Green', 'Reguler 24s', 'TS-ORGANIC-GREEN'],
            ['Hightimes', 'Reguler 24s', 'TS-HIGHTIMES'],
            ['Basic Black', 'Reguler 24s', 'TS-POLOS-BLACK'],
            ['Basic White', 'Reguler 24s', 'TS-POLOS-WHITE'],
            ['Basic Misty', 'Reguler 24s', 'TS-POLOS-MISGREY'],
            ['Basic Maroon', 'Reguler 24s', 'TS-POLOS-MAROON'],
            ['Basic Sage', 'Reguler 24s', 'TS-POLOS-SAGE'],
            ['Basic Dark Grey', 'Reguler 24s', 'TS-POLOS-DARKGREY'],
            ['Basic Blue Ocean', 'Reguler 24s', 'TS-POLOS-BLUE'],
            ['Basic Mustard', 'Reguler 24s', 'TS-POLOS-MUSTARD'],
            ['Teach Peace', 'Hoodie Jumper 280gsm', 'HOOD-TEACHPEACE'],
            ['Tropical Vibes', 'Reguler 24s', 'TS-TROVIB'],
        ];

        foreach ($data as [$nama, $kategori, $base]) {
            $categoryId = $categories[$kategori] ?? null;
            if (! $categoryId) {
                $this->command?->warn("Kategori '{$kategori}' tidak ditemukan untuk {$nama}, dilewati.");
                continue;
            }

            $product = Product::updateOrCreate(
                ['brand_id' => $tm420->id, 'nama_artikel' => $nama],
                ['category_id' => $categoryId, 'sku_induk' => $base, 'aktif' => true],
            );

            foreach (Ukuran::cases() as $u) {
                $product->sizes()->updateOrCreate(
                    ['ukuran' => $u->value],
                    ['sku_turunan' => $base.'-'.$u->value],
                );
            }
        }

        $this->command?->info('TmProductSeeder: '.count($data).' produk diproses.');
    }
}

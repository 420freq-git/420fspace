<?php

namespace Database\Seeders;

use App\Enums\BrandType;
use App\Enums\Role;
use App\Enums\Ukuran;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Brands
        $tm420 = Brand::updateOrCreate(
            ['nama' => 'TM420'],
            ['tipe' => BrandType::Eksternal, 'kode' => 'TM', 'aktif' => true],
        );
        Brand::updateOrCreate(
            ['nama' => 'VOOJAH'],
            ['tipe' => BrandType::MilikSendiri, 'kode' => 'VJ', 'aktif' => true],
        );

        // Kategori + master harga (Diferd, TM420) per tier [s_xl, xxl]
        $categories = [
            'Reguler 24s'          => ['s_xl' => [62000, 67000], 'xxl' => [67000, 72000]],
            'Longsleeve 24s'       => ['s_xl' => [73000, 78000], 'xxl' => [78000, 83000]],
            'Oversized 20s'        => ['s_xl' => [70000, 80000], 'xxl' => [75000, 85000]],
            'Hoodie Jumper 280gsm' => ['s_xl' => [175000, 185000], 'xxl' => [180000, 190000]],
            'Double Layer 24s'     => ['s_xl' => [78000, 83000], 'xxl' => [83000, 88000]],
        ];

        foreach ($categories as $nama => $tiers) {
            $category = Category::updateOrCreate(['nama' => $nama], ['aktif' => true]);
            foreach ($tiers as $tier => [$hargaDiferd, $hargaTm420]) {
                $category->prices()->updateOrCreate(
                    ['size_tier' => $tier],
                    ['harga_diferd' => $hargaDiferd, 'harga_tm420' => $hargaTm420],
                );
            }
        }

        // Users
        $users = [
            ['name' => '420Frequency', 'email' => '420freq@gmail.com', 'role' => Role::Admin, 'brand_id' => null],
            ['name' => 'TM420',  'email' => 'tm420@420frequency.test',  'role' => Role::Tm420,  'brand_id' => $tm420->id],
            ['name' => 'VOOJAH', 'email' => 'voojah@420frequency.test', 'role' => Role::Voojah, 'brand_id' => Brand::where('nama', 'VOOJAH')->first()->id],
            ['name' => 'Diferd', 'email' => 'difred@420frequency.test', 'role' => Role::Diferd, 'brand_id' => null],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'role' => $u['role'],
                    'brand_id' => $u['brand_id'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );
        }

        // Produk contoh (dummy dari spreadsheet) — brand TM420, kategori Reguler 24s
        $reguler = Category::where('nama', 'Reguler 24s')->first();
        $artikels = [
            'Peace Of God' => 'POG',
            'Keep it Green' => 'KIG',
            'Smile Leaf' => 'SML',
            'Stoned Icon' => 'STI',
            'Organic Black' => 'ORB',
        ];

        foreach ($artikels as $nama => $abbr) {
            $product = Product::updateOrCreate(
                ['brand_id' => $tm420->id, 'nama_artikel' => $nama],
                ['category_id' => $reguler->id, 'sku_induk' => "TM-{$abbr}", 'aktif' => true],
            );
            foreach (Ukuran::cases() as $ukuran) {
                $product->sizes()->updateOrCreate(
                    ['ukuran' => $ukuran->value],
                    ['sku_turunan' => "TM-{$abbr}-{$ukuran->value}"],
                );
            }
        }

        // Produk dummy untuk VOOJAH
        $voojah = Brand::where('nama', 'VOOJAH')->first();
        $artikelsVoojah = [
            'Voojah Signature' => 'V-SIG',
            'Voojah Basic Black' => 'V-BB',
            'Voojah Minimalist' => 'V-MIN',
        ];

        $hoodie = Category::where('nama', 'Hoodie Jumper 280gsm')->first();

        foreach ($artikelsVoojah as $nama => $abbr) {
            $product = Product::updateOrCreate(
                ['brand_id' => $voojah->id, 'nama_artikel' => $nama],
                ['category_id' => $hoodie->id, 'sku_induk' => "{$abbr}", 'aktif' => true],
            );
            foreach (Ukuran::cases() as $ukuran) {
                $product->sizes()->updateOrCreate(
                    ['ukuran' => $ukuran->value],
                    ['sku_turunan' => "{$abbr}-{$ukuran->value}"],
                );
            }
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\SizeTier;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('prices')->orderBy('nama')->get();

        return view('categories.index', compact('categories'));
    }

    public function create()
    {
        return view('categories.create', ['category' => new Category(['aktif' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $category = Category::create(['nama' => $data['nama'], 'aktif' => $data['aktif']]);
        $this->savePrices($category, $data['prices']);

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(Category $category)
    {
        $category->load('prices');

        return view('categories.edit', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validated($request, $category);

        $category->update(['nama' => $data['nama'], 'aktif' => $data['aktif']]);
        $this->savePrices($category, $data['prices']);

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Kategori dihapus.');
    }

    private function savePrices(Category $category, array $prices): void
    {
        foreach (SizeTier::cases() as $tier) {
            $row = $prices[$tier->value] ?? [];
            $tm420 = $row['harga_tm420'] ?? null;
            $category->prices()->updateOrCreate(
                ['size_tier' => $tier->value],
                [
                    'harga_diferd' => $row['harga_diferd'] ?? 0,
                    'harga_tm420' => ($tm420 === null || $tm420 === '') ? null : (int) $tm420,
                ],
            );
        }
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('categories', 'nama')->ignore($category?->id)],
            'aktif' => ['nullable', 'boolean'],
            'prices.s_xl.harga_diferd' => ['required', 'integer', 'min:0'],
            'prices.s_xl.harga_tm420' => ['nullable', 'integer', 'min:0'],
            'prices.xxl.harga_diferd' => ['required', 'integer', 'min:0'],
            'prices.xxl.harga_tm420' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['aktif'] = $request->boolean('aktif');

        return $validated;
    }
}

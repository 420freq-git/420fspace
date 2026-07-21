<?php

namespace App\Http\Controllers;

use App\Enums\BrandType;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::withCount('users')->orderBy('nama')->get();

        return view('brands.index', compact('brands'));
    }

    public function create()
    {
        return view('brands.create', ['brand' => new Brand(['aktif' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Brand::create($data);

        return redirect()->route('brands.index')->with('success', 'Brand berhasil ditambahkan.');
    }

    public function edit(Brand $brand)
    {
        return view('brands.edit', compact('brand'));
    }

    public function update(Request $request, Brand $brand)
    {
        $data = $this->validated($request, $brand);

        $brand->update($data);

        return redirect()->route('brands.index')->with('success', 'Brand berhasil diperbarui.');
    }

    public function destroy(Brand $brand)
    {
        if ($brand->users()->exists()) {
            return back()->with('error', 'Brand tidak bisa dihapus karena masih terhubung ke akun pengguna.');
        }

        $brand->delete();

        return redirect()->route('brands.index')->with('success', 'Brand dihapus.');
    }

    private function validated(Request $request, ?Brand $brand = null): array
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('brands', 'nama')->ignore($brand?->id)],
            'tipe' => ['required', new Enum(BrandType::class)],
            'kode' => ['nullable', 'string', 'max:10'],
            'aktif' => ['boolean'],
        ]);

        $validated['aktif'] = $request->boolean('aktif');

        return $validated;
    }
}

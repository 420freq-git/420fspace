<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', [
            'users' => User::with('brand')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('users.create', [
            'user' => new User(),
            'brands' => Brand::orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['password'] = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ])['password'];

        User::create($data);

        return redirect()->route('users.index')->with('success', 'Akun dibuat.');
    }

    public function edit(User $user)
    {
        return view('users.edit', [
            'user' => $user,
            'brands' => Brand::orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $user->update($this->validated($request, $user));

        return redirect()->route('users.index')->with('success', 'Akun diperbarui.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->update(['password' => $data['password']]);

        return back()->with('success', 'Password '.$user->name.' direset.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Tidak bisa menghapus akun sendiri.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Akun dihapus.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', new Enum(Role::class)],
            'brand_id' => [
                Rule::requiredIf(fn () => in_array($request->input('role'), [Role::Tm420->value, Role::Voojah->value], true)),
                'nullable',
                'exists:brands,id',
            ],
        ]);
    }
}

<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-sand-900">Masuk</h1>
        <p class="mt-1 text-sm text-sand-500">Silakan masuk ke akun Anda untuk melanjutkan.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full border-sand-300 focus:border-brand-600 focus:ring-brand-600 rounded-lg" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full border-sand-300 focus:border-brand-600 focus:ring-brand-600 rounded-lg"
                            type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-sand-300 text-brand-700 shadow-sm focus:ring-brand-600" name="remember">
                <span class="ms-2 text-sm text-sand-600">{{ __('Ingat saya') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm text-brand-700 hover:text-brand-800 hover:underline focus:outline-none" href="{{ route('password.request') }}">
                    {{ __('Lupa password?') }}
                </a>
            @endif
        </div>

        <button type="submit" class="w-full inline-flex items-center justify-center rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-600 focus:ring-offset-2 transition">
            {{ __('Masuk') }}
        </button>
    </form>
</x-guest-layout>

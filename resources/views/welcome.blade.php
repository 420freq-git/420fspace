<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ config('app.name', '420Frequency') }} &mdash; Produksi &amp; Settlement</title>
        <meta name="description" content="Sistem internal 420Frequency untuk melacak produksi apparel, stok, dan pelunasan vendor.">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-sand-800 antialiased bg-sand-50">
        <div class="min-h-screen flex flex-col">

            {{-- Header --}}
            <header class="border-b border-sand-200 bg-white">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="inline-grid place-items-center h-9 w-9 rounded-lg bg-brand-700 text-white font-bold text-sm">420</span>
                        <div class="leading-tight">
                            <div class="font-semibold text-sand-900">Frequency</div>
                            <div class="text-xs text-sand-500">produksi &amp; settlement</div>
                        </div>
                    </div>

                    @auth
                        <a href="{{ route('dashboard') }}"
                           class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                            Ke Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                           class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                            Masuk
                        </a>
                    @endauth
                </div>
            </header>

            {{-- Hero --}}
            <main class="flex-1">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
                    <p class="text-sm font-semibold uppercase tracking-wider text-brand-700">Sistem internal</p>
                    <h1 class="mt-3 text-3xl sm:text-4xl font-semibold leading-snug text-sand-900 max-w-2xl">
                        Satu sistem untuk produksi, stok, dan pelunasan vendor.
                    </h1>
                    <p class="mt-4 text-sand-600 max-w-xl">
                        420Frequency melacak siapa memproduksi apa, stok ada di mana, serta siapa berutang
                        ke siapa &mdash; dari pengajuan batch sampai settlement.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}"
                               class="rounded-lg bg-brand-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                                Buka Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}"
                               class="rounded-lg bg-brand-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                                Masuk ke sistem
                            </a>
                            <span class="text-xs text-sand-500">Akun dibuat oleh admin 420Frequency.</span>
                        @endauth
                    </div>

                    {{-- Peran --}}
                    <div class="mt-16">
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-sand-400">Pihak yang terlibat</h2>
                        <div class="mt-4 grid gap-4 sm:grid-cols-3">
                            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                                <p class="font-semibold text-sand-900">420Frequency</p>
                                <p class="mt-1 text-sm text-sand-600">Penengah &amp; pemilik sistem. Mengelola uang, approval, dan settlement.</p>
                            </div>
                            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                                <p class="font-semibold text-sand-900">TM420 &amp; VOOJAH</p>
                                <p class="mt-1 text-sm text-sand-600">Brand. Mengajukan batch produksi, mengelola produk, pesanan, dan tagihan.</p>
                            </div>
                            <div class="rounded-xl border border-sand-200 bg-white shadow-sm p-5">
                                <p class="font-semibold text-sand-900">Diferd</p>
                                <p class="mt-1 text-sm text-sand-600">Vendor produksi. Memperbarui tahap produksi, surat jalan, dan penarikan hak.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            {{-- Footer --}}
            <footer class="border-t border-sand-200 bg-white">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-xs text-sand-500">
                    &copy; {{ date('Y') }} 420Frequency
                </div>
            </footer>
        </div>
    </body>
</html>

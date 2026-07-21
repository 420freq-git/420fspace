<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-sand-800 antialiased bg-sand-50">
        <div class="min-h-screen flex items-center justify-center p-4">
            <div class="w-full max-w-4xl grid md:grid-cols-2 rounded-2xl overflow-hidden border border-sand-200 bg-white shadow-sm">

                {{-- Brand panel --}}
                <div class="bg-brand-700 text-white p-8 sm:p-10 flex flex-col justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="inline-grid place-items-center h-10 w-10 rounded-lg bg-white/15 font-bold">420</span>
                        <div class="leading-tight">
                            <div class="font-semibold text-lg">Frequency</div>
                            <div class="text-xs text-brand-100">produksi &amp; settlement</div>
                        </div>
                    </div>

                    <div class="hidden md:block mt-10">
                        <h2 class="text-2xl font-semibold leading-snug text-balance">Satu sistem untuk produksi, stok, dan pelunasan vendor.</h2>
                        <ul class="mt-6 space-y-2.5 text-sm text-brand-100">
                            <li class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-brand-200"></span> 420Frequency &mdash; penengah &amp; sistem</li>
                            <li class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-brand-200"></span> TM420 &amp; VOOJAH &mdash; brand</li>
                            <li class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-brand-200"></span> Diferd &mdash; vendor produksi</li>
                        </ul>
                    </div>

                    <div class="mt-10 text-xs text-brand-200">&copy; {{ date('Y') }} 420Frequency</div>
                </div>

                {{-- Form panel --}}
                <div class="p-8 sm:p-10 flex flex-col justify-center">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>

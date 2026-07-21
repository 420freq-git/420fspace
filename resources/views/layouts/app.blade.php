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
    <body class="font-sans antialiased text-sand-800">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen">

            {{-- Mobile overlay --}}
            <div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"
                 class="fixed inset-0 z-30 bg-sand-900/40 lg:hidden" style="display:none"></div>

            {{-- Sidebar --}}
            <aside class="fixed inset-y-0 left-0 z-40 w-64 bg-white border-r border-sand-200 transform transition-transform duration-200 lg:translate-x-0"
                   :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
                @include('layouts.sidebar')
            </aside>

            {{-- Main column --}}
            <div class="lg:pl-64 flex min-h-screen flex-col">
                {{-- Topbar --}}
                <header class="sticky top-0 z-20 bg-sand-50/90 backdrop-blur border-b border-sand-200">
                    <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                        <button @click="sidebarOpen = true" class="lg:hidden p-2 -ms-2 rounded-md text-sand-500 hover:bg-sand-100">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                        </button>

                        <div class="min-w-0 flex-1">
                            @isset($header)
                                {{ $header }}
                            @else
                                <h1 class="text-lg font-semibold text-sand-900">{{ $title ?? '' }}</h1>
                            @endisset
                        </div>

                        {{-- Pusat notifikasi --}}
                        @php
                            $notifikasi = $notifikasi ?? [];
                            $notifTotal = array_sum(array_column($notifikasi, 'count'));
                            $notifSig = md5(json_encode($notifikasi));
                            $toneDot = ['danger' => 'bg-red-500', 'warn' => 'bg-amber-500', 'info' => 'bg-blue-500'];
                        @endphp
                        <div x-data="{
                                 open: false,
                                 sig: @js($notifSig),
                                 seen: localStorage.getItem('notifSeen') || '',
                                 markSeen() { this.seen = this.sig; localStorage.setItem('notifSeen', this.sig); },
                             }" class="relative">
                            <button @click="open = !open; if (open) markSeen()" class="relative p-2 rounded-md text-sand-500 hover:bg-sand-100" title="Notifikasi">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                                @if ($notifTotal > 0)
                                    <span x-show="seen !== sig" x-cloak
                                          class="absolute top-0.5 right-0.5 inline-flex items-center justify-center min-w-[17px] h-[17px] px-1 rounded-full bg-red-600 text-white text-[10px] font-semibold leading-none">{{ $notifTotal > 99 ? '99+' : $notifTotal }}</span>
                                @endif
                            </button>
                            <div x-show="open" x-transition @click.outside="open = false" x-cloak
                                 class="absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-xl border border-sand-200 bg-white shadow-lg z-40 overflow-hidden">
                                <div class="px-4 py-3 border-b border-sand-100 flex items-center justify-between">
                                    <span class="text-sm font-semibold text-sand-800">Perlu tindakan</span>
                                    @if ($notifTotal > 0)<span class="text-xs text-sand-400">{{ $notifTotal }} item</span>@endif
                                </div>
                                <div class="max-h-96 overflow-y-auto divide-y divide-sand-100">
                                    @forelse ($notifikasi as $n)
                                        <a href="{{ $n['url'] }}" class="flex items-center gap-3 px-4 py-3 hover:bg-sand-50">
                                            <span class="h-2 w-2 rounded-full shrink-0 {{ $toneDot[$n['tone']] ?? 'bg-sand-400' }}"></span>
                                            <span class="flex-1 text-sm text-sand-700">{{ $n['label'] }}</span>
                                            <span class="tnum text-xs font-semibold text-sand-500 bg-sand-100 rounded-full px-2 py-0.5">{{ $n['count'] }}</span>
                                        </a>
                                    @empty
                                        <div class="px-4 py-10 text-center text-sm text-sand-400">Tidak ada yang perlu tindakan 🎉</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <span class="hidden sm:inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ auth()->user()->role->badgeClasses() }}">
                            {{ auth()->user()->role->label() }}
                        </span>
                    </div>
                </header>

                {{-- Content --}}
                <main class="flex-1">
                    @if (session('success') || session('error'))
                        @php $isError = (bool) session('error'); @endphp
                        <div x-data="{ show: true }" x-show="show" x-transition
                             class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
                            <div class="flex items-center gap-3 rounded-lg border px-4 py-3 text-sm
                                        {{ $isError ? 'border-red-200 bg-red-50 text-red-800' : 'border-brand-200 bg-brand-50 text-brand-800' }}">
                                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    @if ($isError)
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                                    @else
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    @endif
                                </svg>
                                <span>{{ session('success') ?? session('error') }}</span>
                                <button @click="show = false" class="ms-auto opacity-60 hover:opacity-100">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>

@php
    use App\Enums\Role;
    $role = auth()->user()->role;

    $groups = [
        ['title' => null, 'items' => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'ready' => true, 'roles' => [Role::Admin, Role::Tm420, Role::Voojah, Role::Diferd],
             'icon' => 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25A2.25 2.25 0 0113.5 8.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z'],
        ]],
        ['title' => 'Katalog', 'items' => [
            ['label' => 'Brand', 'route' => 'brands.index', 'ready' => true, 'roles' => [Role::Admin],
             'icon' => 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3zM6 6h.008v.008H6V6z'],
            ['label' => 'Kategori & Harga', 'route' => 'categories.index', 'ready' => true, 'roles' => [Role::Admin],
             'icon' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z'],
            ['label' => 'Produk', 'route' => 'products.index', 'ready' => true, 'roles' => [Role::Admin, Role::Tm420, Role::Voojah, Role::Diferd],
             'icon' => 'M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9'],
        ]],
        ['title' => 'Produksi', 'items' => [
            ['label' => 'Batch / PO', 'route' => 'batches.index', 'ready' => true, 'roles' => [Role::Admin, Role::Tm420, Role::Voojah, Role::Diferd],
             'icon' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z'],
            ['label' => 'Monitoring produksi', 'route' => 'monitoring-produksi.index', 'ready' => true, 'roles' => [Role::Admin, Role::Tm420, Role::Voojah, Role::Diferd],
             'icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z'],
            ['label' => 'Stok', 'route' => 'stok.index', 'ready' => true, 'roles' => [Role::Admin, Role::Tm420, Role::Voojah, Role::Diferd],
             'icon' => 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z'],
            ['label' => 'Pengiriman', 'route' => 'pengiriman.index', 'ready' => true, 'roles' => [Role::Admin, Role::Tm420, Role::Voojah, Role::Diferd],
             'icon' => 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-6m0 0v-9.75m0 9.75H8.25m6-9.75H4.5m0 0V6.108c0-1.135.845-2.098 1.976-2.192a48.424 48.424 0 011.123-.08M14.25 8.25V6.108'],
            ['label' => 'Radar deadline', 'route' => 'radar.index', 'ready' => true, 'roles' => [Role::Admin, Role::Tm420, Role::Voojah],
             'icon' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'],
        ]],
        ['title' => 'Penjualan', 'items' => [
            ['label' => 'Pesanan', 'route' => 'orders.index', 'ready' => true, 'roles' => [Role::Admin, Role::Tm420, Role::Voojah],
             'icon' => 'M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12A2.25 2.25 0 0118.489 21H5.511a2.25 2.25 0 01-2.24-2.493l1.263-12A2.25 2.25 0 016.772 6h10.456a2.25 2.25 0 012.24 2.507z'],
            ['label' => 'Monitoring cek', 'route' => 'monitoring.cek', 'ready' => true, 'roles' => [Role::Admin, Role::Tm420, Role::Voojah],
             'icon' => 'm21 21-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z'],
            ['label' => 'Barang kembali', 'route' => 'monitoring.kembali', 'ready' => true, 'roles' => [Role::Admin, Role::Tm420, Role::Voojah],
             'icon' => 'M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3'],
        ]],
        ['title' => 'Keuangan', 'items' => [
            ['label' => 'Invoice TM', 'route' => 'invoices.index', 'ready' => true, 'roles' => [Role::Admin, Role::Tm420, Role::Voojah],
             'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z'],
            ['label' => 'Penarikan Diferd', 'route' => 'penarikan.index', 'ready' => true, 'roles' => [Role::Admin, Role::Diferd],
             'icon' => 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z'],
            ['label' => 'Settlement', 'route' => 'settlement.index', 'ready' => true, 'roles' => [Role::Admin, Role::Diferd],
             'icon' => 'M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3'],
            ['label' => 'Cashflow', 'route' => 'cashflow.index', 'ready' => true, 'roles' => [Role::Admin],
             'icon' => 'M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5'],
            ['label' => 'Saldo 420F', 'route' => 'saldo.index', 'ready' => true, 'roles' => [Role::Admin],
             'icon' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z'],
            ['label' => 'Rekonsiliasi TM', 'route' => 'rekonsiliasi.index', 'ready' => true, 'roles' => [Role::Admin],
             'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ]],
        ['title' => 'Laporan', 'items' => [
            ['label' => 'Penjualan', 'route' => 'laporan.penjualan', 'ready' => true, 'roles' => [Role::Admin, Role::Tm420, Role::Voojah, Role::Diferd],
             'icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z'],
            ['label' => 'Kerugian', 'route' => 'laporan.kerugian', 'ready' => true, 'roles' => [Role::Admin, Role::Tm420, Role::Voojah, Role::Diferd],
             'icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z'],
            ['label' => 'Produksi & vendor', 'route' => 'laporan.produksi', 'ready' => true, 'roles' => [Role::Admin, Role::Tm420, Role::Voojah, Role::Diferd],
             'icon' => 'M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z'],
            ['label' => 'Scorecard vendor', 'route' => 'scorecard.index', 'ready' => true, 'roles' => [Role::Admin, Role::Diferd],
             'icon' => 'M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25'],
            ['label' => 'Perputaran stok', 'route' => 'laporan.perputaran', 'ready' => true, 'roles' => [Role::Admin, Role::Tm420, Role::Voojah, Role::Diferd],
             'icon' => 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99'],
            ['label' => 'Terjual per kategori', 'route' => 'laporan.terjual-kategori', 'ready' => true, 'roles' => [Role::Admin, Role::Diferd],
             'icon' => 'M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6'],
            ['label' => 'Keuangan bulanan', 'route' => 'laporan.keuangan', 'ready' => true, 'roles' => [Role::Admin, Role::Tm420, Role::Voojah, Role::Diferd],
             'icon' => 'M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.281m5.94 2.28l-2.28 5.941'],
        ]],
        ['title' => 'Pengaturan', 'items' => [
            ['label' => 'Pengguna', 'route' => 'users.index', 'ready' => true, 'roles' => [Role::Admin],
             'icon' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z'],
            ['label' => 'Threshold monitoring', 'route' => 'settings.index', 'ready' => true, 'roles' => [Role::Admin],
             'icon' => 'M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.855.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.855-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.855-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894zM15 12a3 3 0 11-6 0 3 3 0 016 0z'],
            ['label' => 'Audit log', 'route' => 'audit.index', 'ready' => true, 'roles' => [Role::Admin],
             'icon' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z'],
        ]],
    ];
@endphp

<div class="flex h-full flex-col">
    {{-- Brand --}}
    <div class="flex items-center gap-2.5 px-5 h-16 border-b border-sand-200/70">
        <span class="inline-grid place-items-center h-9 w-9 rounded-lg bg-brand-700 text-white font-bold text-sm">420</span>
        <div class="leading-tight">
            <div class="font-semibold text-sand-900">Frequency</div>
            <div class="text-[11px] text-sand-500">produksi &amp; settlement</div>
        </div>
    </div>

    {{-- Nav --}}
    <nav id="sidebarNav" class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
        @foreach ($groups as $group)
            @php $items = array_filter($group['items'], fn ($i) => in_array($role, $i['roles'])); @endphp
            @if (count($items))
                <div>
                    @if ($group['title'])
                        <p class="px-3 mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-sand-400">{{ $group['title'] }}</p>
                    @endif
                    <div class="space-y-0.5">
                        @foreach ($items as $item)
                            @php $active = $item['ready'] && $item['route'] && request()->routeIs($item['route']); @endphp
                            @if ($item['ready'] && $item['route'])
                                <a href="{{ route($item['route']) }}"
                                   class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition
                                          {{ $active ? 'bg-brand-700 text-white' : 'text-sand-700 hover:bg-sand-100' }}">
                                    <svg class="h-5 w-5 shrink-0 {{ $active ? 'text-white' : 'text-sand-400 group-hover:text-sand-600' }}" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            @else
                                <span class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-sand-400 cursor-default">
                                    <svg class="h-5 w-5 shrink-0 text-sand-300" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                                    <span>{{ $item['label'] }}</span>
                                    <span class="ms-auto text-[10px] font-medium uppercase tracking-wide text-sand-300">soon</span>
                                </span>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </nav>

    {{-- Simpan posisi scroll sidebar antar-halaman --}}
    <script>
        (function () {
            var nav = document.getElementById('sidebarNav');
            if (!nav) return;
            var KEY = 'sidebarScroll';
            var saved = sessionStorage.getItem(KEY);
            if (saved) nav.scrollTop = parseInt(saved, 10) || 0;
            var t;
            nav.addEventListener('scroll', function () {
                clearTimeout(t);
                t = setTimeout(function () { sessionStorage.setItem(KEY, nav.scrollTop); }, 100);
            }, { passive: true });
            nav.addEventListener('click', function () { sessionStorage.setItem(KEY, nav.scrollTop); });
        })();
    </script>

    {{-- User --}}
    <div class="border-t border-sand-200/70 p-3">
        <div class="flex items-center gap-3 rounded-lg px-2 py-2">
            <span class="inline-grid place-items-center h-9 w-9 rounded-full bg-sand-200 text-sand-700 font-semibold text-sm">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </span>
            <div class="min-w-0 flex-1 leading-tight">
                <div class="truncate text-sm font-medium text-sand-900">{{ auth()->user()->name }}</div>
                <div class="truncate text-xs text-sand-500">{{ auth()->user()->role->label() }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" title="Keluar" class="p-1.5 rounded-md text-sand-400 hover:text-sand-700 hover:bg-sand-100 transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
                </button>
            </form>
        </div>
    </div>
</div>

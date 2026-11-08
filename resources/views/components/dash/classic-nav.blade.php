<header id="classic-nav" class="hud-classic-only no-print sticky top-0 z-50 shadow-sm">
    <div class="ch-topbar">
        <div class="mx-auto flex h-16 max-w-[1750px] items-center justify-between gap-4 px-5">
            <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-2.5">
                <span class="ch-logo">L</span>
                <span>
                    <span class="block text-[15px] font-bold leading-tight tracking-tight">Leans</span>
                    <span class="ch-muted block text-[11px]">{{ $user->name }}</span>
                </span>
            </a>

            <form method="GET" action="{{ route('dashboard') }}" class="relative mx-4 hidden max-w-md flex-1 md:block" {{ tour(11, 'Arama', 'Sistemdeki kullanıcıları ad, e-posta veya telefonla bulursunuz.') }}>
                <label class="sr-only" for="classic-q">Kullanıcı ara</label>
                <input id="classic-q" name="q" value="{{ request('q') }}" autocomplete="off" placeholder="Kullanıcı, e-posta veya telefon…" class="ch-search">
                <span class="ch-muted pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"><x-icon name="search" :size="15" /></span>
            </form>

            <div class="flex shrink-0 items-center gap-2 text-xs">
                @include('components.dash.setup-button', ['buttonClass' => 'ch-btn'])

                <button type="button" {{ tour(10, 'Site seçici', 'Yönettiğiniz siteler arasında buradan geçersiniz. Ekrandaki tüm rakamlar seçili siteye aittir. Yeni site de buradan eklenir.') }} data-toggle="apt-modal" class="ch-btn">
                    <x-icon name="building-2" :size="14" />
                    <span class="max-w-[150px] truncate">{{ $currentSite?->name ?? 'Konut Seç' }}</span>
                    <x-icon name="chevron-down" :size="12" />
                </button>

                <button type="button" {{ tour(41, 'Görünüm ayarları', 'Modern veya Klasik arayüz, açık/koyu mod ve tema rengi burada. Bu anlatımı da buradan yeniden başlatabilirsiniz.') }} data-toggle="settings-modal" class="ch-icon" title="Görünüm ayarları" aria-label="Görünüm ayarları"><x-icon name="settings" :size="16" /></button>

                <a href="{{ $siteRoute('site.tickets.index') }}" class="ch-icon relative" {{ tour(42, 'Destek talepleri', 'Sakinlerin açtığı arıza ve destek taleplerine gider. Üzerindeki sayı açık talep adedidir.') }} title="Açık destek talepleri" aria-label="Açık destek talepleri: {{ $openTickets }}">
                    <x-icon name="bell" :size="16" />
                    @if ($openTickets)
                        <span class="ch-count">{{ $openTickets }}</span>
                    @endif
                </a>

                <details class="dropdown relative">
                    <summary class="ch-logo ch-logo-sm" aria-label="Hesap menüsü" {{ tour(43, 'Hesabınız', 'Profilinizi görür ve işiniz bitince güvenli çıkış yaparsınız.') }}>{{ $initial }}</summary>
                    <div class="ch-menu right-0 w-48">
                        <a href="{{ route('users.show', $user) }}" class="drop-item">Profilim</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="drop-item w-full">Çıkış yap</button>
                        </form>
                    </div>
                </details>
            </div>
        </div>
    </div>

    <nav class="ch-navband" aria-label="Üst menü">
        <div class="mx-auto flex max-w-[1750px] flex-wrap items-center gap-0.5 px-5 py-1.5">
            <a href="{{ route('dashboard') }}" {{ tour(15, 'Genel', 'Her zaman ana panoya döner. Sitenizin para durumunu tek ekranda burada görürsünüz.') }} @class(['ch-tab', 'is-active' => $is('dashboard', 'site.dashboard')])>
                <x-icon name="home" :size="14" /><span>Genel</span>
            </a>

            @foreach ($railMenus as [$groupLabel, $icon, $primaryRoute, $active, $items])
                <details class="dropdown relative">
                    <summary {{ tour(20 + $loop->index, $groupLabel, $menuHelp[$groupLabel]) }} @class(['ch-tab', 'is-active' => $active])>
                        <x-icon :name="$icon" :size="14" /><span>{{ $groupLabel }}</span><x-icon name="chevron-down" :size="11" />
                    </summary>
                    <div @class(['ch-menu w-64', $loop->remaining < 3 ? 'right-0' : 'left-0'])>
                        @foreach ($items as $item)
                            @php
                                [$itemLabel, $itemRoute, $itemIcon] = $item;
                                $itemHref = ($item[3] ?? false) ? route($itemRoute) : $siteRoute($itemRoute);
                            @endphp
                            <a href="{{ $itemHref }}" @class(['drop-item', 'active' => $is($itemRoute.'*')])>
                                <span class="flex items-center gap-2"><x-icon :name="$itemIcon" :size="14" />{{ $itemLabel }}</span>
                            </a>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    </nav>
</header>

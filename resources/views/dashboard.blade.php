@php
    $siteCount = auth()->user()?->accessibleApartments()->count() ?? 0;
@endphp

<x-layout heading="Genel Bakış" subheading="Sistemdeki tüm konutlar, kullanıcılar ve operasyonların canlı durumu.">
    <x-slot:actions>
        <button type="button" class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors" title="Yardım">
            <x-icon name="help-circle" :size="16" />
        </button>
        <button type="button" class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors" title="Ayarlar">
            <x-icon name="settings" :size="16" />
        </button>
        <a href="{{ route('properties.create') }}" class="btn-brand">
            <x-icon name="plus" :size="14" />
            <span>Yeni İlan Ekle</span>
        </a>
    </x-slot:actions>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-5 transition-all hover:shadow-md">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-500">Aktif Portföy / İlanlar</p>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-extrabold tracking-tight text-slate-900 tabular-nums">{{ $propertyCount }}</span>
                        <span class="inline-flex items-center text-xs font-bold text-emerald-600">
                            <x-icon name="trending-up" :size="13" class="mr-0.5" /> +12%
                        </span>
                    </div>
                </div>
                <div class="w-24 h-10">
                    <svg class="w-full h-full" viewBox="0 0 100 40" fill="none">
                        <path d="M0 30 Q 20 10, 40 25 T 80 15 T 100 8" stroke="#ff5a36" stroke-width="2.5" stroke-linecap="round" fill="none" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="card p-5 transition-all hover:shadow-md">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-500">Kayıtlı Kullanıcılar</p>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-extrabold tracking-tight text-slate-900 tabular-nums">{{ $userCount }}</span>
                        <span class="inline-flex items-center text-xs font-bold text-emerald-600">
                            <x-icon name="trending-up" :size="13" class="mr-0.5" /> +4.2%
                        </span>
                    </div>
                </div>
                <div class="w-24 h-10">
                    <svg class="w-full h-full" viewBox="0 0 100 40" fill="none">
                        <path d="M0 28 Q 25 35, 45 15 T 75 22 T 100 5" stroke="#ff5a36" stroke-width="2.5" stroke-linecap="round" fill="none" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="card p-5 transition-all hover:shadow-md">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-500">Açık Talepler / Emirler</p>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-extrabold tracking-tight text-slate-900 tabular-nums">{{ $openTickets }}</span>
                        <span class="inline-flex items-center text-xs font-bold text-rose-500">
                            <x-icon name="trending-down" :size="13" class="mr-0.5" /> -2%
                        </span>
                    </div>
                </div>
                <div class="w-24 h-10">
                    <svg class="w-full h-full" viewBox="0 0 100 40" fill="none">
                        <path d="M0 15 Q 30 5, 50 25 T 80 18 T 100 32" stroke="#ff5a36" stroke-width="2.5" stroke-linecap="round" fill="none" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="card p-5 transition-all hover:shadow-md">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-500">Yönetilen Siteler</p>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-extrabold tracking-tight text-slate-900 tabular-nums">{{ $siteCount }}</span>
                        <span class="inline-flex items-center text-xs font-bold text-emerald-600">
                            <x-icon name="check" :size="13" class="mr-0.5" /> Aktif
                        </span>
                    </div>
                </div>
                <div class="w-24 h-10">
                    <svg class="w-full h-full" viewBox="0 0 100 40" fill="none">
                        <path d="M0 32 Q 20 28, 45 18 T 75 12 T 100 4" stroke="#ff5a36" stroke-width="2.5" stroke-linecap="round" fill="none" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="border-b border-slate-100 px-6 pt-3">
            <div class="flex items-center gap-6 overflow-x-auto text-xs font-bold">
                <button type="button" class="relative pb-3 text-[#ff5a36] after:absolute after:bottom-0 after:left-0 after:right-0 after:h-0.5 after:bg-[#ff5a36]">Tümü</button>
                <button type="button" class="pb-3 text-slate-400 hover:text-slate-700 transition-colors">Yöneticiler</button>
                <button type="button" class="pb-3 text-slate-400 hover:text-slate-700 transition-colors">Sakinler</button>
                <button type="button" class="pb-3 text-slate-400 hover:text-slate-700 transition-colors">Süper Adminler</button>
                <button type="button" class="pb-3 text-slate-400 hover:text-slate-700 transition-colors">Aktif Konutlar</button>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/50 p-4 sm:px-6">
            <div class="flex flex-1 items-center gap-2 max-w-md">
                <div class="relative w-full">
                    <x-icon name="search" :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input type="text" placeholder="Kullanıcı veya kayıt ara..." class="w-full rounded-xl border border-slate-200 bg-white py-1.5 pl-9 pr-4 text-xs text-slate-800 placeholder:text-slate-400 focus:border-[#ff5a36] focus:outline-none focus:ring-2 focus:ring-[#ff5a36]/15">
                </div>
                <button type="button" class="flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 transition-colors" title="Filtrele">
                    <x-icon name="filter" :size="14" />
                </button>
                <button type="button" class="flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 transition-colors" title="Sırala">
                    <x-icon name="arrow-up-down" :size="14" />
                </button>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('sites.index') }}" class="btn btn-secondary btn-sm">
                    <x-icon name="building" :size="13" />
                    <span>Siteler</span>
                </a>
                <a href="{{ route('users.index') }}" class="btn btn-primary btn-sm">
                    <x-icon name="user-plus" :size="13" />
                    <span>Yeni Kullanıcı</span>
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th class="w-10">
                            <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-[#ff5a36] focus:ring-[#ff5a36]">
                        </th>
                        <th>KULLANICI</th>
                        <th>E-POSTA</th>
                        <th>ROL</th>
                        <th>DURUM</th>
                        <th>GÜVENLİK / PUAN</th>
                        <th>KAYIT TARİHİ</th>
                        <th class="text-right">İŞLEMLER</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentUsers as $u)
                        <tr class="group">
                            <td>
                                <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-[#ff5a36] focus:ring-[#ff5a36]">
                            </td>
                            <td>
                                <a href="{{ route('users.show', $u) }}" class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-xs font-black text-[#ff5a36] group-hover:bg-[#ff5a36] group-hover:text-white transition-colors">
                                        {{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-900 group-hover:text-[#ff5a36] transition-colors">{{ $u->name }}</p>
                                        <p class="text-[11px] text-slate-400">{{ $u->phone ?? 'Telefon belirtilmedi' }}</p>
                                    </div>
                                </a>
                            </td>
                            <td>
                                <span class="text-slate-600 font-medium">{{ $u->email }}</span>
                            </td>
                            <td>
                                @if ($u->is_super_admin)
                                    <span class="badge badge-orange">Süper Admin</span>
                                @elseif ($u->role === 'admin')
                                    <span class="badge badge-blue">Yönetici</span>
                                @else
                                    <span class="badge badge-gray">Sakin</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-green">AKTİF</span>
                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <div class="flex gap-0.5">
                                        <span class="h-3 w-1 rounded-sm bg-emerald-500"></span>
                                        <span class="h-3 w-1 rounded-sm bg-emerald-500"></span>
                                        <span class="h-3 w-1 rounded-sm bg-emerald-500"></span>
                                        <span class="h-3 w-1 rounded-sm bg-emerald-500"></span>
                                        <span class="h-3 w-1 rounded-sm bg-amber-400"></span>
                                        <span class="h-3 w-1 rounded-sm bg-slate-200"></span>
                                    </div>
                                    <span class="text-[11px] font-bold text-slate-500">9/10</span>
                                </div>
                            </td>
                            <td>
                                <span class="text-xs text-slate-500 font-medium">{{ $u->created_at?->format('d M Y') ?? '—' }}</span>
                            </td>
                            <td class="text-right">
                                <a href="{{ route('users.show', $u) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors" title="Detay">
                                    <x-icon name="more-horizontal" :size="16" />
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-xs font-bold uppercase tracking-wider text-slate-400">
                                Henüz kayıtlı kullanıcı bulunmuyor
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="card p-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Sistem ve Veritabanı Durumu</h3>
                <span class="badge badge-green">ÇEVRİMİÇİ</span>
            </div>
            <div class="mt-4 space-y-3">
                <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/60 p-3.5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600">
                            <x-icon name="check-circle-2" :size="16" />
                        </span>
                        <div>
                            <p class="text-xs font-bold text-slate-800">Uygulama & Sunucu</p>
                            <p class="text-[11px] text-slate-400">Laravel {{ app()->version() }} · PHP {{ PHP_VERSION }}</p>
                        </div>
                    </div>
                    <span class="text-xs font-bold text-emerald-600">Aktif</span>
                </div>

                <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/60 p-3.5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-100 text-sky-600">
                            <x-icon name="database" :size="16" />
                        </span>
                        <div>
                            <p class="text-xs font-bold text-slate-800">Veritabanı Motoru</p>
                            <p class="text-[11px] text-slate-400">{{ strtoupper(config('database.default')) }} SQL Bağlantısı</p>
                        </div>
                    </div>
                    <span class="text-xs font-bold text-sky-600">Bağlı</span>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Hızlı Kısayollar</h3>
                <span class="text-xs text-slate-400">Sık kullanılan işlemler</span>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3">
                <a href="{{ route('sites.index') }}" class="group flex items-center gap-3 rounded-xl border border-slate-200/80 p-3.5 transition-all hover:border-[#ff5a36] hover:bg-orange-50/30">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-orange-100 text-[#ff5a36] group-hover:scale-105 transition-transform">
                        <x-icon name="building-2" :size="18" />
                    </span>
                    <div>
                        <p class="text-xs font-bold text-slate-800 group-hover:text-[#ff5a36] transition-colors">Site Yönetimi</p>
                        <p class="text-[10px] text-slate-400">Blok ve daireler</p>
                    </div>
                </a>

                <a href="{{ route('properties.index') }}" class="group flex items-center gap-3 rounded-xl border border-slate-200/80 p-3.5 transition-all hover:border-[#ff5a36] hover:bg-orange-50/30">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-100 text-blue-600 group-hover:scale-105 transition-transform">
                        <x-icon name="home" :size="18" />
                    </span>
                    <div>
                        <p class="text-xs font-bold text-slate-800 group-hover:text-[#ff5a36] transition-colors">Emlak Portföyü</p>
                        <p class="text-[10px] text-slate-400">Satılık ve kiralık</p>
                    </div>
                </a>

                <a href="{{ route('users.index') }}" class="group flex items-center gap-3 rounded-xl border border-slate-200/80 p-3.5 transition-all hover:border-[#ff5a36] hover:bg-orange-50/30">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 group-hover:scale-105 transition-transform">
                        <x-icon name="users" :size="18" />
                    </span>
                    <div>
                        <p class="text-xs font-bold text-slate-800 group-hover:text-[#ff5a36] transition-colors">Sakin Kayıtları</p>
                        <p class="text-[10px] text-slate-400">Kullanıcı rolleri</p>
                    </div>
                </a>

                <button type="button" data-toggle="apt-modal" class="group flex items-center gap-3 rounded-xl border border-slate-200/80 p-3.5 text-left transition-all hover:border-[#ff5a36] hover:bg-orange-50/30">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-purple-100 text-purple-600 group-hover:scale-105 transition-transform">
                        <x-icon name="plus" :size="18" />
                    </span>
                    <div>
                        <p class="text-xs font-bold text-slate-800 group-hover:text-[#ff5a36] transition-colors">Konut Değiştir</p>
                        <p class="text-[10px] text-slate-400">Mevcut siteler</p>
                    </div>
                </button>
            </div>
        </div>
    </div>
</x-layout>


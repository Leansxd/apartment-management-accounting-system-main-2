@php
    $paidRatio = ($collectionState['paid_count'] ?? 0).' / '.($collectionState['total_count'] ?? 0);
    $firstSite = $sites->first();
    $sRoute = fn (string $name) => $firstSite ? route($name, $firstSite) : route('sites.index');
    $newHref = $sRoute('site.debts.index');
    $siteUnitRatio = ($counts['sites'] ?? 0).' site / '.($totals['units'] ?? 0).' daire';

    $portfolioModules = [
        [
            'title' => 'Portföy, Yapı & Sakin Modelleri',
            'desc' => 'Apartment · Block · Unit · UnitGroup · Member · Property',
            'icon' => 'building-2',
            'items' => [
                ['Konut & Site Yönetimi', 'Apartment · Çoklu site yetkisi', ($counts['sites'] ?? 0).' aktif site', route('sites.index'), 'building'],
                ['Blok, Daire & Gruplar', 'Block · Unit · UnitGroup', ($totals['units'] ?? 0).' bağımsız bölüm', $sRoute('site.units.index'), 'building-2'],
                ['Sakin & Üye Kayıtları', 'Member · Davet kodu & Portal', ($totals['members'] ?? 0).' sakin', $sRoute('site.members.index'), 'users'],
                ['Sistem Kullanıcıları & Roller', 'User · Admin / Muhasebe / Sakin', ($counts['users'] ?? 0).' kullanıcı', route('users.index'), 'user-plus'],
                ['Emlak Portföyü', 'Property · Kiralık / Satılık', ($counts['properties'] ?? 0).' portföy kaydı', route('properties.index'), 'home'],
            ],
        ],
        [
            'title' => 'Tahakkuk, Aidat & Sayaç Modelleri',
            'desc' => 'DuesDefinition · Debt · Meter · MeterReading · MeterBill',
            'icon' => 'file-text',
            'items' => [
                ['Aidat Tanımları & Otomasyon', 'DuesDefinition · Aylık tahakkuk', 'Otomatik aidat', $sRoute('site.dues.index'), 'filter'],
                ['Borçlandırma, Taksit & İcra', 'Debt · Açık alacak & İcra takibi', ($counts['debts'] ?? 0).' tahakkuk kaydı', $sRoute('site.debts.index'), 'circle-dollar-sign'],
                ['Gecikme Zammı Hesaplama', 'Otomatik vade farkı motoru', 'Vade kontrolü', $sRoute('site.late-fees.index'), 'flame'],
                ['Sayaç & Endeks Okuma', 'Meter · MeterReading', 'Sıcak su / doğalgaz', $sRoute('site.meters.index'), 'zap'],
                ['Sayaç Faturası Dağıtımı', 'MeterBill · Pay ölçer dağıtımı', 'Fatura paylaştırma', $sRoute('site.meter-bills.index'), 'receipt'],
            ],
        ],
        [
            'title' => 'Tahsilat, Kasa & Banka Modelleri',
            'desc' => 'Cashbox · CashTransaction · DebtPayment · BankStatementLine',
            'icon' => 'wallet',
            'items' => [
                ['Tahsilat & Makbuz Kesimi', 'DebtPayment · CashTransaction', ($collectionState['paid_count'] ?? 0).' ödenen kayıt', $sRoute('site.collections.index'), 'hand-coins'],
                ['Kasa & Banka Hesapları', 'Cashbox · Virman & Hareketler', tl($totals['balance'] ?? 0).' toplam', $sRoute('site.cashboxes.index'), 'wallet'],
                ['Banka Ekstresi Eşleştirme', 'BankStatementLine · CSV içe aktarım', ($counts['bank_lines'] ?? 0).' banka satırı', $sRoute('site.bank.index'), 'landmark'],
                ['Avans & Kredi Mahsup', 'Unit kredi bakiyesi mahsubu', 'Avans yönetimi', $sRoute('site.advances.index'), 'credit-card'],
                ['Muhasebe, Defter & Bütçe', 'LedgerEntry · Mizan · Bilanço · BudgetItem', ($counts['transactions'] ?? 0).' hareket', $sRoute('site.ledger'), 'database'],
            ],
        ],
        [
            'title' => 'Operasyon, Gider, Kurul & Denetim',
            'desc' => 'Expense · Vendor · Staff · Task · Ticket · Meeting · AuditLog',
            'icon' => 'package',
            'items' => [
                ['Gider, Tedarikçi & Bordro', 'Expense · Vendor · Staff · Payroll', ($counts['expenses'] ?? 0).' gider kaydı', $sRoute('site.expenses.index'), 'receipt'],
                ['Görev, Stok & Demirbaş', 'Task · InventoryItem · Asset', ($counts['inventory'] ?? 0).' stok kalemi', $sRoute('site.inventory.index'), 'package'],
                ['Rezervasyon & Güvenlik / Kargo', 'CommonArea · Reservation · SecurityEntry', ($counts['reservations'] ?? 0).' rezervasyon', $sRoute('site.reservations.index'), 'shield-check'],
                ['Kurul, Karar, Duyuru & Belge', 'Meeting · Decision · Announcement · Document', 'Kurul & İletişim', $sRoute('site.meetings.index'), 'megaphone'],
                ['Rapor Merkezi & Denetim İzleri', 'ReportService · AuditLog (PDF/CSV)', ($counts['audits'] ?? 0).' denetim izi', $sRoute('site.reports.index'), 'printer'],
            ],
        ],
    ];
@endphp
<x-layout heading="Genel Portföy ve Sistem Yönetim Panosu" subheading="Yönetiminizdeki tüm apartman ve sitelerin finansal özetini, veri modellerini ve operasyonel modüllerini tek ekrandan kontrol edin.">
    <x-slot:actions>
        <details class="dropdown relative">
            <summary class="select-btn">
                <x-icon name="calendar" :size="14" class="text-[#71717a]" />
                <span data-range-label>{{ $window['label'] }}</span>
                <x-icon name="chevron-down" :size="14" class="text-[#71717a]" />
            </summary>
            <div class="absolute right-0 top-full z-40 mt-1.5 w-44 rounded-2xl border border-[#242428] bg-[#141416] p-1.5 shadow-2xl">
                @foreach (\App\Services\DashboardService::RANGES as $key => $label)
                    <a href="{{ route('dashboard', ['range' => $key]) }}" class="drop-item {{ $range === $key ? 'active' : '' }}" @if ($range === $key) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </div>
        </details>
        <button type="button" data-toggle="apt-modal" class="btn btn-secondary">
            <x-icon name="building-2" :size="14" /><span>Konut Seç / Ekle</span>
        </button>
        <a href="{{ $newHref }}" class="btn-brand">
            <x-icon name="plus" :size="14" /><span>Yeni Tahakkuk</span>
        </a>
    </x-slot:actions>

    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <x-dash.stat :stagger="0" label="Toplam Tahakkuk" :value="$collectionState['accrued']" icon="file-text" :progress="$collectionState['rate']" :href="$newHref" />
        <x-dash.stat :stagger="1" label="Tahsil Edilen" :value="$collectionState['collected']" icon="check-circle-2" :note="($collectionState['paid_count'] ?? 0).' tahsilat'" :href="$sRoute('site.collections.index')" />
        <x-dash.stat :stagger="2" label="Bekleyen Alacak" :value="$totals['receivables']" icon="circle-dollar-sign" :note="'Geciken: '.tl($totals['overdue'] ?? 0)" :href="$newHref" />
        <x-dash.stat :stagger="3" label="Toplam Kasa & Banka" :value="$totals['balance']" icon="wallet" :note="($counts['transactions'] ?? 0).' kasa hareketi'" :href="$sRoute('site.cashboxes.index')" />
        <x-dash.stat :stagger="4" label="Ödenen Kayıtlar" :ratio="$paidRatio" icon="trending-up" :progress="$collectionState['rate']" :href="route('sites.index')" />
        <x-dash.stat :stagger="5" label="Portföy & Yapı" :ratio="$siteUnitRatio" icon="building-2" :note="($totals['members'] ?? 0).' sakin · '.($counts['users'] ?? 0).' kullanıcı'" :href="route('sites.index')" />
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
        <section style="--stagger: 4" class="spotlight-card reveal-up rounded-[24px] border border-[#1f1f23] bg-[#141416] p-6 xl:col-span-8">
            <div class="relative z-10 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-[15px] font-semibold text-white">Portföy Gelir ve Tahakkuk Özeti</h2>
                    <p class="mt-0.5 text-xs text-[#71717a]">Tüm sitelerde seçilen dönemde tahsil edilen ve tahakkuk eden tutarlar.</p>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <span class="inline-flex items-center gap-1.5 text-xs text-[#a1a1aa]"><i class="h-2 w-2 rounded-full bg-emerald-500"></i>Tahsilat</span>
                    <span class="inline-flex items-center gap-1.5 text-xs text-[#a1a1aa]"><i class="h-2 w-2 rounded-full bg-white"></i>Tahakkuk</span>
                    <div class="inline-flex items-center rounded-full border border-[#242428] bg-[#0c0c0e] p-0.5 text-[11px]" data-chart-switcher>
                        <button type="button" data-chart-mode="bar" class="rounded-full bg-[#1f1f23] px-3 py-1 font-medium text-white transition-all duration-200">30 Gün</button>
                        <button type="button" data-chart-mode="month" class="rounded-full px-3 py-1 font-medium text-[#71717a] transition-all duration-200 hover:text-white">12 Ay</button>
                    </div>
                </div>
            </div>

            <x-dash.bars :daily="$daily" :items="$monthly" label="Gelir özeti" />
        </section>

        <section style="--stagger: 5" class="spotlight-card reveal-up flex flex-col justify-between rounded-[24px] border border-[#1f1f23] bg-[#141416] p-6 xl:col-span-4">
            <div class="relative z-10">
                <div class="flex items-center justify-between border-b border-[#1f1f23] pb-3.5">
                    <div>
                        <h2 class="text-[15px] font-semibold text-white">Portföy Operasyonel Uyarılar</h2>
                        <p class="text-[11px] text-[#71717a]">Tüm sitelerde aksiyon bekleyen kayıtlar</p>
                    </div>
                    <span class="badge badge-amber">{{ collect($attention)->sum(fn ($a) => $a[1]) }} kayıt</span>
                </div>

                <div class="mt-4 space-y-2">
                    @foreach ($attention as [$label, $count, $icon, $tone])
                        <div class="soft-row py-2.5 text-xs">
                            <span class="flex items-center gap-2.5 text-[#e4e4e7]">
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg border border-[#26262c] bg-[#141416] text-[#a1a1aa]">
                                    <x-icon :name="$icon" :size="13" />
                                </span>
                                <span>{{ $label }}</span>
                            </span>
                            <span class="badge {{ $count ? ($tone === 'red' ? 'badge-red' : 'badge-amber') : 'badge-gray' }}">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="relative z-10 mt-4 border-t border-[#1f1f23] pt-4">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-[#71717a]">Açık Destek Talepleri</span>
                    <span class="font-semibold text-white">{{ $counts['tickets'] ?? 0 }} talep ({{ $counts['tickets_urgent'] ?? 0 }} acil)</span>
                </div>
            </div>
        </section>
    </div>

    <section style="--stagger: 6" class="spotlight-card reveal-up rounded-[24px] border border-[#1f1f23] bg-[#141416] p-6">
        <div class="relative z-10 flex flex-wrap items-center justify-between gap-4 border-b border-[#1f1f23] pb-4">
            <div>
                <h2 class="text-base font-bold text-white">Tüm Sistem Modülleri ve Veri Modelleri</h2>
                <p class="mt-0.5 text-xs text-[#71717a]">Sistemdeki 35+ model ve yönetim ekranına kategorilerine göre doğrudan geçiş yapın.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('sites.index') }}" class="btn btn-secondary btn-sm">Tüm Siteler ({{ $counts['sites'] ?? 0 }})</a>
                <a href="{{ route('users.index') }}" class="btn btn-secondary btn-sm">Kullanıcılar ({{ $counts['users'] ?? 0 }})</a>
                <a href="{{ route('properties.index') }}" class="btn btn-secondary btn-sm">Emlak Portföyü ({{ $counts['properties'] ?? 0 }})</a>
            </div>
        </div>

        <div class="relative z-10 mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($portfolioModules as $group)
                <div class="flex flex-col justify-between rounded-2xl border border-[#222228] bg-[#101013] p-4 transition-all duration-200 hover:border-[#32323c]">
                    <div>
                        <div class="flex items-start gap-3 border-b border-[#1c1c22] pb-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-[#2a2a34] bg-[#18181e] text-white">
                                <x-icon :name="$group['icon']" :size="16" />
                            </span>
                            <div class="min-w-0">
                                <h3 class="truncate text-[13px] font-semibold text-white">{{ $group['title'] }}</h3>
                                <p class="truncate text-[11px] text-[#71717a]">{{ $group['desc'] }}</p>
                            </div>
                        </div>

                        <div class="mt-3 space-y-1.5">
                            @foreach ($group['items'] as [$mTitle, $mModel, $mStat, $mHref, $mIcon])
                                <a href="{{ $mHref }}" class="group/item flex items-center justify-between gap-2 rounded-xl border border-transparent bg-[#151519] px-3 py-2 text-xs transition-all duration-150 hover:border-[#2c2c36] hover:bg-[#1b1b22]">
                                    <div class="flex min-w-0 items-center gap-2.5">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#1e1e26] text-[#a1a1aa] transition-colors group-hover/item:bg-white group-hover/item:text-[#09090b]">
                                            <x-icon :name="$mIcon" :size="13" />
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-[#e4e4e7] group-hover/item:text-white">{{ $mTitle }}</p>
                                            <p class="truncate text-[10px] text-[#6b6b76]">{{ $mModel }}</p>
                                        </div>
                                    </div>
                                    <span class="shrink-0 rounded-full border border-[#26262f] bg-[#121216] px-2 py-0.5 text-[10px] font-medium text-[#a1a1aa] group-hover/item:border-[#3a3a46] group-hover/item:text-white">{{ $mStat }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section style="--stagger: 7" class="spotlight-card reveal-up rounded-[24px] border border-[#1f1f23] bg-[#141416] p-6" data-tabs>
        <div class="relative z-10 flex flex-wrap items-center justify-between gap-3 border-b border-[#1f1f23] pb-4">
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" data-filter="all" class="range-pill active">Yönetilen Siteler ({{ $sites->count() }})</button>
                <button type="button" data-filter="activity" class="range-pill">Son İşlemler ({{ count($activity) }})</button>
                <button type="button" data-filter="users" class="range-pill">Kullanıcılar ({{ $users->count() }})</button>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" data-toggle="apt-modal" class="btn btn-secondary btn-sm">Konut Ekle</button>
                <a href="{{ $newHref }}" class="btn btn-primary btn-sm">+ Tahakkuk Oluştur</a>
            </div>
        </div>

        @if ($sites->isEmpty())
            <div class="relative z-10 flex flex-col items-center justify-center py-14 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl border border-[#26262c] bg-[#1a1a1f] text-[#71717a]">
                    <x-icon name="building-2" :size="20" />
                </span>
                <h3 class="mt-4 text-sm font-semibold text-white">Henüz kayıtlı konut yok</h3>
                <p class="mt-1 max-w-sm text-xs text-[#71717a]">Yönetime başlamak için ilk site veya apartman kaydınızı oluşturun.</p>
            </div>
        @else
            <div class="relative z-10 mt-4 space-y-2">
                @foreach ($sites as $site)
                    @php $rate = $site->accrued > 0 ? round($site->collected / $site->accrued * 100) : 0; @endphp
                    <div data-kind="all" class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-[#1f1f23] bg-[#18181c] px-4 py-3.5 text-xs transition-colors hover:border-[#2c2c34]">
                        <a href="{{ route('site.dashboard', $site) }}" class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-[#26262c] bg-[#141416] text-white">
                                <x-icon name="building-2" :size="17" />
                            </span>
                            <div>
                                <p class="text-[13px] font-semibold text-white">{{ $site->name }}</p>
                                <p class="text-[11px] text-[#71717a]">{{ $site->code }} · {{ $site->units_count }} daire · {{ $site->members_count }} sakin</p>
                            </div>
                        </a>
                        <div class="flex flex-wrap items-center gap-4 sm:gap-6">
                            <div class="text-right">
                                <p class="text-[11px] text-[#71717a]">Kasa & Banka</p>
                                <p class="font-semibold text-emerald-400 tabular-nums">{{ tl($site->balance) }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-[11px] text-[#71717a]">Bekleyen Alacak</p>
                                <p class="font-semibold text-amber-400 tabular-nums">{{ tl($site->receivables) }}</p>
                            </div>
                            <span class="badge badge-green">%{{ $rate }} tahsilat</span>
                            <div class="flex items-center gap-1.5">
                                <a href="{{ route('site.dashboard', $site) }}" class="btn btn-secondary btn-sm">Panoya Git</a>
                                <a href="{{ route('site.units.index', $site) }}" class="btn btn-secondary btn-sm">Daireler</a>
                                <a href="{{ route('site.finance', $site) }}" class="btn btn-secondary btn-sm">Finans</a>
                            </div>
                        </div>
                    </div>
                @endforeach

                @foreach ($activity as $a)
                    <div data-kind="activity" hidden class="flex items-center justify-between gap-3 rounded-2xl border border-[#1f1f23] bg-[#18181c] px-4 py-3 text-xs">
                        <div>
                            <p class="font-medium text-white">{{ $a['label'] }}</p>
                            <p class="text-[11px] text-[#71717a]">{{ $a['site'] }} · {{ $a['user'] ?? 'Sistem' }} · {{ $a['at']->diffForHumans() }}</p>
                        </div>
                        @if ($a['detail'])
                            <span class="font-semibold text-white tabular-nums">{{ tl($a['detail']) }}</span>
                        @endif
                    </div>
                @endforeach

                @foreach ($users as $u)
                    <a href="{{ route('users.show', $u) }}" data-kind="users" hidden class="flex items-center justify-between gap-3 rounded-2xl border border-[#1f1f23] bg-[#18181c] px-4 py-3 text-xs transition-colors hover:border-[#2c2c34]">
                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full border border-[#26262c] bg-[#141416] font-semibold text-white">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                            <div>
                                <p class="font-medium text-white">{{ $u->name }}</p>
                                <p class="text-[11px] text-[#71717a]">{{ $u->email }}</p>
                            </div>
                        </div>
                        <span class="badge badge-gray">{{ $u->is_super_admin ? 'Süper admin' : ($u->role === 'admin' ? 'Yönetici' : 'Sakin') }}</span>
                    </a>
                @endforeach
            </div>

            <div data-empty hidden class="relative z-10 flex flex-col items-center justify-center py-14 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl border border-[#26262c] bg-[#1a1a1f] text-[#71717a]">
                    <x-icon name="file-text" :size="20" />
                </span>
                <h3 class="mt-4 text-sm font-semibold text-white">Kayıt bulunamadı</h3>
                <p class="mt-1 max-w-sm text-xs text-[#71717a]">Bu sekmede henüz gösterilecek kayıt bulunmuyor.</p>
            </div>
        @endif
    </section>
</x-layout>

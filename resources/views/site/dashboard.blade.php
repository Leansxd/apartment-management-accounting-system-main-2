@php
    $s = $summary;
    $mc = $modelCounts ?? [];
    $unitRatio = ($occupancy['occupied'] ?? 0).' / '.($occupancy['units'] ?? 0);
    $unitPct = ($occupancy['units'] ?? 0) > 0 ? round(($occupancy['occupied'] / $occupancy['units']) * 100) : 0;
    $openItems = collect($attention)->filter(fn ($r) => $r[1] > 0)->count();
    $maxFunnel = max(1, (int) ($funnel[0][1] ?? 1));
    $maxCat = max(1, (float) collect($expenseByCategory)->max('value'));

    $moduleGroups = [
        [
            'title' => 'Yapı, Blok & Sakin Modelleri',
            'desc' => 'Blok, daire, arsa payı, gruplar ve sakin kayıtları',
            'icon' => 'building-2',
            'items' => [
                ['Blok, Daire & Gruplar', 'Block · Unit · UnitGroup', ($mc['blocks'] ?? 0).' blok · '.($mc['units'] ?? 0).' daire', route('site.units.index', $apartment), 'building-2'],
                ['Sakin & Üye Yönetimi', 'Member · Davet & Portal', ($mc['members'] ?? 0).' kayıtlı sakin', route('site.members.index', $apartment), 'users'],
                ['Taşınma & Üye Geçmişi', 'Arşivlenen sakin kayıtları', 'Geçmiş kayıtlar', route('site.members.history', $apartment), 'clock'],
                ['Emlak & Portföy', 'Property · Kiralık / Satılık', 'Portföy yönetimi', route('properties.index'), 'home'],
            ],
        ],
        [
            'title' => 'Tahakkuk, Aidat & Sayaç Modelleri',
            'desc' => 'Periyodik aidat, borçlandırma, gecikme ve sayaç',
            'icon' => 'file-text',
            'items' => [
                ['Aidat & Otomasyon', 'DuesDefinition · Periyodik', ($mc['dues'] ?? 0).' aidat tanımı', route('site.dues.index', $apartment), 'filter'],
                ['Borçlandırma & Taksit / İcra', 'Debt · Açık & İcra takibi', ($mc['open_debts'] ?? 0).' açık / '.($mc['debts'] ?? 0).' toplam', route('site.debts.index', $apartment), 'circle-dollar-sign'],
                ['Gecikme Zammı Motoru', 'Otomatik oran & vade farkı', $apartment->late_fee_enabled ? '%'.$apartment->late_fee_rate.' aktif' : 'Pasif', route('site.late-fees.index', $apartment), 'flame'],
                ['Sayaç & Endeks Okuma', 'Meter · MeterReading', ($mc['meters'] ?? 0).' kayıtlı sayaç', route('site.meters.index', $apartment), 'zap'],
                ['Sayaç Faturası Dağıtımı', 'MeterBill · Ortak tüketim payı', ($mc['meter_bills'] ?? 0).' fatura dağıtımı', route('site.meter-bills.index', $apartment), 'receipt'],
            ],
        ],
        [
            'title' => 'Tahsilat, Kasa & Banka Modelleri',
            'desc' => 'Makbuz, kasa/banka hesapları, virman ve avans',
            'icon' => 'wallet',
            'items' => [
                ['Tahsilat & Makbuz İşlemleri', 'DebtPayment · CashTransaction', ($collectionState['paid_count'] ?? 0).' tahsilat', route('site.collections.index', $apartment), 'hand-coins'],
                ['Kasa & Banka Hesapları', 'Cashbox · Virman & Hareket', ($mc['cashboxes'] ?? 0).' aktif hesap', route('site.cashboxes.index', $apartment), 'wallet'],
                ['Banka Ekstresi Eşleştirme', 'BankStatementLine · CSV/OFX', ($mc['pending_bank'] ?? 0).' bekleyen / '.($mc['bank_lines'] ?? 0).' satır', route('site.bank.index', $apartment), 'landmark'],
                ['Avans & Kredi Mahsup', 'Unit alacak bakiyesi yönetimi', tl($occupancy['advances'] ?? 0).' avans', route('site.advances.index', $apartment), 'credit-card'],
                ['Finansal Özet Panosu', 'Nakit akışı & hesap özeti', tl($s['balance']).' bakiye', route('site.finance', $apartment), 'trending-up'],
            ],
        ],
        [
            'title' => 'Muhasebe, Defter & Bütçe Modelleri',
            'desc' => 'Yevmiye, mizan, bilanço, yaşlandırma ve bütçe',
            'icon' => 'database',
            'items' => [
                ['Gelir - Gider & Kategoriler', 'TransactionCategory · Analiz', ($mc['categories'] ?? 0).' kategori', route('site.income-expense', $apartment), 'trending-up'],
                ['Daire Hesap Ekstreleri', 'Cari borç/alacak dökümü', ($mc['units'] ?? 0).' daire ekstresi', route('site.statements', $apartment), 'file-text'],
                ['Yevmiye & Kasa Defteri', 'LedgerEntry · Resmi kayıtlar', ($mc['ledger_entries'] ?? 0).' yevmiye maddesi', route('site.ledger', $apartment), 'clipboard-list'],
                ['Mizan & Mali Durum (Bilanço)', 'Trial Balance · Balance Sheet', 'Muhasebe tabloları', route('site.accounting.trial-balance', $apartment), 'layout-list'],
                ['Alacak Yaşlandırma & Bütçe', 'Aging · BudgetItem planlama', ($mc['budget_items'] ?? 0).' bütçe kalemi', route('site.budget.index', $apartment), 'piggy-bank'],
            ],
        ],
        [
            'title' => 'Gider, Tedarikçi & İK Modelleri',
            'desc' => 'Fatura onayı, tedarikçi cari, personel ve bordro',
            'icon' => 'receipt',
            'items' => [
                ['Gider & Fatura Yönetimi', 'Expense · Onay & Ödeme', ($mc['pending_expenses'] ?? 0).' bekleyen / '.($mc['expenses'] ?? 0).' gider', route('site.expenses.index', $apartment), 'trending-down'],
                ['Tedarikçi & Firma Cari', 'Vendor · Hizmet sağlayıcılar', ($mc['vendors'] ?? 0).' kayıtlı firma', route('site.vendors.index', $apartment), 'briefcase'],
                ['Personel & Görevliler', 'Staff · Maaş & İletişim', ($mc['staff'] ?? 0).' personel', route('site.staff.index', $apartment), 'users-round'],
                ['Personel Bordro Tahakkuku', 'PayrollService · Aylık tahakkuk', 'Maaş & SGK bordrosu', route('site.payroll.index', $apartment), 'circle-dollar-sign'],
            ],
        ],
        [
            'title' => 'Saha, Envanter & Güvenlik Modelleri',
            'desc' => 'Görevler, stok, demirbaş, ortak alan ve güvenlik',
            'icon' => 'package',
            'items' => [
                ['Görev & Bakım Takibi', 'Task · Saha iş emirleri', ($mc['open_tasks'] ?? 0).' açık / '.($mc['tasks'] ?? 0).' görev', route('site.tasks.index', $apartment), 'check-circle-2'],
                ['Stok & Sarf Envanteri', 'InventoryItem · Kritik stok', ($mc['inventory'] ?? 0).' stok kalemi', route('site.inventory.index', $apartment), 'package'],
                ['Demirbaş & Zimmet Kaydı', 'Asset · Bakım & Amortisman', ($mc['assets'] ?? 0).' demirbaş', route('site.assets.index', $apartment), 'wrench'],
                ['Ortak Alan Rezervasyonu', 'CommonArea · Reservation', ($mc['common_areas'] ?? 0).' tesis · '.($mc['reservations'] ?? 0).' rez.', route('site.reservations.index', $apartment), 'calendar-check'],
                ['Güvenlik, Ziyaretçi & Kargo', 'SecurityEntry · Araç & Kargo', ($mc['security'] ?? 0).' güvenlik kaydı', route('site.security.index', $apartment), 'shield-check'],
                ['Arıza & Destek Talepleri', 'Ticket · Sakin bildirimleri', ($mc['open_tickets'] ?? 0).' açık / '.($mc['tickets'] ?? 0).' talep', route('site.tickets.index', $apartment), 'message-square'],
            ],
        ],
        [
            'title' => 'Kurul, İletişim & Belge Modelleri',
            'desc' => 'Toplantı, karar defteri, duyuru, anket ve arşiv',
            'icon' => 'megaphone',
            'items' => [
                ['Genel Kurul & Toplantılar', 'Meeting · MeetingAttendance', ($mc['meetings'] ?? 0).' toplantı', route('site.meetings.index', $apartment), 'users'],
                ['Karar Defteri', 'Decision · YK & Kurul kararları', ($mc['decisions'] ?? 0).' karar kaydı', route('site.decisions.index', $apartment), 'gavel'],
                ['Duyuru Panosu & Anketler', 'Announcement · Poll · PollVote', ($mc['announcements'] ?? 0).' duyuru · '.($mc['polls'] ?? 0).' anket', route('site.announcements.index', $apartment), 'megaphone'],
                ['Bildirim & Borç Hatırlatıcı', 'SiteNotification · Otomatik SMS/Mail', ($mc['notifications'] ?? 0).' bildirim', route('site.reminders.index', $apartment), 'bell'],
                ['Dijital Belge & Sözleşmeler', 'Document · Süre takipli arşiv', ($mc['documents'] ?? 0).' belge', route('site.documents.index', $apartment), 'file-text'],
            ],
        ],
        [
            'title' => 'Raporlama, Denetim & Sistem',
            'desc' => 'PDF/CSV raporlar, işlem izleri ve yetki ayarları',
            'icon' => 'settings',
            'items' => [
                ['Rapor Merkezi (PDF / Excel)', 'ReportService · 8 resmi rapor', 'Yazdırılabilir & CSV', route('site.reports.index', $apartment), 'printer'],
                ['Kasa Defteri & Yaşlandırma', 'AccountingReportService', 'Detaylı mali analiz', route('site.accounting.aging', $apartment), 'clock'],
                ['İşlem & Denetim Kayıtları', 'AuditLog · Güvenlik izleri', ($mc['audit_logs'] ?? 0).' işlem kaydı', route('site.audit', $apartment), 'shield-alert'],
                ['Site Ayarları & Yöneticiler', 'Apartment · Yetki & Parametre', 'Yönetici & gecikme ayarı', route('site.settings', $apartment), 'settings'],
            ],
        ],
    ];
@endphp
<x-layout :heading="$apartment->name.' — Yönetim ve Muhasebe Panosu'" subheading="Tüm finansal tabloları, daire/sakin modellerini, tahakkuk-tahsilat akışını ve saha operasyon modüllerini tek ekrandan yönetin." :title="$apartment->name">
    <x-slot:actions>
        <details class="dropdown relative">
            <summary class="select-btn">
                <x-icon name="calendar" :size="14" class="text-[#71717a]" />
                <span data-range-label>{{ $window['label'] }}</span>
                <x-icon name="chevron-down" :size="14" class="text-[#71717a]" />
            </summary>
            <div class="absolute right-0 top-full z-40 mt-1.5 w-44 rounded-2xl border border-[#242428] bg-[#141416] p-1.5 shadow-2xl">
                @foreach (\App\Services\DashboardService::RANGES as $key => $label)
                    <a href="{{ route('site.dashboard', [$apartment, 'range' => $key]) }}" class="drop-item {{ $range === $key ? 'active' : '' }}" @if ($range === $key) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </div>
        </details>
        <a href="{{ route('site.collections.index', $apartment) }}" class="btn btn-secondary">
            <x-icon name="hand-coins" :size="14" /><span>Tahsilat Al</span>
        </a>
        <a href="{{ route('site.expenses.index', $apartment) }}" class="btn btn-secondary">
            <x-icon name="receipt" :size="14" /><span>Gider Gir</span>
        </a>
        <a href="{{ route('site.debts.index', $apartment) }}" class="btn-brand">
            <x-icon name="plus" :size="14" /><span>Yeni Tahakkuk</span>
        </a>
    </x-slot:actions>

    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <x-dash.stat :stagger="0" label="Toplam Tahakkuk" :value="$collectionState['accrued']" icon="file-text" :progress="$collectionState['rate']" :href="route('site.debts.index', $apartment)" />
        <x-dash.stat :stagger="1" label="Tahsil Edilen" :value="$collectionState['collected']" icon="check-circle-2" :note="($collectionState['paid_count'] ?? 0).' ödenen · '.($collectionState['total_count'] ?? 0).' kayıt'" :href="route('site.collections.index', $apartment)" />
        <x-dash.stat :stagger="2" label="Bekleyen Alacak" :value="$s['receivables']" icon="circle-dollar-sign" :note="'Geciken: '.tl($collectionState['overdue'] ?? 0)" :href="route('site.debts.index', [$apartment, 'status' => 'overdue'])" />
        <x-dash.stat :stagger="3" label="Kasa & Banka Mevcudu" :value="$s['balance']" icon="wallet" :note="$cashboxes->count().' hesap · Avans: '.tl($occupancy['advances'] ?? 0)" :href="route('site.cashboxes.index', $apartment)" />
        <x-dash.stat :stagger="4" label="Dönem Giderleri" :value="$s['expense']" icon="trending-down" :note="($mc['pending_expenses'] ?? 0).' onay bekleyen · '.($mc['vendors'] ?? 0).' cari'" :href="route('site.expenses.index', $apartment)" />
        <x-dash.stat :stagger="5" label="Daire & Doluluk" :ratio="$unitRatio" icon="building-2" :progress="$unitPct" :href="route('site.units.index', $apartment)" />
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
        <section style="--stagger: 4" class="spotlight-card reveal-up flex flex-col justify-between rounded-[24px] border border-[#1f1f23] bg-[#141416] p-6 xl:col-span-8">
            <div>
                <div class="relative z-10 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-[15px] font-semibold text-white">Gelir ve Tahakkuk Özeti</h2>
                        <p class="mt-0.5 text-xs text-[#71717a]">Seçilen dönemde günlük tahsilat/tahakkuk akışı ve 12 aylık karşılaştırma.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        <span class="inline-flex items-center gap-1.5 text-xs text-[#a1a1aa]"><i class="h-2 w-2 rounded-full bg-emerald-500"></i>Tahsilat</span>
                        <span class="inline-flex items-center gap-1.5 text-xs text-[#a1a1aa]"><i class="h-2 w-2 rounded-full bg-white"></i>Tahakkuk / Gider</span>
                        <div class="inline-flex items-center rounded-full border border-[#242428] bg-[#0c0c0e] p-0.5 text-[11px]" data-chart-switcher>
                            <button type="button" data-chart-mode="bar" class="rounded-full bg-[#1f1f23] px-3 py-1 font-medium text-white transition-all duration-200">30 Gün</button>
                            <button type="button" data-chart-mode="month" class="rounded-full px-3 py-1 font-medium text-[#71717a] transition-all duration-200 hover:text-white">12 Ay</button>
                        </div>
                    </div>
                </div>

                <x-dash.bars :daily="$daily" :items="$monthly" label="Gelir özeti" />
            </div>

            <div class="relative z-10 mt-5 grid grid-cols-2 gap-3 border-t border-[#1f1f23] pt-4 sm:grid-cols-4">
                <div class="rounded-2xl border border-[#1f1f23] bg-[#18181c] p-3">
                    <p class="text-[11px] text-[#71717a]">Dönem Tahsilatı</p>
                    <p class="mt-1 text-sm font-bold text-emerald-400 tabular-nums">{{ tl($s['income']) }}</p>
                </div>
                <div class="rounded-2xl border border-[#1f1f23] bg-[#18181c] p-3">
                    <p class="text-[11px] text-[#71717a]">Dönem Gideri</p>
                    <p class="mt-1 text-sm font-bold text-rose-400 tabular-nums">{{ tl($s['expense']) }}</p>
                </div>
                <div class="rounded-2xl border border-[#1f1f23] bg-[#18181c] p-3">
                    <p class="text-[11px] text-[#71717a]">Net Nakit Akışı</p>
                    <p class="mt-1 text-sm font-bold text-white tabular-nums">{{ tl(($s['income'] ?? 0) - ($s['expense'] ?? 0)) }}</p>
                </div>
                <div class="rounded-2xl border border-[#1f1f23] bg-[#18181c] p-3">
                    <p class="text-[11px] text-[#71717a]">7 Gün İçinde Vadesi Gelen</p>
                    <p class="mt-1 text-sm font-bold text-amber-400 tabular-nums">{{ tl($dueSoon) }}</p>
                </div>
            </div>
        </section>

        <section style="--stagger: 5" class="spotlight-card reveal-up flex flex-col justify-between gap-5 rounded-[24px] border border-[#1f1f23] bg-[#141416] p-6 xl:col-span-4">
            <div class="relative z-10">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-[15px] font-semibold text-white">Tahsilat Hunisi & Kasa</h2>
                        <p class="mt-0.5 text-xs text-[#71717a]">Borç yaşam döngüsü ve aktif kasa/banka hesapları.</p>
                    </div>
                    <a href="{{ route('site.debts.index', $apartment) }}" class="text-xs font-medium text-[#a1a1aa] hover:text-white">Tümü →</a>
                </div>

                <div class="mt-4 space-y-2.5">
                    @foreach ($funnel as $idx => [$fLabel, $fCount])
                        @php $fPct = round($fCount / $maxFunnel * 100); @endphp
                        <div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-[#a1a1aa]">{{ $fLabel }}</span>
                                <span class="font-semibold text-white tabular-nums">{{ $fCount }} kayıt</span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-[#1f1f23]">
                                <div class="grow-w h-full rounded-full {{ $idx === 3 ? 'bg-emerald-500' : 'bg-white/75' }}" style="width: {{ max(4, $fPct) }}%; --d: {{ $idx }}"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="relative z-10 border-t border-[#1f1f23] pt-4">
                <div class="mb-2.5 flex items-center justify-between">
                    <span class="text-xs font-semibold text-white">Kasa & Banka Hesapları ({{ $cashboxes->count() }})</span>
                    <a href="{{ route('site.cashboxes.index', $apartment) }}" class="text-[11px] text-[#a1a1aa] hover:text-white">Virman / Ekle →</a>
                </div>
                <div class="space-y-2">
                    @forelse ($cashboxes->take(3) as $c)
                        <a href="{{ route('site.cashboxes.index', $apartment) }}" class="soft-row py-2.5 text-xs">
                            <span class="flex items-center gap-2.5">
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg border border-[#2a2a32] bg-[#141416] text-[#d4d4d8]">
                                    <x-icon :name="$c->type === 'bank' ? 'landmark' : 'wallet'" :size="13" />
                                </span>
                                <span>
                                    <span class="block font-medium text-white">{{ $c->name }}</span>
                                    <span class="block text-[10px] text-[#71717a]">{{ \App\Models\Cashbox::TYPES[$c->type] ?? $c->type }}</span>
                                </span>
                            </span>
                            <span class="font-semibold text-emerald-400 tabular-nums">{{ tl($c->balance) }}</span>
                        </a>
                    @empty
                        <p class="py-2 text-xs text-[#71717a]">Henüz kasa veya banka hesabı tanımlanmadı.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </div>

    <section style="--stagger: 6" class="spotlight-card reveal-up rounded-[24px] border border-[#1f1f23] bg-[#141416] p-6">
        <div class="relative z-10 flex flex-wrap items-center justify-between gap-4 border-b border-[#1f1f23] pb-4">
            <div>
                <h2 class="text-base font-bold text-white">Tüm Sistem Modülleri ve Veri Modelleri</h2>
                <p class="mt-0.5 text-xs text-[#71717a]">Apartman ve site yönetiminin tüm muhasebe, tahakkuk, saha operasyonu, kurul ve denetim modüllerine doğrudan erişin.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="badge badge-green">{{ $mc['units'] ?? 0 }} Daire</span>
                <span class="badge badge-gray">{{ $mc['members'] ?? 0 }} Sakin</span>
                <span class="badge badge-amber">{{ $mc['open_debts'] ?? 0 }} Açık Tahakkuk</span>
                <span class="badge badge-blue">{{ $mc['transactions'] ?? 0 }} Kasa Hareketi</span>
            </div>
        </div>

        <div class="relative z-10 mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($moduleGroups as $gIdx => $group)
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

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
        <section class="spotlight-card reveal-up rounded-[24px] border border-[#1f1f23] bg-[#141416] p-6 xl:col-span-5" data-tabs>
            <div class="relative z-10 flex flex-wrap items-center justify-between gap-2 border-b border-[#1f1f23] pb-3.5">
                <div class="flex flex-wrap items-center gap-1.5">
                    <button type="button" data-filter="all" class="range-pill active">Son Hareketler ({{ $transactions->count() }})</button>
                    <button type="button" data-filter="debtors" class="range-pill">Borçlu Daireler ({{ $topDebtors->count() }})</button>
                </div>
                <a href="{{ route('site.collections.index', $apartment) }}" class="text-xs font-medium text-[#a1a1aa] hover:text-white">Tahsilat Ekranı →</a>
            </div>

            <div class="relative z-10 mt-4 space-y-2">
                @forelse ($transactions as $tx)
                    @php $isInc = $tx->type === 'income'; @endphp
                    <div data-kind="all" class="soft-row text-xs">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-[#26262c] bg-[#141416] {{ $isInc ? 'text-emerald-400' : 'text-rose-400' }}">
                                <x-icon :name="$isInc ? 'trending-up' : 'trending-down'" :size="14" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate font-medium text-white">{{ $tx->description ?: ($isInc ? 'Aidat tahsilatı' : 'Genel gider') }}</p>
                                <p class="truncate text-[11px] text-[#71717a]">{{ $tx->cashbox?->name ?? 'Merkez Kasa' }} @if ($tx->unit) · Daire {{ $tx->unit->label() }} @endif · {{ $tx->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2.5">
                            <span class="font-semibold tabular-nums {{ $isInc ? 'text-emerald-400' : 'text-white' }}">{{ $isInc ? '+' : '−' }}{{ tl($tx->amount) }}</span>
                            <a href="{{ route('site.transactions.receipt', [$apartment, $tx]) }}" class="rounded-full border border-[#26262c] bg-[#141416] px-2.5 py-1 text-[11px] text-[#a1a1aa] hover:text-white">Makbuz</a>
                        </div>
                    </div>
                @empty
                    <div data-kind="all" class="rounded-2xl border border-dashed border-[#26262e] p-6 text-center">
                        <p class="text-xs font-medium text-white">Henüz kasa hareketi bulunmuyor</p>
                        <p class="mt-1 text-[11px] text-[#71717a]">Tahsilat aldığınızda veya gider girdiğinizde burada listelenir.</p>
                        <div class="mt-3 flex justify-center gap-2">
                            <a href="{{ route('site.debts.index', $apartment) }}" class="btn btn-primary btn-sm">+ Tahakkuk Ekle</a>
                            <a href="{{ route('site.collections.index', $apartment) }}" class="btn btn-secondary btn-sm">Tahsilat Al</a>
                        </div>
                    </div>
                @endforelse

                @foreach ($topDebtors as $d)
                    <a href="{{ $d['href'] }}" data-kind="debtors" hidden class="soft-row text-xs">
                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl border border-[#26262c] bg-[#141416] font-semibold text-white">{{ $d['label'] }}</span>
                            <div>
                                <p class="font-medium text-white">{{ $d['sub'] }}</p>
                                <p class="text-[11px] text-[#71717a]">Daire {{ $d['label'] }} · {{ $d['count'] }} açık borç kalemi</p>
                            </div>
                        </div>
                        <span class="font-semibold text-amber-400 tabular-nums">{{ tl($d['value']) }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="spotlight-card reveal-up rounded-[24px] border border-[#1f1f23] bg-[#141416] p-6 xl:col-span-4">
            <div class="relative z-10 flex items-center justify-between border-b border-[#1f1f23] pb-3.5">
                <div>
                    <h2 class="text-[15px] font-semibold text-white">Operasyonel Uyarılar & Kontroller</h2>
                    <p class="text-[11px] text-[#71717a]">Onay, eşleştirme ve takip gerektiren kayıtlar</p>
                </div>
                <span class="badge {{ $openItems > 0 ? 'badge-amber' : 'badge-green' }}">{{ $openItems }} aktif uyarı</span>
            </div>

            <div class="relative z-10 mt-4 space-y-2">
                @foreach ($attention as [$label, $count, $route, $icon, $tone])
                    <a href="{{ route($route, $apartment) }}" class="soft-row py-2.5 text-xs">
                        <span class="flex items-center gap-2.5 text-[#e4e4e7]">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg border border-[#26262c] bg-[#141416] text-[#a1a1aa]">
                                <x-icon :name="$icon" :size="13" />
                            </span>
                            <span>{{ $label }}</span>
                        </span>
                        <span class="badge {{ $count ? ($tone === 'red' ? 'badge-red' : 'badge-amber') : 'badge-gray' }}">{{ $count }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="spotlight-card reveal-up flex flex-col justify-between rounded-[24px] border border-[#1f1f23] bg-[#141416] p-6 xl:col-span-3">
            <div class="relative z-10">
                <div class="flex items-center justify-between border-b border-[#1f1f23] pb-3.5">
                    <div>
                        <h2 class="text-[15px] font-semibold text-white">Gider Dağılımı & Denetim</h2>
                        <p class="text-[11px] text-[#71717a]">Kategori bazlı giderler ve son işlemler</p>
                    </div>
                    <a href="{{ route('site.income-expense', $apartment) }}" class="text-[11px] text-[#a1a1aa] hover:text-white">Detay →</a>
                </div>

                <div class="mt-4 space-y-2.5">
                    @forelse ($expenseByCategory as $idx => $cat)
                        @php $cPct = round(($cat['value'] / $maxCat) * 100); @endphp
                        <div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="truncate text-[#a1a1aa]">{{ $cat['label'] }}</span>
                                <span class="font-semibold text-white tabular-nums">{{ tl($cat['value']) }}</span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-[#1f1f23]">
                                <div class="grow-w h-full rounded-full bg-emerald-500" style="width: {{ max(6, $cPct) }}%; --d: {{ $idx }}"></div>
                            </div>
                        </div>
                    @empty
                        <p class="py-2 text-xs text-[#71717a]">Seçilen dönemde kategori bazlı gider kaydı bulunmuyor.</p>
                    @endforelse
                </div>
            </div>

            <div class="relative z-10 mt-5 border-t border-[#1f1f23] pt-4">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-xs font-semibold text-white">Son Denetim İzleri (AuditLog)</span>
                    <a href="{{ route('site.audit', $apartment) }}" class="text-[11px] text-[#a1a1aa] hover:text-white">Tümü →</a>
                </div>
                <div class="space-y-1.5">
                    @forelse (collect($activity)->take(3) as $act)
                        <div class="rounded-xl border border-[#1f1f23] bg-[#18181c] px-3 py-2 text-[11px]">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium text-white">{{ $act['label'] }}</span>
                                @if ($act['detail'])<span class="font-semibold text-emerald-400 tabular-nums">{{ tl($act['detail']) }}</span>@endif
                            </div>
                            <p class="text-[10px] text-[#71717a]">{{ $act['user'] ?? 'Sistem' }} · {{ $act['at']->diffForHumans() }}</p>
                        </div>
                    @empty
                        <p class="text-[11px] text-[#71717a]">Henüz işlem kaydı yok.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
</x-layout>

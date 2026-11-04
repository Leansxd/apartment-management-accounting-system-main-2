<x-layout heading="Rapor Merkezi" subheading="Her rapor yazdırılabilir (PDF olarak kaydedilebilir) ve Excel'e aktarılabilir">
    @foreach ($types as $group => $items)
        <section>
            <h2 class="mb-3 text-sm font-semibold text-slate-500 uppercase tracking-wide">{{ $group }}</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $key => $type)
                    <div class="card card-body flex flex-col gap-3">
                        <h3 class="font-semibold text-slate-900">{{ $type[0] }}</h3>
                        <div class="mt-auto flex gap-2">
                            <a class="btn btn-primary btn-sm" href="{{ route('site.reports.show', [$apartment, $key]) }}">Görüntüle</a>
                            <a class="btn btn-secondary btn-sm" href="{{ route('site.reports.show', [$apartment, $key, 'format' => 'csv']) }}">Excel (CSV)</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach

    <section>
        <h2 class="mb-3 text-sm font-semibold text-slate-500 uppercase tracking-wide">Mali Raporlar</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['Mali Durum Tablosu', 'Kasa, alacaklar, demirbaşlar ve avans/ödenecek yükümlülükler', 'site.accounting.balance-sheet'],
                ['Mizan', 'Hesap bazında borç / alacak toplamları ve bakiyeler', 'site.accounting.trial-balance'],
                ['Kasa Defteri', 'Kasa/banka hareketleri yürüyen bakiyeyle', 'site.accounting.cash-book'],
                ['Alacak Yaşlandırma', 'Açık borçların vade gününe göre dağılımı', 'site.accounting.aging'],
                ['Bütçe & Gerçekleşen', 'Yıllık plan ile tahsilat / gider karşılaştırması', 'site.budget.index'],
                ['Gelir Gider Tablosu', 'Kategori bazında gelir ve gider', 'site.income-expense'],
                ['Daire Hesap Ekstresi', 'Daire bazında borç, ödeme ve bakiye', 'site.statements'],
                ['Yevmiye Defteri', 'Çift taraflı muhasebe kayıtları', 'site.ledger'],
            ] as [$title, $desc, $route])
                <a href="{{ route($route, $apartment) }}" class="card card-body flex flex-col gap-1 transition-colors hover:border-brand-500">
                    <h3 class="font-semibold text-slate-900">{{ $title }}</h3>
                    <p class="text-xs text-slate-500">{{ $desc }}</p>
                </a>
            @endforeach
        </div>
    </section>
</x-layout>

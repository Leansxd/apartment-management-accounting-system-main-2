<x-layout heading="Mali Durum Tablosu" :subheading="$apartment->name.' · '.today()->format('d.m.Y').' itibarıyla'">
    <x-slot:actions><button type="button" class="btn btn-primary" onclick="window.print()">Yazdır</button></x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card">
            <div class="card-head"><h2 class="card-title">Varlıklar</h2></div>
            <table class="table">
                <tbody>
                @foreach ($sheet['cash'] as $c)
                    <tr><td class="pl-8 text-slate-500">{{ $c->name }}</td><td class="num">{{ tl($c->balance) }}</td></tr>
                @endforeach
                <tr class="font-medium"><td>Kasa & Banka toplamı</td><td class="num">{{ tl($sheet['cash_total']) }}</td></tr>
                <tr class="font-medium"><td>Daire alacakları <span class="text-xs font-normal text-rose-600">(gecikmiş {{ tl($sheet['overdue']) }})</span></td><td class="num">{{ tl($sheet['receivables']) }}</td></tr>
                <tr class="font-medium"><td>Demirbaşlar (net defter değeri)</td><td class="num">{{ tl($sheet['fixed_assets']) }}</td></tr>
                <tr class="bg-slate-50 font-bold"><td>TOPLAM VARLIK</td><td class="num">{{ tl($sheet['assets_total']) }}</td></tr>
                </tbody>
            </table>
        </section>
        <section class="card">
            <div class="card-head"><h2 class="card-title">Yükümlülükler</h2></div>
            <table class="table">
                <tbody>
                <tr class="font-medium"><td>Daire avansları (iadesi gereken)</td><td class="num">{{ tl($sheet['advances']) }}</td></tr>
                <tr class="font-medium"><td>Ödenecek giderler (onay bekleyen)</td><td class="num">{{ tl($sheet['payables']) }}</td></tr>
                <tr class="bg-slate-50 font-bold"><td>TOPLAM YÜKÜMLÜLÜK</td><td class="num">{{ tl($sheet['liabilities_total']) }}</td></tr>
                <tr class="bg-brand-50/60 font-black"><td>NET VARLIK (Varlık − Yükümlülük)</td><td class="num {{ $sheet['net'] < 0 ? 'text-rose-600' : 'text-brand-500' }}">{{ tl($sheet['net']) }}</td></tr>
                </tbody>
            </table>
            <p class="card-body text-xs text-slate-400">Demirbaşlar doğrusal amortismanla değerlenir (Demirbaş sayfası). Alacaklar, vadesi gelmemiş borçları da içerir.</p>
        </section>
    </div>
</x-layout>

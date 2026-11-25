<x-layout heading="Mizan" :subheading="$apartment->name.' · hesap bazında borç / alacak toplamları'">
    <x-slot:actions><x-report.actions /></x-slot:actions>

    <x-report.toolbar :range="true" :from="$from" :to="$to" />

    <x-dash.module-summary :summary="$moduleSummary ?? null" />

    @php
        $ledgerUrl = fn (string $account, $accountId = null) => route('site.ledger', array_filter([$apartment->id, 'account' => $account,
            'account_id' => $accountId, 'from' => $from, 'to' => $to], fn ($v) => $v !== null && $v !== ''));
        $debitBalance = round($tb['rows']->sum(fn ($r) => max($r['balance'], 0)), 2);
        $creditBalance = round($tb['rows']->sum(fn ($r) => max(-$r['balance'], 0)), 2);
    @endphp

    <section class="card">
        <x-report.print-header :apartment="$apartment" title="Mizan" :from="$from" :to="$to" />
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr><th rowspan="2">Hesap</th><th rowspan="2">Alt hesap</th><th colspan="2" class="num">Hareket toplamı</th><th colspan="2" class="num">Bakiye</th><th rowspan="2" class="no-print"></th></tr>
                    <tr><th class="num">Borç</th><th class="num">Alacak</th><th class="num">Borç</th><th class="num">Alacak</th></tr>
                </thead>
                <tbody>
                @forelse ($tb['groups'] as $account => $g)
                    <tr class="bg-slate-50/70 font-semibold">
                        <td colspan="2" class="whitespace-nowrap">{{ $account }}</td>
                        <td class="num">{{ tl($g['debit']) }}</td><td class="num">{{ tl($g['credit']) }}</td>
                        <td class="num">{{ $g['balance'] > 0 ? tl($g['balance']) : '' }}</td><td class="num">{{ $g['balance'] < 0 ? tl(-$g['balance']) : '' }}</td>
                        <td class="no-print whitespace-nowrap text-right"><a class="text-xs font-normal text-brand-600 hover:underline" href="{{ $ledgerUrl($account) }}">Hareketler →</a></td>
                    </tr>
                    @foreach ($g['rows'] as $r)
                        <tr>
                            <td></td>
                            <td class="whitespace-nowrap text-slate-500"><x-unit-link :unit="$r['unit']" :fallback="$r['sub']" /></td>
                            <td class="num">{{ $r['debit'] ? tl($r['debit']) : '' }}</td><td class="num">{{ $r['credit'] ? tl($r['credit']) : '' }}</td>
                            <td class="num">{{ $r['balance'] > 0 ? tl($r['balance']) : '' }}</td><td class="num">{{ $r['balance'] < 0 ? tl(-$r['balance']) : '' }}</td>
                            <td class="no-print whitespace-nowrap text-right"><a class="text-xs text-slate-500 hover:text-brand-600 hover:underline" href="{{ $ledgerUrl($account, $r['account_id'] ?? 0) }}">Hareketler</a></td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="7" class="text-center text-slate-500">Bu aralıkta kayıt yok.</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50 font-bold">
                        <td colspan="2">GENEL TOPLAM</td>
                        <td class="num">{{ tl($tb['debit']) }}</td><td class="num">{{ tl($tb['credit']) }}</td>
                        <td class="num">{{ tl($debitBalance) }}</td><td class="num">{{ tl($creditBalance) }}</td>
                        <td class="no-print text-right">@if (round($tb['debit'] - $tb['credit'], 2) == 0)<span class="text-emerald-600">Dengede ✓</span>@else<span class="text-rose-600">Fark {{ tl($tb['debit'] - $tb['credit']) }}</span>@endif</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="card-body text-xs text-slate-400">Borç bakiyesi veren hesaplar (kasa, daire alacakları, gider) solda; alacak bakiyesi veren hesaplar (gelir, avans) sağda gösterilir. İki sütunun toplamı eşittir.</p>
    </section>
    <x-report.signatures />
</x-layout>

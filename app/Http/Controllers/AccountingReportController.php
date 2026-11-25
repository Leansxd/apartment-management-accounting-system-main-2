<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Services\AccountingReportService;
use App\Support\SpreadsheetExport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Mali raporlar: mizan, kasa defteri, mali durum, alacak yaşlandırma (yazdırılabilir, Excel'e aktarılabilir) */
class AccountingReportController extends Controller
{
    public function __construct(private AccountingReportService $reports) {}

    public function trialBalance(Request $request, Apartment $apartment)
    {
        $from = $this->dateQuery($request, 'from');
        $to = $this->dateQuery($request, 'to');
        $tb = $this->reports->trialBalance($apartment, $from, $to);

        if (in_array($request->query('format'), ['xlsx', 'csv'], true)) {
            return SpreadsheetExport::download($this->filename($apartment, 'mizan'),
                ['account' => 'Hesap', 'sub' => 'Alt hesap', 'debit' => 'Borç', 'credit' => 'Alacak', 'balance' => 'Bakiye'],
                $tb['rows'], ['account' => 'GENEL TOPLAM', 'debit' => $tb['debit'], 'credit' => $tb['credit'], 'balance' => round($tb['debit'] - $tb['credit'], 2)],
                ['title' => 'Mizan', 'period' => $this->periodLabel($from, $to)]);
        }

        return view('site.accounting.trial-balance', ['apartment' => $apartment, 'tb' => $tb, 'from' => $from, 'to' => $to]);
    }

    public function cashBook(Request $request, Apartment $apartment)
    {
        $cashboxes = $apartment->cashboxes()->orderBy('name')->get();
        $cashbox = $cashboxes->firstWhere('id', $request->integer('cashbox_id')) ?? $cashboxes->first();
        $from = $this->dateQuery($request, 'from') ?? now()->startOfMonth()->toDateString();
        $to = $this->dateQuery($request, 'to') ?? today()->toDateString();
        $book = $cashbox ? $this->reports->cashBook($cashbox, $from, $to) : null;

        if ($book && in_array($request->query('format'), ['xlsx', 'csv'], true)) {
            $rows = collect($book['rows'])->map(fn (array $r) => [
                'date' => $r['tx']->created_at->format('d.m.Y H:i'), 'no' => $r['tx']->receiptNo(), 'description' => $r['tx']->description,
                'unit' => $r['tx']->unit?->label(), 'status' => $r['tx']->isReversed() ? 'İptal' : '',
                'in' => (float) $r['in'], 'out' => (float) $r['out'], 'balance' => (float) $r['balance'],
            ])->prepend(['description' => 'Devreden bakiye', 'balance' => (float) $book['opening']]);

            return SpreadsheetExport::download($this->filename($apartment, 'kasa-defteri'),
                ['date' => 'Tarih', 'no' => 'No', 'description' => 'Açıklama', 'unit' => 'Daire', 'status' => 'Durum', 'in' => 'Giriş', 'out' => 'Çıkış', 'balance' => 'Bakiye'],
                $rows, ['description' => 'TOPLAM / KAPANIŞ', 'in' => (float) $book['in'], 'out' => (float) $book['out'], 'balance' => (float) $book['closing']],
                ['title' => 'Kasa Defteri · '.$cashbox->labelWithCurrency(), 'period' => $this->periodLabel($from, $to)]);
        }

        return view('site.accounting.cash-book', [
            'apartment' => $apartment, 'cashboxes' => $cashboxes, 'cashbox' => $cashbox, 'from' => $from, 'to' => $to, 'book' => $book,
        ]);
    }

    /** Seçilen gün itibarıyla bilanço; karşılaştırma için varsayılan bir önceki yılın son günüdür. */
    public function balanceSheet(Request $request, Apartment $apartment)
    {
        $asOf = CarbonImmutable::parse($this->dateQuery($request, 'as_of') ?? today()->toDateString());
        $compareTo = CarbonImmutable::parse($this->dateQuery($request, 'compare_to') ?? $asOf->subYear()->endOfYear()->toDateString());
        $sheet = $this->reports->balanceSheet($apartment, $asOf);
        $previous = $this->reports->balanceSheet($apartment, $compareTo);

        if (in_array($request->query('format'), ['xlsx', 'csv'], true)) {
            $line = fn (string $label, string $key) => ['item' => $label, 'current' => (float) $sheet[$key], 'previous' => (float) $previous[$key],
                'change' => round($sheet[$key] - $previous[$key], 2)];

            return SpreadsheetExport::download($this->filename($apartment, 'bilanco-'.$asOf->format('Ymd')),
                ['item' => 'Kalem', 'current' => $asOf->format('d.m.Y'), 'previous' => $compareTo->format('d.m.Y'), 'change' => 'Değişim'], [
                    $line('Kasa & Banka', 'cash_total'), $line('Daire alacakları', 'receivables'), $line('Demirbaşlar (net)', 'fixed_assets'),
                    $line('TOPLAM VARLIK', 'assets_total'), $line('Daire avansları', 'advances'), $line('Ödenecek giderler', 'payables'),
                    $line('TOPLAM YÜKÜMLÜLÜK', 'liabilities_total'), $line('NET VARLIK', 'net'),
                ], null, ['title' => 'Mali Durum Tablosu', 'period' => $asOf->format('d.m.Y').' itibarıyla · karşılaştırma '.$compareTo->format('d.m.Y')]);
        }

        return view('site.accounting.balance-sheet', [
            'apartment' => $apartment, 'sheet' => $sheet, 'previous' => $previous, 'asOf' => $asOf, 'compareTo' => $compareTo,
        ]);
    }

    public function aging(Request $request, Apartment $apartment)
    {
        $asOf = CarbonImmutable::parse($this->dateQuery($request, 'as_of') ?? today()->toDateString());
        $aging = $this->reports->aging($apartment, $asOf);
        $blockId = $request->integer('block_id') ?: null;
        $rows = $aging['rows']
            ->when($blockId, fn ($rows) => $rows->filter(fn (array $r) => $r['unit']->block_id === $blockId))
            ->when($request->boolean('legal'), fn ($rows) => $rows->where('legal', true))
            ->values();
        $totals = collect($aging['buckets'])->mapWithKeys(fn ($label, $key) => [$key => round($rows->sum("buckets.{$key}"), 2)])->all()
            + ['total' => round($rows->sum('total'), 2)];

        if (in_array($request->query('format'), ['xlsx', 'csv'], true)) {
            return SpreadsheetExport::download($this->filename($apartment, 'alacak-yaslandirma'),
                ['unit' => 'Daire', 'member' => 'Sakin', 'legal' => 'İcra'] + $aging['buckets'] + ['total' => 'Toplam'],
                $rows->map(fn (array $r) => ['unit' => $r['unit']->label(), 'member' => $r['member'], 'legal' => $r['legal'] ? 'Evet' : '']
                    + array_map('floatval', $r['buckets']) + ['total' => (float) $r['total']]),
                ['unit' => 'TOPLAM'] + array_map('floatval', $totals),
                ['title' => 'Alacak Yaşlandırma', 'period' => $asOf->format('d.m.Y').' itibarıyla']);
        }

        return view('site.accounting.aging', [
            'apartment' => $apartment, 'aging' => ['buckets' => $aging['buckets'], 'rows' => $rows, 'totals' => $totals], 'asOf' => $asOf,
            'blocks' => $apartment->blocks()->orderBy('name')->get(),
        ]);
    }

    private function filename(Apartment $apartment, string $report): string
    {
        return "{$apartment->code}-{$report}-".today()->format('Ymd').'.csv';
    }
}

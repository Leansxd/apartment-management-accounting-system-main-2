<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\Debt;
use App\Models\Member;
use App\Services\ModuleReportService;
use App\Services\ReportService;
use App\Support\Money;
use App\Support\SpreadsheetExport;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Rapor merkezi. Her rapor tablo olarak üretilir; tarayıcıdan yazdırılabilir / PDF kaydedilebilir
 * ya da Excel'in doğrudan açtığı CSV (UTF-8, ; ayraçlı) olarak indirilebilir.
 */
class ReportController extends Controller
{
    public const TYPES = [
        'members' => ['Sakin Listesi', 'Listeler', 'Daire, sakin türü, iletişim ve mobil bağlantı'],
        'hazirun' => ['Hazirun Cetveli', 'Listeler', 'Genel kurul için imzalı katılım listesi'],
        'balance' => ['Bakiye Durum Listesi', 'Listeler', 'Daire bazında tahakkuk, ödeme, gecikme ve net bakiye'],
        'debts' => ['Detaylı Borç Raporu', 'Tahsilat ve Borç', 'Tüm borç kalemleri, vade ve ödeme durumu'],
        'overdue' => ['Vadesi Geçmiş Borçlar', 'Tahsilat ve Borç', 'Vadesi geçen borçlar ve gecikme günleri'],
        'receipts' => ['Tahsilat Makbuzları', 'Tahsilat ve Borç', 'Makbuz numaralarıyla tüm tahsilatlar'],
    ] + ModuleReportService::TYPES;

    /** Tarih aralığı filtresi gösterilen raporlar */
    public const DATED = ['debts', 'overdue', 'receipts', ...ModuleReportService::DATED];

    public function index(Apartment $apartment)
    {
        return view('site.reports.index', ['apartment' => $apartment, 'types' => collect(self::TYPES)->groupBy(fn ($t) => $t[1], true)]);
    }

    public function show(Request $request, Apartment $apartment, string $type, ReportService $reports, ModuleReportService $moduleReports)
    {
        $from = $request->date('from')?->toDateString();
        $to = $request->date('to')?->toDateString();
        $blockId = $request->integer('block_id') ?: null;

        [$columns, $rows, $totals] = isset(ModuleReportService::TYPES[$type])
            ? $moduleReports->build($apartment, $type, $from, $to, $blockId)
            : $this->build($apartment, $type, $reports, $from, $to, $blockId);
        $title = self::TYPES[$type][0];
        $documentNo = strtoupper(substr(hash('sha256', implode('|', [$apartment->id, $type, $from, $to, $blockId, today()->toDateString()])), 0, 8));

        if (in_array($request->query('format'), ['xlsx', 'csv'], true)) {
            return SpreadsheetExport::download("{$apartment->code}-{$type}-".today()->format('Ymd').'.csv', $columns, $rows, $totals,
                ['title' => $title, 'period' => $this->periodLabel($from, $to), 'document_no' => $documentNo]);
        }

        return view('site.reports.show', compact('apartment', 'type', 'title', 'columns', 'rows', 'totals', 'from', 'to', 'blockId', 'documentNo') + [
            'blocks' => $apartment->blocks()->orderBy('name')->get(),
            'print' => $request->boolean('print'),
        ]);
    }

    /** @return array{0: array<string,string>, 1: Collection, 2: array<string,mixed>|null} */
    private function build(Apartment $apartment, string $type, ReportService $reports, ?string $from, ?string $to, ?int $blockId): array
    {
        $inBlock = fn ($unit) => ! $blockId || $unit?->block_id === $blockId;

        switch ($type) {
            case 'members':
                $rows = $apartment->members()->with('unit.block')->get()->filter(fn ($m) => $inBlock($m->unit))
                    ->sortBy(fn ($m) => [$m->unit?->block?->name, (int) $m->unit?->unit_no])
                    ->map(fn (Member $m) => [
                        '_links' => ['unit' => $m->unit, 'name' => $m], 'unit' => $m->unit?->label(), 'type' => Member::TYPES[$m->type] ?? $m->type, 'name' => $m->full_name,
                        'phone' => $m->phone, 'email' => $m->email, 'mobile' => $m->user_id ? 'Bağlı' : 'Bekliyor',
                    ]);

                return [['unit' => 'Daire', 'type' => 'Tür', 'name' => 'Ad Soyad', 'phone' => 'Telefon', 'email' => 'E-posta', 'mobile' => 'Mobil'], $rows->values(), null];

            case 'hazirun':
                $rows = $reports->balanceList($apartment)->filter(fn ($r) => $inBlock($r['unit']))->map(fn ($r) => [
                    '_links' => ['unit' => $r['unit'], 'owner' => $r['owner']], 'unit' => $r['unit']->label(), 'owner' => $r['owner']?->full_name, 'share' => $r['unit']->land_share,
                    'proxy' => '', 'signature' => '',
                ]);

                return [['unit' => 'Daire', 'owner' => 'Kat Maliki', 'share' => 'Arsa Payı', 'proxy' => 'Vekil', 'signature' => 'İmza'], $rows->values(), null];

            case 'balance':
                $list = $reports->balanceList($apartment)->filter(fn ($r) => $inBlock($r['unit']));
                $rows = $list->map(fn ($r) => [
                    '_links' => ['unit' => $r['unit'], 'owner' => $r['owner'], 'tenant' => $r['tenant']], 'unit' => $r['unit']->label(), 'owner' => $r['owner']?->full_name, 'tenant' => $r['tenant']?->full_name,
                    'accrued' => $r['accrued'], 'paid' => $r['paid'], 'remaining' => $r['remaining'],
                    'overdue' => $r['overdue'], 'credit' => $r['credit'], 'net' => $r['net'],
                    'status' => $r['net'] > 0 ? 'Borçlu' : ($r['net'] < 0 ? 'Alacaklı' : 'Temiz'),
                ]);
                $totals = ['unit' => 'TOPLAM'] + collect(['accrued', 'paid', 'remaining', 'overdue', 'credit', 'net'])
                    ->mapWithKeys(fn ($k) => [$k => round($rows->sum($k), 2)])->all();

                return [['unit' => 'Daire', 'owner' => 'Mülk Sahibi', 'tenant' => 'Kiracı', 'accrued' => 'Tahakkuk', 'paid' => 'Ödenen',
                    'remaining' => 'Kalan Borç', 'overdue' => 'Gecikmiş', 'credit' => 'Avans', 'net' => 'Net Bakiye', 'status' => 'Durum'],
                    $rows->values(), $totals];

            case 'debts':
            case 'overdue':
                $rows = $apartment->debts()->with('unit.block', 'unit.members')
                    ->when($type === 'overdue', fn ($q) => $q->where('status', Debt::PENDING)->whereDate('due_date', '<', today()))
                    ->when($from, fn ($q) => $q->whereDate('due_date', '>=', $from))
                    ->when($to, fn ($q) => $q->whereDate('due_date', '<=', $to))
                    ->orderBy('due_date')->get()->filter(fn ($d) => $inBlock($d->unit))
                    ->map(fn (Debt $d) => [
                        '_links' => ['unit' => $d->unit, 'member' => $d->unit?->members->first()], 'unit' => $d->unit?->label(), 'member' => $d->unit?->members->first()?->full_name,
                        'responsible' => Debt::RESPONSIBLES[$d->responsible] ?? 'Daire geneli',
                        'description' => $d->description, 'due' => $d->due_date->format('d.m.Y'),
                        'days' => $d->displayStatus() === 'overdue' ? (int) $d->due_date->diffInDays(today()) : '',
                        'amount' => (float) $d->amount, 'paid' => (float) $d->paid_amount, 'remaining' => (float) $d->remaining_amount,
                        'status' => Debt::DISPLAY[$d->displayStatus()],
                    ]);
                $totals = ['unit' => 'TOPLAM', 'amount' => round($rows->sum('amount'), 2), 'paid' => round($rows->sum('paid'), 2),
                    'remaining' => round($rows->sum('remaining'), 2)];

                return [['unit' => 'Daire', 'member' => 'Sakin', 'responsible' => 'Sorumlu', 'description' => 'Açıklama', 'due' => 'Vade', 'days' => 'Gecikme (gün)',
                    'amount' => 'Tutar', 'paid' => 'Ödenen', 'remaining' => 'Kalan', 'status' => 'Durum'], $rows->values(), $totals];

            case 'receipts':
                $rows = $apartment->cashTransactions()->effective()->with('unit.block', 'cashbox', 'performer', 'payer')
                    ->whereIn('sub_type', ['collection', 'pos', 'advance'])
                    ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
                    ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
                    ->orderBy('id')->get()->filter(fn ($t) => $inBlock($t->unit))
                    ->map(fn ($t) => [
                        'no' => 'M-'.str_pad((string) $t->id, 6, '0', STR_PAD_LEFT), 'date' => $t->created_at->format('d.m.Y H:i'),
                        '_links' => ['unit' => $t->unit], 'unit' => $t->unit?->label(), 'payer' => $t->payer?->full_name, 'description' => $t->description, 'cashbox' => $t->cashbox?->name,
                        'amount' => (float) $t->amount, 'original' => $t->originalLabel(), 'by' => $t->performer?->name,
                    ]);

                return [['no' => 'Makbuz No', 'date' => 'Tarih', 'unit' => 'Daire', 'payer' => 'Ödeyen', 'description' => 'Açıklama', 'cashbox' => 'Kasa',
                    'amount' => 'Tutar (TL)', 'original' => 'Orijinal Tutar × Kur', 'by' => 'İşlemi Yapan'], $rows->values(), ['no' => 'TOPLAM', 'amount' => round($rows->sum('amount'), 2)]];
        }

        abort(404);
    }

    /** Görünümde para sütunlarını biçimlendirmek için */
    public static function cell(mixed $value): string
    {
        return is_float($value) ? Money::format($value) : (string) ($value ?? '');
    }
}

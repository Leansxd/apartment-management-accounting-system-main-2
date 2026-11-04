<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\BankStatementLine;
use App\Services\BankImportService;
use App\Services\FinanceException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Banka ekstresi içe aktarma ve mutabakat: satırı daireyle eşleştirip tahsilat / gider olarak işler. */
class BankController extends Controller
{
    public function __construct(private BankImportService $bank)
    {
    }

    public function index(Request $request, Apartment $apartment)
    {
        $status = in_array($request->query('status'), ['pending', 'posted', 'ignored'], true) ? $request->query('status') : 'pending';

        return view('site.finance.bank', [
            'apartment' => $apartment,
            'lines' => $apartment->bankLines()->with('unit.block', 'cashbox')->where('status', $status)
                ->orderByDesc('line_date')->orderByDesc('id')->paginate(50)->withQueryString(),
            'status' => $status,
            'counts' => $apartment->bankLines()->selectRaw('status, COUNT(*) c, SUM(amount) s')->groupBy('status')->get()->keyBy('status'),
            'cashboxes' => $apartment->cashboxes()->where('type', 'bank')->orderBy('name')->get()->whenEmpty(fn () => $apartment->cashboxes()->orderBy('name')->get()),
            'units' => $apartment->units()->with('block')->get()->sortBy(fn ($u) => [$u->block?->name, (int) $u->unit_no])->values(),
            'categories' => $apartment->categories()->orderBy('type')->orderBy('name')->get(),
            'matchedPending' => $apartment->bankLines()->where('status', 'pending')->where('amount', '>', 0)->whereNotNull('unit_id')->count(),
        ]);
    }

    public function import(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'cashbox_id' => ['required', Rule::exists('cashboxes', 'id')->where('apartment_id', $apartment->id)],
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,txt'],
        ]);

        return $this->attempt(function () use ($apartment, $data, $request) {
            $rows = $this->bank->parse((string) file_get_contents($request->file('file')->getRealPath()));
            $s = $this->bank->import($apartment, $apartment->cashboxes()->findOrFail($data['cashbox_id']), $rows);

            return "{$s['imported']} satır alındı ({$s['matched']} daire eşleşti), {$s['duplicates']} satır daha önce yüklendiği için atlandı.";
        }, '');
    }

    public function assign(Request $request, Apartment $apartment, BankStatementLine $bankLine)
    {
        $data = $request->validate(['unit_id' => ['nullable', Rule::exists('units', 'id')->where('apartment_id', $apartment->id)]]);
        abort_unless($bankLine->status === 'pending', 422, 'İşlenmiş satır değiştirilemez.');
        $bankLine->update(['unit_id' => $bankLine->amount > 0 ? ($data['unit_id'] ?? null) : null]);

        return back()->with('status', 'Satır güncellendi.');
    }

    public function post(Request $request, Apartment $apartment, BankStatementLine $bankLine)
    {
        $data = $request->validate(['category_id' => ['nullable', Rule::exists('transaction_categories', 'id')->where('apartment_id', $apartment->id)]]);

        return $this->attempt(fn () => $this->bank->post($bankLine, $data['category_id'] ?? null, $request->user()), 'Satır kasa hareketine dönüştürüldü.');
    }

    public function postMatched(Request $request, Apartment $apartment)
    {
        $r = $this->bank->postMatched($apartment, $request->user());

        return back()->with($r['failed'] ? 'error' : 'status', "{$r['posted']} satır tahsilata dönüştürüldü.".($r['failed'] ? ' Hatalı: '.implode('; ', $r['failed']) : ''));
    }

    public function ignore(Apartment $apartment, BankStatementLine $bankLine)
    {
        abort_unless($bankLine->status === 'pending', 422, 'Yalnızca bekleyen satır yok sayılabilir.');
        $bankLine->update(['status' => 'ignored']);

        return back()->with('status', 'Satır yok sayıldı.');
    }

    /** Bir yüklemenin henüz işlenmemiş satırlarını siler */
    public function destroyBatch(Request $request, Apartment $apartment)
    {
        $batch = $request->validate(['batch' => ['required', 'string', 'size:36']])['batch'];
        $deleted = $apartment->bankLines()->where('batch', $batch)->where('status', 'pending')->delete();

        return back()->with('status', "{$deleted} bekleyen satır silindi.");
    }
}

<?php

namespace App\Services;

use App\Models\Apartment;
use App\Models\BankStatementLine;
use App\Models\Cashbox;
use App\Models\Unit;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Banka ekstresi (CSV) içe aktarma, daire eşleştirme ve kasa hareketine dönüştürme.
 * Aynı ekstre ikinci kez yüklenirse satırlar hash ile tanınır ve atlanır.
 */
class BankImportService
{
    public function __construct(private FinanceService $finance) {}

    /**
     * CSV metnini satırlara ayırır. Ayraç (; , sekme) ve başlıklar otomatik bulunur.
     * Desteklenen sütunlar: tarih, açıklama, tutar (işaretli) veya giriş/çıkış (alacak/borç) ayrı sütunlar, referans.
     *
     * @return array<int, array{date: string, description: string, amount: int, reference: ?string}> amount kuruş (+ gelen, − giden)
     */
    public function parse(string $content): array
    {
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1254');
        }
        $content = ltrim($content, "\xEF\xBB\xBF");
        $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', $content), fn ($l) => trim($l) !== ''));
        if (count($lines) < 2) {
            throw new FinanceException('Dosyada başlık ve en az bir satır olmalıdır.');
        }

        $delimiter = collect([';', ',', "\t"])->sortByDesc(fn ($d) => substr_count($lines[0], $d))->first();
        $header = array_map(fn ($h) => $this->normalize($h), str_getcsv($lines[0], $delimiter));
        // Adlar öncelik sırasıyla aranır: "İşlem Tarihi" gibi başlıklar açıklama sütunu sanılmasın
        $col = function (array $names, array $except = []) use ($header): int|false {
            foreach ($names as $name) {
                foreach ($header as $index => $title) {
                    if (! in_array($index, $except, true) && str_contains($title, $name)) {
                        return $index;
                    }
                }
            }

            return false;
        };

        $date = $col(['tarih', 'date']);
        $desc = $col(['aciklama', 'description', 'islem'], [$date]);
        $amount = $col(['tutar', 'amount']);
        $in = $col(['giris', 'gelen', 'alacak', 'credit']);
        $out = $col(['cikis', 'giden', 'borc', 'debit']);
        $ref = $col(['referans', 'dekont', 'reference', 'belge']);
        if ($date === false || $desc === false || ($amount === false && $in === false && $out === false)) {
            throw new FinanceException('Başlıkta tarih, açıklama ve tutar (veya giriş/çıkış) sütunları bulunmalıdır.');
        }

        $rows = [];
        foreach (array_slice($lines, 1) as $n => $line) {
            $cells = str_getcsv($line, $delimiter);
            $when = $this->parseDate($cells[$date] ?? '');
            if (! $when) {
                throw new FinanceException('Satır '.($n + 2).': tarih anlaşılamadı ("'.($cells[$date] ?? '').'").');
            }
            $cents = $amount !== false
                ? Money::parse($cells[$amount] ?? '')
                : Money::parse($cells[$in] ?? '') - abs(Money::parse($cells[$out] ?? ''));
            if ($cents === 0) {
                continue;
            }
            $rows[] = ['date' => $when, 'description' => trim((string) ($cells[$desc] ?? '')), 'amount' => $cents,
                'reference' => $ref === false ? null : (trim((string) ($cells[$ref] ?? '')) ?: null)];
        }
        if ($rows === []) {
            throw new FinanceException('İçe aktarılacak satır bulunamadı.');
        }

        return $rows;
    }

    /** @return array{imported: int, duplicates: int, matched: int, batch: string} */
    public function import(Apartment $apartment, Cashbox $cashbox, array $rows): array
    {
        $units = $apartment->units()->with('block', 'members')->get();
        $batch = (string) Str::uuid();
        $seen = [];
        $stats = ['imported' => 0, 'duplicates' => 0, 'matched' => 0, 'batch' => $batch];

        DB::transaction(function () use ($apartment, $cashbox, $rows, $units, $batch, &$seen, &$stats) {
            foreach ($rows as $row) {
                $base = sha1(implode('|', [$cashbox->id, $row['date'], $row['amount'], $row['description'], $row['reference']]));
                $seen[$base] = ($seen[$base] ?? 0) + 1;
                $hash = sha1($base.'#'.$seen[$base]); // aynı dosyadaki birebir aynı satırlar ayrı sayılır

                if (BankStatementLine::where('apartment_id', $apartment->id)->where('hash', $hash)->exists()) {
                    $stats['duplicates']++;

                    continue;
                }
                $unit = $row['amount'] > 0 ? $this->matchUnit($row['description'], $units) : null;
                BankStatementLine::create([
                    'apartment_id' => $apartment->id, 'cashbox_id' => $cashbox->id, 'batch' => $batch,
                    'line_date' => $row['date'], 'description' => mb_substr($row['description'], 0, 500),
                    'reference' => $row['reference'], 'amount' => Money::decimal($row['amount']),
                    'unit_id' => $unit?->id, 'hash' => $hash,
                ]);
                $stats['imported']++;
                $unit && $stats['matched']++;
            }
        });

        return $stats;
    }

    /**
     * Açıklamadan daireyi bulur: önce "A-5 / A5 / A 5" gibi daire etiketi, sonra üye adı.
     * Birden fazla daire eşleşirse (belirsiz) eşleştirme yapılmaz.
     *
     * @param  Collection<int, Unit>  $units  block ve members yüklü
     */
    public function matchUnit(string $description, Collection $units): ?Unit
    {
        $text = ' '.$this->normalize($description).' ';

        $byLabel = $units->filter(function (Unit $u) use ($text) {
            $block = preg_quote($this->normalize((string) $u->block?->name), '/');
            $no = preg_quote($this->normalize($u->unit_no), '/');

            return (bool) preg_match("/(?<![a-z0-9]){$block}\\s*[-\\/. ]?\\s*{$no}(?![a-z0-9])/", $text)
                || (bool) preg_match("/(?<![a-z0-9])(daire|no|d)\\s*[:.]?\\s*{$no}(?![a-z0-9])/", $text) && $units->where('unit_no', $u->unit_no)->count() === 1;
        });
        if ($byLabel->count() === 1) {
            return $byLabel->first();
        }

        $byName = $units->filter(fn (Unit $u) => $u->members->contains(function ($m) use ($text) {
            $parts = array_filter(explode(' ', $this->normalize($m->full_name)), fn ($p) => mb_strlen($p) > 1);

            return count($parts) >= 2 && collect($parts)->every(fn ($p) => str_contains($text, " {$p} ") || str_contains($text, " {$p}"));
        }));

        return $byName->count() === 1 ? $byName->first() : null;
    }

    /**
     * Satırı kasa hareketine dönüştürür. Gelen para eşleşen daireye tahsilat olur (borçlara dağıtılır);
     * eşleşmeyen gelen para / giden para genel gelir / gider olarak yazılır.
     */
    public function post(BankStatementLine $line, ?int $categoryId = null, ?User $by = null): void
    {
        DB::transaction(function () use ($line, $categoryId, $by) {
            $line = BankStatementLine::whereKey($line->id)->lockForUpdate()->firstOrFail();
            if ($line->status !== 'pending') {
                throw new FinanceException('Bu satır zaten işlenmiş.');
            }
            $cents = Money::cents($line->amount);
            $cashbox = $line->cashbox;
            $text = trim(($line->reference ? "[{$line->reference}] " : '').$line->description);

            if ($cents > 0 && $line->unit_id) {
                $tx = $this->finance->collect($line->unit, $cashbox, $cents, "Banka: {$text}", $by);
            } else {
                $tx = $this->finance->generalTransaction($cashbox, $cents > 0 ? 'income' : 'expense', abs($cents), "Banka: {$text}", $categoryId, $by);
            }
            $line->update(['status' => 'posted', 'cash_transaction_id' => $tx->id]);
        });
    }

    /** Daire eşleşmesi olan bekleyen gelen satırları toplu işler. @return array{posted: int, failed: array<int, string>} */
    public function postMatched(Apartment $apartment, ?User $by = null): array
    {
        $result = ['posted' => 0, 'failed' => []];
        foreach ($apartment->bankLines()->where('status', 'pending')->where('amount', '>', 0)->whereNotNull('unit_id')->orderBy('line_date')->get() as $line) {
            try {
                $this->post($line, null, $by);
                $result['posted']++;
            } catch (FinanceException $e) {
                $result['failed'][] = "{$line->description}: {$e->getMessage()}";
            }
        }

        return $result;
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        foreach (['d.m.Y', 'd/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y H:i:s', 'd.m.Y H:i', 'Y-m-d H:i:s', 'd/m/Y H:i:s'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date->toDateString();
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /** Küçük harf, Türkçe karakterleri sadeleştir, noktalama boşluk olur. */
    private function normalize(?string $s): string
    {
        $s = strtr((string) $s, ['İ' => 'i', 'I' => 'i', 'ı' => 'i', 'Ş' => 's', 'ş' => 's', 'Ğ' => 'g', 'ğ' => 'g', 'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o', 'Ç' => 'c', 'ç' => 'c']);

        return trim(preg_replace('/\s+/', ' ', mb_strtolower($s)));
    }
}

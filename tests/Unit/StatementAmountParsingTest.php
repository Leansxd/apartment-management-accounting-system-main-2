<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/** Banka ekstresindeki farklı tutar yazımları kuruşa doğru çevrilir */
class StatementAmountParsingTest extends TestCase
{
    #[TestWith(['1.250,00', 125000])]
    #[TestWith(['1.250', 125000])]
    #[TestWith(['12.500', 1250000])]
    #[TestWith(['1,234.56', 123456])]
    #[TestWith(['1,234,567.89', 123456789])]
    #[TestWith(['TL 1.250,00', 125000])]
    #[TestWith(['1.250,00 TL', 125000])]
    #[TestWith(['₺2.000', 200000])]
    #[TestWith(['1 250,00', 125000])]
    #[TestWith(['(1.250,00)', -125000])]
    #[TestWith(['1.250,00-', -125000])]
    #[TestWith(['-450,00', -45000])]
    #[TestWith(['450.75', 45075])]
    #[TestWith(['12.50', 1250])]
    #[TestWith(['', 0])]
    public function test_ekstre_tutari_okunur(string $input, int $cents): void
    {
        $this->assertSame($cents, Money::parseStatementAmount($input));
    }

    public function test_form_tutari_ondalik_nokta_olarak_okunmaya_devam_eder(): void
    {
        $this->assertSame(1250, Money::parse('12.500'));
    }
}

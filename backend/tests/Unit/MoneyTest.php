<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_format_utilise_separateur_de_milliers_et_espaces_insecables(): void
    {
        $this->assertSame("12\u{202F}500\u{00A0}F\u{00A0}CFA", Money::format(12500));
        $this->assertSame("0\u{00A0}F\u{00A0}CFA", Money::format(null));
        $this->assertSame("1\u{202F}250\u{202F}000\u{00A0}F\u{00A0}CFA", Money::format('1250000'));
    }

    public function test_format_ne_contient_ni_xof_ni_espace_secable(): void
    {
        $out = Money::format(75000);
        $this->assertStringNotContainsString('XOF', $out);
        $this->assertStringNotContainsString(' ', $out);
    }

    public function test_per_period(): void
    {
        $this->assertSame("25\u{202F}000\u{00A0}F\u{00A0}CFA\u{00A0}/\u{00A0}jour", Money::perPeriod(25000, 'jour'));
    }

    public function test_number(): void
    {
        $this->assertSame("3\u{202F}941", Money::number(3941));
    }
}

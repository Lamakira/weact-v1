<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Sql;
use PHPUnit\Framework\TestCase;

class SqlTest extends TestCase
{
    public function test_escapes_percent_wildcard(): void
    {
        $this->assertSame('100\%', Sql::escapeLike('100%'));
    }

    public function test_escapes_underscore_wildcard(): void
    {
        $this->assertSame('awa\_a', Sql::escapeLike('awa_a'));
    }

    public function test_escapes_backslash_first(): void
    {
        // Le backslash est échappé AVANT les wildcards : un '\' final ne doit
        // pas échapper le '%' fermant du motif construit autour du terme.
        $this->assertSame('back\\\\', Sql::escapeLike('back\\'));
    }

    public function test_backslash_before_wildcard_leaves_no_live_wildcard(): void
    {
        // '\%' saisi littéralement → backslash échappé PUIS % échappé :
        // aucun wildcard vivant ne survit.
        $this->assertSame('\\\\\%', Sql::escapeLike('\\%'));
    }

    public function test_plain_text_untouched(): void
    {
        $this->assertSame('Adjovi', Sql::escapeLike('Adjovi'));
    }
}

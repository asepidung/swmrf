<?php

namespace Tests\Unit;

use App\Support\TidyName;
use PHPUnit\Framework\TestCase;

class TidyNameTest extends TestCase
{
    public function test_company_prefix_moves_to_the_end_without_dots(): void
    {
        $this->assertSame('ARDANA PERMATA ANUGERAH PT', TidyName::company('PT. ARDANA PERMATA ANUGERAH'));
        $this->assertSame('PERMATA PACKINDO SOLUTION PT', TidyName::company('PT.PERMATA PACKINDO SOLUTION'));
        $this->assertSame('RIZAVIN JAYA MANDIRI CV', TidyName::company('CV.RIZAVIN JAYA MANDIRI'));
        $this->assertSame('GARI SETIAWAN MAKMUR CV', TidyName::company('cv. gari  setiawan makmur'));
    }

    public function test_names_already_in_the_final_form_are_unchanged(): void
    {
        foreach (['KARIYANA GITA UTAMA PT', 'H. DONI', 'ASEP BPK', 'E - COMMERCE', 'SWM (STOCK IN)'] as $name) {
            $this->assertSame($name, TidyName::company($name));
        }
    }

    public function test_brackets_have_no_inner_spaces(): void
    {
        $this->assertSame('BERKAT AMADEUS SUKSES SENTOSA (BASS) PT', TidyName::company('BERKAT AMADEUS SUKSES SENTOSA ( BASS ) PT'));
    }
}

<?php

namespace Tests\Unit\Enums;

use App\Enums\BankName;
use PHPUnit\Framework\TestCase;

class BankNameTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('Bank Central Asia (BCA)', BankName::BCA->label());
        $this->assertSame('Bank Mandiri', BankName::MANDIRI->label());
        $this->assertSame('Bank Negara Indonesia (BNI)', BankName::BNI->label());
        $this->assertSame('Bank Rakyat Indonesia (BRI)', BankName::BRI->label());
        $this->assertSame('CIMB Niaga', BankName::CIMB_NIAGA->label());
        $this->assertSame('Bank Danamon', BankName::DANAMON->label());
        $this->assertSame('Bank Permata', BankName::PERMATA->label());
        $this->assertSame('Maybank Indonesia', BankName::MAYBANK_INDONESIA->label());
        $this->assertSame('OCBC NISP', BankName::OCBC_NISP->label());
        $this->assertSame('Panin Bank', BankName::PANIN_BANK->label());
    }

    public function test_to_array_returns_value_and_label(): void
    {
        $this->assertSame([
            'value' => 'bca',
            'label' => 'Bank Central Asia (BCA)',
        ], BankName::BCA->toArray());

        $this->assertSame([
            'value' => 'mandiri',
            'label' => 'Bank Mandiri',
        ], BankName::MANDIRI->toArray());
    }
}

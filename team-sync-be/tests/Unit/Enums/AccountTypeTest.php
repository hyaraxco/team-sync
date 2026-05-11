<?php

namespace Tests\Unit\Enums;

use App\Enums\AccountType;
use PHPUnit\Framework\TestCase;

class AccountTypeTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('Savings Account', AccountType::SAVINGS->label());
        $this->assertSame('Checking Account', AccountType::CHECKING->label());
        $this->assertSame('Current Account', AccountType::CURRENT->label());
    }

    public function test_to_array_returns_value_and_label(): void
    {
        $this->assertSame([
            'value' => 'savings',
            'label' => 'Savings Account',
        ], AccountType::SAVINGS->toArray());
    }
}

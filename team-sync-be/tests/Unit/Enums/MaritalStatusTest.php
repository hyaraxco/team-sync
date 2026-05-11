<?php

namespace Tests\Unit\Enums;

use App\Enums\MaritalStatus;
use PHPUnit\Framework\TestCase;

class MaritalStatusTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('Belum Menikah', MaritalStatus::SINGLE->label());
        $this->assertSame('Menikah', MaritalStatus::MARRIED->label());
        $this->assertSame('Janda/Duda', MaritalStatus::WIDOWED->label());
        $this->assertSame('Cerai', MaritalStatus::DIVORCED->label());
    }

    public function test_to_array_returns_value_and_label(): void
    {
        $this->assertSame([
            'value' => 'married',
            'label' => 'Menikah',
        ], MaritalStatus::MARRIED->toArray());
    }
}

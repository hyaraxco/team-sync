<?php

namespace Tests\Unit\Enums;

use App\Enums\Religion;
use PHPUnit\Framework\TestCase;

class ReligionTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('Islam', Religion::ISLAM->label());
        $this->assertSame('Kristen Protestan', Religion::KRISTEN->label());
        $this->assertSame('Katolik', Religion::KATOLIK->label());
        $this->assertSame('Hindu', Religion::HINDU->label());
        $this->assertSame('Budha', Religion::BUDHA->label());
        $this->assertSame('Konghucu', Religion::KONGHUCU->label());
    }

    public function test_to_array_returns_value_and_label(): void
    {
        $this->assertSame([
            'value' => 'islam',
            'label' => 'Islam',
        ], Religion::ISLAM->toArray());
    }
}

<?php

namespace Tests\Unit\Enums;

use App\Enums\BloodType;
use PHPUnit\Framework\TestCase;

class BloodTypeTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('A', BloodType::A->label());
        $this->assertSame('B', BloodType::B->label());
        $this->assertSame('AB', BloodType::AB->label());
        $this->assertSame('O', BloodType::O->label());
    }

    public function test_to_array_returns_value_and_label(): void
    {
        $this->assertSame([
            'value' => 'AB',
            'label' => 'AB',
        ], BloodType::AB->toArray());
    }
}

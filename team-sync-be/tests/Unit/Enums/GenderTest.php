<?php

namespace Tests\Unit\Enums;

use App\Enums\Gender;
use PHPUnit\Framework\TestCase;

class GenderTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('Male', Gender::MALE->label());
        $this->assertSame('Female', Gender::FEMALE->label());
    }

    public function test_to_array_returns_value_and_label(): void
    {
        $this->assertSame([
            'value' => 'male',
            'label' => 'Male',
        ], Gender::MALE->toArray());
    }
}

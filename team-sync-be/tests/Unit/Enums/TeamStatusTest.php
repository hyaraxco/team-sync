<?php

namespace Tests\Unit\Enums;

use App\Enums\TeamStatus;
use PHPUnit\Framework\TestCase;

class TeamStatusTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('Active', TeamStatus::ACTIVE->label());
        $this->assertSame('Forming', TeamStatus::FORMING->label());
        $this->assertSame('Planning', TeamStatus::PLANNING->label());
        $this->assertSame('Dormant', TeamStatus::DORMANT->label());
    }

    public function test_to_array_returns_value_and_label(): void
    {
        $this->assertSame([
            'value' => 'forming',
            'label' => 'Forming',
        ], TeamStatus::FORMING->toArray());
    }
}

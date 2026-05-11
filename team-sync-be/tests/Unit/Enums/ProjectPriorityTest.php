<?php

namespace Tests\Unit\Enums;

use App\Enums\ProjectPriority;
use PHPUnit\Framework\TestCase;

class ProjectPriorityTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('Low', ProjectPriority::LOW->label());
        $this->assertSame('Medium', ProjectPriority::MEDIUM->label());
        $this->assertSame('High', ProjectPriority::HIGH->label());
    }

    public function test_to_array_returns_value_and_label(): void
    {
        $this->assertSame([
            'value' => 'medium',
            'label' => 'Medium',
        ], ProjectPriority::MEDIUM->toArray());
    }
}

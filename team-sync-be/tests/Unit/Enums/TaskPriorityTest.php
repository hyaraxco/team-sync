<?php

namespace Tests\Unit\Enums;

use App\Enums\TaskPriority;
use PHPUnit\Framework\TestCase;

class TaskPriorityTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('Low', TaskPriority::LOW->label());
        $this->assertSame('Medium', TaskPriority::MEDIUM->label());
        $this->assertSame('High', TaskPriority::HIGH->label());
        $this->assertSame('Urgent', TaskPriority::URGENT->label());
    }

    public function test_to_array_returns_value_and_label(): void
    {
        $this->assertSame([
            'value' => 'urgent',
            'label' => 'Urgent',
        ], TaskPriority::URGENT->toArray());
    }
}

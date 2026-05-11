<?php

namespace Tests\Unit\Enums;

use App\Enums\TaskStatus;
use PHPUnit\Framework\TestCase;

class TaskStatusTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('To Do', TaskStatus::TODO->label());
        $this->assertSame('In Progress', TaskStatus::IN_PROGRESS->label());
        $this->assertSame('Review', TaskStatus::REVIEW->label());
        $this->assertSame('Done', TaskStatus::DONE->label());
        $this->assertSame('Rejected', TaskStatus::REJECTED->label());
        $this->assertSame('Cancelled', TaskStatus::CANCELLED->label());
    }

    public function test_to_array_returns_value_and_label(): void
    {
        $this->assertSame([
            'value' => 'in_progress',
            'label' => 'In Progress',
        ], TaskStatus::IN_PROGRESS->toArray());
    }
}

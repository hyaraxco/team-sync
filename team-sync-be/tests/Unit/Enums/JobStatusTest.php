<?php

namespace Tests\Unit\Enums;

use App\Enums\JobStatus;
use PHPUnit\Framework\TestCase;

class JobStatusTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('Active', JobStatus::ACTIVE->label());
        $this->assertSame('On Leave', JobStatus::ON_LEAVE->label());
        $this->assertSame('Resigned', JobStatus::RESIGNED->label());
    }

    public function test_to_array_returns_value_and_label(): void
    {
        $this->assertSame([
            'value' => 'on_leave',
            'label' => 'On Leave',
        ], JobStatus::ON_LEAVE->toArray());
    }
}

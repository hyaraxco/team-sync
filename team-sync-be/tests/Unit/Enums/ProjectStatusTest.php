<?php

namespace Tests\Unit\Enums;

use App\Enums\ProjectStatus;
use PHPUnit\Framework\TestCase;

class ProjectStatusTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('Draft', ProjectStatus::DRAFT->label());
        $this->assertSame('Planning', ProjectStatus::PLANNING->label());
        $this->assertSame('Active', ProjectStatus::ACTIVE->label());
        $this->assertSame('On Hold', ProjectStatus::ON_HOLD->label());
        $this->assertSame('Completed', ProjectStatus::COMPLETED->label());
        $this->assertSame('Cancelled', ProjectStatus::CANCELLED->label());
    }

    public function test_to_array_returns_value_and_label(): void
    {
        $this->assertSame([
            'value' => 'on_hold',
            'label' => 'On Hold',
        ], ProjectStatus::ON_HOLD->toArray());
    }
}

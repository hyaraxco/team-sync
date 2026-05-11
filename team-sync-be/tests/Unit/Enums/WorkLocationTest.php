<?php

namespace Tests\Unit\Enums;

use App\Enums\WorkLocation;
use PHPUnit\Framework\TestCase;

class WorkLocationTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('Office', WorkLocation::OFFICE->label());
        $this->assertSame('Remote', WorkLocation::REMOTE->label());
        $this->assertSame('Hybrid', WorkLocation::HYBRID->label());
    }

    public function test_to_array_returns_value_and_label(): void
    {
        $this->assertSame([
            'value' => 'hybrid',
            'label' => 'Hybrid',
        ], WorkLocation::HYBRID->toArray());
    }
}

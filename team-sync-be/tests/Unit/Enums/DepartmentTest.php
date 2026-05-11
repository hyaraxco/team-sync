<?php

namespace Tests\Unit\Enums;

use App\Enums\Department;
use PHPUnit\Framework\TestCase;

class DepartmentTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('Development', Department::DEVELOPMENT->label());
        $this->assertSame('Design', Department::DESIGN->label());
        $this->assertSame('Marketing', Department::MARKETING->label());
        $this->assertSame('Sales', Department::SALES->label());
        $this->assertSame('Support', Department::SUPPORT->label());
        $this->assertSame('Management', Department::MANAGEMENT->label());
    }

    public function test_to_array_returns_value_and_label(): void
    {
        $this->assertSame([
            'value' => 'development',
            'label' => 'Development',
        ], Department::DEVELOPMENT->toArray());
    }
}

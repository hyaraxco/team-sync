<?php

namespace Tests\Unit\Enums;

use App\Enums\EmploymentType;
use PHPUnit\Framework\TestCase;

class EmploymentTypeTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('Full-time', EmploymentType::FULL_TIME->label());
        $this->assertSame('Part-time', EmploymentType::PART_TIME->label());
        $this->assertSame('Contract', EmploymentType::CONTRACT->label());
        $this->assertSame('Intern', EmploymentType::INTERN->label());
    }

    public function test_to_array_returns_value_and_label(): void
    {
        $this->assertSame([
            'value' => 'contract',
            'label' => 'Contract',
        ], EmploymentType::CONTRACT->toArray());
    }
}

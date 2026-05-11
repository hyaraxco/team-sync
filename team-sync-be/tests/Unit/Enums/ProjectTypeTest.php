<?php

namespace Tests\Unit\Enums;

use App\Enums\ProjectType;
use PHPUnit\Framework\TestCase;

class ProjectTypeTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('Web Development', ProjectType::WEB_DEVELOPMENT->label());
        $this->assertSame('Mobile App', ProjectType::MOBILE_APP->label());
        $this->assertSame('Design', ProjectType::DESIGN->label());
        $this->assertSame('Marketing', ProjectType::MARKETING->label());
        $this->assertSame('Research', ProjectType::RESEARCH->label());
        $this->assertSame('Infrastructure', ProjectType::INFRASTRUCTURE->label());
    }

    public function test_to_array_returns_value_and_label(): void
    {
        $this->assertSame([
            'value' => 'mobile_app',
            'label' => 'Mobile App',
        ], ProjectType::MOBILE_APP->toArray());
    }
}

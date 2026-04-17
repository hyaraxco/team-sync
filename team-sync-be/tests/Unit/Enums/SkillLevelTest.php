<?php

namespace Tests\Unit\Enums;

use App\Enums\SkillLevel;
use PHPUnit\Framework\TestCase;

class SkillLevelTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('Beginner', SkillLevel::BEGINNER->label());
        $this->assertSame('Intermediate', SkillLevel::INTERMEDIATE->label());
        $this->assertSame('Advanced', SkillLevel::ADVANCED->label());
        $this->assertSame('Expert', SkillLevel::EXPERT->label());
    }

    public function test_to_array_returns_value_and_label(): void
    {
        $this->assertSame([
            'value' => 'expert',
            'label' => 'Expert',
        ], SkillLevel::EXPERT->toArray());
    }
}

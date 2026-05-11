<?php

namespace Tests\Unit\Enums;

use App\Enums\PtkpStatus;
use PHPUnit\Framework\TestCase;

class PtkpStatusTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('TK/0 - Tidak Kawin, Tanpa Tanggungan', PtkpStatus::TK_0->label());
        $this->assertSame('TK/1 - Tidak Kawin, 1 Tanggungan', PtkpStatus::TK_1->label());
        $this->assertSame('TK/2 - Tidak Kawin, 2 Tanggungan', PtkpStatus::TK_2->label());
        $this->assertSame('TK/3 - Tidak Kawin, 3 Tanggungan', PtkpStatus::TK_3->label());
        $this->assertSame('K/0 - Kawin, Tanpa Tanggungan', PtkpStatus::K_0->label());
        $this->assertSame('K/1 - Kawin, 1 Tanggungan', PtkpStatus::K_1->label());
        $this->assertSame('K/2 - Kawin, 2 Tanggungan', PtkpStatus::K_2->label());
        $this->assertSame('K/3 - Kawin, 3 Tanggungan', PtkpStatus::K_3->label());
        $this->assertSame('K/I/0 - Kawin, Penghasilan Istri Digabung, Tanpa Tanggungan', PtkpStatus::K_I_0->label());
        $this->assertSame('K/I/1 - Kawin, Penghasilan Istri Digabung, 1 Tanggungan', PtkpStatus::K_I_1->label());
        $this->assertSame('K/I/2 - Kawin, Penghasilan Istri Digabung, 2 Tanggungan', PtkpStatus::K_I_2->label());
        $this->assertSame('K/I/3 - Kawin, Penghasilan Istri Digabung, 3 Tanggungan', PtkpStatus::K_I_3->label());
    }

    public function test_to_array_returns_value_and_label(): void
    {
        $this->assertSame([
            'value' => 'TK/0',
            'label' => 'TK/0 - Tidak Kawin, Tanpa Tanggungan',
        ], PtkpStatus::TK_0->toArray());

        $this->assertSame([
            'value' => 'K/I/3',
            'label' => 'K/I/3 - Kawin, Penghasilan Istri Digabung, 3 Tanggungan',
        ], PtkpStatus::K_I_3->toArray());
    }
}

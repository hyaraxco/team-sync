<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BpjsRiskClassMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_creates_all_five_jkk_classes_on_fresh_database(): void
    {
        $rows = DB::table('bpjs_rates')->where('component', 'jkk')->get();

        $this->assertCount(5, $rows);
        $expected = [1 => 0.24, 2 => 0.54, 3 => 0.89, 4 => 1.27, 5 => 1.74];
        foreach ($rows as $row) {
            $this->assertArrayHasKey($row->risk_class, $expected);
            $this->assertEquals($expected[$row->risk_class], (float) $row->employer_rate);
            $this->assertEquals(0, (float) $row->employee_rate);
            $this->assertNull($row->max_salary_base);
        }
    }

    public function test_migration_converts_legacy_class_i_row(): void
    {
        // Simulate legacy state: single JKK row with original seeder description
        DB::table('bpjs_rates')->where('component', 'jkk')->delete();
        Schema::table('bpjs_rates', function ($table) {
            $table->dropUnique(['component', 'risk_class', 'effective_date']);
        });
        Schema::table('bpjs_rates', function ($table) {
            $table->dropColumn('risk_class');
            $table->unique(['component', 'effective_date']);
        });

        DB::table('bpjs_rates')->insert([
            'component' => 'jkk', 'employee_rate' => 0, 'employer_rate' => 0.24,
            'max_salary_base' => null, 'effective_date' => '2020-01-01', 'valid_until' => null,
            'description' => 'Jaminan Kecelakaan Kerja (Risk Level I)',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('migrations')->where('migration', 'like', '%add_risk_class%')->delete();

        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_11_25_100100_add_risk_class_to_bpjs_rates.php',
            '--realpath' => false,
        ])->assertSuccessful();

        $rows = DB::table('bpjs_rates')->where('component', 'jkk')
            ->orderBy('risk_class')->get();
        $this->assertCount(5, $rows);
        // Legacy row converted to class 1, keeps original description
        $class1 = $rows->firstWhere('risk_class', 1);
        $this->assertNotNull($class1);
        $this->assertEquals(0.24, (float) $class1->employer_rate);
        $this->assertSame('Jaminan Kecelakaan Kerja (Risk Level I)', $class1->description);
    }

    public function test_migration_rollback_restores_single_jkk_row(): void
    {
        $beforeCount = DB::table('bpjs_rates')->where('component', '!=', 'jkk')->count();

        $this->artisan('migrate:rollback', [
            '--path' => 'database/migrations/2026_11_25_100100_add_risk_class_to_bpjs_rates.php',
            '--realpath' => false,
        ])->assertSuccessful();

        $this->assertFalse(Schema::hasColumn('bpjs_rates', 'risk_class'));
        $jkk = DB::table('bpjs_rates')->where('component', 'jkk')->get();
        $this->assertCount(1, $jkk);
        $this->assertEquals(0.24, (float) $jkk->first()->employer_rate);

        // Non-JKK rows untouched
        $afterCount = DB::table('bpjs_rates')->where('component', '!=', 'jkk')->count();
        $this->assertSame($beforeCount, $afterCount);
    }

    public function test_rollback_then_migrate_roundtrip(): void
    {
        $path = 'database/migrations/2026_11_25_100100_add_risk_class_to_bpjs_rates.php';

        $this->artisan('migrate:rollback', ['--path' => $path, '--realpath' => false])
            ->assertSuccessful();
        $this->artisan('migrate', ['--path' => $path, '--realpath' => false])
            ->assertSuccessful();

        $rows = DB::table('bpjs_rates')->where('component', 'jkk')->get();
        $this->assertCount(5, $rows);
        $this->assertTrue(Schema::hasColumn('bpjs_rates', 'risk_class'));
    }
}

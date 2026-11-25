<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const START = '2020-01-01';

    private const STANDARD_JKK = [1 => 0.24, 2 => 0.54, 3 => 0.89, 4 => 1.27, 5 => 1.74];

    private const LEGACY_CLASS_I = 'Jaminan Kecelakaan Kerja (Risk Level I)';

    public function up(): void
    {
        Schema::table('bpjs_rates', function (Blueprint $table) {
            $table->unsignedTinyInteger('risk_class')->default(0);
            $table->dropUnique(['component', 'effective_date']);
            $table->unique(['component', 'risk_class', 'effective_date']);
        });

        DB::transaction(function () {
            $legacy = $this->standardRow(0, 0.24, self::LEGACY_CLASS_I)->update(['risk_class' => 1]);
            $now = now();
            foreach (self::STANDARD_JKK as $class => $employerRate) {
                if ($class === 1 && $legacy) {
                    continue;
                }
                DB::table('bpjs_rates')->insert([
                    'component' => 'jkk', 'risk_class' => $class, 'employee_rate' => 0,
                    'employer_rate' => $employerRate, 'max_salary_base' => null,
                    'effective_date' => self::START, 'valid_until' => null,
                    'description' => "Jaminan Kecelakaan Kerja (Risk Class {$class})",
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $legacyExists = DB::table('bpjs_rates')->where('component', 'jkk')->where('risk_class', 0)
                ->whereDate('effective_date', self::START)->exists();
            $this->standardRow(1, 0.24, self::LEGACY_CLASS_I)->update(['risk_class' => 0]);
            $standardClassOne = $this->standardRow(1, 0.24, 'Jaminan Kecelakaan Kerja (Risk Class 1)');
            if ($legacyExists) {
                $standardClassOne->delete();
            } else {
                $standardClassOne->update(['risk_class' => 0, 'description' => self::LEGACY_CLASS_I]);
            }
            foreach (array_diff_key(self::STANDARD_JKK, [1 => true]) as $class => $employerRate) {
                $this->standardRow($class, $employerRate, "Jaminan Kecelakaan Kerja (Risk Class {$class})")->delete();
            }
            if (DB::table('bpjs_rates')->where('risk_class', '>', 0)->exists()) {
                throw new RuntimeException('Cannot roll back BPJS risk classes while configured classified rates exist.');
            }
        });
        Schema::table('bpjs_rates', function (Blueprint $table) {
            $table->dropUnique(['component', 'risk_class', 'effective_date']);
            $table->dropColumn('risk_class');
            $table->unique(['component', 'effective_date']);
        });
    }

    private function standardRow(int $class, float $employerRate, string $description): \Illuminate\Database\Query\Builder
    {
        return DB::table('bpjs_rates')->where('component', 'jkk')->where('risk_class', $class)
            ->whereDate('effective_date', self::START)->whereNull('valid_until')->whereNull('max_salary_base')
            ->where('employee_rate', 0)->where('employer_rate', $employerRate)->where('description', $description);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildSqliteTable([
                'workforce',
                'attendance',
                'leave',
                'payroll',
                'project',
                'performance',
            ]);

            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE analytics_snapshots MODIFY COLUMN metric_type ENUM('workforce', 'attendance', 'leave', 'payroll', 'project', 'performance') NOT NULL");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildSqliteTable([
                'workforce',
                'attendance',
                'leave',
                'payroll',
                'project',
            ], true);

            return;
        }

        if ($driver === 'mysql') {
            DB::table('analytics_snapshots')
                ->where('metric_type', 'performance')
                ->update(['metric_type' => 'project']);

            DB::statement("ALTER TABLE analytics_snapshots MODIFY COLUMN metric_type ENUM('workforce', 'attendance', 'leave', 'payroll', 'project') NOT NULL");
        }
    }

    /**
     * @param  array<int, string>  $metricTypes
     */
    private function rebuildSqliteTable(array $metricTypes, bool $mapPerformanceToProject = false): void
    {
        $metricTypeList = "'".implode("', '", $metricTypes)."'";

        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement("CREATE TABLE analytics_snapshots_temp (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            metric_type TEXT NOT NULL CHECK(metric_type IN ({$metricTypeList})),
            metric_name VARCHAR NOT NULL,
            period_type TEXT NOT NULL CHECK(period_type IN ('daily', 'weekly', 'monthly', 'quarterly', 'annual')),
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            value NUMERIC,
            metadata TEXT,
            calculated_at DATETIME NOT NULL,
            created_at DATETIME,
            updated_at DATETIME
        )");

        $metricTypeSelect = $mapPerformanceToProject
            ? "CASE WHEN metric_type = 'performance' THEN 'project' ELSE metric_type END"
            : 'metric_type';

        DB::statement("INSERT INTO analytics_snapshots_temp (id, metric_type, metric_name, period_type, period_start, period_end, value, metadata, calculated_at, created_at, updated_at)
            SELECT id, {$metricTypeSelect}, metric_name, period_type, period_start, period_end, value, metadata, calculated_at, created_at, updated_at
            FROM analytics_snapshots");

        DB::statement('DROP TABLE analytics_snapshots');
        DB::statement('ALTER TABLE analytics_snapshots_temp RENAME TO analytics_snapshots');
        DB::statement('CREATE INDEX idx_analytics_metric_period ON analytics_snapshots (metric_type, metric_name, period_type, period_start)');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};

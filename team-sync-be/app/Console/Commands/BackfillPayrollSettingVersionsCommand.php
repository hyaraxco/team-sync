<?php

namespace App\Console\Commands;

use App\Models\Payroll;
use App\Models\PayrollSetting;
use App\Models\PayrollSettingVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class BackfillPayrollSettingVersionsCommand extends Command
{
    protected $signature = 'payroll-settings:backfill-payroll-versions
        {--dry-run : Preview updates without writing changes}
        {--chunk=200 : Number of payroll rows processed per chunk}';

    protected $description = 'Backfill payroll_setting_version_id for legacy payroll rows that are still null';

    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $isDryRun = (bool) $this->option('dry-run');

        $setting = PayrollSetting::current();
        $activeVersion = $setting->resolveActiveVersion($setting->updated_by);

        /** @var Collection<int, PayrollSettingVersion> $versions */
        $versions = PayrollSettingVersion::query()
            ->where('payroll_setting_id', $setting->id)
            ->orderBy('effective_at')
            ->orderBy('version_number')
            ->get();

        $fallbackVersionId = (int) ($versions->first()?->id ?? $activeVersion->id);

        $totalCandidates = Payroll::query()
            ->whereNull('payroll_setting_version_id')
            ->count();

        if ($totalCandidates === 0) {
            $this->info('No legacy payroll rows require backfill.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s backfill for %d payroll rows (chunk=%d).',
            $isDryRun ? 'Running dry-run' : 'Running',
            $totalCandidates,
            $chunkSize
        ));

        $scanned = 0;
        $updated = 0;
        $countsByVersion = [];

        Payroll::query()
            ->whereNull('payroll_setting_version_id')
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $payrolls) use (
                $versions,
                $fallbackVersionId,
                $isDryRun,
                &$scanned,
                &$updated,
                &$countsByVersion
            ): void {
                foreach ($payrolls as $payroll) {
                    /** @var Payroll $payroll */
                    $scanned++;

                    $targetVersionId = $this->resolveVersionIdForPayroll(
                        $payroll,
                        $versions,
                        $fallbackVersionId
                    );

                    $countsByVersion[$targetVersionId] = ($countsByVersion[$targetVersionId] ?? 0) + 1;

                    if ($isDryRun) {
                        continue;
                    }

                    $payroll->forceFill([
                        'payroll_setting_version_id' => $targetVersionId,
                    ])->save();

                    $updated++;
                }
            });

        $this->line(sprintf('Scanned rows: %d', $scanned));
        $this->line(sprintf('Rows matched: %d', array_sum($countsByVersion)));

        if (! empty($countsByVersion)) {
            ksort($countsByVersion);

            $rows = [];
            foreach ($countsByVersion as $versionId => $count) {
                $rows[] = [(string) $versionId, (string) $count];
            }

            $this->table(['Settings Version ID', 'Payroll Count'], $rows);
        }

        if ($isDryRun) {
            $this->info('Dry-run completed. No data was updated.');

            return self::SUCCESS;
        }

        $remainingNullRows = Payroll::query()
            ->whereNull('payroll_setting_version_id')
            ->count();

        $this->info(sprintf('Backfill completed. Updated rows: %d', $updated));
        $this->line(sprintf('Remaining null rows: %d', $remainingNullRows));

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, PayrollSettingVersion>  $versions
     */
    private function resolveVersionIdForPayroll(Payroll $payroll, Collection $versions, int $fallbackVersionId): int
    {
        $createdAt = $payroll->created_at;

        if (! $createdAt) {
            return $fallbackVersionId;
        }

        /** @var PayrollSettingVersion|null $matchingVersion */
        $matchingVersion = $versions
            ->filter(function (PayrollSettingVersion $version) use ($createdAt): bool {
                return $version->effective_at !== null && $version->effective_at->lte($createdAt);
            })
            ->sortByDesc('effective_at')
            ->first();

        if ($matchingVersion) {
            return (int) $matchingVersion->id;
        }

        return $fallbackVersionId;
    }
}

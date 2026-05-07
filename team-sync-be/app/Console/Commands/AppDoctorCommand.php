<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\LicenseService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AppDoctorCommand extends Command
{
    protected $signature = 'app:doctor';

    protected $description = 'Check self-hosted application readiness and surface blocking configuration issues';

    public function __construct(
        private readonly Application $app,
        private readonly LicenseService $licenseService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $databaseReady = $this->checkDatabaseConnection();

        $checks = [
            ['label' => 'Database connection', 'result' => $databaseReady],
            ['label' => 'Core tables', 'result' => $databaseReady['status'] === 'pass' ? $this->checkCoreTables() : $this->doctorFail('Skipped because database connection is unavailable.')],
            ['label' => 'Company seeded', 'result' => $databaseReady['status'] === 'pass' ? $this->checkCompany() : $this->doctorFail('Skipped because database connection is unavailable.')],
            ['label' => 'License configured', 'result' => $databaseReady['status'] === 'pass' ? $this->checkLicense() : $this->doctorFail('Skipped because database connection is unavailable.')],
            ['label' => 'Queue configuration', 'result' => $this->checkQueue()],
            ['label' => 'Mail configuration', 'result' => $this->checkMail()],
            ['label' => 'Search configuration', 'result' => $this->checkSearch()],
        ];

        $blockingFailures = 0;

        foreach ($checks as $check) {
            $status = $check['result']['status'];
            $message = $check['result']['message'];

            if ($status === 'pass') {
                $this->info("[PASS] {$check['label']}: {$message}");

                continue;
            }

            if ($status === 'warn') {
                $this->warn("[WARN] {$check['label']}: {$message}");

                continue;
            }

            $blockingFailures++;
            $this->error("[FAIL] {$check['label']}: {$message}");
        }

        $this->newLine();

        if ($blockingFailures > 0) {
            $this->error("Application doctor found {$blockingFailures} blocking issue(s).");

            return self::FAILURE;
        }

        $this->info('Application doctor completed without blocking issues.');

        return self::SUCCESS;
    }

    private function checkDatabaseConnection(): array
    {
        try {
            DB::connection()->getPdo();

            return $this->doctorPass('Database connection is available.');
        } catch (Throwable $exception) {
            return $this->doctorFail('Unable to connect to the configured database.');
        }
    }

    private function checkCoreTables(): array
    {
        $requiredTables = ['users', 'companies', 'licenses', 'jobs'];
        $missingTables = [];

        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                $missingTables[] = $table;
            }
        }

        if ($missingTables !== []) {
            return $this->doctorFail('Missing required tables: '.implode(', ', $missingTables).'. Run migrations first.');
        }

        return $this->doctorPass('Required tables are present.');
    }

    private function checkCompany(): array
    {
        if (! Schema::hasTable('companies')) {
            return $this->doctorFail('Companies table is missing.');
        }

        if (Company::query()->doesntExist()) {
            return $this->doctorFail('No company record found. Seed or create the default company first.');
        }

        return $this->doctorPass('Default company record is present.');
    }

    private function checkLicense(): array
    {
        if (! Schema::hasTable('licenses')) {
            return $this->doctorFail('Licenses table is missing.');
        }

        $activeLicense = $this->licenseService->getActive();

        if ($activeLicense === null) {
            return $this->doctorFail('No active valid license found. Activate a license before enabling production use.');
        }

        return $this->doctorPass('Active license found for '.$activeLicense->company_name.'.');
    }

    private function checkQueue(): array
    {
        $defaultQueue = (string) config('queue.default');

        if ($defaultQueue === 'database' && ! Schema::hasTable((string) config('queue.connections.database.table', 'jobs'))) {
            return $this->doctorFail('Queue is configured for database but jobs table is missing.');
        }

        if ($defaultQueue === 'sync') {
            return $this->doctorWarn('Queue uses sync driver. Background notifications will run inline.');
        }

        return $this->doctorPass("Queue driver '{$defaultQueue}' is configured.");
    }

    private function checkMail(): array
    {
        $mailer = (string) config('mail.default');

        if ($mailer === 'log' || $mailer === 'array') {
            return $this->doctorWarn("Mail driver '{$mailer}' is configured. Real email delivery is disabled.");
        }

        return $this->doctorPass("Mail driver '{$mailer}' is configured.");
    }

    private function checkSearch(): array
    {
        $driver = (string) config('scout.driver');

        if ($driver === 'meilisearch') {
            $host = (string) config('scout.meilisearch.host');

            if ($host === '') {
                return $this->doctorFail('Scout driver is meilisearch but host is not configured.');
            }

            return $this->doctorWarn('Scout uses Meilisearch. Ensure the search service is reachable before indexing.');
        }

        if ($driver === 'null') {
            return $this->doctorWarn('Scout driver is null. Search indexing is disabled.');
        }

        return $this->doctorPass("Scout driver '{$driver}' is configured.");
    }

    private function doctorPass(string $message): array
    {
        return ['status' => 'pass', 'message' => $message];
    }

    private function doctorWarn(string $message): array
    {
        return ['status' => 'warn', 'message' => $message];
    }

    private function doctorFail(string $message): array
    {
        return ['status' => 'fail', 'message' => $message];
    }
}

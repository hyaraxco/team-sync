<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\SetupBootstrapRequest;
use App\Models\Company;
use App\Models\License;
use App\Models\User;
use App\Services\LicenseService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SetupController extends Controller
{
    public function __construct(
        private readonly LicenseService $licenseService
    ) {}

    /**
     * Check if the application needs initial setup.
     * Public endpoint — no auth required.
     */
    public function status()
    {
        $hasLicense = License::query()->valid()->exists();
        $hasCompany = Schema::hasTable('companies') && Company::query()->exists();
        $hasSuperadmin = User::role('superadmin')->exists();

        $needsSetup = ! $hasLicense || ! $hasCompany || ! $hasSuperadmin;

        return ResponseHelper::jsonResponse(true, 'Setup status retrieved', [
            'needs_setup' => $needsSetup,
            'has_license' => $hasLicense,
            'has_company' => $hasCompany,
            'has_superadmin' => $hasSuperadmin,
        ]);
    }

    /**
     * Run app:doctor checks and return results as JSON.
     * Public endpoint — no auth required (used during setup wizard).
     */
    public function doctor()
    {
        $checks = [];

        // Database
        try {
            DB::connection()->getPdo();
            $checks[] = ['label' => 'Database connection', 'status' => 'pass', 'message' => 'Database connection is available.'];
        } catch (\Throwable $e) {
            $checks[] = ['label' => 'Database connection', 'status' => 'fail', 'message' => 'Unable to connect to the configured database.'];
        }

        // Core tables
        $requiredTables = ['users', 'companies', 'licenses', 'jobs'];
        $missingTables = array_filter($requiredTables, fn (string $table) => ! Schema::hasTable($table));

        if ($missingTables !== []) {
            $checks[] = ['label' => 'Core tables', 'status' => 'fail', 'message' => 'Missing required tables: '.implode(', ', $missingTables).'. Run migrations first.'];
        } else {
            $checks[] = ['label' => 'Core tables', 'status' => 'pass', 'message' => 'Required tables are present.'];
        }

        // Storage writable
        $storagePath = storage_path('app');
        $checks[] = is_writable($storagePath)
            ? ['label' => 'Storage writable', 'status' => 'pass', 'message' => 'Storage directory is writable.']
            : ['label' => 'Storage writable', 'status' => 'fail', 'message' => 'Storage directory is not writable. Run: chmod -R 775 storage/'];

        // Queue
        $queueDriver = (string) config('queue.default');
        if ($queueDriver === 'sync') {
            $checks[] = ['label' => 'Queue worker', 'status' => 'warn', 'message' => 'Queue uses sync driver. Background notifications will run inline.'];
        } elseif ($queueDriver === 'database' && ! Schema::hasTable((string) config('queue.connections.database.table', 'jobs'))) {
            $checks[] = ['label' => 'Queue worker', 'status' => 'fail', 'message' => 'Queue is configured for database but jobs table is missing.'];
        } else {
            $checks[] = ['label' => 'Queue worker', 'status' => 'pass', 'message' => "Queue driver '{$queueDriver}' is configured."];
        }

        // Cache
        try {
            cache()->put('_doctor_probe', true, 5);
            $retrieved = cache()->get('_doctor_probe');
            cache()->forget('_doctor_probe');
            $checks[] = $retrieved
                ? ['label' => 'Cache', 'status' => 'pass', 'message' => 'Cache is working.']
                : ['label' => 'Cache', 'status' => 'warn', 'message' => 'Cache write succeeded but read returned null.'];
        } catch (\Throwable $e) {
            $checks[] = ['label' => 'Cache', 'status' => 'warn', 'message' => 'Cache is not available: '.$e->getMessage()];
        }

        // License
        $activeLicense = $this->licenseService->getActive();
        $checks[] = $activeLicense
            ? ['label' => 'License', 'status' => 'pass', 'message' => 'Active license found for '.$activeLicense->company_name.'.']
            : ['label' => 'License', 'status' => 'info', 'message' => 'No active license found. Upload a license to continue setup.'];

        $hasBlockingFailure = collect($checks)->contains(fn (array $c) => $c['status'] === 'fail');

        return ResponseHelper::jsonResponse(true, 'System health check completed', [
            'healthy' => ! $hasBlockingFailure,
            'checks' => $checks,
        ]);
    }

    /**
     * Bootstrap the application: create superadmin + seed roles.
     * Only allowed when no superadmin exists yet.
     */
    public function bootstrap(SetupBootstrapRequest $request)
    {
        $validated = $request->validated();

        if (User::role('superadmin')->exists()) {
            return ResponseHelper::jsonResponse(false, 'Setup already completed. A superadmin account already exists.', null, 409);
        }

        try {
            DB::beginTransaction();

            // Ensure roles and permissions are seeded
            Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
            Artisan::call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);
            Artisan::call('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(),
            ]);

            $user->assignRole('superadmin');

            // Create token for immediate login
            $token = $user->createToken('setup_token')->plainTextToken;

            DB::commit();

            return ResponseHelper::jsonResponse(true, 'Setup completed successfully. Superadmin account created.', [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'token' => $token,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('SetupController bootstrap error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Setup failed. Please check server logs.', null, 500);
        }
    }
}

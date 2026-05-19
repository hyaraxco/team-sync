<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\PayrollSettingResource;
use App\Http\Resources\PayrollSettingVersionResource;
use App\Interfaces\PayrollRepositoryInterface;
use App\Models\PayrollSetting;
use App\Services\Payroll\PayrollAnalyticsService;
use App\Services\Payroll\TaxCalculationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;

class PayrollSettingController extends Controller implements HasMiddleware
{
    public function __construct(
        private PayrollRepositoryInterface $payrollRepository,
        private TaxCalculationService $taxCalculationService,
        private PayrollAnalyticsService $analyticsService,
    ) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['payroll-statistics']), only: ['show', 'history', 'versionDiff', 'bpjsRateHistory', 'bpjsValidation']),
            new Middleware(PermissionMiddleware::using(['payroll-edit']), only: ['update']),
        ];
    }

    public function show()
    {
        try {
            $setting = PayrollSetting::current();
            $setting->resolveActiveVersion($setting->updated_by);
            $setting->load(['updatedBy', 'latestVersion.updatedBy']);

            return ResponseHelper::jsonResponse(
                true,
                'Payroll Settings Retrieved Successfully',
                new PayrollSettingResource($setting),
                200
            );
        } catch (\Throwable $e) {
            Log::error('PayrollSettingController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function history()
    {
        try {
            $setting = PayrollSetting::current();
            $setting->resolveActiveVersion($setting->updated_by);

            $versions = $setting->versions()
                ->with('updatedBy')
                ->get();

            return ResponseHelper::jsonResponse(
                true,
                'Payroll Settings History Retrieved Successfully',
                PayrollSettingVersionResource::collection($versions),
                200
            );
        } catch (\Throwable $e) {
            Log::error('PayrollSettingController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function versionDiff(int $id)
    {
        try {
            $diff = $this->analyticsService->getSettingVersionDiff($id);

            return ResponseHelper::jsonResponse(
                true,
                'Version Diff Retrieved Successfully',
                $diff,
                200
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Version Not Found', null, 404);
        } catch (\Throwable $e) {
            Log::error('PayrollSettingController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'payday_day' => 'required|integer|min:1|max:31',
            'attendance_cutoff_day' => 'required|integer|min:1|max:31',
            'working_days_mode' => 'required|in:auto_business_days,fixed',
            'default_working_days' => 'required_if:working_days_mode,fixed|nullable|integer|min:1|max:31',
            'absent_deduction_rate' => 'required|numeric|min:0|max:5',
            'rounding_mode' => 'required|in:none,nearest,floor,ceil',
            'rounding_unit' => 'nullable|integer|min:1|max:1000000',
            'note_template' => 'nullable|string|max:2000',
            'payroll_bank_name' => 'nullable|string|max:100',
            'payroll_bank_code' => 'nullable|string|max:10',
        ]);

        try {
            $setting = PayrollSetting::current();

            $setting->fill([
                ...$validated,
                'default_working_days' => $validated['working_days_mode'] === 'fixed'
                    ? $validated['default_working_days']
                    : ($setting->default_working_days ?? PayrollSetting::defaults()['default_working_days']),
                'rounding_unit' => $validated['rounding_mode'] === 'none'
                    ? 1
                    : ($validated['rounding_unit'] ?? $setting->rounding_unit ?? PayrollSetting::defaults()['rounding_unit']),
                'note_template' => filled($validated['note_template'] ?? null)
                    ? trim($validated['note_template'])
                    : PayrollSetting::DEFAULT_NOTE_TEMPLATE,
                'payroll_bank_name' => filled($validated['payroll_bank_name'] ?? null)
                    ? trim($validated['payroll_bank_name'])
                    : null,
                'payroll_bank_code' => filled($validated['payroll_bank_code'] ?? null)
                    ? strtoupper(trim($validated['payroll_bank_code']))
                    : null,
                'updated_by' => $request->user()->id,
            ]);

            $setting->save();
            $setting->resolveActiveVersion($request->user()?->id);
            $setting->load(['updatedBy', 'latestVersion.updatedBy']);

            return ResponseHelper::jsonResponse(
                true,
                'Payroll Settings Updated Successfully',
                new PayrollSettingResource($setting),
                200
            );
        } catch (\Throwable $e) {
            Log::error('PayrollSettingController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function bpjsRateHistory()
    {
        try {
            $history = $this->payrollRepository->getBpjsRateHistory();

            return ResponseHelper::jsonResponse(
                true,
                'BPJS Rate History Retrieved Successfully',
                $history,
                200
            );
        } catch (\Throwable $e) {
            Log::error('PayrollSettingController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function bpjsValidation()
    {
        try {
            $validation = $this->taxCalculationService->validateBpjsRates();

            return ResponseHelper::jsonResponse(
                true,
                'BPJS Validation Retrieved Successfully',
                $validation,
                200
            );
        } catch (\Throwable $e) {
            Log::error('PayrollSettingController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}

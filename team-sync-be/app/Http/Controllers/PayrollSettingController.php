<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\PayrollSettingResource;
use App\Http\Resources\PayrollSettingVersionResource;
use App\Models\PayrollSetting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class PayrollSettingController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['payroll-statistics']), only: ['show', 'history', 'update']),
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
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
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
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
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
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}

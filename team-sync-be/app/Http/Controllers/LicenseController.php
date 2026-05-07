<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\License\LicenseActivateRequest;
use App\Http\Requests\License\LicenseUpdateRequest;
use App\Http\Requests\License\LicenseVerifyRequest;
use App\Http\Resources\LicenseResource;
use App\Services\LicenseService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;

class LicenseController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly LicenseService $licenseService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware(PermissionMiddleware::using('license-view'), only: ['index', 'show', 'current']),
            new Middleware(PermissionMiddleware::using('license-manage'), only: ['store', 'update', 'destroy', 'verify']),
        ];
    }

    public function index(): JsonResponse
    {
        $licenses = $this->licenseService->getAll();

        return ResponseHelper::jsonResponse(true, 'Licenses retrieved successfully', LicenseResource::collection($licenses));
    }

    public function store(LicenseActivateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $license = $this->licenseService->activateLicense(
                $validated['license_key'],
                [
                    'company_name' => $validated['company_name'] ?? null,
                    'contact_email' => $validated['contact_email'] ?? null,
                ]
            );

            return ResponseHelper::jsonResponse(true, 'License activated successfully', new LicenseResource($license), 201);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            Log::error('LicenseController store error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            return ResponseHelper::jsonResponse(true, 'License retrieved successfully', new LicenseResource($this->licenseService->getById($id)));
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'License not found', null, 404);
        }
    }

    public function current(): JsonResponse
    {
        $license = $this->licenseService->getActive();

        if ($license === null) {
            return ResponseHelper::jsonResponse(false, 'No active license found', null, 404);
        }

        return ResponseHelper::jsonResponse(true, 'Active license retrieved successfully', new LicenseResource($license));
    }

    public function update(LicenseUpdateRequest $request, int $id): JsonResponse
    {
        try {
            $license = $this->licenseService->updateLicense($id, $request->validated());

            return ResponseHelper::jsonResponse(true, 'License updated successfully', new LicenseResource($license));
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'License not found', null, 404);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 422);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->licenseService->deactivateLicense($id);

            return ResponseHelper::jsonResponse(true, 'License deactivated successfully');
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'License not found', null, 404);
        }
    }

    public function verify(LicenseVerifyRequest $request): JsonResponse
    {
        try {
            $payload = $this->licenseService->verifyLicenseKey($request->validated('license_key'));

            return ResponseHelper::jsonResponse(true, 'License verified successfully', $payload);
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            Log::error('LicenseController verify error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}

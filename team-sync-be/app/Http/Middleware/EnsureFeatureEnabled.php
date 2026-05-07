<?php

namespace App\Http\Middleware;

use App\Helpers\ResponseHelper;
use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    public function __construct(
        private readonly LicenseService $licenseService
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! $this->licenseService->isFeatureEnabled($feature)) {
            return ResponseHelper::jsonResponse(
                false,
                "Feature '{$feature}' is not enabled for the active license.",
                ['feature' => $feature],
                403
            );
        }

        return $next($request);
    }
}

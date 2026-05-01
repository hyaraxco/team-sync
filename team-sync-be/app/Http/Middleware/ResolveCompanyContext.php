<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveCompanyContext
{
    /**
     * Resolve the current company from the authenticated user's company_id.
     * Stores the company in the container as 'current_company'.
     *
     * NOTE: This middleware does NOT enforce scoping — it only makes the
     * company context available for future use.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->company_id) {
            $company = Company::find($user->company_id);

            if ($company) {
                app()->instance('current_company', $company);
            }
        }

        return $next($request);
    }
}

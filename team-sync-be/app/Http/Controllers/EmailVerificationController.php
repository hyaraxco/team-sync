<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\SendEmailVerificationRequest;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class EmailVerificationController extends Controller
{
    public function sendVerificationEmail(SendEmailVerificationRequest $request)
    {
        $request->validated();

        $user = $request->user();

        if (! $user && $request->filled('email')) {
            $user = User::where('email', $request->string('email'))->first();
        }

        if (! $user) {
            return ResponseHelper::jsonResponse(false, 'User not found.', null, 404);
        }

        if ($user->hasVerifiedEmail()) {
            return ResponseHelper::jsonResponse(true, 'Email already verified.', null, 200);
        }

        $user->sendEmailVerificationNotification();

        return ResponseHelper::jsonResponse(true, 'Verification link sent successfully.', null, 200);
    }

    public function verify(Request $request, int $id, string $hash)
    {
        if (! URL::hasValidSignature($request)) {
            return redirect()->away(config('app.frontend_url', 'http://localhost:5173').'/auth/verify-email?status=invalid');
        }

        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return redirect()->away(config('app.frontend_url', 'http://localhost:5173').'/auth/verify-email?status=invalid');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->away(config('app.frontend_url', 'http://localhost:5173').'/auth/verify-email?status=success');
    }
}

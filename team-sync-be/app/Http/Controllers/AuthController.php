<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\LoginStoreRequest;
use App\Http\Resources\UserResource;
use App\Interfaces\AuthRepositoryInterface;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    private AuthRepositoryInterface $authRepository;

    public function __construct(AuthRepositoryInterface $authRepository)
    {
        $this->authRepository = $authRepository;
    }

    public function login(LoginStoreRequest $request)
    {
        $request = $request->validated();

        try {
            $user = $this->authRepository->login($request);

            return ResponseHelper::jsonResponse(true, 'Login Successful', new UserResource($user), 200);
        } catch (\Exception $e) {
            $status = $e->getCode() ?: 500;

            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, $status);
        }
    }

    public function me()
    {
        try {
            $user = $this->authRepository->me();

            return ResponseHelper::jsonResponse(true, 'Profile Retrieved Successfully', new UserResource($user), 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AuthController::me error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function logout()
    {
        try {
            $user = $this->authRepository->logout();

            return ResponseHelper::jsonResponse(true, 'Logout Successful', new UserResource($user), 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AuthController::logout error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'nullable|string|min:8|confirmed',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        try {
            $user = $this->authRepository->updateProfile([
                'name' => $request->input('name'),
                'password' => $request->input('password'),
                'profile_photo' => $request->file('profile_photo'),
            ]);

            return ResponseHelper::jsonResponse(true, 'Profile Updated Successfully', new UserResource($user), 200);
        } catch (\Exception $e) {
            $status = $e->getCode() ?: 500;
            $message = $status < 500 ? $e->getMessage() : 'Internal Server Error';

            if ($status >= 500) {
                \Illuminate\Support\Facades\Log::error('AuthController::updateProfile error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            }

            return ResponseHelper::jsonResponse(false, $message, null, $status);
        }
    }
}

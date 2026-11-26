<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\AuthProfileUpdateRequest;
use App\Http\Requests\LoginStoreRequest;
use App\Http\Resources\UserResource;
use App\Interfaces\AuthRepositoryInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        } catch (AuthenticationException $e) {
            return ResponseHelper::jsonResponse(false, 'Unauthorized', null, 401);
        } catch (Throwable $e) {
            Log::error('AuthController::login error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function me()
    {
        try {
            $user = $this->authRepository->me();

            return ResponseHelper::jsonResponse(true, 'Profile Retrieved Successfully', new UserResource($user), 200);
        } catch (AuthenticationException $e) {
            return ResponseHelper::jsonResponse(false, 'Unauthorized', null, 401);
        } catch (Throwable $e) {
            Log::error('AuthController::me error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function logout()
    {
        try {
            $user = $this->authRepository->logout();

            return ResponseHelper::jsonResponse(true, 'Logout Successful', new UserResource($user), 200);
        } catch (AuthenticationException $e) {
            return ResponseHelper::jsonResponse(false, 'Unauthorized', null, 401);
        } catch (Throwable $e) {
            Log::error('AuthController::logout error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function updateProfile(AuthProfileUpdateRequest $request)
    {
        $validated = $request->validated();

        try {
            $validated['profile_photo'] = $request->file('profile_photo');

            $user = $this->authRepository->updateProfile($validated);

            return ResponseHelper::jsonResponse(true, 'Profile Updated Successfully', new UserResource($user), 200);
        } catch (AuthenticationException $e) {
            return ResponseHelper::jsonResponse(false, 'Unauthorized', null, 401);
        } catch (Throwable $e) {
            Log::error('AuthController::updateProfile error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}

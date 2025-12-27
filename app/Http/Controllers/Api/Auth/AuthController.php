<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseController
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        $tokenResult = $user->createToken('auth_token');

        $token = PersonalAccessToken::where('tokenable_id', $user->id)
            ->where('tokenable_type', $user->getMorphClass())
            ->latest('created_at')
            ->first();

        if ($token) {
            $token->update(['tenant_id' => $user->tenant_id]);
        }

        return $this->created([
            'user' => $user,
            'token' => $tokenResult->plainTextToken,
        ], 'Registration successful');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->tenant->is_active) {
            return $this->forbidden('Tenant is inactive');
        }

        $tokenResult = $user->createToken('auth_token');

        $token = PersonalAccessToken::where('tokenable_id', $user->id)
            ->where('tokenable_type', $user->getMorphClass())
            ->latest('created_at')
            ->first();

        if ($token) {
            $token->update(['tenant_id' => $user->tenant_id]);
        }

        return $this->success([
            'user' => $user,
            'token' => $tokenResult->plainTextToken,
        ], 'Login successful');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success($request->user()->load('tenant'));
    }
}

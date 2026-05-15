<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DriverAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            throw ValidationException::withMessages([
                'email' => ['This account is not registered as a driver.'],
            ]);
        }

        if (!$driver->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your driver account has been deactivated.'],
            ]);
        }

        // Revoke old driver tokens
        $user->tokens()->where('name', 'driver-app')->delete();
        $token = $user->createToken('driver-app', ['role:driver'])->plainTextToken;

        return response()->json([
            'user'   => $user,
            'driver' => $driver,
            'token'  => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request): JsonResponse
    {
        $user   = $request->user();
        $driver = Driver::where('user_id', $user->id)->first();

        return response()->json([
            'user'   => $user,
            'driver' => $driver,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'required|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Create linked customer profile
        Customer::create([
            'name'  => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
        ]);

        $token = $user->createToken('customer-app', ['role:customer'])->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user  = $request->user() ?? User::where('email', $request->email)->first();
        $user  = User::where('email', $request->email)->first();

        // Revoke old tokens for this device name
        $user->tokens()->where('name', 'customer-app')->delete();
        $token = $user->createToken('customer-app', ['role:customer'])->plainTextToken;

        $customer = Customer::where('email', $user->email)->first();

        return response()->json([
            'user'     => $user,
            'customer' => $customer,
            'token'    => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request): JsonResponse
    {
        $user     = $request->user();
        $customer = Customer::where('email', $user->email)->first();

        return response()->json([
            'user'     => $user,
            'customer' => $customer,
        ]);
    }
}

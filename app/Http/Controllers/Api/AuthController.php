<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'data' => [
                'user'  => $user,
                'token' => $token,
                'type'  => 'Bearer',
            ],
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Opcional: revocar tokens anteriores
        $user->tokens()->delete();

        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'data' => [
                'user'  => $user,
                'token' => $token,
                'type'  => 'Bearer',
            ],
        ]);
    }

    public function me(Request $request)
    {
        $token = $request->bearerToken();

        if (! $token || ! \Laravel\Sanctum\PersonalAccessToken::findToken($token)) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'data' => [
                'user' => $request->user(),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $token = $request->bearerToken();

        // Revoke current token (and any remaining tokens as safety) so it cannot be reused.
        $user->tokens()->delete();

        if ($token) {
            \Laravel\Sanctum\PersonalAccessToken::findToken($token)?->delete();
        }

        return response()->json([
            'data' => [
                'message' => 'Logged out',
            ],
        ]);
    }

    public function refresh(Request $request)
    {
        $token = $request->bearerToken();

        if (! $token || ! $request->user()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $user = $request->user();

        // Revoca el token actual
        if ($current = $request->user()->currentAccessToken()) {
            $current->delete();
        }

        \Laravel\Sanctum\PersonalAccessToken::findToken($token)?->delete();

        // Crea un nuevo token
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'data' => [
                'user'  => $user,
                'token' => $token,
                'type'  => 'Bearer',
            ],
        ]);
    }
}

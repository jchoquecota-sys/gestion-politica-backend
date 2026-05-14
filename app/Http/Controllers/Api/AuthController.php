<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Authenticate a user and issue an API token.
     *
     * Returns the plain-text token along with the user's basic profile
     * and their flattened permission list, ready to be consumed by any
     * API client (Next.js, mobile app, etc.).
     *
     * @throws ValidationException
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
        ]);

        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'              => $user->id,
                'name'            => $user->name,
                'email'           => $user->email,
                'roles'           => $user->getRoleNames(),
                'permissions'     => $user->getAllPermissions()->pluck('name'),
                'allowed_sectors' => $user->getAllowedSectorIds(),
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * Authenticate a user and issue an API token.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json(
                ['message' => 'Credenciales inválidas.'],
                JsonResponse::HTTP_UNAUTHORIZED
            );
        }

        /** @var User $user */
        $user = User::where('email', $request->email)->firstOrFail();

        // Revoke all previous tokens to enforce single-session behaviour.
        // Remove this line if you want to allow multiple simultaneous sessions.
        $user->tokens()->delete();

        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'              => $user->id,
                'name'            => $user->name,
                'email'           => $user->email,
                'roles'           => $user->getRoleNames(),
                'permissions'     => $user->getAllPermissions()->pluck('name'),
                'allowed_sectors' => $user->getAllowedSectorIds(),
            ],
        ], JsonResponse::HTTP_OK);
    }

    /**
     * Revoke the current access token (logout).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(
            ['message' => 'Sesión cerrada correctamente.'],
            JsonResponse::HTTP_OK
        );
    }

    /**
     * Return the authenticated user's profile and permissions.
     *
     * Useful for the frontend to refresh its local state without
     * issuing a new token.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => [
                'id'              => $user->id,
                'name'            => $user->name,
                'email'           => $user->email,
                'roles'           => $user->getRoleNames(),
                'permissions'     => $user->getAllPermissions()->pluck('name'),
                'allowed_sectors' => $user->getAllowedSectorIds(),
            ],
        ], JsonResponse::HTTP_OK);
    }
}

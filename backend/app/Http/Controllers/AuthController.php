<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    /** Intercambia email y contraseña por un token JWT. */
    public function login(LoginRequest $request): JsonResponse
    {
        $token = auth()->attempt($request->validated());

        if (! $token) {
            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        return $this->respondWithToken($token);
    }

    /** Usuario autenticado, con sus roles y permisos. */
    public function me(): UserResource
    {
        return new UserResource(auth()->user());
    }

    /** Invalida el token actual (lo agrega a la lista negra hasta que expire). */
    public function logout(): JsonResponse
    {
        auth()->logout();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    /**
     * Emite un token nuevo e invalida el anterior. Acepta un token ya expirado
     * mientras siga dentro de la ventana de refresco (JWT_REFRESH_TTL).
     */
    public function refresh(): JsonResponse
    {
        try {
            return $this->respondWithToken(auth()->refresh());
        } catch (JWTException) {
            return response()->json(['message' => 'No se pudo renovar el token.'], 401);
        }
    }

    private function respondWithToken(string $token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
        ]);
    }
}

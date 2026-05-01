<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profesional;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Controlador de autenticación.
 * Gestiona el registro, inicio de sesión y cierre de sesión
 * usando tokens de Sanctum (Bearer Token).
 */
class AuthController extends Controller
{
    /**
     * POST /api/auth/registrar
     * Registra un nuevo usuario (cliente o profesional).
     * Si el rol es profesional, crea también su perfil profesional vacío.
     */
    public function registrar(Request $request): JsonResponse
    {
        $validados = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users',
            'password'  => ['required', 'confirmed', Password::min(8)],
            'rol'       => 'required|in:cliente,profesional',
            'telefono'  => 'nullable|string|max:20',
        ]);

        $usuario = User::create([
            'name'     => $validados['name'],
            'email'    => $validados['email'],
            'password' => Hash::make($validados['password']),
            'rol'      => $validados['rol'],
            'telefono' => $validados['telefono'] ?? null,
        ]);

        if ($usuario->esProfesional()) {
            Profesional::create(['user_id' => $usuario->id]);
        }

        $token = $usuario->createToken('token_acceso')->plainTextToken;

        return response()->json([
            'usuario' => $usuario,
            'token'   => $token,
        ], 201);
    }

    /**
     * POST /api/auth/iniciar-sesion
     * Verifica credenciales y retorna un token de acceso.
     */
    public function iniciarSesion(Request $request): JsonResponse
    {
        $validados = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $usuario = User::where('email', $validados['email'])->first();

        if (!$usuario || !Hash::check($validados['password'], $usuario->password)) {
            return response()->json(['error' => 'Credenciales incorrectas.'], 401);
        }

        $token = $usuario->createToken('token_acceso')->plainTextToken;

        return response()->json([
            'usuario' => $usuario,
            'token'   => $token,
        ]);
    }

    /**
     * POST /api/auth/cerrar-sesion
     * Revoca el token actual del usuario autenticado.
     */
    public function cerrarSesion(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['mensaje' => 'Sesión cerrada correctamente.']);
    }

    /**
     * GET /api/auth/perfil
     * Retorna los datos del usuario autenticado con su perfil profesional si aplica.
     */
    public function perfil(Request $request): JsonResponse
    {
        $usuario = $request->user()->load('profesional');

        return response()->json($usuario);
    }
}

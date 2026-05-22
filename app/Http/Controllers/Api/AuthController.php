<?php

namespace App\Http\Controllers\Api;

use App\Mail\WelcomeMail;
use App\Http\Controllers\Controller;
use App\Models\Profesional;
use App\Models\User;
use App\Services\AtlasLogService;
use Illuminate\Validation\ValidationException;
use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

/**
 * Controlador de autenticación.
 * Gestiona el registro, inicio de sesión, cierre de sesión y perfil
 * usando tokens de Sanctum (Bearer Token).
 */
class AuthController extends Controller
{
    public function __construct(private readonly AtlasLogService $atlasLogService)
    {
    }
    /**
     * POST /api/auth/registrar
     * Registra un nuevo usuario (cliente o profesional).
     * Si el rol es profesional, crea también su perfil profesional vacío.
     */
    public function registrar(Request $request): JsonResponse
    {
        try {
            $validados = $request->validate([
                'name'      => 'required|string|max:255',
                'email'     => 'required|email|unique:users',
                'password'  => ['required', 'confirmed', Password::min(8)],
                'rol'       => 'required|in:cliente,profesional',
                'telefono'  => ['nullable', 'regex:/^\+?[\d\s\-()+]{6,20}$/'],
            ]);
        } catch (ValidationException $e) {
            $this->atlasLogService->registrarCreacionUsuario($request->input('email'), false, ['errors' => $e->errors()]);
            throw $e;
        }

        try {
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

            try {
                Mail::to($usuario->email)->send(new WelcomeMail($usuario));
                Log::info('Welcome email sent to ' . $usuario->email);
            } catch (Throwable $mailException) {
                Log::error('Welcome email failed for ' . $usuario->email . ': ' . $mailException->getMessage(), [
                    'exception' => get_class($mailException),
                    'trace' => $mailException->getTraceAsString(),
                ]);
                $this->atlasLogService->registrarError($mailException, [
                    'route' => 'api/auth/registrar',
                    'user_email' => $usuario->email,
                    'mail_action' => 'welcome_email',
                ]);
            }

            $this->atlasLogService->registrarCreacionUsuario($validados['email'], true, ['user_id' => $usuario->id, 'rol' => $usuario->rol]);

            $token = $usuario->createToken('token_acceso')->plainTextToken;

            return response()->json([
                'usuario' => $usuario->load('profesional'),
                'token'   => $token,
            ], 201);
        } catch (Throwable $e) {
            $this->atlasLogService->registrarCreacionUsuario($request->input('email'), false, ['exception' => $e->getMessage()]);
            throw $e;
        }
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

        if (!$usuario->activo) {
            return response()->json(['error' => 'Tu cuenta está desactivada. Contactá al administrador.'], 403);
        }

        $token = $usuario->createToken('token_acceso')->plainTextToken;

        return response()->json([
            'usuario' => $usuario->load('profesional'),
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
        return response()->json($request->user()->load('profesional'));
    }

    /**
     * PUT /api/auth/perfil
     * Actualiza los datos personales del usuario autenticado.
     * Acepta multipart/form-data cuando se sube un avatar.
     */
    public function actualizarPerfil(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $validados = $request->validate([
            'name'                 => 'sometimes|string|max:255',
            'telefono'             => ['nullable', 'regex:/^\+?[\d\s\-()+]{6,20}$/'],
            'notificaciones_email' => 'sometimes|boolean',
            'avatar'               => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            $ruta = $request->file('avatar')->store('avatars', 'public');
            $validados['avatar'] = asset('storage/' . $ruta);
        } else {
            unset($validados['avatar']);
        }

        $usuario->update($validados);

        return response()->json([
            'usuario' => $usuario->fresh()->load('profesional'),
            'mensaje' => 'Perfil actualizado correctamente.',
        ]);
    }
}

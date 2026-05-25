<?php

// Archivo de login y autenticación: app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use App\Mail\WelcomeMail;
use App\Http\Controllers\Controller;
use App\Models\Profesional;
use App\Models\User;
use App\Services\AtlasLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Controlador de autenticación.
 * Gestiona el registro, inicio de sesión, cierre de sesión y perfil
 * usando tokens de Sanctum (Bearer Token).
 */
class AuthController extends Controller
{
    private const PROVEEDORES_OAUTH = ['google', 'github', 'facebook'];

    public function __construct(private readonly AtlasLogService $atlasLogService)
    {
    }

    private function proveedorOAuthValido(string $proveedor): bool
    {
        return in_array($proveedor, self::PROVEEDORES_OAUTH, true);
    }

    private function proveedorOAuthConfigurado(string $proveedor): bool
    {
        $configurado = match ($proveedor) {
            'google' => (bool) config('services.google.client_id') && (bool) config('services.google.client_secret'),
            'github' => (bool) config('services.github.client_id') && (bool) config('services.github.client_secret'),
            'facebook' => (bool) config('services.facebook.client_id') && (bool) config('services.facebook.client_secret'),
            default => false,
        };

        if (!$configurado) {
            Log::warning("OAuth {$proveedor} no configurado", [
                'client_id' => config("services.{$proveedor}.client_id") ? 'set' : 'empty',
                'client_secret' => config("services.{$proveedor}.client_secret") ? 'set' : 'empty',
                'env_client_id' => env("GOOGLE_CLIENT_ID") ? 'set' : 'empty',
                'env_client_secret' => env("GOOGLE_CLIENT_SECRET") ? 'set' : 'empty',
            ]);
        }

        return $configurado;
    }

    private function generarStateOAuth(string $provider): string
    {
        $state = bin2hex(random_bytes(16));
        Cache::put("oauth_state_{$state}", [
            'provider' => $provider,
            'created_at' => now()->timestamp,
        ], 3600); // 1 hora
        return $state;
    }

    private function validarStateOAuth(string $state): ?array
    {
        $data = Cache::get("oauth_state_{$state}");
        if (!$data) {
            return null;
        }
        Cache::forget("oauth_state_{$state}");
        return $data;
    }

    private function redirigirAFrontend(array $parametros = []): RedirectResponse
    {
        $frontendUrl = rtrim(config('services.frontend_url', config('app.url')), '/');

        return redirect()->away($frontendUrl . '/auth/iniciar-sesion?' . http_build_query($parametros));
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
            $archivo = $request->file('avatar');
            $mime    = $archivo->getMimeType();
            $base64  = base64_encode(file_get_contents($archivo->getRealPath()));
            $validados['avatar'] = "data:{$mime};base64,{$base64}";
        } else {
            unset($validados['avatar']);
        }

        $usuario->update($validados);

        return response()->json([
            'usuario' => $usuario->fresh()->load('profesional'),
            'mensaje' => 'Perfil actualizado correctamente.',
        ]);
    }

    /**
     * GET /auth/{provider}/redirect
     * Inicia el flujo OAuth con el proveedor configurado.
     */
    public function redirigirOAuth(string $provider)
    {
        abort_unless($this->proveedorOAuthValido($provider), 404);

        if (!$this->proveedorOAuthConfigurado($provider)) {
            return $this->redirigirAFrontend([
                'oauth_error' => 'El inicio de sesión con ' . ucfirst($provider) . ' no está configurado en el servidor.',
            ]);
        }

        return Socialite::driver($provider)->redirect();
    }

    /**
     * GET /auth/{provider}/callback
     * Completa el login social, crea o enlaza el usuario y devuelve un token Sanctum.
     */
    public function manejarCallbackOAuth(string $provider)
    {
        $oauthError = null;
        $socialUsuario = null;
        $usuario = null;

        if (!$this->proveedorOAuthValido($provider)) {
            $oauthError = 'Proveedor OAuth no soportado.';
        }

        if (!$oauthError) {
            try {
                $socialUsuario = Socialite::driver($provider)->user();
            } catch (Throwable $e) {
                $this->atlasLogService->registrarError($e, [
                    'route' => "auth/{$provider}/callback",
                    'provider' => $provider,
                ]);

                $oauthError = 'No se pudo completar el inicio de sesión social.';
            }
        }

        if (!$oauthError && $socialUsuario instanceof SocialiteUser) {
            [$usuario, $oauthError] = $this->resolverUsuarioOAuth($socialUsuario);
        }

        if ($oauthError || !$usuario) {
            return $this->redirigirAFrontend(['oauth_error' => $oauthError ?? 'No se pudo completar el inicio de sesión social.']);
        }

        return $this->redirigirAFrontend([
            'oauth_token' => $usuario->createToken('oauth_' . $provider)->plainTextToken,
        ]);
    }

    private function sincronizarUsuarioOAuth(?User $usuario, SocialiteUser $socialUsuario, string $email): User
    {
        if (!$usuario) {
            return User::create([
                'name'              => $socialUsuario->getName() ?: $socialUsuario->getNickname() ?: 'Usuario OAuth',
                'email'             => $email,
                'password'          => Hash::make(str()->random(40)),
                'rol'               => 'cliente',
                'avatar'            => $socialUsuario->getAvatar(),
                'activo'            => true,
                'email_verified_at' => now(),
            ]);
        }

        $actualizacion = [];

        if (!$usuario->avatar && $socialUsuario->getAvatar()) {
            $actualizacion['avatar'] = $socialUsuario->getAvatar();
        }

        if (!$usuario->name && $socialUsuario->getName()) {
            $actualizacion['name'] = $socialUsuario->getName();
        }

        if (!empty($actualizacion)) {
            $usuario->update($actualizacion);
        }

        return $usuario;
    }

    private function resolverUsuarioOAuth(SocialiteUser $socialUsuario): array
    {
        $email = $socialUsuario->getEmail();

        if (!$email) {
            return [null, 'El proveedor no devolvió un email válido.'];
        }

        $usuario = User::query()->where('email', $email)->first();

        if ($usuario && !$usuario->activo) {
            return [null, 'Tu cuenta está desactivada. Contactá al administrador.'];
        }

        return [$this->sincronizarUsuarioOAuth($usuario, $socialUsuario, $email), null];
    }
}

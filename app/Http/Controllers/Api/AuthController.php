<?php

// Archivo de login y autenticación: app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use App\Events\AdminPanelActualizado;
use App\Mail\PasswordResetMail;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Throwable;

/**
 * Controlador de autenticación.
 * Gestiona el registro, inicio de sesión, cierre de sesión y perfil
 * usando tokens de Sanctum (Bearer Token).
 */
class AuthController extends Controller
{
    private const PROVEEDORES_OAUTH = ['google'];

    public function __construct(private readonly AtlasLogService $atlasLogService)
    {
    }

    private function proveedorOAuthValido(string $proveedor): bool
    {
        return in_array($proveedor, self::PROVEEDORES_OAUTH, true);
    }

    private function proveedorOAuthConfigurado(): bool
    {
        return (bool) config('services.google.client_id') && (bool) config('services.google.client_secret');
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
                'password'  => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
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

            AdminPanelActualizado::dispatch('usuarios', 'creado');

            try {
                Mail::to($usuario->email)->queue(new WelcomeMail($usuario));
                $this->atlasLogService->registrarEmailBienvenida($usuario->email, true, ['origen' => 'registro']);
            } catch (Throwable $mailException) {
                Log::error('Welcome email failed for ' . $usuario->email . ': ' . $mailException->getMessage());
                $this->atlasLogService->registrarEmailBienvenida($usuario->email, false, [
                    'origen'    => 'registro',
                    'exception' => $mailException->getMessage(),
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

        AdminPanelActualizado::dispatch('usuarios', 'actualizado');

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

        if (!$this->proveedorOAuthConfigurado()) {
            return $this->redirigirAFrontend([
                'oauth_error' => 'El inicio de sesión con ' . ucfirst($provider) . ' no está configurado en el servidor.',
            ]);
        }

        /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
        $driver = Socialite::driver($provider);

        return $driver->stateless()->redirect();
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
                /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
                $driver = Socialite::driver($provider);
                $socialUsuario = $driver->stateless()->user();
            } catch (Throwable $e) {
                $this->atlasLogService->registrarError($e, [
                    'route' => "auth/{$provider}/callback",
                    'provider' => $provider,
                ]);

                $oauthError = 'No se pudo completar el inicio de sesión social.';
            }
        }

        if (!$oauthError && $socialUsuario instanceof SocialiteUser) {
            [$usuario, $pendingToken, $oauthError] = $this->resolverUsuarioOAuth($socialUsuario);
        }

        if ($oauthError) {
            return $this->redirigirAFrontend(['oauth_error' => $oauthError]);
        }

        // Usuario nuevo: redirigir para que elija su rol antes de crear la cuenta
        if ($pendingToken ?? null) {
            return $this->redirigirAFrontend(['oauth_pending' => $pendingToken]);
        }

        return $this->redirigirAFrontend([
            'oauth_token' => $usuario->createToken('oauth_' . $provider)->plainTextToken,
        ]);
    }

    /**
     * POST /api/auth/oauth/completar
     * El usuario nuevo de OAuth elige su rol y se crea la cuenta.
     * Devuelve token Sanctum igual que el registro normal.
     */
    public function completarRegistroOAuth(Request $request): JsonResponse
    {
        $validados = $request->validate([
            'pending_token' => 'required|string',
            'rol'           => 'required|in:cliente,profesional',
        ]);

        $datos = Cache::get("oauth_pending_{$validados['pending_token']}");

        if (!$datos) {
            return response()->json(['error' => 'El enlace expiró. Iniciá sesión con Google nuevamente.'], 422);
        }

        Cache::forget("oauth_pending_{$validados['pending_token']}");

        if (User::where('email', $datos['email'])->exists()) {
            return response()->json(['error' => 'Esta cuenta ya fue registrada. Iniciá sesión normalmente.'], 422);
        }

        $usuario = User::create([
            'name'              => $datos['name'],
            'email'             => $datos['email'],
            'password'          => Hash::make(str()->random(40)),
            'rol'               => $validados['rol'],
            'avatar'            => $datos['avatar'],
            'activo'            => true,
            'email_verified_at' => now(),
        ]);

        if ($usuario->esProfesional()) {
            Profesional::create(['user_id' => $usuario->id]);
        }

        AdminPanelActualizado::dispatch('usuarios', 'creado');

        try {
            Mail::to($usuario->email)->queue(new WelcomeMail($usuario));
            $this->atlasLogService->registrarEmailBienvenida($usuario->email, true, ['origen' => 'oauth']);
        } catch (Throwable $mailException) {
            Log::error('Welcome email failed (OAuth) for ' . $usuario->email . ': ' . $mailException->getMessage());
            $this->atlasLogService->registrarEmailBienvenida($usuario->email, false, [
                'origen'    => 'oauth',
                'exception' => $mailException->getMessage(),
            ]);
        }

        $token = $usuario->createToken('oauth_google')->plainTextToken;

        return response()->json([
            'usuario' => $usuario->load('profesional'),
            'token'   => $token,
        ], 201);
    }

    /**
     * POST /api/auth/recuperar-contrasena
     * Genera un token y envía el email con el enlace de recuperación.
     */
    public function solicitarRecuperacion(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $usuario = User::where('email', $request->email)->first();

        // Respuesta genérica para no revelar si el email existe
        if (!$usuario) {
            return response()->json(['mensaje' => 'Si el email está registrado, recibirás un enlace en breve.']);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->upsert(
            ['email' => $usuario->email, 'token' => Hash::make($token), 'created_at' => now()],
            ['email'],
            ['token', 'created_at']
        );

        $url = rtrim(config('app.frontend_url', config('app.url')), '/')
            . '/auth/nueva-contrasena?token=' . $token . '&email=' . urlencode($usuario->email);

        try {
            Mail::to($usuario->email)->send(new PasswordResetMail($usuario, $url));
        } catch (Throwable $e) {
            Log::error('Password reset email failed for ' . $usuario->email . ': ' . $e->getMessage());
        }

        return response()->json(['mensaje' => 'Si el email está registrado, recibirás un enlace en breve.']);
    }

    /**
     * POST /api/auth/restablecer-contrasena
     * Valida el token y actualiza la contraseña del usuario.
     */
    public function restablecerContrasena(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $registro = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$registro || !Hash::check($request->token, $registro->token)) {
            return response()->json(['error' => 'El enlace no es válido.'], 422);
        }

        if (now()->diffInMinutes($registro->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['error' => 'El enlace expiró. Solicitá uno nuevo.'], 422);
        }

        $usuario = User::where('email', $request->email)->first();

        if (!$usuario) {
            return response()->json(['error' => 'El enlace no es válido.'], 422);
        }

        $usuario->update(['password' => Hash::make($request->password)]);
        $usuario->tokens()->delete();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['mensaje' => 'Contraseña actualizada. Ya podés iniciar sesión.']);
    }

    private function sincronizarUsuarioExistenteOAuth(User $usuario, SocialiteUser $socialUsuario): User
    {
        $actualizacion = [];

        if (!$usuario->avatar && $socialUsuario->getAvatar()) {
            $actualizacion['avatar'] = $socialUsuario->getAvatar();
        }
        if (!$usuario->name && $socialUsuario->getName()) {
            $actualizacion['name'] = $socialUsuario->getName();
        }
        if (!empty($actualizacion)) {
            $usuario->update($actualizacion);
            AdminPanelActualizado::dispatch('usuarios', 'actualizado');
        }

        return $usuario;
    }

    // Retorna [usuario|null, pendingToken|null, error|null]
    private function resolverUsuarioOAuth(SocialiteUser $socialUsuario): array
    {
        $email = $socialUsuario->getEmail();
        $usuario = null;
        $pendingToken = null;
        $error = null;

        if (!$email) {
            $error = 'El proveedor no devolvió un email válido.';
        }

        if (!$error) {
            $usuario = User::query()->where('email', $email)->first();
        }

        if ($usuario && !$usuario->activo) {
            $error = 'Tu cuenta está desactivada. Contactá al administrador.';
            $usuario = null;
        }

        // Usuario existente: sincronizar y continuar
        if ($usuario) {
            $usuario = $this->sincronizarUsuarioExistenteOAuth($usuario, $socialUsuario);
        }

        // Usuario nuevo: guardar datos en cache y pedir que elija rol
        if (!$usuario && !$error) {
            $pendingToken = bin2hex(random_bytes(16));
            Cache::put("oauth_pending_{$pendingToken}", [
                'name'   => $socialUsuario->getName() ?: $socialUsuario->getNickname() ?: 'Usuario',
                'email'  => $email,
                'avatar' => $socialUsuario->getAvatar(),
            ], now()->addMinutes(30));
        }

        return [$usuario, $pendingToken, $error];
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AuthRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── Registro y login con email ────────────────────────────────────────────

    public function test_user_can_register_and_login(): void
    {
        $email    = 'user+' . Str::random(8) . '@example.com';
        $password = 'Password123!';

        $register = $this->postJson('/api/auth/registrar', [
            'name'                  => 'Usuario Test',
            'email'                 => $email,
            'password'              => $password,
            'password_confirmation' => $password,
            'rol'                   => 'cliente',
        ]);

        $register
            ->assertStatus(201)
            ->assertJsonPath('usuario.email', $email)
            ->assertJsonStructure(['usuario' => ['id', 'email'], 'token']);

        $this->assertDatabaseHas('users', ['email' => $email]);

        $login = $this->postJson('/api/auth/iniciar-sesion', [
            'email'    => $email,
            'password' => $password,
        ]);

        $login
            ->assertOk()
            ->assertJsonPath('usuario.email', $email)
            ->assertJsonStructure(['usuario' => ['id', 'email'], 'token']);
    }

    // ── OAuth: usuario nuevo → pide elegir rol ────────────────────────────────

    public function test_oauth_callback_redirects_new_user_with_pending_token(): void
    {
        $email = 'oauth+' . Str::random(8) . '@example.com';

        $this->mockSocialite('google', $email, 'Usuario OAuth');

        $response = $this->get('/api/auth/google/callback');

        $response->assertRedirect();
        $location = $response->headers->get('Location');

        $this->assertStringContainsString('/auth/iniciar-sesion?oauth_pending=', $location);

        // El usuario NO debe existir aún en la DB
        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    // ── OAuth: completar registro eligiendo rol ───────────────────────────────

    public function test_oauth_completar_creates_user_with_chosen_rol(): void
    {
        $email = 'oauth+' . Str::random(8) . '@example.com';

        $pendingToken = 'test-pending-token-' . Str::random(8);
        Cache::put("oauth_pending_{$pendingToken}", [
            'name'   => 'Usuario OAuth',
            'email'  => $email,
            'avatar' => 'https://example.com/avatar.png',
        ], now()->addMinutes(30));

        $response = $this->postJson('/api/auth/oauth/completar', [
            'pending_token' => $pendingToken,
            'rol'           => 'profesional',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('usuario.email', $email)
            ->assertJsonPath('usuario.rol', 'profesional')
            ->assertJsonStructure(['usuario' => ['id', 'email', 'rol'], 'token']);

        $this->assertDatabaseHas('users', ['email' => $email, 'rol' => 'profesional']);

        // El token de cache debe haberse consumido
        $this->assertNull(Cache::get("oauth_pending_{$pendingToken}"));
    }

    public function test_oauth_completar_fails_with_expired_token(): void
    {
        $response = $this->postJson('/api/auth/oauth/completar', [
            'pending_token' => 'token-inexistente',
            'rol'           => 'cliente',
        ]);

        $response->assertStatus(422)->assertJsonPath('error', fn ($v) => str_contains($v, 'expiró'));
    }

    // ── OAuth: usuario existente → token directo ──────────────────────────────

    public function test_oauth_callback_existing_user_redirects_with_token(): void
    {
        $email = 'oauth+' . Str::random(8) . '@example.com';

        User::create([
            'name'     => 'Usuario Existente',
            'email'    => $email,
            'password' => Hash::make('Password123'),
            'rol'      => 'cliente',
            'activo'   => true,
        ]);

        $this->mockSocialite('google', $email, 'Usuario OAuth');

        $response = $this->get('/api/auth/google/callback');

        $response->assertRedirect();
        $this->assertStringContainsString('/auth/iniciar-sesion?oauth_token=', $response->headers->get('Location'));
    }

    // ── OAuth: errores de configuración y proveedor ───────────────────────────

    public function test_oauth_callback_redirects_with_error_when_provider_is_not_configured(): void
    {
        $response = $this->get('/api/auth/google/redirect');

        $response->assertRedirect();
        $this->assertStringContainsString('/auth/iniciar-sesion?oauth_error=', $response->headers->get('Location'));
    }

    public function test_oauth_callback_redirects_with_error_for_invalid_provider(): void
    {
        $response = $this->get('/api/auth/unknown/callback');

        $response->assertRedirect();
        $this->assertStringContainsString('/auth/iniciar-sesion?oauth_error=', $response->headers->get('Location'));
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function mockSocialite(string $driver, string $email, string $name): void
    {
        config([
            'services.google.client_id'     => 'google-client-id-test',
            'services.google.client_secret' => 'google-client-secret-test',
        ]);

        $socialUser = Mockery::mock(SocialiteUser::class);
        $socialUser->shouldReceive('getEmail')->andReturn($email);
        $socialUser->shouldReceive('getName')->andReturn($name);
        $socialUser->shouldReceive('getNickname')->andReturnNull();
        $socialUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.png');

        $provider = Mockery::mock(SocialiteProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialUser);

        $factory = Mockery::mock(SocialiteFactory::class);
        $factory->shouldReceive('driver')->with($driver)->andReturn($provider);

        $this->app->instance(SocialiteFactory::class, $factory);
    }
}

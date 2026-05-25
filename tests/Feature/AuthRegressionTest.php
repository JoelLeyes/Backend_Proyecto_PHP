<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_user_can_register_and_login(): void
    {
        $email = 'user+' . Str::random(8) . '@example.com';
        $password = 'Password123';

        $register = $this->postJson('/api/auth/registrar', [
            'name' => 'Usuario Test',
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
            'rol' => 'cliente',
        ]);

        $register
            ->assertStatus(201)
            ->assertJsonPath('usuario.email', $email)
            ->assertJsonStructure([
                'usuario' => ['id', 'email'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => $email,
        ]);

        $login = $this->postJson('/api/auth/iniciar-sesion', [
            'email' => $email,
            'password' => $password,
        ]);

        $login
            ->assertOk()
            ->assertJsonPath('usuario.email', $email)
            ->assertJsonStructure([
                'usuario' => ['id', 'email'],
                'token',
            ]);
    }

    public function test_oauth_callback_creates_user_and_redirects_with_token(): void
    {
        $email = 'oauth+' . Str::random(8) . '@example.com';

        $provider = Mockery::mock(SocialiteProvider::class);
        $factory = Mockery::mock(SocialiteFactory::class);
        $socialUser = Mockery::mock(SocialiteUser::class);

        $socialUser->shouldReceive('getEmail')->andReturn($email);
        $socialUser->shouldReceive('getName')->andReturn('Usuario OAuth');
        $socialUser->shouldReceive('getNickname')->andReturnNull();
        $socialUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.png');

        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialUser);

        $factory->shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $this->app->instance(SocialiteFactory::class, $factory);

        $response = $this->get('/api/auth/google/callback');

        $response->assertRedirect();
        $this->assertStringContainsString('/auth/iniciar-sesion?oauth_token=', $response->headers->get('Location'));

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => 'Usuario OAuth',
        ]);
    }

    public function test_oauth_callback_redirects_with_error_for_invalid_provider(): void
    {
        $response = $this->get('/api/auth/unknown/callback');

        $response->assertRedirect();
        $this->assertStringContainsString('/auth/iniciar-sesion?oauth_error=', $response->headers->get('Location'));
    }
}

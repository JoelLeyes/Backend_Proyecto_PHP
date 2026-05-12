<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthRegressionTest extends TestCase
{
    use RefreshDatabase;

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
}

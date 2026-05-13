<?php

namespace Tests\Feature;

use App\Models\Profesional;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResenaFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cliente_puede_crear_resena_y_actualizar_promedio_del_profesional(): void
    {
        Http::fake();

        config()->set('services.notificaciones.url', 'http://notificaciones.test');
        config()->set('services.notificaciones.token', 'token-test');

        $profesionalUsuario = User::create([
            'name' => 'Laura Profesional',
            'email' => 'laura-profesional@example.com',
            'password' => 'Password123',
            'rol' => 'profesional',
        ]);

        $perfilProfesional = Profesional::create([
            'user_id' => $profesionalUsuario->id,
            'nombre_negocio' => 'Laura Coaching',
            'modalidad' => 'remota',
            'ciudad' => 'Montevideo',
            'pais' => 'UY',
        ]);

        $servicio = Servicio::create([
            'profesional_id' => $perfilProfesional->id,
            'nombre' => 'Sesión inicial',
            'precio' => 1200,
            'duracion_minutos' => 60,
            'modalidad' => 'remota',
        ]);

        $cliente = User::create([
            'name' => 'Carlos Cliente',
            'email' => 'carlos-cliente@example.com',
            'password' => 'Password123',
            'rol' => 'cliente',
        ]);

        $reserva = Reserva::create([
            'servicio_id' => $servicio->id,
            'cliente_id' => $cliente->id,
            'profesional_id' => $profesionalUsuario->id,
            'fecha_hora' => now()->subDay(),
            'duracion_minutos' => 60,
            'estado' => 'finalizada',
            'modalidad' => 'remota',
        ]);

        $respuesta = $this->actingAs($cliente, 'sanctum')->postJson("/api/reservas/{$reserva->id}/resena", [
            'calificacion' => 5,
            'comentario' => 'Excelente atención.',
        ]);

        $respuesta
            ->assertCreated()
            ->assertJsonPath('calificacion', 5)
            ->assertJsonPath('comentario', 'Excelente atención.');

        $this->assertDatabaseHas('resenas', [
            'reserva_id' => $reserva->id,
            'evaluador_id' => $cliente->id,
            'profesional_id' => $profesionalUsuario->id,
            'calificacion' => 5,
        ]);

        $perfilProfesional->refresh();
        $this->assertSame(5.0, (float) $perfilProfesional->promedio_calificacion);
        $this->assertSame(1, (int) $perfilProfesional->total_calificaciones);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://notificaciones.test/api/notificar'
                && $request['tipo'] === 'resena_creada'
                && $request['email_usuario'] === 'laura-profesional@example.com';
        });
    }
}
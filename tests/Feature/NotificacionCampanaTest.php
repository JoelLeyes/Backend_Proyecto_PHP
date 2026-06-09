<?php

namespace Tests\Feature;

use App\Models\Profesional;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificacionCampanaTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente;
    private User $profesionalUser;
    private Profesional $profesional;
    private Servicio $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        config()->set('services.notificaciones.url', 'http://notificaciones.test');
        config()->set('services.notificaciones.token', 'token-test');

        $this->profesionalUser = User::create([
            'name'     => 'Ana Profesional',
            'email'    => 'ana@example.com',
            'password' => 'Password1!',
            'rol'      => 'profesional',
            'activo'   => true,
        ]);
        $this->profesional = Profesional::create([
            'user_id'           => $this->profesionalUser->id,
            'horas_cancelacion' => 0,
        ]);
        $this->servicio = Servicio::create([
            'profesional_id'   => $this->profesional->id,
            'nombre'           => 'Coaching',
            'precio'           => 1000,
            'duracion_minutos' => 60,
            'modalidad'        => 'remota',
        ]);
        $this->cliente = User::create([
            'name'     => 'Bob Cliente',
            'email'    => 'bob@example.com',
            'password' => 'Password1!',
            'rol'      => 'cliente',
            'activo'   => true,
        ]);
    }

    private function crearReserva(string $estado = 'pendiente'): Reserva
    {
        return Reserva::create([
            'servicio_id'      => $this->servicio->id,
            'cliente_id'       => $this->cliente->id,
            'profesional_id'   => $this->profesionalUser->id,
            'fecha_hora'       => Carbon::tomorrow()->setTime(10, 0),
            'duracion_minutos' => 60,
            'estado'           => $estado,
            'modalidad'        => 'remota',
        ]);
    }

    public function test_crear_reserva_genera_notificacion_campana_al_profesional(): void
    {
        $this->actingAs($this->cliente, 'sanctum')
            ->postJson('/api/reservas', [
                'servicio_id' => $this->servicio->id,
                'fecha_hora'  => Carbon::tomorrow()->setTime(10, 0)->toISOString(),
                'modalidad'   => 'remota',
            ])->assertCreated();

        $this->assertDatabaseHas('notificaciones_app', [
            'usuario_id' => $this->profesionalUser->id,
            'titulo'     => 'Nueva reserva',
        ]);
        // No debe notificar al cliente al crear
        $this->assertDatabaseMissing('notificaciones_app', [
            'usuario_id' => $this->cliente->id,
            'titulo'     => 'Nueva reserva',
        ]);
    }

    public function test_confirmar_reserva_genera_notificacion_campana_al_cliente(): void
    {
        $reserva = $this->crearReserva('pendiente');

        $this->actingAs($this->profesionalUser, 'sanctum')
            ->postJson("/api/reservas/{$reserva->id}/confirmar")
            ->assertOk();

        $this->assertDatabaseHas('notificaciones_app', [
            'usuario_id' => $this->cliente->id,
            'titulo'     => 'Reserva confirmada',
            'tipo'       => 'success',
        ]);
    }

    public function test_cancelar_por_cliente_genera_notificacion_al_profesional(): void
    {
        $reserva = $this->crearReserva('confirmada');

        $this->actingAs($this->cliente, 'sanctum')
            ->postJson("/api/reservas/{$reserva->id}/cancelar")
            ->assertOk();

        $this->assertDatabaseHas('notificaciones_app', [
            'usuario_id' => $this->profesionalUser->id,
            'titulo'     => 'Reserva cancelada',
        ]);
        // El cliente no debe recibir notificación de su propia cancelación
        $this->assertDatabaseMissing('notificaciones_app', [
            'usuario_id' => $this->cliente->id,
            'titulo'     => 'Reserva cancelada',
        ]);
    }

    public function test_cancelar_por_profesional_genera_notificacion_al_cliente(): void
    {
        $reserva = $this->crearReserva('confirmada');

        $this->actingAs($this->profesionalUser, 'sanctum')
            ->postJson("/api/reservas/{$reserva->id}/cancelar")
            ->assertOk();

        $this->assertDatabaseHas('notificaciones_app', [
            'usuario_id' => $this->cliente->id,
            'titulo'     => 'Reserva cancelada',
        ]);
        // El profesional no debe recibir notificación de su propia cancelación
        $this->assertDatabaseMissing('notificaciones_app', [
            'usuario_id' => $this->profesionalUser->id,
            'titulo'     => 'Reserva cancelada',
        ]);
    }

    public function test_finalizar_reserva_genera_notificacion_campana_al_cliente(): void
    {
        $reserva = $this->crearReserva('confirmada');

        $this->actingAs($this->profesionalUser, 'sanctum')
            ->postJson("/api/reservas/{$reserva->id}/finalizar")
            ->assertOk();

        $this->assertDatabaseHas('notificaciones_app', [
            'usuario_id' => $this->cliente->id,
            'titulo'     => 'Sesión finalizada',
            'tipo'       => 'info',
        ]);
    }

    public function test_reprogramar_genera_notificacion_a_ambas_partes(): void
    {
        $reserva = $this->crearReserva('pendiente');

        $this->actingAs($this->cliente, 'sanctum')
            ->patchJson("/api/reservas/{$reserva->id}/reprogramar", [
                'fecha_hora' => Carbon::now()->addDays(3)->setTime(14, 0)->toISOString(),
            ])->assertOk();

        $this->assertDatabaseHas('notificaciones_app', [
            'usuario_id' => $this->cliente->id,
            'titulo'     => 'Reserva reprogramada',
        ]);
        $this->assertDatabaseHas('notificaciones_app', [
            'usuario_id' => $this->profesionalUser->id,
            'titulo'     => 'Reserva reprogramada',
        ]);
    }
}

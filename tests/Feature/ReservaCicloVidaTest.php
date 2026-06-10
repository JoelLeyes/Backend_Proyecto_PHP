<?php

namespace Tests\Feature;

use App\Events\ReservaActualizada;
use App\Models\Profesional;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReservaCicloVidaTest extends TestCase
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
        Event::fake([ReservaActualizada::class]);
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
            'horas_cancelacion' => 24,
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

    private function crearReserva(string $estado = 'pendiente', ?Carbon $fecha = null): Reserva
    {
        return Reserva::create([
            'servicio_id'      => $this->servicio->id,
            'cliente_id'       => $this->cliente->id,
            'profesional_id'   => $this->profesionalUser->id,
            'fecha_hora'       => $fecha ?? Carbon::tomorrow()->setTime(10, 0),
            'duracion_minutos' => 60,
            'estado'           => $estado,
            'modalidad'        => 'remota',
        ]);
    }

    // ── Creación ──────────────────────────────────────────────────────────────

    public function test_cliente_puede_crear_reserva(): void
    {
        $respuesta = $this->actingAs($this->cliente, 'sanctum')
            ->postJson('/api/reservas', [
                'servicio_id' => $this->servicio->id,
                'fecha_hora'  => Carbon::tomorrow()->setTime(10, 0)->toISOString(),
                'modalidad'   => 'remota',
            ]);

        $respuesta->assertCreated()->assertJsonPath('estado', 'pendiente');
        $this->assertDatabaseHas('reservas', [
            'cliente_id' => $this->cliente->id,
            'estado'     => 'pendiente',
        ]);
    }

    public function test_no_autenticado_no_puede_crear_reserva(): void
    {
        $this->postJson('/api/reservas', [
            'servicio_id' => $this->servicio->id,
            'fecha_hora'  => Carbon::tomorrow()->setTime(10, 0)->toISOString(),
            'modalidad'   => 'remota',
        ])->assertUnauthorized();
    }

    // ── Confirmar ─────────────────────────────────────────────────────────────

    public function test_profesional_puede_confirmar_reserva_pendiente(): void
    {
        $reserva = $this->crearReserva('pendiente');

        $this->actingAs($this->profesionalUser, 'sanctum')
            ->postJson("/api/reservas/{$reserva->id}/confirmar")
            ->assertOk()
            ->assertJsonPath('estado', 'confirmada');

        $this->assertDatabaseHas('reservas', ['id' => $reserva->id, 'estado' => 'confirmada']);
    }

    public function test_no_se_puede_confirmar_reserva_ya_confirmada(): void
    {
        $reserva = $this->crearReserva('confirmada');

        $this->actingAs($this->profesionalUser, 'sanctum')
            ->postJson("/api/reservas/{$reserva->id}/confirmar")
            ->assertStatus(422);
    }

    public function test_cliente_no_puede_confirmar_reserva(): void
    {
        $reserva = $this->crearReserva('pendiente');

        $this->actingAs($this->cliente, 'sanctum')
            ->postJson("/api/reservas/{$reserva->id}/confirmar")
            ->assertForbidden();
    }

    // ── Finalizar ─────────────────────────────────────────────────────────────

    public function test_profesional_puede_finalizar_reserva_confirmada(): void
    {
        $reserva = $this->crearReserva('confirmada');

        $this->actingAs($this->profesionalUser, 'sanctum')
            ->postJson("/api/reservas/{$reserva->id}/finalizar")
            ->assertOk()
            ->assertJsonPath('estado', 'finalizada');

        $this->assertDatabaseHas('reservas', ['id' => $reserva->id, 'estado' => 'finalizada']);
    }

    public function test_no_se_puede_finalizar_reserva_cancelada(): void
    {
        $reserva = $this->crearReserva('cancelada');

        $this->actingAs($this->profesionalUser, 'sanctum')
            ->postJson("/api/reservas/{$reserva->id}/finalizar")
            ->assertStatus(422);
    }

    public function test_no_se_puede_finalizar_reserva_pendiente(): void
    {
        $reserva = $this->crearReserva('pendiente');

        $this->actingAs($this->profesionalUser, 'sanctum')
            ->postJson("/api/reservas/{$reserva->id}/finalizar")
            ->assertStatus(422);
    }

    // ── Cancelar ──────────────────────────────────────────────────────────────

    public function test_cliente_puede_cancelar_con_suficiente_anticipacion(): void
    {
        // horas_cancelacion = 24, reserva en 48 h → debe poder cancelar
        $reserva = $this->crearReserva('confirmada', Carbon::now()->addHours(48));

        $this->actingAs($this->cliente, 'sanctum')
            ->postJson("/api/reservas/{$reserva->id}/cancelar")
            ->assertOk()
            ->assertJsonPath('estado', 'cancelada');
    }

    public function test_cliente_no_puede_cancelar_con_poca_anticipacion(): void
    {
        // horas_cancelacion = 24, reserva en 12 h → debe rechazar
        $reserva = $this->crearReserva('confirmada', Carbon::now()->addHours(12));

        $this->actingAs($this->cliente, 'sanctum')
            ->postJson("/api/reservas/{$reserva->id}/cancelar")
            ->assertStatus(422);

        $this->assertDatabaseMissing('reservas', ['id' => $reserva->id, 'estado' => 'cancelada']);
    }

    public function test_profesional_puede_cancelar_sin_restriccion_horaria(): void
    {
        // El profesional puede cancelar sin importar las horas de anticipación
        $reserva = $this->crearReserva('confirmada', Carbon::now()->addHours(1));

        $this->actingAs($this->profesionalUser, 'sanctum')
            ->postJson("/api/reservas/{$reserva->id}/cancelar")
            ->assertOk()
            ->assertJsonPath('estado', 'cancelada');
    }

    public function test_no_se_puede_cancelar_reserva_finalizada(): void
    {
        $reserva = $this->crearReserva('finalizada');

        $this->actingAs($this->cliente, 'sanctum')
            ->postJson("/api/reservas/{$reserva->id}/cancelar")
            ->assertStatus(422);
    }

    // ── Reprogramar ───────────────────────────────────────────────────────────

    public function test_cliente_puede_reprogramar_reserva_pendiente(): void
    {
        $reserva    = $this->crearReserva('pendiente');
        $nuevaFecha = Carbon::now()->addDays(3)->setTime(14, 0)->toISOString();

        $this->actingAs($this->cliente, 'sanctum')
            ->patchJson("/api/reservas/{$reserva->id}/reprogramar", ['fecha_hora' => $nuevaFecha])
            ->assertOk()
            ->assertJsonPath('estado', 'confirmada');
    }

    public function test_no_se_puede_reprogramar_reserva_cancelada(): void
    {
        $reserva = $this->crearReserva('cancelada');

        $this->actingAs($this->cliente, 'sanctum')
            ->patchJson("/api/reservas/{$reserva->id}/reprogramar", [
                'fecha_hora' => Carbon::now()->addDays(3)->toISOString(),
            ])->assertStatus(422);
    }

    // ── Control de concurrencia ───────────────────────────────────────────────

    public function test_doble_reserva_mismo_horario_devuelve_conflicto(): void
    {
        // Reserva existente en ese horario
        $this->crearReserva('confirmada', Carbon::tomorrow()->setTime(10, 0));

        // Segundo cliente intenta el mismo horario
        $otroCliente = User::create([
            'name' => 'Otro', 'email' => 'otro@example.com',
            'password' => 'Password1!', 'rol' => 'cliente', 'activo' => true,
        ]);

        $this->actingAs($otroCliente, 'sanctum')
            ->postJson('/api/reservas', [
                'servicio_id' => $this->servicio->id,
                'fecha_hora'  => Carbon::tomorrow()->setTime(10, 0)->toISOString(),
                'modalidad'   => 'remota',
            ])->assertStatus(409);
    }

    public function test_horario_superpuesto_parcialmente_devuelve_conflicto(): void
    {
        // Reserva de 60 min a las 10:00 → ocupa hasta 11:00
        $this->crearReserva('confirmada', Carbon::tomorrow()->setTime(10, 0));

        // Intento a las 10:30 (se superpone con la existente)
        $otroCliente = User::create([
            'name' => 'Otro', 'email' => 'otro@example.com',
            'password' => 'Password1!', 'rol' => 'cliente', 'activo' => true,
        ]);

        $this->actingAs($otroCliente, 'sanctum')
            ->postJson('/api/reservas', [
                'servicio_id' => $this->servicio->id,
                'fecha_hora'  => Carbon::tomorrow()->setTime(10, 30)->toISOString(),
                'modalidad'   => 'remota',
            ])->assertStatus(409);
    }
}

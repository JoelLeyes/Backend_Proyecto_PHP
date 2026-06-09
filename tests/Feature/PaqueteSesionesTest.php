<?php

namespace Tests\Feature;

use App\Models\PaqueteCliente;
use App\Models\PaqueteServicio;
use App\Models\Profesional;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaqueteSesionesTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente;
    private User $profesionalUser;
    private Profesional $profesional;
    private Servicio $servicio;
    private PaqueteServicio $paqueteServicio;

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
            'user_id' => $this->profesionalUser->id,
        ]);
        $this->servicio = Servicio::create([
            'profesional_id'   => $this->profesional->id,
            'nombre'           => 'Coaching',
            'precio'           => 1000,
            'duracion_minutos' => 60,
            'modalidad'        => 'remota',
        ]);
        $this->paqueteServicio = PaqueteServicio::create([
            'servicio_id' => $this->servicio->id,
            'nombre'      => 'Pack 4 sesiones',
            'precio'      => 3600,
        ]);
        $this->cliente = User::create([
            'name'     => 'Bob Cliente',
            'email'    => 'bob@example.com',
            'password' => 'Password1!',
            'rol'      => 'cliente',
            'activo'   => true,
        ]);
    }

    private function crearPaqueteActivo(int $total = 4, int $usadas = 0): PaqueteCliente
    {
        return PaqueteCliente::create([
            'cliente_id'          => $this->cliente->id,
            'paquete_servicio_id' => $this->paqueteServicio->id,
            'sesiones_total'      => $total,
            'sesiones_usadas'     => $usadas,
            'estado'              => 'activo',
            'fecha_compra'        => now(),
            'fecha_vencimiento'   => now()->addYear(),
        ]);
    }

    private function crearReservaConPaquete(PaqueteCliente $paquete, string $estado = 'confirmada', ?Carbon $fecha = null): Reserva
    {
        return Reserva::create([
            'servicio_id'        => $this->servicio->id,
            'cliente_id'         => $this->cliente->id,
            'profesional_id'     => $this->profesionalUser->id,
            'paquete_cliente_id' => $paquete->id,
            'fecha_hora'         => $fecha ?? Carbon::tomorrow()->setTime(10, 0),
            'duracion_minutos'   => 60,
            'estado'             => $estado,
            'modalidad'          => 'remota',
        ]);
    }

    // ── Descuento al momento correcto ─────────────────────────────────────────

    public function test_sesion_no_se_descuenta_al_reservar(): void
    {
        $paquete = $this->crearPaqueteActivo(4, 0);

        $this->actingAs($this->cliente, 'sanctum')
            ->postJson('/api/reservas', [
                'servicio_id'        => $this->servicio->id,
                'fecha_hora'         => Carbon::tomorrow()->setTime(10, 0)->toISOString(),
                'modalidad'          => 'remota',
                'paquete_cliente_id' => $paquete->id,
            ])->assertCreated();

        $paquete->refresh();
        $this->assertSame(0, $paquete->sesiones_usadas);
    }

    public function test_sesion_se_descuenta_al_finalizar(): void
    {
        $paquete = $this->crearPaqueteActivo(4, 0);
        $reserva = $this->crearReservaConPaquete($paquete, 'confirmada');

        $this->actingAs($this->profesionalUser, 'sanctum')
            ->postJson("/api/reservas/{$reserva->id}/finalizar")
            ->assertOk();

        $paquete->refresh();
        $this->assertSame(1, $paquete->sesiones_usadas);
        $this->assertSame('activo', $paquete->estado);
    }

    // ── Estado del paquete ────────────────────────────────────────────────────

    public function test_paquete_se_marca_consumido_al_agotar_sesiones(): void
    {
        $paquete = $this->crearPaqueteActivo(2, 1); // 2 total, 1 ya usada
        $reserva = $this->crearReservaConPaquete($paquete, 'confirmada');

        $this->actingAs($this->profesionalUser, 'sanctum')
            ->postJson("/api/reservas/{$reserva->id}/finalizar")
            ->assertOk();

        $paquete->refresh();
        $this->assertSame(2, $paquete->sesiones_usadas);
        $this->assertSame('consumido', $paquete->estado);
    }

    public function test_paquete_permanece_activo_si_quedan_sesiones(): void
    {
        $paquete = $this->crearPaqueteActivo(4, 0);
        $reserva = $this->crearReservaConPaquete($paquete, 'confirmada');

        $this->actingAs($this->profesionalUser, 'sanctum')
            ->postJson("/api/reservas/{$reserva->id}/finalizar")
            ->assertOk();

        $paquete->refresh();
        $this->assertSame('activo', $paquete->estado);
    }

    // ── Control de sesiones disponibles ──────────────────────────────────────

    public function test_no_se_puede_reservar_si_sesiones_activas_llegan_al_limite(): void
    {
        // Paquete de 2 sesiones, 0 usadas pero con 2 reservas activas
        $paquete = $this->crearPaqueteActivo(2, 0);
        $this->crearReservaConPaquete($paquete, 'confirmada', Carbon::tomorrow()->setTime(9, 0));
        $this->crearReservaConPaquete($paquete, 'confirmada', Carbon::tomorrow()->setTime(11, 0));

        $this->actingAs($this->cliente, 'sanctum')
            ->postJson('/api/reservas', [
                'servicio_id'        => $this->servicio->id,
                'fecha_hora'         => Carbon::tomorrow()->setTime(15, 0)->toISOString(),
                'modalidad'          => 'remota',
                'paquete_cliente_id' => $paquete->id,
            ])->assertStatus(422);
    }

    public function test_reserva_cancelada_libera_sesion_del_paquete(): void
    {
        $paquete = $this->crearPaqueteActivo(1, 0);
        // La única sesión tiene una reserva activa
        $reservaActiva = $this->crearReservaConPaquete($paquete, 'confirmada', Carbon::tomorrow()->setTime(9, 0));
        // La cancelamos → libera la sesión
        $reservaActiva->update(['estado' => 'cancelada']);

        // Ahora se puede reservar de nuevo con el paquete
        $this->actingAs($this->cliente, 'sanctum')
            ->postJson('/api/reservas', [
                'servicio_id'        => $this->servicio->id,
                'fecha_hora'         => Carbon::tomorrow()->setTime(15, 0)->toISOString(),
                'modalidad'          => 'remota',
                'paquete_cliente_id' => $paquete->id,
            ])->assertCreated();
    }

    public function test_no_se_puede_usar_paquete_consumido(): void
    {
        $paquete = $this->crearPaqueteActivo(2, 2); // todas usadas
        $paquete->update(['estado' => 'consumido']);

        $this->actingAs($this->cliente, 'sanctum')
            ->postJson('/api/reservas', [
                'servicio_id'        => $this->servicio->id,
                'fecha_hora'         => Carbon::tomorrow()->setTime(10, 0)->toISOString(),
                'modalidad'          => 'remota',
                'paquete_cliente_id' => $paquete->id,
            ])->assertStatus(422);
    }
}

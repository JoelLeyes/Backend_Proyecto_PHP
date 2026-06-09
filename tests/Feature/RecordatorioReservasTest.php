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

class RecordatorioReservasTest extends TestCase
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
            'horas_cancelacion' => 0, // umbral de envío = 0 + 3 = 3 horas antes
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

    private function crearReserva(Carbon $fechaHora, string $estado = 'confirmada'): Reserva
    {
        return Reserva::create([
            'servicio_id'      => $this->servicio->id,
            'cliente_id'       => $this->cliente->id,
            'profesional_id'   => $this->profesionalUser->id,
            'fecha_hora'       => $fechaHora,
            'duracion_minutos' => 60,
            'estado'           => $estado,
            'modalidad'        => 'remota',
        ]);
    }

    // ── No envía cuando no corresponde ───────────────────────────────────────

    public function test_no_envia_recordatorio_con_mucha_anticipacion(): void
    {
        // Reserva en 5 horas, umbral = 3 horas → momentoEnvio = now+5-3 = now+2 → no es momento
        $this->crearReserva(Carbon::now()->addHours(5));

        $this->artisan('reservas:recordatorios')->assertSuccessful();

        $this->assertDatabaseEmpty('notificaciones_app');
    }

    public function test_no_envia_recordatorio_a_reserva_cancelada(): void
    {
        // La reserva está cancelada → no aplica aunque esté dentro del umbral
        $this->crearReserva(Carbon::now()->addHours(2), 'cancelada');

        $this->artisan('reservas:recordatorios')->assertSuccessful();

        $this->assertDatabaseEmpty('notificaciones_app');
    }

    public function test_no_envia_si_recordatorio_ya_fue_enviado(): void
    {
        // Dentro del umbral pero ya se envió
        $reserva = $this->crearReserva(Carbon::now()->addHours(2));
        $reserva->recordatorio_enviado_at = Carbon::now()->subHour();
        $reserva->save();

        $this->artisan('reservas:recordatorios')->assertSuccessful();

        $this->assertDatabaseEmpty('notificaciones_app');
    }

    // ── Sí envía cuando corresponde ──────────────────────────────────────────

    public function test_envia_recordatorio_cuando_es_momento(): void
    {
        // Reserva en 2 horas, umbral = 3 horas → momentoEnvio = now+2-3 = now-1 → ya pasó → enviar
        $this->crearReserva(Carbon::now()->addHours(2));

        $this->artisan('reservas:recordatorios')->assertSuccessful();

        $this->assertDatabaseHas('notificaciones_app', [
            'usuario_id' => $this->cliente->id,
            'titulo'     => 'Recordatorio de cita',
        ]);
    }

    public function test_envia_recordatorio_respetando_horas_cancelacion_del_profesional(): void
    {
        // Profesional con 5 horas de cancelación → umbral = 5+3 = 8 horas
        $this->profesional->horas_cancelacion = 5;
        $this->profesional->save();

        // Reserva en 7 horas → momentoEnvio = now+7-8 = now-1 → ya pasó → enviar
        $this->crearReserva(Carbon::now()->addHours(7));

        $this->artisan('reservas:recordatorios')->assertSuccessful();

        $this->assertDatabaseHas('notificaciones_app', [
            'usuario_id' => $this->cliente->id,
            'titulo'     => 'Recordatorio de cita',
        ]);
    }

    public function test_reserva_con_umbral_alto_no_envia_si_falta_mucho(): void
    {
        // Profesional con 5 horas → umbral = 8 horas
        $this->profesional->horas_cancelacion = 5;
        $this->profesional->save();

        // Reserva en 10 horas → momentoEnvio = now+10-8 = now+2 → no es momento
        $this->crearReserva(Carbon::now()->addHours(10));

        $this->artisan('reservas:recordatorios')->assertSuccessful();

        $this->assertDatabaseEmpty('notificaciones_app');
    }

    // ── Efectos secundarios ───────────────────────────────────────────────────

    public function test_marca_recordatorio_enviado_at_tras_envio(): void
    {
        $reserva = $this->crearReserva(Carbon::now()->addHours(2));

        $this->artisan('reservas:recordatorios')->assertSuccessful();

        $reserva->refresh();
        $this->assertNotNull($reserva->recordatorio_enviado_at);
    }

    public function test_no_envia_duplicado_en_segunda_ejecucion(): void
    {
        $this->crearReserva(Carbon::now()->addHours(2));

        $this->artisan('reservas:recordatorios')->assertSuccessful();
        $this->artisan('reservas:recordatorios')->assertSuccessful(); // segunda pasada

        // Solo debe haber una notificación, no dos
        $this->assertDatabaseCount('notificaciones_app', 1);
    }
}

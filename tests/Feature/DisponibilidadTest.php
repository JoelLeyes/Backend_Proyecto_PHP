<?php

namespace Tests\Feature;

use App\Models\ExcepcionDisponibilidad;
use App\Models\Profesional;
use App\Models\ReglaDisponibilidad;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisponibilidadTest extends TestCase
{
    use RefreshDatabase;

    private User $profesionalUser;
    private Profesional $profesional;
    private Servicio $servicio;
    private Carbon $fechaTest;
    private int $diaSemana;

    protected function setUp(): void
    {
        parent::setUp();

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

        // Usar mañana como fecha de test para conocer su día de semana
        $this->fechaTest = Carbon::tomorrow()->startOfDay();
        $this->diaSemana = $this->fechaTest->dayOfWeek;
    }

    private function crearRegla(
        string $inicio = '09:00',
        string $fin = '18:00',
        int $bufferAntes = 0,
        int $bufferDespues = 0
    ): ReglaDisponibilidad {
        return ReglaDisponibilidad::create([
            'profesional_id'         => $this->profesional->id,
            'dia_semana'             => $this->diaSemana,
            'hora_inicio'            => $inicio,
            'hora_fin'               => $fin,
            'buffer_antes_minutos'   => $bufferAntes,
            'buffer_despues_minutos' => $bufferDespues,
            'activo'                 => true,
        ]);
    }

    private function urlHorarios(): string
    {
        return "/api/profesionales/{$this->profesional->id}/disponibilidad/horarios"
            . "?fecha={$this->fechaTest->toDateString()}&servicio_id={$this->servicio->id}";
    }

    // ── Reglas de horario ────────────────────────────────────────────────────

    public function test_dia_sin_regla_devuelve_sin_horarios(): void
    {
        // No se crea ninguna regla para este día
        $this->getJson($this->urlHorarios())
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_horario_libre_aparece_como_disponible(): void
    {
        $this->crearRegla('09:00', '11:00');

        $respuesta = $this->getJson($this->urlHorarios())->assertOk();

        $horarios = $respuesta->json();
        $this->assertNotEmpty($horarios);
        $this->assertSame(
            $this->fechaTest->toDateString() . ' 09:00:00',
            $horarios[0]['inicio']
        );
    }

    public function test_regla_genera_multiples_slots_consecutivos(): void
    {
        // Regla de 3 horas con servicio de 60 min → 3 slots: 09:00, 10:00, 11:00
        $this->crearRegla('09:00', '12:00');

        $horarios = $this->getJson($this->urlHorarios())->json();

        $this->assertCount(3, $horarios);
    }

    // ── Reservas existentes bloquean slots ───────────────────────────────────

    public function test_horario_ocupado_no_aparece_como_disponible(): void
    {
        $this->crearRegla('09:00', '11:00');

        $clienteAux = User::create([
            'name' => 'C', 'email' => 'c@c.com',
            'password' => 'p', 'rol' => 'cliente',
        ]);
        Reserva::create([
            'servicio_id'      => $this->servicio->id,
            'cliente_id'       => $clienteAux->id,
            'profesional_id'   => $this->profesionalUser->id,
            'fecha_hora'       => $this->fechaTest->copy()->setTime(9, 0),
            'duracion_minutos' => 60,
            'estado'           => 'confirmada',
            'modalidad'        => 'remota',
        ]);

        $horarios = $this->getJson($this->urlHorarios())->json();
        $inicios  = array_column($horarios, 'inicio');

        $this->assertNotContains($this->fechaTest->toDateString() . ' 09:00:00', $inicios);
    }

    public function test_reserva_cancelada_no_bloquea_el_slot(): void
    {
        $this->crearRegla('09:00', '11:00');

        $clienteAux = User::create([
            'name' => 'C', 'email' => 'c@c.com',
            'password' => 'p', 'rol' => 'cliente',
        ]);
        Reserva::create([
            'servicio_id'      => $this->servicio->id,
            'cliente_id'       => $clienteAux->id,
            'profesional_id'   => $this->profesionalUser->id,
            'fecha_hora'       => $this->fechaTest->copy()->setTime(9, 0),
            'duracion_minutos' => 60,
            'estado'           => 'cancelada', // cancelada → no bloquea
            'modalidad'        => 'remota',
        ]);

        $horarios = $this->getJson($this->urlHorarios())->json();
        $inicios  = array_column($horarios, 'inicio');

        $this->assertContains($this->fechaTest->toDateString() . ' 09:00:00', $inicios);
    }

    // ── Excepciones ──────────────────────────────────────────────────────────

    public function test_dia_bloqueado_por_excepcion_devuelve_sin_horarios(): void
    {
        $this->crearRegla('09:00', '18:00');

        ExcepcionDisponibilidad::create([
            'profesional_id' => $this->profesional->id,
            'fecha'          => $this->fechaTest->toDateString(),
            'disponible'     => false,
            'motivo'         => 'Feriado',
        ]);

        $this->getJson($this->urlHorarios())
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_excepcion_disponible_no_bloquea_horarios(): void
    {
        $this->crearRegla('09:00', '11:00');

        ExcepcionDisponibilidad::create([
            'profesional_id' => $this->profesional->id,
            'fecha'          => $this->fechaTest->toDateString(),
            'disponible'     => true, // disponible = true → no bloquea
        ]);

        $horarios = $this->getJson($this->urlHorarios())->json();
        $this->assertNotEmpty($horarios);
    }

    // ── Buffers ───────────────────────────────────────────────────────────────

    public function test_buffer_despues_elimina_slot_contiguo(): void
    {
        // Servicio 60 min + 30 min buffer después = 90 min por slot
        // Regla 09:00-11:00 → solo cabe el slot de 09:00 (termina 10:30), no el de 10:00
        $this->crearRegla('09:00', '11:00', bufferAntes: 0, bufferDespues: 30);

        $horarios = $this->getJson($this->urlHorarios())->json();
        $inicios  = array_column($horarios, 'inicio');

        $this->assertContains($this->fechaTest->toDateString() . ' 09:00:00', $inicios);
        $this->assertNotContains($this->fechaTest->toDateString() . ' 10:00:00', $inicios);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExcepcionDisponibilidad;
use App\Models\Profesional;
use App\Models\ReglaDisponibilidad;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para la disponibilidad del profesional.
 * Gestiona reglas de horario, excepciones y el motor de cálculo
 * de horarios disponibles para un servicio en una fecha dada.
 */
class DisponibilidadController extends Controller
{
    /**
     * GET /api/profesionales/{profesional}/disponibilidad/reglas
     * Lista las reglas de disponibilidad activas del profesional.
     */
    public function reglas(Profesional $profesional): JsonResponse
    {
        $reglas = $profesional->reglasDisponibilidad()
            ->where('activo', true)
            ->get();

        return response()->json($reglas);
    }

    /**
     * POST /api/profesionales/{profesional}/disponibilidad/reglas
     * Crea una nueva regla de disponibilidad (horario por día de semana).
     */
    public function guardarRegla(Request $request, Profesional $profesional): JsonResponse
    {
        $this->authorize('manage', $profesional);

        $validados = $request->validate([
            'dia_semana'             => 'required|integer|between:0,6',
            'hora_inicio'            => 'required|date_format:H:i',
            'hora_fin'               => 'required|date_format:H:i|after:hora_inicio',
            'buffer_antes_minutos'   => 'nullable|integer|min:0',
            'buffer_despues_minutos' => 'nullable|integer|min:0',
        ]);

        $regla = $profesional->reglasDisponibilidad()->create($validados);

        return response()->json($regla, 201);
    }

    /**
     * PUT /api/profesionales/{profesional}/disponibilidad/reglas/{regla}
     * Actualiza una regla de disponibilidad existente.
     */
    public function actualizarRegla(Request $request, Profesional $profesional, ReglaDisponibilidad $regla): JsonResponse
    {
        $this->authorize('manage', $profesional);

        $validados = $request->validate([
            'dia_semana'             => 'sometimes|integer|between:0,6',
            'hora_inicio'            => 'sometimes|date_format:H:i',
            'hora_fin'               => 'sometimes|date_format:H:i',
            'buffer_antes_minutos'   => 'nullable|integer|min:0',
            'buffer_despues_minutos' => 'nullable|integer|min:0',
            'activo'                 => 'sometimes|boolean',
        ]);

        $regla->update($validados);

        return response()->json($regla);
    }

    /**
     * DELETE /api/profesionales/{profesional}/disponibilidad/reglas/{regla}
     * Elimina una regla de disponibilidad.
     */
    public function eliminarRegla(Profesional $profesional, ReglaDisponibilidad $regla): JsonResponse
    {
        $this->authorize('manage', $profesional);

        $regla->delete();

        return response()->json(['mensaje' => 'Regla eliminada correctamente.']);
    }

    /**
     * GET /api/profesionales/{profesional}/disponibilidad/excepciones
     * Lista las excepciones de disponibilidad futuras del profesional.
     */
    public function excepciones(Profesional $profesional): JsonResponse
    {
        $excepciones = $profesional->excepcionesDisponibilidad()
            ->where('fecha', '>=', now()->toDateString())
            ->orderBy('fecha')
            ->get();

        return response()->json($excepciones);
    }

    /**
     * POST /api/profesionales/{profesional}/disponibilidad/excepciones
     * Registra una excepción (día bloqueado o día extra habilitado).
     */
    public function guardarExcepcion(Request $request, Profesional $profesional): JsonResponse
    {
        $this->authorize('manage', $profesional);

        $validados = $request->validate([
            'fecha'       => 'required|date|after_or_equal:today',
            'motivo'      => 'nullable|string|max:255',
            'disponible'  => 'boolean',
        ]);

        // updateOrCreate para no duplicar si ya existe esa fecha
        $excepcion = $profesional->excepcionesDisponibilidad()->updateOrCreate(
            ['fecha' => $validados['fecha']],
            $validados
        );

        return response()->json($excepcion, 201);
    }

    /**
     * GET /api/profesionales/{profesional}/disponibilidad/horarios
     * Calcula los horarios disponibles de un profesional para una fecha y servicio dados.
     * Tiene en cuenta las reglas de horario, excepciones y reservas existentes.
     */
    public function horarios(Request $request, Profesional $profesional): JsonResponse
    {
        $request->validate([
            'fecha'       => 'required|date|after_or_equal:today',
            'servicio_id' => 'required|exists:servicios,id',
        ]);

        $fecha    = Carbon::parse($request->fecha);
        $servicio = $profesional->servicios()->findOrFail($request->servicio_id);
        $diaSemana = $fecha->dayOfWeek;

        // Si hay excepción de día bloqueado, no hay horarios
        $excepcion = $profesional->excepcionesDisponibilidad()
            ->where('fecha', $fecha->toDateString())
            ->first();

        if ($excepcion && !$excepcion->disponible) {
            return response()->json([]);
        }

        // Buscar reglas de ese día de la semana
        $reglas = $profesional->reglasDisponibilidad()
            ->where('dia_semana', $diaSemana)
            ->where('activo', true)
            ->get();

        if ($reglas->isEmpty()) {
            return response()->json([]);
        }

        // Reservas ya existentes para ese profesional en esa fecha
        $reservasExistentes = Reserva::where('profesional_id', $profesional->user_id)
            ->whereDate('fecha_hora', $fecha->toDateString())
            ->whereNotIn('estado', ['cancelada', 'no_asistida'])
            ->get();

        $horariosDisponibles = [];

        foreach ($reglas as $regla) {
            $actual   = Carbon::parse($fecha->toDateString() . ' ' . $regla->hora_inicio);
            $finRegla = Carbon::parse($fecha->toDateString() . ' ' . $regla->hora_fin);
            $duracion = $servicio->duracion_minutos + $regla->buffer_despues_minutos;

            while ($actual->copy()->addMinutes($duracion)->lte($finRegla)) {
                $finSlot    = $actual->copy()->addMinutes($servicio->duracion_minutos);
                $disponible = true;

                foreach ($reservasExistentes as $reserva) {
                    $inicioBuffer = Carbon::parse($reserva->fecha_hora)
                        ->subMinutes($regla->buffer_antes_minutos);
                    $finBuffer    = Carbon::parse($reserva->fecha_hora)
                        ->addMinutes($reserva->duracion_minutos + $regla->buffer_despues_minutos);

                    if ($actual->lt($finBuffer) && $finSlot->gt($inicioBuffer)) {
                        $disponible = false;
                        break;
                    }
                }

                if ($disponible) {
                    $horariosDisponibles[] = [
                        'inicio' => $actual->format('Y-m-d H:i:s'),
                        'fin'    => $finSlot->format('Y-m-d H:i:s'),
                    ];
                }

                $actual->addMinutes($servicio->duracion_minutos + $regla->buffer_despues_minutos);
            }
        }

        return response()->json($horariosDisponibles);
    }
}

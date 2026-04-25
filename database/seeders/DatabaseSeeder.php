<?php

namespace Database\Seeders;

use App\Models\PaqueteServicio;
use App\Models\Profesional;
use App\Models\ReglaDisponibilidad;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Administrador del sistema
        User::create([
            'name'     => 'Administrador',
            'email'    => 'admin@serviciospro.com',
            'password' => 'admin1234',
            'rol'      => 'admin',
        ]);

        // Usuario profesional de ejemplo
        $usuarioProfesional = User::create([
            'name'     => 'Laura Pérez',
            'email'    => 'laura@ejemplo.com',
            'password' => 'password',
            'rol'      => 'profesional',
            'telefono' => '099123456',
        ]);

        // Perfil profesional de Laura
        $profesional = Profesional::create([
            'user_id'        => $usuarioProfesional->id,
            'nombre_negocio' => 'Laura Coaching',
            'bio'            => 'Coach certificada con 5 años de experiencia en desarrollo personal y profesional.',
            'modalidad'      => 'hibrida',
            'ciudad'         => 'Montevideo',
            'pais'           => 'UY',
            'horas_cancelacion' => 24,
        ]);

        // Servicio de coaching individual
        $servicio = Servicio::create([
            'profesional_id'   => $profesional->id,
            'nombre'           => 'Sesión de coaching personal',
            'descripcion'      => 'Sesión individual de 60 minutos para trabajar objetivos personales y profesionales.',
            'precio'           => 50.00,
            'duracion_minutos' => 60,
            'modalidad'        => 'hibrida',
        ]);

        // Paquete de 6 sesiones con descuento
        PaqueteServicio::create([
            'servicio_id'       => $servicio->id,
            'nombre'            => 'Paquete 6 sesiones',
            'descripcion'       => '6 sesiones de coaching con 15% de descuento.',
            'cantidad_sesiones' => 6,
            'precio'            => 255.00,
        ]);

        // Reglas de disponibilidad: lunes a viernes de 9:00 a 18:00
        $diasLaborales = [1, 2, 3, 4, 5]; // 1=lunes ... 5=viernes
        foreach ($diasLaborales as $dia) {
            ReglaDisponibilidad::create([
                'profesional_id'         => $profesional->id,
                'dia_semana'             => $dia,
                'hora_inicio'            => '09:00',
                'hora_fin'               => '18:00',
                'buffer_despues_minutos' => 15,
            ]);
        }

        // Usuario cliente de ejemplo
        $usuarioCliente = User::create([
            'name'     => 'Carlos Gómez',
            'email'    => 'carlos@ejemplo.com',
            'password' => 'password',
            'rol'      => 'cliente',
            'telefono' => '091987654',
        ]);

        // Reserva de ejemplo (confirmada, en 3 días)
        Reserva::create([
            'servicio_id'      => $servicio->id,
            'cliente_id'       => $usuarioCliente->id,
            'profesional_id'   => $usuarioProfesional->id,
            'fecha_hora'       => now()->addDays(3)->setHour(10)->setMinute(0),
            'duracion_minutos' => 60,
            'estado'           => 'confirmada',
            'modalidad'        => 'remota',
            'notas'            => 'Primera sesión de presentación.',
        ]);
    }
}

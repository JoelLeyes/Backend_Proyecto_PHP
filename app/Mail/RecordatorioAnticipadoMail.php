<?php

namespace App\Mail;

use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RecordatorioAnticipadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reserva $reserva,
        public int $horas,
    ) {
         $this->horas = abs($horas);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Tu cita es en {$this->horas} horas");
    }

    public function content(): Content
    {
        $fechaHora = Carbon::parse($this->reserva->fecha_hora)
            ->setTimezone(config('app.timezone', 'America/Montevideo'))
            ->translatedFormat('d/m/Y \a \l\a\s H:i');

        return new Content(
            view: 'emails.recordatorio-anticipado',
            with: [
                'reserva'   => $this->reserva,
                'fechaHora' => $fechaHora,
                'horas'     => $this->horas,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

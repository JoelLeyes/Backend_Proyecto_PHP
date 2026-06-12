<?php

namespace App\Mail;

use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RecordatorioReservaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reserva $reserva)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Falta una hora para tu reserva');
    }

    public function content(): Content
    {
        $fechaHora = Carbon::parse($this->reserva->fecha_hora)
            ->setTimezone(config('app.timezone', 'America/Montevideo'))
            ->translatedFormat('d/m/Y \a \l\a\s H:i');

        return new Content(
            view: 'emails.recordatorio-reserva',
            with: [
                'reserva' => $this->reserva,
                'fechaHora' => $fechaHora,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

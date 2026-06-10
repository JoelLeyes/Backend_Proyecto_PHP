<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Recuperación de contraseña — AgendaOnline');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.recuperar-contrasena',
            with: ['user' => $this->user, 'url' => $this->url],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $code,
        public int $expiresInMinutes = 15,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify your AgroAide email address',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verify-email-code',
            text: 'emails.verify-email-code-text',
            with: [
                'name' => $this->user->name,
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
                'appName' => config('app.name', 'AgroAide'),
            ],
        );
    }
}

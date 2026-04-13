<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeMember extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $memberName,
        public string $memberEmail,
        public string $tempPassword,
        public string $roleName,
        public string $addedBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to eWards PMS - Your Account is Ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome-member',
        );
    }
}

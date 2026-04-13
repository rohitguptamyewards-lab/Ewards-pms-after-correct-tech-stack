<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProjectAssigned extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $projectName,
        public int $projectId,
        public string $assignedRole,
        public string $assignedBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been assigned to: {$this->projectName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.project-assigned',
        );
    }
}

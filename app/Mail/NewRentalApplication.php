<?php

namespace App\Mail;

use App\Models\RentalApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewRentalApplication extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RentalApplication $application) {}

    public function envelope(): Envelope
    {
        $name = $this->application->full_name;

        return new Envelope(
            subject: "New rental application from {$name}",
            // So hitting reply in the inbox answers the applicant.
            replyTo: [new Address($this->application->email, $name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.applications.new',
            with: [
                'application' => $this->application,
                'adminUrl' => rtrim((string) config('services.frontend.url'), '/').'/a/applications',
            ],
        );
    }
}

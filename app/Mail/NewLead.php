<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewLead extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Lead $lead) {}

    public function envelope(): Envelope
    {
        $name = "{$this->lead->firstname} {$this->lead->lastname}";

        return new Envelope(
            subject: "New lead from {$name}",
            // So hitting reply in the inbox answers the person who filled in the form.
            replyTo: [new Address($this->lead->email, $name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.leads.new',
            with: ['lead' => $this->lead],
        );
    }
}

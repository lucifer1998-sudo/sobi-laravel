<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The confirmation the person who filled in the form gets back, so they know
 * the message arrived rather than wondering. NewLead is the other side of the
 * same submission, sent to the team.
 */
class LeadReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Lead $lead) {}

    public function envelope(): Envelope
    {
        $adminEmail = config('mail.admin_address');

        return new Envelope(
            subject: __('emails.lead_received.subject'),
            // A reply should reach the team, not the address the site sends from.
            replyTo: $adminEmail ? [new Address($adminEmail, config('app.name'))] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.leads.received',
            with: [
                'lead' => $this->lead,
                'listingsUrl' => $this->listingsUrl(),
            ],
        );
    }

    /**
     * The stays page, in the language this email is being written in. English
     * has no prefix on the site. Returns null when the frontend URL is not
     * configured, and the template then leaves the button out.
     */
    protected function listingsUrl(): ?string
    {
        $frontend = config('services.frontend.url');

        if (! $frontend) {
            return null;
        }

        $locale = app()->getLocale();
        $prefix = $locale === config('app.fallback_locale') ? '' : "/{$locale}";

        return rtrim($frontend, '/').$prefix.'/listings';
    }
}

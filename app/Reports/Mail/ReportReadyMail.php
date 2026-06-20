<?php

namespace App\Reports\Mail;

use App\Models\Central\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Report $report) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Report is Ready');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reports.ready');
    }
}

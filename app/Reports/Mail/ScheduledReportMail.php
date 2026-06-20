<?php

namespace App\Reports\Mail;

use App\Models\Central\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduledReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Report $report) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Scheduled Report: {$this->report->type}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reports.scheduled');
    }

    /** @return Attachment[] */
    public function attachments(): array
    {
        if ($this->report->file_path === null) {
            return [];
        }

        return [
            Attachment::fromStorage($this->report->file_path)
                ->as("{$this->report->type}.{$this->report->format->value}"),
        ];
    }
}

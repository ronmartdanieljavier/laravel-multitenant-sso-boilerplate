<?php

namespace App\Admin\Mail;

use App\Data\Repositories\Central\UserRepositoryData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class UserInvitationMail extends Mailable
{
    use Queueable;

    public string $acceptUrl;

    public function __construct(public UserRepositoryData $user)
    {
        $this->acceptUrl = route('invitation.accept', ['token' => $user->invitationToken]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You've been invited");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invitation');
    }
}

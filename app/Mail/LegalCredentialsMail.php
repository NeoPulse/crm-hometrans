<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LegalCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Initialize the message with solicitor context and new password.
     */
    public function __construct(public User $legal, public string $password, public string $loginUrl)
    {
        // Persist the legal, password, and login URL for rendering.
    }

    /**
     * Build the email using the solicitor-specific credentials template.
     */
    public function build(): static
    {
        // Apply a clear subject line and dedicated email view.
        return $this
            ->subject('Your solicitor portal access')
            ->view('emails.legal-credentials');
    }
}

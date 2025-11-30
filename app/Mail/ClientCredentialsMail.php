<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClientCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance with client context and password.
     */
    public function __construct(public User $client, public string $password, public string $loginUrl)
    {
        // Store the client, password, and login URL for the email view.
    }

    /**
     * Build the message with the dedicated client credentials template.
     */
    public function build(): static
    {
        // Use a tailored subject and view for client access details.
        return $this
            ->subject('Your client portal access')
            ->view('emails.client-credentials');
    }
}

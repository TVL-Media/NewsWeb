<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminInvitation extends Notification
{
    public function __construct(public string $url, public string $inviter) {}

    public function via($n): array
    {
        return ['mail'];
    }

    public function toMail($n): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been invited to '.setting('general.platform_name', 'Newsroom').' Admin')
            ->line("{$this->inviter} invited you to join the admin panel as {$n->roleNames()}.")
            ->action('Set up your account', $this->url)
            ->line('This link expires in 48 hours.');
    }
}

<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email to editors when a reel waits for approval or failed to process. (The bell notice is sent by NotifyReelEditors.) */
class ReelNeedsAttention extends Notification
{
    public function __construct(public string $title, public string $why, public string $url, public bool $failed = false) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting($this->failed ? 'A reel could not be made' : 'A reel is waiting for review')
            ->line($this->why)
            ->action('Open the reel', $this->url);
    }
}

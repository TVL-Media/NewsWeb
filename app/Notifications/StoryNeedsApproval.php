<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email to editors when a story waits for approval. (The in-panel notice is sent by NotifyEditor.) */
class StoryNeedsApproval extends Notification
{
    public function __construct(public string $headline, public string $why, public string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Needs approval: '.$this->headline)
            ->greeting('A story is waiting for review')
            ->line($this->headline)
            ->line($this->why ?: 'It did not pass the automatic publishing checks.')
            ->action('Open the approval queue', $this->url);
    }
}

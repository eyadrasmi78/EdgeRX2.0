<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Single notification class fan-out to both DB (in-app bell) and mail channels.
 * One class kept deliberately simple — for the demo, all 9 event types share this shape.
 *
 * Note on async: we deliberately do NOT implement ShouldQueue because the
 * production deploy uses the `database` queue driver but no dedicated queue
 * worker. Queued notifications would sit until the hourly scheduler picks
 * them up. Instead, we use the `$email` constructor flag — set to false
 * for fan-out paths (admin notification on registration) so only the
 * cheap DB row is written, no SMTP latency.
 */
class EdgeRxNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $kind,        // 'registration_pending' | 'registration_approved' | 'order_created' | ...
        public string $title,       // Bell + email subject
        public string $message,     // Bell + email body
        public ?string $actionUrl = null,
        public array $data = [],    // Extra metadata for the bell (orderId, productId, etc.)
        public bool $email = true,  // Some events are bell-only
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($this->email) $channels[] = 'mail';
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $msg = (new MailMessage)
            ->subject('[EdgeRX] ' . $this->title)
            ->greeting('Hi ' . ($notifiable->name ?? 'there') . ',')
            ->line($this->message);

        if ($this->actionUrl) {
            $msg->action('Open EdgeRX', $this->actionUrl);
        }
        return $msg
            ->line('Thank you for using EdgeRX.')
            ->salutation('— EdgeRX')
            ;
    }

    public function toArray(object $notifiable): array
    {
        // Keep title and message as separate fields — the frontend renders them
        // distinctly (bold title + body text). Composing them here was redundant.
        return [
            'type' => $this->mapType(),
            'kind' => $this->kind,
            'title' => $this->title,
            'message' => $this->message,
            'actionUrl' => $this->actionUrl,
            'meta' => $this->data,
        ];
    }

    private function mapType(): string
    {
        return match (true) {
            str_contains($this->kind, 'rejected'), str_contains($this->kind, 'declined') => 'warning',
            str_contains($this->kind, 'approved'), str_contains($this->kind, 'completed'), str_contains($this->kind, 'accepted') => 'success',
            default => 'info',
        };
    }
}

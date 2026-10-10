<?php

namespace App\Notifications;

use App\Models\UserNotificationChannel;
use App\Models\UserNotificationSent;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

/**
 * Tells the user that it is time to reach out to a contact they wanted to stay in touch with.
 */
class StayInTouchTriggered extends Notification
{
    use Queueable;

    public function __construct(
        private UserNotificationChannel $channel,
        private string $contactName,
        private int $daysSinceLastInteraction,
        private string $contactUrl,
    ) {}

    public function via($notifiable): array
    {
        return match ($this->channel->type) {
            UserNotificationChannel::TYPE_EMAIL => ['mail'],
            UserNotificationChannel::TYPE_TELEGRAM => ['telegram'],
            default => [],
        };
    }

    public function toMail($notifiable): MailMessage
    {
        $this->log();

        return (new MailMessage)
            ->subject(trans('Time to reach out to :name', ['name' => $this->contactName]))
            ->line(trans('You wanted to stay in touch with :name, but nothing happened for :count days.', [
                'name' => $this->contactName,
                'count' => $this->daysSinceLastInteraction,
            ]))
            ->line(trans('A short message is often enough to keep a relationship alive.'))
            ->action(trans('Open the profile of :name', ['name' => $this->contactName]), $this->contactUrl);
    }

    public function toTelegram($notifiable): TelegramMessage
    {
        $this->log();

        return TelegramMessage::create()
            ->content(trans('👋 Time to reach out to :name: nothing happened for :count days.', [
                'name' => $this->contactName,
                'count' => $this->daysSinceLastInteraction,
            ]).' '.$this->contactUrl)
            ->to($this->channel->content);
    }

    private function log(): void
    {
        UserNotificationSent::create([
            'user_notification_channel_id' => $this->channel->id,
            'sent_at' => Carbon::now(),
            'subject_line' => trans('Time to reach out to :name', ['name' => $this->contactName]),
        ]);
    }

    public function toArray($notifiable): array
    {
        return [];
    }
}

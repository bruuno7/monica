<?php

namespace App\Domains\Contact\ManageStayInTouch\Jobs;

use App\Helpers\NameHelper;
use App\Helpers\StayInTouchHelper;
use App\Models\Contact;
use App\Models\User;
use App\Models\UserNotificationChannel;
use App\Models\Vault;
use App\Notifications\StayInTouchTriggered;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Once a day, look for contacts the user wanted to stay in touch with and that
 * went quiet for longer than planned, and tell the user through their channels.
 * A contact is flagged once; it is flagged again only after a new interaction
 * (or after a full extra period of silence).
 */
class ProcessStayInTouch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        Contact::whereNotNull('stay_in_touch_frequency')
            ->where('listed', true)
            ->with('vault.users')
            ->chunkById(200, function ($contacts) {
                foreach ($contacts as $contact) {
                    if ($this->shouldNotify($contact)) {
                        $this->notify($contact);
                    }
                }
            });
    }

    private function shouldNotify(Contact $contact): bool
    {
        if (! StayInTouchHelper::isDue($contact)) {
            return false;
        }

        if ($contact->stay_in_touch_triggered_at === null) {
            return true;
        }

        // flagged already: wait a full period before insisting
        return $contact->stay_in_touch_triggered_at->copy()->addDays($contact->stay_in_touch_frequency)->isPast();
    }

    private function notify(Contact $contact): void
    {
        $days = StayInTouchHelper::daysSinceLastInteraction($contact) ?? 0;

        $contact->vault->users
            ->filter(fn (User $user) => $user->pivot->permission <= Vault::PERMISSION_EDIT)
            ->each(function (User $user) use ($contact, $days) {
                $channels = $user->notificationChannels()
                    ->where('active', true)
                    ->whereNotNull('verified_at')
                    ->get();

                foreach ($channels as $channel) {
                    $this->send($channel, $contact, $user, $days);
                }
            });

        $contact->stay_in_touch_triggered_at = Carbon::now();
        $contact->saveQuietly();
    }

    private function send(UserNotificationChannel $channel, Contact $contact, User $user, int $days): void
    {
        $type = match ($channel->type) {
            UserNotificationChannel::TYPE_EMAIL => 'mail',
            UserNotificationChannel::TYPE_TELEGRAM => 'telegram',
            default => null,
        };

        if ($type === null) {
            return;
        }

        try {
            Notification::route($type, $channel->content)
                ->notify((new StayInTouchTriggered(
                    $channel,
                    NameHelper::formatContactName($user, $contact),
                    $days,
                    route('contact.show', ['vault' => $contact->vault_id, 'contact' => $contact->id]),
                ))->locale($user->locale));
        } catch (\Exception $e) {
            Log::error('Error sending stay in touch notification', [
                'message' => $e->getMessage(),
                'contact' => $contact->id,
            ]);
        }
    }
}

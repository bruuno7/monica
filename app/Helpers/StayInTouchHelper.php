<?php

namespace App\Helpers;

use App\Models\Contact;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StayInTouchHelper
{
    /** No frequency set for this contact. */
    public const STATUS_OFF = 'off';

    /** Last interaction is within the desired frequency. */
    public const STATUS_FRESH = 'fresh';

    /** The frequency is exceeded, but not by much yet. */
    public const STATUS_COOLING = 'cooling';

    /** The contact has been neglected for a long time. */
    public const STATUS_COLD = 'cold';

    /**
     * The frequencies the user can choose from, in days.
     */
    public const FREQUENCIES = [7, 14, 30, 60, 90, 180, 365];

    /**
     * Number of days since something last happened with the contact.
     * Falls back to the creation date when nothing was ever recorded.
     */
    public static function daysSinceLastInteraction(Contact $contact): ?int
    {
        $reference = $contact->last_interaction_at ?? $contact->created_at;

        return $reference ? (int) $reference->copy()->startOfDay()->diffInDays(Carbon::now()->startOfDay(), false) : null;
    }

    public static function status(Contact $contact): string
    {
        if ($contact->stay_in_touch_frequency === null) {
            return self::STATUS_OFF;
        }

        $days = self::daysSinceLastInteraction($contact);

        if ($days === null || $days <= $contact->stay_in_touch_frequency) {
            return self::STATUS_FRESH;
        }

        if ($days <= $contact->stay_in_touch_frequency * 1.5) {
            return self::STATUS_COOLING;
        }

        return self::STATUS_COLD;
    }

    /**
     * Whether the user should be told that it is time to reach out.
     */
    public static function isDue(Contact $contact): bool
    {
        return in_array(self::status($contact), [self::STATUS_COOLING, self::STATUS_COLD]);
    }

    public static function frequencyLabel(?int $days): string
    {
        return match ($days) {
            null => trans('No goal'),
            7 => trans('Every week'),
            14 => trans('Every two weeks'),
            30 => trans('Every month'),
            60 => trans('Every two months'),
            90 => trans('Every three months'),
            180 => trans('Twice a year'),
            365 => trans('Once a year'),
            default => trans_choice('Every :count day|Every :count days', $days, ['count' => $days]),
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_FRESH => trans('In touch'),
            self::STATUS_COOLING => trans('Time to reach out'),
            self::STATUS_COLD => trans('Losing touch'),
            default => '',
        };
    }

    /**
     * Everything the interface needs to display and edit the stay in touch goal.
     */
    public static function dto(Contact $contact, User $user): array
    {
        $status = self::status($contact);
        $days = self::daysSinceLastInteraction($contact);

        return [
            'frequency' => $contact->stay_in_touch_frequency,
            'frequency_label' => self::frequencyLabel($contact->stay_in_touch_frequency),
            'status' => $status,
            'status_label' => self::statusLabel($status),
            'last_interaction_at' => $contact->last_interaction_at ? DateHelper::format($contact->last_interaction_at, $user) : null,
            'days_since_last_interaction' => $contact->last_interaction_at ? $days : null,
            'frequencies' => collect(self::FREQUENCIES)->map(fn (int $frequency) => [
                'id' => $frequency,
                'label' => self::frequencyLabel($frequency),
            ])->values(),
            'url' => [
                'update' => route('contact.stay_in_touch.update', [
                    'vault' => $contact->vault_id,
                    'contact' => $contact->id,
                ]),
                'touch' => route('contact.stay_in_touch.touch', [
                    'vault' => $contact->vault_id,
                    'contact' => $contact->id,
                ]),
            ],
        ];
    }

    /**
     * The contacts of a vault that are due for a catch-up, the coldest first.
     *
     * @return Collection<int, Contact>
     */
    public static function dueContacts(string $vaultId, int $limit = 10): Collection
    {
        return Contact::where('vault_id', $vaultId)
            ->where('listed', true)
            ->whereNotNull('stay_in_touch_frequency')
            ->get()
            ->filter(fn (Contact $contact) => self::isDue($contact))
            ->sortByDesc(fn (Contact $contact) => (self::daysSinceLastInteraction($contact) ?? 0) - $contact->stay_in_touch_frequency)
            ->take($limit)
            ->values();
    }
}

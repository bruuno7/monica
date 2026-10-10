<?php

namespace App\Domains\Contact\ManageContact\Web\ViewHelpers;

use App\Helpers\DateHelper;
use App\Helpers\StayInTouchHelper;
use App\Models\Contact;
use App\Models\ContactImportantDate;
use App\Models\Label;
use App\Models\User;
use App\Models\Vault;
use Carbon\Carbon;

class ContactIndexViewHelper
{
    public static function data($contacts, Vault $vault, ?int $labelId, User $user): array
    {
        $contactCollection = collect();
        foreach ($contacts as $contact) {
            $contactCollection->push(self::dtoContact($contact, $vault, $user));
        }

        $labelsCollection = $vault->labels()
            ->withCount('contacts')
            ->get()
            ->sortByCollator('name')
            ->filter(fn (Label $label): bool => $label->contacts_count > 0)
            ->map(fn (Label $label) => [
                'id' => $label->id,
                'name' => $label->name,
                'count' => $label->contacts_count,
                'url' => [
                    'show' => route('contact.label.index', [
                        'vault' => $label->vault_id,
                        'label' => $label->id,
                    ]),
                ],
            ]);

        return [
            'contacts' => $contactCollection,
            'labels' => $labelsCollection,
            'current_label' => $labelId,
            'user_contact_sort_order' => $user->contact_sort_order,
            'contact_sort_orders' => collect([
                [
                    'id' => User::CONTACT_SORT_ORDER_LAST_UPDATED,
                    'name' => trans('By last updated'),
                ],
                [
                    'id' => User::CONTACT_SORT_ORDER_LAST_INTERACTION,
                    'name' => trans('By last interaction'),
                ],
                [
                    'id' => User::CONTACT_SORT_ORDER_ASC,
                    'name' => trans('From A to Z'),
                ],
                [
                    'id' => User::CONTACT_SORT_ORDER_DESC,
                    'name' => trans('From Z to A'),
                ],
            ]),
            'url' => [
                'contact' => [
                    'index' => route('contact.index', [
                        'vault' => $vault->id,
                    ]),
                    'create' => route('contact.create', [
                        'vault' => $vault->id,
                    ]),
                    'duplicates' => route('contact.duplicates.index', [
                        'vault' => $vault->id,
                    ]),
                ],
                'sort' => [
                    'update' => route('contact.sort.update', [
                        'vault' => $vault->id,
                    ]),
                ],
            ],
        ];
    }

    public static function dtoContact(Contact $contact, Vault $vault, User $user): array
    {
        $status = StayInTouchHelper::status($contact);
        $nextDate = self::nextImportantDate($contact);

        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'avatar' => $contact->avatar,
            'job' => collect([$contact->job_position, $contact->company?->name])->filter()->join(' · '),
            'labels' => $contact->labels->map(fn (Label $label) => [
                'id' => $label->id,
                'name' => $label->name,
                'bg_color' => $label->bg_color,
                'text_color' => $label->text_color,
            ])->values(),
            'last_interaction' => [
                'date' => $contact->last_interaction_at ? DateHelper::format($contact->last_interaction_at, $user) : null,
                'days_ago' => $contact->last_interaction_at ? StayInTouchHelper::daysSinceLastInteraction($contact) : null,
            ],
            'stay_in_touch' => [
                'status' => $status,
                'status_label' => StayInTouchHelper::statusLabel($status),
            ],
            'next_important_date' => $nextDate,
            'url' => [
                'show' => route('contact.show', [
                    'vault' => $vault->id,
                    'contact' => $contact->id,
                ]),
            ],
        ];
    }

    /**
     * The next important date (birthday, anniversary…) happening within the next 30 days.
     */
    private static function nextImportantDate(Contact $contact): ?array
    {
        $today = Carbon::now()->startOfDay();

        return $contact->importantDates
            ->filter(fn (ContactImportantDate $date) => $date->day !== null && $date->month !== null)
            ->map(function (ContactImportantDate $date) use ($today) {
                $next = Carbon::createFromDate($today->year, $date->month, $date->day)->startOfDay();
                if ($next->lessThan($today)) {
                    $next->addYear();
                }

                return [
                    'label' => $date->label,
                    'in_days' => (int) $today->diffInDays($next),
                ];
            })
            ->filter(fn (array $date) => $date['in_days'] <= 30)
            ->sortBy('in_days')
            ->first();
    }
}

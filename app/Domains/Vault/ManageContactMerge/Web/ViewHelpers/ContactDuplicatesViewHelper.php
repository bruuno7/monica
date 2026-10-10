<?php

namespace App\Domains\Vault\ManageContactMerge\Web\ViewHelpers;

use App\Helpers\DateHelper;
use App\Models\Contact;
use App\Models\ContactInformation;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

use function Safe\preg_replace;

class ContactDuplicatesViewHelper
{
    public static function data(Vault $vault, User $user): array
    {
        return [
            'pairs' => self::pairs($vault, $user),
            'url' => [
                'merge' => route('contact.merge.store', ['vault' => $vault->id]),
                'contacts' => route('contact.index', ['vault' => $vault->id]),
                'search_contacts_only' => route('vault.user.search.index', ['vault' => $vault->id]),
                'get_most_consulted_contacts' => route('vault.user.search.mostconsulted', ['vault' => $vault->id]),
            ],
        ];
    }

    /**
     * Pairs of contacts that are probably the same person: same name once
     * normalised, or a shared email address or phone number.
     *
     * @return Collection<int, array>
     */
    public static function pairs(Vault $vault, User $user): Collection
    {
        $contacts = $vault->contacts()
            ->with(['contactInformations.contactInformationType', 'notes', 'calls', 'importantDates'])
            ->where('listed', true)
            ->get();

        $pairs = collect();
        $seen = [];

        $register = function (Contact $a, Contact $b, string $reason) use (&$pairs, &$seen, $user) {
            [$first, $second] = $a->created_at <= $b->created_at ? [$a, $b] : [$b, $a];
            $key = $first->id.'|'.$second->id;
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $pairs->push([
                'id' => $key,
                'reason' => $reason,
                'contacts' => [self::dto($first, $user), self::dto($second, $user)],
            ]);
        };

        // same name
        $contacts->groupBy(fn (Contact $contact) => self::normaliseName($contact))
            ->filter(fn (Collection $group, string $name) => $name !== '' && $group->count() > 1)
            ->each(function (Collection $group) use ($register) {
                $group = $group->values();
                for ($i = 0; $i < $group->count() - 1; $i++) {
                    $register($group[$i], $group[$i + 1], trans('Same name'));
                }
            });

        // same email or phone
        $byValue = [];
        foreach ($contacts as $contact) {
            foreach ($contact->contactInformations as $information) {
                $type = $information->contactInformationType?->type;
                if (! in_array($type, ['email', 'phone'])) {
                    continue;
                }
                $value = $type === 'email'
                    ? Str::lower(trim($information->data ?? ''))
                    : preg_replace('/\D+/', '', $information->data ?? '');
                if ($value === '' || $value === null) {
                    continue;
                }
                $byValue[$type.':'.$value][$contact->id] = $contact;
            }
        }
        foreach ($byValue as $key => $owners) {
            $owners = array_values($owners);
            for ($i = 0; $i < count($owners) - 1; $i++) {
                $register($owners[$i], $owners[$i + 1], Str::startsWith($key, 'email:') ? trans('Same email address') : trans('Same phone number'));
            }
        }

        return $pairs->values();
    }

    public static function dto(Contact $contact, User $user): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'avatar' => $contact->avatar,
            'created_at' => DateHelper::format($contact->created_at, $user),
            'can_be_deleted' => $contact->can_be_deleted,
            'counts' => [
                'notes' => $contact->notes->count(),
                'calls' => $contact->calls->count(),
                'important_dates' => $contact->importantDates->count(),
                'contact_information' => $contact->contactInformations->count(),
            ],
            'contact_information' => $contact->contactInformations
                ->map(fn (ContactInformation $information) => $information->data)
                ->filter()
                ->take(4)
                ->values(),
            'url' => [
                'show' => route('contact.show', ['vault' => $contact->vault_id, 'contact' => $contact->id]),
            ],
        ];
    }

    private static function normaliseName(Contact $contact): string
    {
        $parts = collect([$contact->first_name, $contact->last_name])
            ->filter()
            ->map(fn (string $part) => Str::lower(Str::ascii(trim($part))))
            ->sort()
            ->values();

        return $parts->count() > 1 ? $parts->join(' ') : '';
    }
}

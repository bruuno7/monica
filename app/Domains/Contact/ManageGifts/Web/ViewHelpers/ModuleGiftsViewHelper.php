<?php

namespace App\Domains\Contact\ManageGifts\Web\ViewHelpers;

use App\Helpers\DateHelper;
use App\Helpers\MonetaryNumberHelper;
use App\Models\Contact;
use App\Models\Gift;
use App\Models\GiftOccasion;
use App\Models\GiftState;
use App\Models\User;

class ModuleGiftsViewHelper
{
    public static function data(Contact $contact, User $user): array
    {
        $gifts = $contact->gifts()
            ->with(['occasion', 'state', 'currency'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Gift $gift) => self::dto($gift, $contact, $user));

        $account = $contact->vault->account;

        return [
            'gifts' => $gifts,
            'occasions' => $account->giftOccasions()->orderBy('position')->get()->map(fn (GiftOccasion $occasion) => [
                'id' => $occasion->id,
                'label' => $occasion->label,
            ])->values(),
            'states' => $account->giftStates()->orderBy('position')->get()->map(fn (GiftState $state) => [
                'id' => $state->id,
                'label' => $state->label,
                'position' => $state->position,
            ])->values(),
            'current_date' => now()->format('Y-m-d'),
            'url' => [
                'currencies' => route('currencies.index'),
                'store' => route('contact.gift.store', [
                    'vault' => $contact->vault_id,
                    'contact' => $contact->id,
                ]),
            ],
        ];
    }

    public static function dto(Gift $gift, Contact $contact, User $user): array
    {
        $currency = $gift->currency?->code;

        return [
            'id' => $gift->id,
            'type' => $gift->type,
            'name' => $gift->name,
            'description' => $gift->description,
            'url_link' => $gift->url,
            'occasion' => $gift->occasion ? ['id' => $gift->occasion->id, 'label' => $gift->occasion->label] : null,
            'state' => $gift->state ? ['id' => $gift->state->id, 'label' => $gift->state->label, 'position' => $gift->state->position] : null,
            'estimated_price' => $gift->estimated_price !== null ? MonetaryNumberHelper::format($user, $gift->estimated_price, $currency) : null,
            'estimated_price_input' => $gift->estimated_price !== null ? MonetaryNumberHelper::inputValue($gift->estimated_price, $currency) : null,
            'currency_id' => $gift->currency_id,
            'given_at' => $gift->given_at?->format('Y-m-d'),
            'given_at_human_format' => $gift->given_at ? DateHelper::format($gift->given_at, $user) : null,
            'received_at' => $gift->received_at?->format('Y-m-d'),
            'received_at_human_format' => $gift->received_at ? DateHelper::format($gift->received_at, $user) : null,
            'is_pending' => $gift->isPending(),
            'url' => [
                'update' => route('contact.gift.update', [
                    'vault' => $contact->vault_id,
                    'contact' => $contact->id,
                    'gift' => $gift->id,
                ]),
                'destroy' => route('contact.gift.destroy', [
                    'vault' => $contact->vault_id,
                    'contact' => $contact->id,
                    'gift' => $gift->id,
                ]),
            ],
        ];
    }
}

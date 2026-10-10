<?php

namespace App\Domains\Contact\ManageGifts\Services;

use App\Interfaces\ServiceInterface;
use App\Models\Gift;
use App\Services\BaseService;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class UpdateGift extends BaseService implements ServiceInterface
{
    private array $data;

    private Gift $gift;

    public function rules(): array
    {
        return [
            'account_id' => 'required|uuid|exists:accounts,id',
            'vault_id' => 'required|uuid|exists:vaults,id',
            'author_id' => 'required|uuid|exists:users,id',
            'contact_id' => 'required|uuid|exists:contacts,id',
            'gift_id' => 'required|integer|exists:gifts,id',
            'type' => ['required', 'string', Rule::in(Gift::TYPES)],
            'gift_occasion_id' => 'nullable|integer|exists:gift_occasions,id',
            'gift_state_id' => 'nullable|integer|exists:gift_states,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
            'url' => 'nullable|url|max:255',
            'estimated_price' => 'nullable|integer|min:0',
            'currency_id' => 'nullable|integer|exists:currencies,id',
            'received_at' => 'nullable|date_format:Y-m-d',
            'given_at' => 'nullable|date_format:Y-m-d',
        ];
    }

    public function permissions(): array
    {
        return [
            'author_must_belong_to_account',
            'vault_must_belong_to_account',
            'author_must_be_vault_editor',
            'contact_must_belong_to_vault',
        ];
    }

    public function execute(array $data): Gift
    {
        $this->data = $data;
        $this->validate();
        $this->update();

        $this->contact->last_updated_at = Carbon::now();
        $this->contact->save();

        return $this->gift;
    }

    private function validate(): void
    {
        $this->validateRules($this->data);

        $this->gift = $this->contact->gifts()->findOrFail($this->data['gift_id']);

        if ($this->valueOrNull($this->data, 'gift_occasion_id')) {
            $this->account()->giftOccasions()->findOrFail($this->data['gift_occasion_id']);
        }

        if ($this->valueOrNull($this->data, 'gift_state_id')) {
            $this->account()->giftStates()->findOrFail($this->data['gift_state_id']);
        }

        if ($this->valueOrNull($this->data, 'currency_id')) {
            $this->account()->currencies()->findOrFail($this->data['currency_id']);
        }
    }

    private function update(): void
    {
        $this->gift->update([
            'type' => $this->data['type'],
            'gift_occasion_id' => $this->valueOrNull($this->data, 'gift_occasion_id'),
            'gift_state_id' => $this->valueOrNull($this->data, 'gift_state_id'),
            'name' => $this->data['name'],
            'description' => $this->valueOrNull($this->data, 'description'),
            'url' => $this->valueOrNull($this->data, 'url'),
            'estimated_price' => $this->valueOrNull($this->data, 'estimated_price'),
            'currency_id' => $this->valueOrNull($this->data, 'currency_id'),
            'received_at' => $this->valueOrNull($this->data, 'received_at'),
            'given_at' => $this->valueOrNull($this->data, 'given_at'),
        ]);
    }
}

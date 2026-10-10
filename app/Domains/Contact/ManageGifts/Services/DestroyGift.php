<?php

namespace App\Domains\Contact\ManageGifts\Services;

use App\Interfaces\ServiceInterface;
use App\Services\BaseService;
use Carbon\Carbon;

class DestroyGift extends BaseService implements ServiceInterface
{
    public function rules(): array
    {
        return [
            'account_id' => 'required|uuid|exists:accounts,id',
            'vault_id' => 'required|uuid|exists:vaults,id',
            'author_id' => 'required|uuid|exists:users,id',
            'contact_id' => 'required|uuid|exists:contacts,id',
            'gift_id' => 'required|integer|exists:gifts,id',
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

    public function execute(array $data): void
    {
        $this->validateRules($data);

        $this->contact->gifts()->findOrFail($data['gift_id'])->delete();

        $this->contact->last_updated_at = Carbon::now();
        $this->contact->save();
    }
}

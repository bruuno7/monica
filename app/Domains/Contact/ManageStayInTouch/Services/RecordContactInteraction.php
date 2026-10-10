<?php

namespace App\Domains\Contact\ManageStayInTouch\Services;

use App\Interfaces\ServiceInterface;
use App\Models\Contact;
use App\Services\BaseService;
use Carbon\Carbon;

/**
 * Mark that the user was in touch with the contact, without writing anything
 * ("we talked today"). Writing a note or logging a call records it automatically.
 */
class RecordContactInteraction extends BaseService implements ServiceInterface
{
    public function rules(): array
    {
        return [
            'account_id' => 'required|uuid|exists:accounts,id',
            'vault_id' => 'required|uuid|exists:vaults,id',
            'author_id' => 'required|uuid|exists:users,id',
            'contact_id' => 'required|uuid|exists:contacts,id',
            'happened_at' => 'nullable|date_format:Y-m-d',
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

    public function execute(array $data): Contact
    {
        $this->validateRules($data);

        $happenedAt = $this->valueOrNull($data, 'happened_at');
        $this->contact->recordInteraction($happenedAt ? Carbon::createFromFormat('Y-m-d', $happenedAt) : null);

        return $this->contact->refresh();
    }
}

<?php

namespace App\Domains\Contact\ManageStayInTouch\Services;

use App\Helpers\StayInTouchHelper;
use App\Interfaces\ServiceInterface;
use App\Models\Contact;
use App\Services\BaseService;
use Illuminate\Validation\Rule;

/**
 * Set how often the user wants to be in touch with a contact (or remove the goal).
 */
class UpdateStayInTouchFrequency extends BaseService implements ServiceInterface
{
    public function rules(): array
    {
        return [
            'account_id' => 'required|uuid|exists:accounts,id',
            'vault_id' => 'required|uuid|exists:vaults,id',
            'author_id' => 'required|uuid|exists:users,id',
            'contact_id' => 'required|uuid|exists:contacts,id',
            'frequency' => ['nullable', 'integer', Rule::in(StayInTouchHelper::FREQUENCIES)],
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

        $this->contact->stay_in_touch_frequency = $this->valueOrNull($data, 'frequency');
        $this->contact->stay_in_touch_triggered_at = null;
        $this->contact->save();

        return $this->contact;
    }
}

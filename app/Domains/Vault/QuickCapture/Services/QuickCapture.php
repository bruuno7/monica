<?php

namespace App\Domains\Vault\QuickCapture\Services;

use App\Domains\Contact\ManageCalls\Services\CreateCall;
use App\Domains\Contact\ManageNotes\Services\CreateNote;
use App\Domains\Contact\ManageReminders\Services\CreateContactReminder;
use App\Domains\Contact\ManageTasks\Services\CreateContactTask;
use App\Interfaces\ServiceInterface;
use App\Models\Call;
use App\Models\Contact;
use App\Models\ContactReminder;
use App\Services\BaseService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use function Safe\preg_split;

/**
 * Record something about one or more contacts in a single step, from anywhere
 * in the app: a note, a call, a task or a reminder.
 */
class QuickCapture extends BaseService implements ServiceInterface
{
    public const TYPE_NOTE = 'note';

    public const TYPE_CALL = 'call';

    public const TYPE_TASK = 'task';

    public const TYPE_REMINDER = 'reminder';

    public const TYPES = [
        self::TYPE_NOTE,
        self::TYPE_CALL,
        self::TYPE_TASK,
        self::TYPE_REMINDER,
    ];

    private array $data;

    /**
     * Get the validation rules that apply to the service.
     */
    public function rules(): array
    {
        return [
            'account_id' => 'required|uuid|exists:accounts,id',
            'vault_id' => 'required|uuid|exists:vaults,id',
            'author_id' => 'required|uuid|exists:users,id',
            'type' => ['required', 'string', Rule::in(self::TYPES)],
            'contact_ids' => 'required|array|min:1|max:20',
            'contact_ids.*' => 'required|uuid|exists:contacts,id',
            'body' => 'required|string|max:65535',
            'title' => 'nullable|string|max:255',
            'date' => 'nullable|date_format:Y-m-d',
        ];
    }

    /**
     * Get the permissions that apply to the user calling the service.
     */
    public function permissions(): array
    {
        return [
            'author_must_belong_to_account',
            'vault_must_belong_to_account',
            'author_must_be_vault_editor',
        ];
    }

    /**
     * Create the entry for each contact.
     *
     * @return Collection<int, Contact> the contacts the entry was recorded for
     */
    public function execute(array $data): Collection
    {
        $this->data = $data;
        $this->validateRules($data);

        return collect($data['contact_ids'])
            ->unique()
            ->map(fn (string $contactId) => $this->record($contactId));
    }

    private function record(string $contactId): Contact
    {
        $base = [
            'account_id' => $this->data['account_id'],
            'vault_id' => $this->data['vault_id'],
            'author_id' => $this->data['author_id'],
            'contact_id' => $contactId,
        ];

        $date = $this->date();
        $body = trim($this->data['body']);
        [$firstLine, $rest] = $this->splitFirstLine($body);

        match ($this->data['type']) {
            self::TYPE_NOTE => (new CreateNote)->execute($base + [
                'title' => $this->valueOrNull($this->data, 'title'),
                'body' => $body,
            ]),
            self::TYPE_CALL => (new CreateCall)->execute($base + [
                'called_at' => $date->format('Y-m-d'),
                'description' => $body,
                'type' => Call::TYPE_AUDIO,
                'answered' => true,
                'who_initiated' => Call::INITIATOR_ME,
            ]),
            self::TYPE_TASK => (new CreateContactTask)->execute($base + [
                'label' => $firstLine,
                'description' => $rest,
                'due_at' => $this->valueOrNull($this->data, 'date'),
            ]),
            self::TYPE_REMINDER => (new CreateContactReminder)->execute($base + [
                'label' => $firstLine,
                'day' => $date->day,
                'month' => $date->month,
                'year' => $date->year,
                'type' => ContactReminder::TYPE_ONE_TIME,
            ]),
            default => throw new \InvalidArgumentException('Unknown quick capture type'),
        };

        return Contact::findOrFail($contactId);
    }

    private function date(): Carbon
    {
        $date = $this->valueOrNull($this->data, 'date');

        return $date ? Carbon::createFromFormat('Y-m-d', $date) : Carbon::now();
    }

    /**
     * Tasks and reminders have a short label: use the first line as the label and
     * keep the rest as a description.
     *
     * @return array{0: string, 1: string|null}
     */
    private function splitFirstLine(string $body): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $body, 2);
        $label = Str::limit(trim($lines[0]), 250);
        $rest = isset($lines[1]) ? trim($lines[1]) : null;

        return [$label, $rest === '' ? null : $rest];
    }
}

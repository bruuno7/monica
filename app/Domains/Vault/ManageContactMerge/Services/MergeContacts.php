<?php

namespace App\Domains\Vault\ManageContactMerge\Services;

use App\Exceptions\CantBeDeletedException;
use App\Interfaces\ServiceInterface;
use App\Models\Contact;
use App\Models\ContactFeedItem;
use App\Services\BaseService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Merge a duplicate contact into another one. Everything attached to the
 * duplicate (notes, calls, dates, relationships, labels, groups, posts, files…)
 * moves to the contact that is kept, blank fields are completed from the
 * duplicate, and the duplicate is deleted.
 */
class MergeContacts extends BaseService implements ServiceInterface
{
    private Contact $duplicate;

    public function rules(): array
    {
        return [
            'account_id' => 'required|uuid|exists:accounts,id',
            'vault_id' => 'required|uuid|exists:vaults,id',
            'author_id' => 'required|uuid|exists:users,id',
            'contact_id' => 'required|uuid|exists:contacts,id',
            'duplicate_contact_id' => 'required|uuid|exists:contacts,id|different:contact_id',
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

        $this->duplicate = $this->vault->contacts()->findOrFail($data['duplicate_contact_id']);

        if (! $this->duplicate->can_be_deleted) {
            throw new CantBeDeletedException;
        }

        DB::transaction(function () {
            $this->moveOwnedRecords();
            $this->movePivots();
            $this->moveRelationships();
            $this->moveFiles();
            $this->completeBlankFields();

            $this->duplicate->delete();
            $this->contact->save();
        });

        $this->createFeedItem();

        return $this->contact->refresh();
    }

    /**
     * Tables where each row belongs to exactly one contact.
     */
    private function moveOwnedRecords(): void
    {
        foreach ([
            'notes', 'contact_information', 'contact_important_dates', 'contact_reminders', 'contact_tasks',
            'calls', 'pets', 'goals', 'mood_tracking_events', 'quick_facts', 'contact_feed_items', 'gifts',
        ] as $table) {
            DB::table($table)->where('contact_id', $this->duplicate->id)->update(['contact_id' => $this->contact->id]);
        }

        DB::table('life_events')->where('paid_by_contact_id', $this->duplicate->id)->update(['paid_by_contact_id' => $this->contact->id]);
        DB::table('contact_loan')->where('loaner_id', $this->duplicate->id)->update(['loaner_id' => $this->contact->id]);
        DB::table('contact_loan')->where('loanee_id', $this->duplicate->id)->update(['loanee_id' => $this->contact->id]);
    }

    /**
     * Many-to-many tables: move the rows of the duplicate, except those the kept
     * contact already has.
     */
    private function movePivots(): void
    {
        foreach ([
            'contact_label' => 'label_id',
            'contact_group' => 'group_id',
            'contact_post' => 'post_id',
            'life_event_participants' => 'life_event_id',
            'timeline_event_participants' => 'timeline_event_id',
            'contact_address' => 'address_id',
            'contact_life_metric' => 'life_metric_id',
            'contact_vault_user' => 'user_id',
        ] as $table => $otherColumn) {
            $existing = DB::table($table)->where('contact_id', $this->contact->id)->pluck($otherColumn);

            DB::table($table)
                ->where('contact_id', $this->duplicate->id)
                ->whereIn($otherColumn, $existing)
                ->delete();

            DB::table($table)
                ->where('contact_id', $this->duplicate->id)
                ->update(['contact_id' => $this->contact->id]);
        }
    }

    /**
     * Relationships reference contacts on both sides.
     */
    private function moveRelationships(): void
    {
        foreach (['contact_id' => 'related_contact_id', 'related_contact_id' => 'contact_id'] as $column => $otherColumn) {
            $rows = DB::table('relationships')->where($column, $this->duplicate->id)->get();

            foreach ($rows as $row) {
                $alreadyExists = DB::table('relationships')
                    ->where($column, $this->contact->id)
                    ->where($otherColumn, $row->{$otherColumn})
                    ->where('relationship_type_id', $row->relationship_type_id)
                    ->exists();

                if ($alreadyExists || $row->{$otherColumn} === $this->contact->id) {
                    DB::table('relationships')->where('id', $row->id)->delete();
                } else {
                    DB::table('relationships')->where('id', $row->id)->update([$column => $this->contact->id]);
                }
            }
        }
    }

    private function moveFiles(): void
    {
        DB::table('files')
            ->where('fileable_type', Contact::class)
            ->where('ufileable_id', $this->duplicate->id)
            ->update(['ufileable_id' => $this->contact->id]);
    }

    /**
     * Keep the details of the contact we keep, but fill the blanks with what the
     * duplicate knew.
     */
    private function completeBlankFields(): void
    {
        foreach ([
            'middle_name', 'nickname', 'maiden_name', 'prefix', 'suffix', 'gender_id', 'pronoun_id',
            'company_id', 'job_position', 'religion_id', 'file_id', 'stay_in_touch_frequency',
        ] as $field) {
            if (blank($this->contact->{$field}) && filled($this->duplicate->{$field})) {
                $this->contact->{$field} = $this->duplicate->{$field};
            }
        }

        if ($this->duplicate->last_interaction_at !== null) {
            $this->contact->recordInteraction($this->duplicate->last_interaction_at);
        }

        if ($this->duplicate->file_id === $this->contact->file_id) {
            // the avatar now belongs to the kept contact: do not let the duplicate delete it
            $this->duplicate->file_id = null;
            $this->duplicate->saveQuietly();
        }

        $this->contact->last_updated_at = Carbon::now();
    }

    private function createFeedItem(): void
    {
        ContactFeedItem::create([
            'author_id' => $this->author->id,
            'contact_id' => $this->contact->id,
            'action' => ContactFeedItem::ACTION_CONTACT_MERGED,
            'description' => $this->duplicate->name,
        ]);
    }
}

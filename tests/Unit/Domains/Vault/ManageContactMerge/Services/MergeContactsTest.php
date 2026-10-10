<?php

namespace Tests\Unit\Domains\Vault\ManageContactMerge\Services;

use App\Domains\Vault\ManageContactMerge\Services\MergeContacts;
use App\Exceptions\NotEnoughPermissionException;
use App\Models\Call;
use App\Models\Contact;
use App\Models\ContactFeedItem;
use App\Models\Label;
use App\Models\Note;
use App\Models\RelationshipType;
use App\Models\User;
use App\Models\Vault;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MergeContactsTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_merges_a_duplicate_into_a_contact(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 10));
        [$user, $vault] = $this->editor();
        $kept = Contact::factory()->create(['vault_id' => $vault->id, 'first_name' => 'Ana', 'nickname' => null, 'last_interaction_at' => Carbon::create(2026, 1, 1)]);
        $duplicate = Contact::factory()->create(['vault_id' => $vault->id, 'first_name' => 'Ana', 'nickname' => 'Anita', 'last_interaction_at' => Carbon::create(2026, 9, 1)]);
        $friend = Contact::factory()->create(['vault_id' => $vault->id]);

        Note::factory()->create(['contact_id' => $duplicate->id, 'vault_id' => $vault->id, 'body' => 'from the duplicate']);
        Call::factory()->create(['contact_id' => $duplicate->id]);

        $label = Label::factory()->create(['vault_id' => $vault->id]);
        $kept->labels()->attach($label);
        $duplicate->labels()->attach($label);
        $otherLabel = Label::factory()->create(['vault_id' => $vault->id]);
        $duplicate->labels()->attach($otherLabel);

        $type = RelationshipType::factory()->create();
        DB::table('relationships')->insert(['relationship_type_id' => $type->id, 'contact_id' => $duplicate->id, 'related_contact_id' => $friend->id]);
        DB::table('relationships')->insert(['relationship_type_id' => $type->id, 'contact_id' => $friend->id, 'related_contact_id' => $duplicate->id]);

        $result = (new MergeContacts)->execute([
            'account_id' => $user->account_id,
            'author_id' => $user->id,
            'vault_id' => $vault->id,
            'contact_id' => $kept->id,
            'duplicate_contact_id' => $duplicate->id,
        ]);

        $this->assertEquals($kept->id, $result->id);
        $this->assertSoftDeleted('contacts', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('notes', ['contact_id' => $kept->id, 'body' => 'from the duplicate']);
        $this->assertDatabaseHas('calls', ['contact_id' => $kept->id]);
        $this->assertEquals(2, $kept->labels()->count());
        $this->assertDatabaseHas('relationships', ['contact_id' => $kept->id, 'related_contact_id' => $friend->id]);
        $this->assertDatabaseHas('relationships', ['contact_id' => $friend->id, 'related_contact_id' => $kept->id]);
        $this->assertEquals('Anita', $result->nickname);
        $this->assertEquals('2026-09-01', $result->last_interaction_at->format('Y-m-d'));
        $this->assertDatabaseHas('contact_feed_items', ['contact_id' => $kept->id, 'action' => ContactFeedItem::ACTION_CONTACT_MERGED]);
    }

    /** @test */
    public function it_refuses_to_merge_a_contact_with_itself(): void
    {
        [$user, $vault] = $this->editor();
        $contact = Contact::factory()->create(['vault_id' => $vault->id]);

        $this->expectException(ValidationException::class);
        (new MergeContacts)->execute([
            'account_id' => $user->account_id, 'author_id' => $user->id, 'vault_id' => $vault->id,
            'contact_id' => $contact->id, 'duplicate_contact_id' => $contact->id,
        ]);
    }

    /** @test */
    public function it_refuses_a_duplicate_from_another_vault(): void
    {
        [$user, $vault] = $this->editor();
        $contact = Contact::factory()->create(['vault_id' => $vault->id]);
        $stranger = Contact::factory()->create();

        $this->expectException(\Exception::class);
        (new MergeContacts)->execute([
            'account_id' => $user->account_id, 'author_id' => $user->id, 'vault_id' => $vault->id,
            'contact_id' => $contact->id, 'duplicate_contact_id' => $stranger->id,
        ]);
    }

    /** @test */
    public function it_fails_for_viewers(): void
    {
        $user = $this->createUser();
        $vault = $this->setPermissionInVault($user, Vault::PERMISSION_VIEW, $this->createVault($user->account));
        $a = Contact::factory()->create(['vault_id' => $vault->id]);
        $b = Contact::factory()->create(['vault_id' => $vault->id]);

        $this->expectException(NotEnoughPermissionException::class);
        (new MergeContacts)->execute([
            'account_id' => $user->account_id, 'author_id' => $user->id, 'vault_id' => $vault->id,
            'contact_id' => $a->id, 'duplicate_contact_id' => $b->id,
        ]);
    }

    /**
     * @return array{0: User, 1: Vault}
     */
    private function editor(): array
    {
        $user = $this->createUser();
        $vault = $this->setPermissionInVault($user, Vault::PERMISSION_EDIT, $this->createVault($user->account));

        return [$user, $vault];
    }
}

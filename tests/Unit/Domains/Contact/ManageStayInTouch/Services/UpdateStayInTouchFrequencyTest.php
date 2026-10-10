<?php

namespace Tests\Unit\Domains\Contact\ManageStayInTouch\Services;

use App\Domains\Contact\ManageStayInTouch\Services\RecordContactInteraction;
use App\Domains\Contact\ManageStayInTouch\Services\UpdateStayInTouchFrequency;
use App\Exceptions\NotEnoughPermissionException;
use App\Models\Contact;
use App\Models\Vault;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UpdateStayInTouchFrequencyTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_sets_and_removes_the_frequency(): void
    {
        $user = $this->createUser();
        $vault = $this->setPermissionInVault($user, Vault::PERMISSION_EDIT, $this->createVault($user->account));
        $contact = Contact::factory()->create(['vault_id' => $vault->id, 'stay_in_touch_triggered_at' => Carbon::now()]);

        $base = ['account_id' => $user->account_id, 'author_id' => $user->id, 'vault_id' => $vault->id, 'contact_id' => $contact->id];

        (new UpdateStayInTouchFrequency)->execute($base + ['frequency' => 30]);
        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'stay_in_touch_frequency' => 30, 'stay_in_touch_triggered_at' => null]);

        (new UpdateStayInTouchFrequency)->execute($base + ['frequency' => null]);
        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'stay_in_touch_frequency' => null]);
    }

    /** @test */
    public function it_rejects_unknown_frequencies(): void
    {
        $user = $this->createUser();
        $vault = $this->setPermissionInVault($user, Vault::PERMISSION_EDIT, $this->createVault($user->account));
        $contact = Contact::factory()->create(['vault_id' => $vault->id]);

        $this->expectException(ValidationException::class);
        (new UpdateStayInTouchFrequency)->execute([
            'account_id' => $user->account_id, 'author_id' => $user->id, 'vault_id' => $vault->id, 'contact_id' => $contact->id, 'frequency' => 13,
        ]);
    }

    /** @test */
    public function it_fails_for_viewers(): void
    {
        $user = $this->createUser();
        $vault = $this->setPermissionInVault($user, Vault::PERMISSION_VIEW, $this->createVault($user->account));
        $contact = Contact::factory()->create(['vault_id' => $vault->id]);

        $this->expectException(NotEnoughPermissionException::class);
        (new UpdateStayInTouchFrequency)->execute([
            'account_id' => $user->account_id, 'author_id' => $user->id, 'vault_id' => $vault->id, 'contact_id' => $contact->id, 'frequency' => 30,
        ]);
    }

    /** @test */
    public function it_records_an_interaction(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 10, 12));
        $user = $this->createUser();
        $vault = $this->setPermissionInVault($user, Vault::PERMISSION_EDIT, $this->createVault($user->account));
        $contact = Contact::factory()->create(['vault_id' => $vault->id, 'last_interaction_at' => null, 'stay_in_touch_triggered_at' => Carbon::now()]);

        $contact = (new RecordContactInteraction)->execute([
            'account_id' => $user->account_id, 'author_id' => $user->id, 'vault_id' => $vault->id, 'contact_id' => $contact->id,
        ]);

        $this->assertEquals('2026-10-10 12:00:00', $contact->last_interaction_at->format('Y-m-d H:i:s'));
        $this->assertNull($contact->stay_in_touch_triggered_at);

        // an older date never moves the counter backwards
        $contact = (new RecordContactInteraction)->execute([
            'account_id' => $user->account_id, 'author_id' => $user->id, 'vault_id' => $vault->id, 'contact_id' => $contact->id, 'happened_at' => '2020-01-01',
        ]);
        $this->assertEquals('2026-10-10', $contact->last_interaction_at->format('Y-m-d'));
    }

    /** @test */
    public function writing_a_note_records_an_interaction(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 10));
        $user = $this->createUser();
        $vault = $this->setPermissionInVault($user, Vault::PERMISSION_EDIT, $this->createVault($user->account));
        $contact = Contact::factory()->create(['vault_id' => $vault->id, 'last_interaction_at' => null]);

        (new \App\Domains\Contact\ManageNotes\Services\CreateNote)->execute([
            'account_id' => $user->account_id, 'author_id' => $user->id, 'vault_id' => $vault->id, 'contact_id' => $contact->id, 'body' => 'We had coffee',
        ]);

        $this->assertEquals('2026-10-10', $contact->fresh()->last_interaction_at->format('Y-m-d'));
    }
}

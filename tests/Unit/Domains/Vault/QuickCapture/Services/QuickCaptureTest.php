<?php

namespace Tests\Unit\Domains\Vault\QuickCapture\Services;

use App\Domains\Vault\QuickCapture\Services\QuickCapture;
use App\Exceptions\NotEnoughPermissionException;
use App\Models\Contact;
use App\Models\User;
use App\Models\Vault;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QuickCaptureTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_records_a_note_for_several_contacts(): void
    {
        [$user, $vault] = $this->editor();
        $ana = Contact::factory()->create(['vault_id' => $vault->id]);
        $luis = Contact::factory()->create(['vault_id' => $vault->id]);

        $contacts = $this->capture($user, $vault, QuickCapture::TYPE_NOTE, [$ana->id, $luis->id], "Dinner at **Casa Lola**.\nGreat evening.");

        $this->assertCount(2, $contacts);
        $this->assertDatabaseHas('notes', ['contact_id' => $ana->id, 'body' => "Dinner at **Casa Lola**.\nGreat evening."]);
        $this->assertDatabaseHas('notes', ['contact_id' => $luis->id, 'body' => "Dinner at **Casa Lola**.\nGreat evening."]);
    }

    /** @test */
    public function it_records_a_call(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 10));
        [$user, $vault] = $this->editor();
        $ana = Contact::factory()->create(['vault_id' => $vault->id]);

        $this->capture($user, $vault, QuickCapture::TYPE_CALL, [$ana->id], 'Talked about the trip', '2026-10-08');

        $this->assertDatabaseHas('calls', [
            'contact_id' => $ana->id,
            'description' => 'Talked about the trip',
            'called_at' => '2026-10-08 00:00:00',
            'who_initiated' => 'me',
        ]);
    }

    /** @test */
    public function it_records_a_task_using_the_first_line_as_label(): void
    {
        [$user, $vault] = $this->editor();
        $ana = Contact::factory()->create(['vault_id' => $vault->id]);

        $this->capture($user, $vault, QuickCapture::TYPE_TASK, [$ana->id], "Send her the photos\nFrom the Rome trip, the ones at the Colosseum", '2026-10-20');

        $this->assertDatabaseHas('contact_tasks', [
            'contact_id' => $ana->id,
            'label' => 'Send her the photos',
            'description' => 'From the Rome trip, the ones at the Colosseum',
        ]);
    }

    /** @test */
    public function it_records_a_one_time_reminder(): void
    {
        [$user, $vault] = $this->editor();
        $ana = Contact::factory()->create(['vault_id' => $vault->id]);

        $this->capture($user, $vault, QuickCapture::TYPE_REMINDER, [$ana->id], 'Ask about the job interview', '2026-11-02');

        $this->assertDatabaseHas('contact_reminders', [
            'contact_id' => $ana->id,
            'label' => 'Ask about the job interview',
            'day' => 2,
            'month' => 11,
            'year' => 2026,
            'type' => 'one_time',
        ]);
    }

    /** @test */
    public function it_fails_with_an_unknown_type(): void
    {
        [$user, $vault] = $this->editor();
        $ana = Contact::factory()->create(['vault_id' => $vault->id]);

        $this->expectException(ValidationException::class);
        $this->capture($user, $vault, 'poem', [$ana->id], 'Roses are red');
    }

    /** @test */
    public function it_fails_if_the_user_is_only_a_viewer(): void
    {
        $user = $this->createUser();
        $vault = $this->createVault($user->account);
        $vault = $this->setPermissionInVault($user, Vault::PERMISSION_VIEW, $vault);
        $ana = Contact::factory()->create(['vault_id' => $vault->id]);

        $this->expectException(NotEnoughPermissionException::class);
        $this->capture($user, $vault, QuickCapture::TYPE_NOTE, [$ana->id], 'Hello');
    }

    /** @test */
    public function it_fails_if_a_contact_belongs_to_another_vault(): void
    {
        [$user, $vault] = $this->editor();
        $stranger = Contact::factory()->create();

        $this->expectException(\Exception::class);
        $this->capture($user, $vault, QuickCapture::TYPE_NOTE, [$stranger->id], 'Hello');
    }

    /**
     * @return array{0: User, 1: Vault}
     */
    private function editor(): array
    {
        $user = $this->createUser();
        $vault = $this->createVault($user->account);
        $vault = $this->setPermissionInVault($user, Vault::PERMISSION_EDIT, $vault);

        return [$user, $vault];
    }

    private function capture(User $user, Vault $vault, string $type, array $contactIds, string $body, ?string $date = null)
    {
        return (new QuickCapture)->execute([
            'account_id' => $user->account_id,
            'author_id' => $user->id,
            'vault_id' => $vault->id,
            'type' => $type,
            'contact_ids' => $contactIds,
            'body' => $body,
            'date' => $date,
        ]);
    }
}

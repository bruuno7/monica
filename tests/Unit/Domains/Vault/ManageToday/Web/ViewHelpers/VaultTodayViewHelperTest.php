<?php

namespace Tests\Unit\Domains\Vault\ManageToday\Web\ViewHelpers;

use App\Domains\Vault\ManageToday\Web\ViewHelpers\VaultTodayViewHelper;
use App\Models\Contact;
use App\Models\ContactImportantDate;
use App\Models\Journal;
use App\Models\Note;
use App\Models\Post;
use App\Models\User;
use App\Models\Vault;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class VaultTodayViewHelperTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_greets_according_to_the_time_of_day(): void
    {
        $user = User::factory()->create(['first_name' => 'Ross']);

        $this->assertEquals('Good morning, Ross', VaultTodayViewHelper::greeting(Carbon::create(2026, 10, 10, 9), $user));
        $this->assertEquals('Good afternoon, Ross', VaultTodayViewHelper::greeting(Carbon::create(2026, 10, 10, 15), $user));
        $this->assertEquals('Good evening, Ross', VaultTodayViewHelper::greeting(Carbon::create(2026, 10, 10, 21), $user));
        $this->assertEquals('Still up, Ross?', VaultTodayViewHelper::greeting(Carbon::create(2026, 10, 10, 3), $user));
    }

    /** @test */
    public function it_gets_the_data_needed_for_the_view(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 10, 10));
        $user = $this->createUser();
        $vault = $this->setPermissionInVault($user, Vault::PERMISSION_EDIT, $this->createVault($user->account));

        $array = VaultTodayViewHelper::data($vault, $user);

        foreach (['greeting', 'date', 'prompt', 'important_dates', 'reminders', 'due_tasks', 'reach_out', 'memories', 'activity', 'mood', 'favorites', 'url'] as $key) {
            $this->assertArrayHasKey($key, $array);
        }
        $this->assertEquals('daily', $array['prompt']['type']);
        $this->assertNotEmpty($array['prompt']['text']);
        $this->assertCount(365, $array['activity']['days']);
    }

    /** @test */
    public function it_lists_important_dates_of_the_coming_week(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 10));
        $user = User::factory()->create();
        $vault = Vault::factory()->create(['account_id' => $user->account_id]);
        $ana = Contact::factory()->create(['vault_id' => $vault->id, 'first_name' => 'Ana', 'last_name' => 'Troyat']);
        $luis = Contact::factory()->create(['vault_id' => $vault->id]);

        ContactImportantDate::factory()->create(['contact_id' => $ana->id, 'label' => 'Birthday', 'day' => 12, 'month' => 10, 'year' => 1990]);
        ContactImportantDate::factory()->create(['contact_id' => $luis->id, 'label' => 'Anniversary', 'day' => 25, 'month' => 12, 'year' => null]);

        $dates = VaultTodayViewHelper::importantDates($vault, Carbon::now());

        $this->assertCount(1, $dates);
        $this->assertEquals('Birthday', $dates[0]['label']);
        $this->assertEquals(2, $dates[0]['in_days']);
        $this->assertEquals(36, $dates[0]['age']);
        $this->assertEquals('Ana Troyat', $dates[0]['contact']['name']);
    }

    /** @test */
    public function it_finds_memories_from_previous_years_on_the_same_day(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 10));
        $user = User::factory()->create();
        $vault = Vault::factory()->create(['account_id' => $user->account_id]);
        $contact = Contact::factory()->create(['vault_id' => $vault->id]);
        $journal = Journal::factory()->create(['vault_id' => $vault->id]);

        Post::factory()->create(['journal_id' => $journal->id, 'title' => 'Trip to Rome', 'written_at' => Carbon::create(2024, 10, 10)]);
        Post::factory()->create(['journal_id' => $journal->id, 'title' => 'Not today', 'written_at' => Carbon::create(2024, 10, 11)]);
        $note = Note::factory()->create(['contact_id' => $contact->id, 'vault_id' => $vault->id, 'title' => null, 'body' => 'We **met** for lunch']);
        $note->created_at = Carbon::create(2021, 10, 10);
        $note->save();

        $memories = VaultTodayViewHelper::memories($vault, $user, Carbon::now());

        $this->assertCount(2, $memories);
        $this->assertEquals(['post', 'note'], $memories->pluck('type')->toArray());
        $this->assertEquals(2, $memories[0]['years_ago']);
        $this->assertEquals('Trip to Rome', $memories[0]['title']);
        $this->assertEquals(5, $memories[1]['years_ago']);
        $this->assertEquals('We met for lunch', $memories[1]['excerpt']);
    }

    /** @test */
    public function it_computes_the_activity_and_the_streak(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 10, 12));
        $user = User::factory()->create(['timezone' => 'UTC']);
        $vault = Vault::factory()->create(['account_id' => $user->account_id]);
        $contact = Contact::factory()->create(['vault_id' => $vault->id]);

        foreach ([0, 1, 2, 5] as $daysAgo) {
            $note = Note::factory()->create(['contact_id' => $contact->id, 'vault_id' => $vault->id]);
            $note->created_at = Carbon::now()->subDays($daysAgo);
            $note->save();
        }

        $activity = VaultTodayViewHelper::activity($vault, $user, Carbon::now());

        $this->assertEquals(3, $activity['streak']['current']);
        $this->assertEquals(3, $activity['streak']['best']);
        $this->assertEquals(4, $activity['active_days']);
        $this->assertEquals(4, $activity['total']);
        $this->assertEquals('2026-10-10', $activity['days']->last()['date']);
        $this->assertEquals(1, $activity['days']->last()['count']);
    }
}

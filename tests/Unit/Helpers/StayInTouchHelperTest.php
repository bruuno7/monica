<?php

namespace Tests\Unit\Helpers;

use App\Helpers\StayInTouchHelper;
use App\Models\Contact;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class StayInTouchHelperTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_is_off_without_a_frequency(): void
    {
        $contact = Contact::factory()->create(['stay_in_touch_frequency' => null]);

        $this->assertEquals(StayInTouchHelper::STATUS_OFF, StayInTouchHelper::status($contact));
        $this->assertFalse(StayInTouchHelper::isDue($contact));
    }

    /** @test */
    public function it_computes_the_status_from_the_last_interaction(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 10));

        $fresh = Contact::factory()->create(['stay_in_touch_frequency' => 30, 'last_interaction_at' => Carbon::now()->subDays(10)]);
        $cooling = Contact::factory()->create(['stay_in_touch_frequency' => 30, 'last_interaction_at' => Carbon::now()->subDays(40)]);
        $cold = Contact::factory()->create(['stay_in_touch_frequency' => 30, 'last_interaction_at' => Carbon::now()->subDays(100)]);

        $this->assertEquals(StayInTouchHelper::STATUS_FRESH, StayInTouchHelper::status($fresh));
        $this->assertEquals(StayInTouchHelper::STATUS_COOLING, StayInTouchHelper::status($cooling));
        $this->assertEquals(StayInTouchHelper::STATUS_COLD, StayInTouchHelper::status($cold));
        $this->assertTrue(StayInTouchHelper::isDue($cooling));
        $this->assertEquals(40, StayInTouchHelper::daysSinceLastInteraction($cooling));
    }

    /** @test */
    public function it_falls_back_to_the_creation_date_when_nothing_happened_yet(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 10));
        $contact = Contact::factory()->create(['stay_in_touch_frequency' => 7, 'last_interaction_at' => null]);
        $contact->created_at = Carbon::now()->subDays(20);
        $contact->save();

        $this->assertEquals(20, StayInTouchHelper::daysSinceLastInteraction($contact));
        $this->assertEquals(StayInTouchHelper::STATUS_COLD, StayInTouchHelper::status($contact));
    }

    /** @test */
    public function it_lists_the_due_contacts_of_a_vault_coldest_first(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 10));
        $user = User::factory()->create();
        $vault = $this->createVault($user->account);

        $fine = Contact::factory()->create(['vault_id' => $vault->id, 'stay_in_touch_frequency' => 30, 'last_interaction_at' => Carbon::now()->subDays(3)]);
        $late = Contact::factory()->create(['vault_id' => $vault->id, 'stay_in_touch_frequency' => 30, 'last_interaction_at' => Carbon::now()->subDays(35)]);
        $veryLate = Contact::factory()->create(['vault_id' => $vault->id, 'stay_in_touch_frequency' => 7, 'last_interaction_at' => Carbon::now()->subDays(60)]);
        Contact::factory()->create(['vault_id' => $vault->id, 'stay_in_touch_frequency' => 7, 'last_interaction_at' => Carbon::now()->subDays(60), 'listed' => false]);

        $due = StayInTouchHelper::dueContacts($vault->id);

        $this->assertEquals([$veryLate->id, $late->id], $due->pluck('id')->toArray());
        $this->assertFalse($due->contains('id', $fine->id));
    }

    /** @test */
    public function it_builds_the_dto(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 10));
        $user = User::factory()->create();
        $contact = Contact::factory()->create(['stay_in_touch_frequency' => 30, 'last_interaction_at' => Carbon::create(2026, 9, 1)]);

        $array = StayInTouchHelper::dto($contact, $user);

        $this->assertEquals(30, $array['frequency']);
        $this->assertEquals('Every month', $array['frequency_label']);
        $this->assertEquals('cooling', $array['status']);
        $this->assertEquals('Time to reach out', $array['status_label']);
        $this->assertEquals(39, $array['days_since_last_interaction']);
        $this->assertEquals([7, 14, 30, 60, 90, 180, 365], $array['frequencies']->pluck('id')->toArray());
        $this->assertEquals([
            'update' => env('APP_URL').'/vaults/'.$contact->vault_id.'/contacts/'.$contact->id.'/stayInTouch',
            'touch' => env('APP_URL').'/vaults/'.$contact->vault_id.'/contacts/'.$contact->id.'/stayInTouch/touch',
        ], $array['url']);
    }
}

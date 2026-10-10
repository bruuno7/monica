<?php

namespace Tests\Unit\Domains\Contact\ManageGifts\Web\ViewHelpers;

use App\Domains\Contact\ManageGifts\Web\ViewHelpers\ModuleGiftsViewHelper;
use App\Models\Contact;
use App\Models\Gift;
use App\Models\GiftOccasion;
use App\Models\GiftState;
use App\Models\User;
use App\Models\Vault;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ModuleGiftsViewHelperTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_gets_the_data_needed_for_the_view(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 10));
        $user = User::factory()->create();
        $vault = Vault::factory()->create(['account_id' => $user->account_id]);
        $contact = Contact::factory()->create(['vault_id' => $vault->id]);
        $occasion = GiftOccasion::factory()->create(['account_id' => $user->account_id, 'label' => 'Birthday']);
        $state = GiftState::factory()->create(['account_id' => $user->account_id, 'label' => 'Idea', 'position' => 1]);
        $gift = Gift::factory()->create([
            'contact_id' => $contact->id,
            'name' => 'Socks',
            'gift_occasion_id' => $occasion->id,
            'gift_state_id' => $state->id,
            'url' => 'https://example.com',
        ]);

        $array = ModuleGiftsViewHelper::data($contact, $user);

        $this->assertEquals(['gifts', 'occasions', 'states', 'current_date', 'url'], array_keys($array));
        $this->assertEquals('Birthday', $array['occasions'][0]['label']);
        $this->assertEquals('Idea', $array['states'][0]['label']);

        $dto = $array['gifts'][0];
        $this->assertEquals($gift->id, $dto['id']);
        $this->assertEquals('Socks', $dto['name']);
        $this->assertEquals('https://example.com', $dto['url_link']);
        $this->assertEquals('Birthday', $dto['occasion']['label']);
        $this->assertEquals('Idea', $dto['state']['label']);
        $this->assertTrue($dto['is_pending']);
        $this->assertNull($dto['estimated_price']);
        $this->assertEquals([
            'update' => env('APP_URL').'/vaults/'.$vault->id.'/contacts/'.$contact->id.'/gifts/'.$gift->id,
            'destroy' => env('APP_URL').'/vaults/'.$vault->id.'/contacts/'.$contact->id.'/gifts/'.$gift->id,
        ], $dto['url']);
    }
}

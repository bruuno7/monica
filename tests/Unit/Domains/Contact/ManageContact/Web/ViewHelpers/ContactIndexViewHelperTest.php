<?php

namespace Tests\Unit\Domains\Contact\ManageContact\Web\ViewHelpers;

use App\Domains\Contact\ManageContact\Web\ViewHelpers\ContactIndexViewHelper;
use App\Models\Contact;
use App\Models\Label;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

use function env;

class ContactIndexViewHelperTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_gets_the_data_needed_for_the_view(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->create();
        $contact = Contact::factory()->create([
            'vault_id' => $vault->id,
        ]);
        $label = Label::factory()->create([
            'vault_id' => $vault->id,
            'name' => 'assigned label',
        ]);
        Label::factory()->create([
            'vault_id' => $vault->id,
            'name' => 'unassigned label',
        ]);
        $contact->labels()->attach($label);

        $contacts = Contact::all();
        $array = ContactIndexViewHelper::data($contacts, $vault, null, $user);

        $this->assertEquals(
            6,
            count($array)
        );

        $this->assertArrayHasKey('contacts', $array);
        $this->assertArrayHasKey('labels', $array);
        $this->assertArrayHasKey('current_label', $array);
        $this->assertArrayHasKey('url', $array);

        $contactDto = $array['contacts']->first();
        $this->assertEquals($contact->id, $contactDto['id']);
        $this->assertEquals($contact->name, $contactDto['name']);
        $this->assertEquals(Contact::AVATAR_TYPE_SVG, $contactDto['avatar']['type']);
        $this->assertEquals('', $contactDto['job']);
        $this->assertEquals(
            [['id' => $label->id, 'name' => 'assigned label', 'bg_color' => 'bg-zinc-200', 'text_color' => 'text-zinc-700']],
            $contactDto['labels']->toArray()
        );
        $this->assertEquals(['date' => null, 'days_ago' => null], $contactDto['last_interaction']);
        $this->assertEquals(['status' => 'off', 'status_label' => ''], $contactDto['stay_in_touch']);
        $this->assertNull($contactDto['next_important_date']);
        $this->assertEquals(
            ['show' => env('APP_URL').'/vaults/'.$vault->id.'/contacts/'.$contact->id],
            $contactDto['url']
        );

        $this->assertEquals(
            [
                0 => [
                    'id' => $label->id,
                    'name' => 'assigned label',
                    'count' => 1,
                    'url' => [
                        'show' => env('APP_URL').'/vaults/'.$vault->id.'/contacts/labels/'.$label->id,
                    ],
                ],
            ],
            $array['labels']->toArray()
        );

        $this->assertEquals(
            [
                'contact' => [
                    'index' => env('APP_URL').'/vaults/'.$vault->id.'/contacts',
                    'create' => env('APP_URL').'/vaults/'.$vault->id.'/contacts/create',
                ],
                'sort' => [
                    'update' => env('APP_URL').'/vaults/'.$vault->id.'/contacts/sort',
                ],
            ],
            $array['url']
        );
    }
}

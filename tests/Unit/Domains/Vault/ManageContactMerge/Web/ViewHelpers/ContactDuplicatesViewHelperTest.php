<?php

namespace Tests\Unit\Domains\Vault\ManageContactMerge\Web\ViewHelpers;

use App\Domains\Vault\ManageContactMerge\Web\ViewHelpers\ContactDuplicatesViewHelper;
use App\Models\Contact;
use App\Models\ContactInformation;
use App\Models\ContactInformationType;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ContactDuplicatesViewHelperTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_finds_contacts_with_the_same_name_or_email(): void
    {
        $user = User::factory()->create();
        $vault = Vault::factory()->create(['account_id' => $user->account_id]);

        $ana = Contact::factory()->create(['vault_id' => $vault->id, 'first_name' => 'Ana', 'last_name' => 'García']);
        $anaBis = Contact::factory()->create(['vault_id' => $vault->id, 'first_name' => 'ana', 'last_name' => 'Garcia']);
        $luis = Contact::factory()->create(['vault_id' => $vault->id, 'first_name' => 'Luis', 'last_name' => 'Pérez']);
        $louis = Contact::factory()->create(['vault_id' => $vault->id, 'first_name' => 'Louis', 'last_name' => 'Perez']);
        Contact::factory()->create(['vault_id' => $vault->id, 'first_name' => 'Solo', 'last_name' => 'Unique']);

        $emailType = ContactInformationType::factory()->create(['account_id' => $user->account_id, 'type' => 'email']);
        ContactInformation::factory()->create(['contact_id' => $luis->id, 'type_id' => $emailType->id, 'data' => 'Luis@Example.com']);
        ContactInformation::factory()->create(['contact_id' => $louis->id, 'type_id' => $emailType->id, 'data' => 'luis@example.com']);

        $pairs = ContactDuplicatesViewHelper::pairs($vault, $user);

        $this->assertCount(2, $pairs);
        $this->assertEquals(['Same name', 'Same email address'], $pairs->pluck('reason')->toArray());
        $this->assertEquals([$ana->id, $anaBis->id], collect($pairs[0]['contacts'])->pluck('id')->toArray());
        $this->assertEquals([$luis->id, $louis->id], collect($pairs[1]['contacts'])->pluck('id')->toArray());
        $this->assertEquals(1, $pairs[1]['contacts'][0]['counts']['contact_information']);
    }
}

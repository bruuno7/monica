<?php

namespace Tests\Unit\Domains\Contact\ManageGifts\Services;

use App\Domains\Contact\ManageGifts\Services\CreateGift;
use App\Domains\Contact\ManageGifts\Services\DestroyGift;
use App\Domains\Contact\ManageGifts\Services\UpdateGift;
use App\Exceptions\NotEnoughPermissionException;
use App\Models\Contact;
use App\Models\Gift;
use App\Models\GiftOccasion;
use App\Models\GiftState;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreateGiftTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_creates_updates_and_deletes_a_gift(): void
    {
        [$user, $vault, $contact] = $this->setup_();
        $occasion = GiftOccasion::factory()->create(['account_id' => $user->account_id]);
        $state = GiftState::factory()->create(['account_id' => $user->account_id]);

        $gift = (new CreateGift)->execute($this->base($user, $vault, $contact) + [
            'type' => Gift::TYPE_TO_GIVE,
            'gift_occasion_id' => $occasion->id,
            'gift_state_id' => $state->id,
            'name' => 'A book about Rome',
            'url' => 'https://example.com/book',
            'estimated_price' => 2500,
        ]);

        $this->assertDatabaseHas('gifts', [
            'id' => $gift->id,
            'contact_id' => $contact->id,
            'type' => 'to_give',
            'gift_occasion_id' => $occasion->id,
            'gift_state_id' => $state->id,
            'name' => 'A book about Rome',
            'estimated_price' => 2500,
        ]);
        $this->assertTrue($gift->isPending());

        $gift = (new UpdateGift)->execute($this->base($user, $vault, $contact) + [
            'gift_id' => $gift->id,
            'type' => Gift::TYPE_TO_GIVE,
            'name' => 'A book about Rome',
            'given_at' => '2026-10-10',
        ]);
        $this->assertFalse($gift->isPending());
        $this->assertEquals('2026-10-10', $gift->given_at->format('Y-m-d'));

        (new DestroyGift)->execute($this->base($user, $vault, $contact) + ['gift_id' => $gift->id]);
        $this->assertDatabaseMissing('gifts', ['id' => $gift->id]);
    }

    /** @test */
    public function it_rejects_an_occasion_from_another_account(): void
    {
        [$user, $vault, $contact] = $this->setup_();
        $occasion = GiftOccasion::factory()->create();

        $this->expectException(ModelNotFoundException::class);
        (new CreateGift)->execute($this->base($user, $vault, $contact) + [
            'type' => Gift::TYPE_TO_GIVE,
            'gift_occasion_id' => $occasion->id,
            'name' => 'Socks',
        ]);
    }

    /** @test */
    public function it_rejects_an_unknown_type(): void
    {
        [$user, $vault, $contact] = $this->setup_();

        $this->expectException(ValidationException::class);
        (new CreateGift)->execute($this->base($user, $vault, $contact) + ['type' => 'stolen', 'name' => 'Socks']);
    }

    /** @test */
    public function it_fails_for_viewers(): void
    {
        $user = $this->createUser();
        $vault = $this->setPermissionInVault($user, Vault::PERMISSION_VIEW, $this->createVault($user->account));
        $contact = Contact::factory()->create(['vault_id' => $vault->id]);

        $this->expectException(NotEnoughPermissionException::class);
        (new CreateGift)->execute($this->base($user, $vault, $contact) + ['type' => Gift::TYPE_TO_GIVE, 'name' => 'Socks']);
    }

    /**
     * @return array{0: User, 1: Vault, 2: Contact}
     */
    private function setup_(): array
    {
        $user = $this->createUser();
        $vault = $this->setPermissionInVault($user, Vault::PERMISSION_EDIT, $this->createVault($user->account));
        $contact = Contact::factory()->create(['vault_id' => $vault->id]);

        return [$user, $vault, $contact];
    }

    private function base(User $user, Vault $vault, Contact $contact): array
    {
        return [
            'account_id' => $user->account_id,
            'author_id' => $user->id,
            'vault_id' => $vault->id,
            'contact_id' => $contact->id,
        ];
    }
}

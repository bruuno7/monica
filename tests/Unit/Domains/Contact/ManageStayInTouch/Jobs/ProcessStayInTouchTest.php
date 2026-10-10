<?php

namespace Tests\Unit\Domains\Contact\ManageStayInTouch\Jobs;

use App\Domains\Contact\ManageStayInTouch\Jobs\ProcessStayInTouch;
use App\Models\Contact;
use App\Models\UserNotificationChannel;
use App\Models\Vault;
use App\Notifications\StayInTouchTriggered;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProcessStayInTouchTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_notifies_once_about_contacts_going_cold(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::create(2026, 10, 10));

        $user = $this->createUser();
        $vault = $this->setPermissionInVault($user, Vault::PERMISSION_EDIT, $this->createVault($user->account));
        UserNotificationChannel::factory()->create([
            'user_id' => $user->id,
            'type' => UserNotificationChannel::TYPE_EMAIL,
            'content' => 'ross@geller.com',
            'active' => true,
            'verified_at' => Carbon::now(),
        ]);

        $cold = Contact::factory()->create(['vault_id' => $vault->id, 'stay_in_touch_frequency' => 30, 'last_interaction_at' => Carbon::now()->subDays(45)]);
        $fine = Contact::factory()->create(['vault_id' => $vault->id, 'stay_in_touch_frequency' => 90, 'last_interaction_at' => Carbon::now()->subDays(2)]);
        Contact::factory()->create(['vault_id' => $vault->id, 'stay_in_touch_frequency' => null, 'last_interaction_at' => Carbon::now()->subDays(400)]);

        (new ProcessStayInTouch)->handle();

        Notification::assertSentTimes(StayInTouchTriggered::class, 1);
        Notification::assertSentOnDemand(StayInTouchTriggered::class, fn ($notification, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'ross@geller.com');
        $this->assertNotNull($cold->fresh()->stay_in_touch_triggered_at);
        $this->assertNull($fine->fresh()->stay_in_touch_triggered_at);

        // running it again the next day does not insist
        Carbon::setTestNow(Carbon::create(2026, 10, 11));
        (new ProcessStayInTouch)->handle();
        Notification::assertSentTimes(StayInTouchTriggered::class, 1);

        // but a full period later, it does
        Carbon::setTestNow(Carbon::create(2026, 11, 12));
        (new ProcessStayInTouch)->handle();
        Notification::assertSentTimes(StayInTouchTriggered::class, 2);
    }

    /** @test */
    public function it_ignores_inactive_channels(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::create(2026, 10, 10));

        $user = $this->createUser();
        $vault = $this->setPermissionInVault($user, Vault::PERMISSION_EDIT, $this->createVault($user->account));
        UserNotificationChannel::factory()->create([
            'user_id' => $user->id,
            'type' => UserNotificationChannel::TYPE_EMAIL,
            'active' => false,
            'verified_at' => Carbon::now(),
        ]);
        Contact::factory()->create(['vault_id' => $vault->id, 'stay_in_touch_frequency' => 7, 'last_interaction_at' => Carbon::now()->subDays(45)]);

        (new ProcessStayInTouch)->handle();

        Notification::assertNothingSent();
    }
}

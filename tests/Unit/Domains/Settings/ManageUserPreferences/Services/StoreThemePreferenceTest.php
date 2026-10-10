<?php

namespace Tests\Unit\Domains\Settings\ManageUserPreferences\Services;

use App\Domains\Settings\ManageUserPreferences\Services\StoreThemePreference;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StoreThemePreferenceTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_stores_the_theme_preferences(): void
    {
        $ross = $this->createUser();
        $this->executeService($ross, $ross->account, User::THEME_DARK, 'violet', true);
    }

    /** @test */
    public function it_falls_back_to_the_default_accent_color(): void
    {
        $ross = $this->createUser();

        $user = (new StoreThemePreference)->execute([
            'account_id' => $ross->account_id,
            'author_id' => $ross->id,
            'theme' => User::THEME_SYSTEM,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'theme' => User::THEME_SYSTEM,
            'accent_color' => User::ACCENT_COLOR_DEFAULT,
            'oled_dark' => false,
        ]);
    }

    /** @test */
    public function it_fails_if_the_theme_is_unknown(): void
    {
        $ross = $this->createUser();

        $this->expectException(ValidationException::class);
        (new StoreThemePreference)->execute([
            'account_id' => $ross->account_id,
            'author_id' => $ross->id,
            'theme' => 'sepia',
        ]);
    }

    /** @test */
    public function it_fails_if_the_accent_color_is_unknown(): void
    {
        $ross = $this->createUser();

        $this->expectException(ValidationException::class);
        (new StoreThemePreference)->execute([
            'account_id' => $ross->account_id,
            'author_id' => $ross->id,
            'theme' => User::THEME_LIGHT,
            'accent_color' => 'magenta',
        ]);
    }

    /** @test */
    public function it_fails_if_wrong_parameters_are_given(): void
    {
        $request = [
            'title' => 'Ross',
        ];

        $this->expectException(ValidationException::class);
        (new StoreThemePreference)->execute($request);
    }

    /** @test */
    public function it_fails_if_user_doesnt_belong_to_account(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $ross = $this->createAdministrator();
        $account = $this->createAccount();
        $this->executeService($ross, $account, User::THEME_LIGHT, 'blue', false);
    }

    private function executeService(User $author, Account $account, string $theme, string $accentColor, bool $oledDark): void
    {
        $request = [
            'account_id' => $account->id,
            'author_id' => $author->id,
            'theme' => $theme,
            'accent_color' => $accentColor,
            'oled_dark' => $oledDark,
        ];

        $user = (new StoreThemePreference)->execute($request);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'account_id' => $account->id,
            'theme' => $theme,
            'accent_color' => $accentColor,
            'oled_dark' => $oledDark,
        ]);

        $this->assertInstanceOf(
            User::class,
            $user
        );
    }
}

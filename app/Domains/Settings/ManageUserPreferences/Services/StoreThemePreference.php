<?php

namespace App\Domains\Settings\ManageUserPreferences\Services;

use App\Interfaces\ServiceInterface;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Validation\Rule;

class StoreThemePreference extends BaseService implements ServiceInterface
{
    /**
     * Get the validation rules that apply to the service.
     */
    public function rules(): array
    {
        return [
            'account_id' => 'required|uuid|exists:accounts,id',
            'author_id' => 'required|uuid|exists:users,id',
            'theme' => ['required', 'string', Rule::in(User::THEMES)],
            'accent_color' => ['nullable', 'string', Rule::in(User::ACCENT_COLORS)],
            'oled_dark' => 'nullable|boolean',
        ];
    }

    /**
     * Get the permissions that apply to the user calling the service.
     */
    public function permissions(): array
    {
        return [
            'author_must_belong_to_account',
        ];
    }

    /**
     * Store the theme preferences (light/dark/system, accent color, OLED) for the given user.
     */
    public function execute(array $data): User
    {
        $this->validateRules($data);

        $this->author->theme = $data['theme'];
        $this->author->accent_color = $this->valueOrNull($data, 'accent_color') ?? User::ACCENT_COLOR_DEFAULT;
        $this->author->oled_dark = (bool) ($data['oled_dark'] ?? false);
        $this->author->save();

        return $this->author;
    }
}

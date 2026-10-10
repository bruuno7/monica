<?php

namespace App\Domains\Settings\ManageUserPreferences\Web\Controllers;

use App\Domains\Settings\ManageUserPreferences\Services\StoreThemePreference;
use App\Domains\Settings\ManageUserPreferences\Web\ViewHelpers\UserPreferencesIndexViewHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PreferencesThemeController extends Controller
{
    public function store(Request $request)
    {
        $data = [
            'account_id' => Auth::user()->account_id,
            'author_id' => Auth::id(),
            'theme' => $request->input('theme'),
            'accent_color' => $request->input('accent_color'),
            'oled_dark' => $request->boolean('oled_dark'),
        ];

        $user = (new StoreThemePreference)->execute($data);

        return response()->json([
            'data' => UserPreferencesIndexViewHelper::dtoTheme($user),
        ], 200);
    }
}

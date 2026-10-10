<?php

namespace App\Domains\Vault\ManageToday\Web\Controllers;

use App\Domains\Vault\ManageToday\Web\ViewHelpers\VaultTodayViewHelper;
use App\Domains\Vault\ManageVault\Web\ViewHelpers\VaultIndexViewHelper;
use App\Http\Controllers\Controller;
use App\Models\Vault;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class VaultTodayController extends Controller
{
    public function index(Vault $vault): Response
    {
        return Inertia::render('Vault/Today/Index', [
            'layoutData' => VaultIndexViewHelper::layoutData($vault),
            'data' => VaultTodayViewHelper::data($vault, Auth::user()),
        ]);
    }
}

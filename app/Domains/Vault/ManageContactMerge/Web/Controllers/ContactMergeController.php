<?php

namespace App\Domains\Vault\ManageContactMerge\Web\Controllers;

use App\Domains\Vault\ManageContactMerge\Services\MergeContacts;
use App\Domains\Vault\ManageContactMerge\Web\ViewHelpers\ContactDuplicatesViewHelper;
use App\Domains\Vault\ManageVault\Web\ViewHelpers\VaultIndexViewHelper;
use App\Http\Controllers\Controller;
use App\Models\Vault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ContactMergeController extends Controller
{
    public function index(Request $request, Vault $vault): Response
    {
        $preselected = $request->query('contact');
        $contact = $preselected ? $vault->contacts()->find($preselected) : null;

        return Inertia::render('Vault/Contact/Duplicates/Index', [
            'layoutData' => VaultIndexViewHelper::layoutData($vault),
            'data' => ContactDuplicatesViewHelper::data($vault, Auth::user()),
            'preselected' => $contact ? ContactDuplicatesViewHelper::dto($contact, Auth::user()) : null,
        ]);
    }

    public function store(Request $request, string $vaultId): JsonResponse
    {
        $contact = (new MergeContacts)->execute([
            'account_id' => Auth::user()->account_id,
            'author_id' => Auth::id(),
            'vault_id' => $vaultId,
            'contact_id' => $request->input('contact_id'),
            'duplicate_contact_id' => $request->input('duplicate_contact_id'),
        ]);

        return response()->json([
            'data' => [
                'contact' => ContactDuplicatesViewHelper::dto($contact, Auth::user()),
                'url' => route('contact.show', ['vault' => $contact->vault_id, 'contact' => $contact->id]),
            ],
        ]);
    }
}

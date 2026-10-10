<?php

namespace App\Domains\Vault\QuickCapture\Web\Controllers;

use App\Domains\Vault\QuickCapture\Services\QuickCapture;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VaultQuickCaptureController extends Controller
{
    public function store(Request $request, string $vaultId): JsonResponse
    {
        $contacts = (new QuickCapture)->execute([
            'account_id' => Auth::user()->account_id,
            'author_id' => Auth::id(),
            'vault_id' => $vaultId,
            'type' => $request->input('type'),
            'contact_ids' => $request->input('contact_ids', []),
            'body' => $request->input('body'),
            'title' => $request->input('title'),
            'date' => $request->input('date'),
        ]);

        return response()->json([
            'data' => [
                'type' => $request->input('type'),
                'contacts' => $contacts->map(fn (Contact $contact) => [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'url' => route('contact.show', [
                        'vault' => $contact->vault_id,
                        'contact' => $contact->id,
                    ]),
                ])->values(),
            ],
        ], 201);
    }
}

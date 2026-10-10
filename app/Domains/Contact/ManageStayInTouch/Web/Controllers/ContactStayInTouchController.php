<?php

namespace App\Domains\Contact\ManageStayInTouch\Web\Controllers;

use App\Domains\Contact\ManageStayInTouch\Services\RecordContactInteraction;
use App\Domains\Contact\ManageStayInTouch\Services\UpdateStayInTouchFrequency;
use App\Helpers\StayInTouchHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactStayInTouchController extends Controller
{
    public function update(Request $request, string $vaultId, string $contactId): JsonResponse
    {
        $frequency = $request->input('frequency');

        $contact = (new UpdateStayInTouchFrequency)->execute([
            'account_id' => Auth::user()->account_id,
            'author_id' => Auth::id(),
            'vault_id' => $vaultId,
            'contact_id' => $contactId,
            'frequency' => $frequency === null || $frequency === '' ? null : (int) $frequency,
        ]);

        return response()->json([
            'data' => StayInTouchHelper::dto($contact, Auth::user()),
        ]);
    }

    public function touch(Request $request, string $vaultId, string $contactId): JsonResponse
    {
        $contact = (new RecordContactInteraction)->execute([
            'account_id' => Auth::user()->account_id,
            'author_id' => Auth::id(),
            'vault_id' => $vaultId,
            'contact_id' => $contactId,
            'happened_at' => $request->input('happened_at'),
        ]);

        return response()->json([
            'data' => StayInTouchHelper::dto($contact, Auth::user()),
        ]);
    }
}

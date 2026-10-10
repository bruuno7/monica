<?php

namespace App\Domains\Contact\ManageGifts\Web\Controllers;

use App\Domains\Contact\ManageGifts\Services\CreateGift;
use App\Domains\Contact\ManageGifts\Services\DestroyGift;
use App\Domains\Contact\ManageGifts\Services\UpdateGift;
use App\Domains\Contact\ManageGifts\Web\ViewHelpers\ModuleGiftsViewHelper;
use App\Helpers\MonetaryNumberHelper;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactModuleGiftController extends Controller
{
    public function store(Request $request, string $vaultId, string $contactId): JsonResponse
    {
        $gift = (new CreateGift)->execute($this->payload($request, $vaultId, $contactId));

        return response()->json([
            'data' => ModuleGiftsViewHelper::dto($gift, Contact::findOrFail($contactId), Auth::user()),
        ], 201);
    }

    public function update(Request $request, string $vaultId, string $contactId, int $giftId): JsonResponse
    {
        $gift = (new UpdateGift)->execute($this->payload($request, $vaultId, $contactId) + ['gift_id' => $giftId]);

        return response()->json([
            'data' => ModuleGiftsViewHelper::dto($gift, Contact::findOrFail($contactId), Auth::user()),
        ]);
    }

    public function destroy(Request $request, string $vaultId, string $contactId, int $giftId): JsonResponse
    {
        (new DestroyGift)->execute([
            'account_id' => Auth::user()->account_id,
            'author_id' => Auth::id(),
            'vault_id' => $vaultId,
            'contact_id' => $contactId,
            'gift_id' => $giftId,
        ]);

        return response()->json(['data' => true]);
    }

    private function payload(Request $request, string $vaultId, string $contactId): array
    {
        $currencyId = $request->input('currency_id');
        $currency = $currencyId ? Currency::find($currencyId)?->code : null;
        $price = $request->input('estimated_price');

        return [
            'account_id' => Auth::user()->account_id,
            'author_id' => Auth::id(),
            'vault_id' => $vaultId,
            'contact_id' => $contactId,
            'type' => $request->input('type'),
            'gift_occasion_id' => $request->input('gift_occasion_id'),
            'gift_state_id' => $request->input('gift_state_id'),
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'url' => $request->input('url') ?: null,
            'estimated_price' => $price !== null && $price !== '' ? MonetaryNumberHelper::parseInput((string) $price, $currency) : null,
            'currency_id' => $currencyId ?: null,
            'received_at' => $request->input('received_at') ?: null,
            'given_at' => $request->input('given_at') ?: null,
        ];
    }
}

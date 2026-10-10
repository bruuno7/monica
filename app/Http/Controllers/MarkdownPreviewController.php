<?php

namespace App\Http\Controllers;

use App\Helpers\MarkdownHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Renders Markdown written by the user, so the editors can show a preview that
 * is exactly what will be displayed once saved.
 */
class MarkdownPreviewController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'nullable|string|max:65535',
        ]);

        return response()->json([
            'data' => [
                'html' => MarkdownHelper::toHtml($validated['content'] ?? null),
            ],
        ]);
    }
}

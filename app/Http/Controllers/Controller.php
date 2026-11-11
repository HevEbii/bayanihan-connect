<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Ends a submission on the confirmation screen, or answers with JSON when an offline entry is being synced (FR07).
     *
     * @param  array<string, string>  $snapshot  label => value rows shown on the confirmation screen
     */
    protected function saved(Request $request, string $title, string $reference, array $snapshot,
        string $againUrl, string $viewUrl): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'reference' => $reference, 'message' => $title]);
        }

        return redirect()->route('confirmation')->with('confirmation', [
            'title' => $title,
            'reference' => $reference,
            'snapshot' => $snapshot,
            'again_url' => $againUrl,
            'view_url' => $viewUrl,
        ]);
    }
}

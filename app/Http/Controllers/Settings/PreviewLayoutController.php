<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PreviewLayoutUpdateRequest;
use Illuminate\Http\RedirectResponse;

class PreviewLayoutController extends Controller
{
    /**
     * Persist the preview-modal pane layout (SPEC §5.4 editable layout:
     * swappable panes + drag split). Guests never reach this endpoint —
     * they keep the preference in localStorage.
     */
    public function update(PreviewLayoutUpdateRequest $request): RedirectResponse
    {
        /** @var numeric-string $split */
        $split = $request->validated('split');

        $request->user()->forceFill([
            'preview_layout' => [
                'side' => $request->string('side')->toString(),
                // Normalize "35" → 35 / "37.5" → 37.5 so the JSON round
                // trip keeps a real number regardless of form encoding.
                'split' => $split + 0,
            ],
        ])->save();

        return back();
    }
}

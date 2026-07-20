<?php

namespace App\Http\Controllers;

use App\Services\Legal\LegalPages;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Legal pages (SPEC §15.7, §15.1): seven thin routes onto one renderer.
 * Content lives as markdown in `resources/legal/` (see LegalPages); every
 * page is SSR and SEO-indexed — no noindex anywhere in this zone.
 */
class LegalController extends Controller
{
    public function __construct(private readonly LegalPages $legal) {}

    public function show(string $page): Response
    {
        $document = $this->legal->find($page);

        abort_if($document === null, 404);

        return Inertia::render('legal/show', [
            'page' => $document,
            'meta' => [
                'title' => $document['title'].' — FrontendParts',
                'description' => $document['description'],
                'canonical' => $document['url'],
                'og_image' => URL::to('/brand/logo.png'),
            ],
        ]);
    }
}

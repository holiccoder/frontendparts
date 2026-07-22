<?php

namespace App\Http\Controllers;

use App\Services\Legal\LegalPages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Legal pages: thin routes onto one renderer. Content lives as markdown in
 * `resources/legal/` (see LegalPages); every page is SSR and SEO-indexed —
 * no noindex anywhere in this zone. Pages are static, so they carry the
 * public cache headers.
 */
class LegalController extends Controller
{
    public function __construct(private readonly LegalPages $legal) {}

    public function show(Request $request, string $page): Response
    {
        $document = $this->legal->find($page);

        abort_if($document === null, 404);

        return $this->cachedResponse(Inertia::render('legal/show', [
            'page' => $document,
            'meta' => [
                'title' => $document['title'].' — '.config('app.name'),
                'description' => $document['description'],
                'canonical' => $document['url'],
                'og_image' => URL::to('/brand/logo.png'),
            ],
        ]), $request);
    }
}

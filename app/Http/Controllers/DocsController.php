<?php

namespace App\Http\Controllers;

use App\Services\Docs\DocsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public documentation (SPEC §13.2): file-based markdown from
 * `docs/content/` rendered SSR at `/docs/{section}/{page}` with sidebar
 * nav tree, per-page TOC and prev/next footer links.
 */
class DocsController extends Controller
{
    public function __construct(private readonly DocsRepository $docs) {}

    public function index(): RedirectResponse
    {
        $first = $this->docs->firstPage();

        abort_if($first === null, 404);

        return redirect()->route('docs.show', $first);
    }

    public function show(string $section, string $page): Response
    {
        $doc = $this->docs->find($section, $page);

        abort_if($doc === null, 404);

        return Inertia::render('docs/show', [
            'doc' => $doc,
            'nav' => $this->docs->navTree($section, $page),
            'pagination' => $this->docs->neighbours($section, $page),
            'meta' => [
                'title' => $doc['title'].' · '.config('app.name').' Docs',
                'description' => $doc['description'] !== ''
                    ? $doc['description']
                    : config('app.name')." documentation — {$doc['title']}.",
                'canonical' => route('docs.show', ['section' => $section, 'page' => $page]),
                'og_image' => URL::to('/brand/logo.png'),
            ],
        ]);
    }

    /**
     * Docs search (SPEC §13.2 — basic at launch): SSR results page fed by
     * the repository's title/body matcher; the sidebar search box submits
     * here as a plain GET so results are deep-linkable.
     */
    public function search(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));

        return Inertia::render('docs/search', [
            'query' => $query,
            'results' => $this->docs->search($query),
            'nav' => $this->docs->navTree(),
            'meta' => [
                'title' => ($query === '' ? 'Search the docs' : "Docs search: {$query}").' · '.config('app.name').' Docs',
                'description' => 'Search the '.config('app.name').' documentation — guides, billing, team and troubleshooting.',
                'canonical' => route('docs.search'),
                'og_image' => URL::to('/brand/logo.png'),
                'robots' => 'noindex',
            ],
        ]);
    }
}

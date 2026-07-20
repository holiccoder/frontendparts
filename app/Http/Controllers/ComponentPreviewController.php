<?php

namespace App\Http\Controllers;

use App\Models\Component;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Serves the prebuilt preview HTML artifacts to the catalog's sandboxed
 * iframes (SPEC §5.3, §10.3). Published components only; the requested
 * `{version}/{framework}` must match the artifact path recorded at build
 * time, which also keeps the URL immune to path traversal. Versioned URLs
 * are cached immutably; CSP `sandbox allow-scripts` mirrors the iframe
 * sandbox (no `allow-same-origin`).
 */
class ComponentPreviewController extends Controller
{
    public function show(string $component, string $version, string $framework): Response
    {
        $model = Component::query()
            ->where('slug', $component)
            ->published()
            ->first();

        abort_if($model === null, 404);

        $path = $model->previewPath($framework);

        abort_if($path !== "{$model->slug}/{$version}/{$framework}.html", 404);

        $disk = Storage::disk((string) config('library.preview_disk', 'previews'));

        abort_if(! $disk->exists($path), 404);

        return response($disk->get($path), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => (string) config('library.preview_cache_control', 'public, max-age=31536000, immutable'),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => (string) config('library.preview_csp', 'sandbox allow-scripts'),
        ]);
    }

    /**
     * Serves one viewport screenshot (`{framework}-{width}.png`) from the
     * same preview disk. Used for catalog thumbnails and OG images; same
     * published-only + version-match rules as the preview HTML above.
     */
    public function shot(string $component, string $version, string $file): Response
    {
        $model = Component::query()
            ->where('slug', $component)
            ->published()
            ->first();

        abort_if($model === null, 404);

        $framework = explode('-', $file)[0];
        $path = $model->previewPath($framework);

        abort_if($path !== "{$model->slug}/{$version}/{$framework}.html", 404);

        $disk = Storage::disk((string) config('library.preview_disk', 'previews'));
        $shot = dirname($path)."/shots/{$file}";

        abort_if(! $disk->exists($shot), 404);

        return response($disk->get($shot), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => (string) config('library.preview_cache_control', 'public, max-age=31536000, immutable'),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

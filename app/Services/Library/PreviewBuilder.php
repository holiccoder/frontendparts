<?php

namespace App\Services\Library;

use App\Models\Component;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the single self-contained preview HTML artifact for one component +
 * framework (SPEC §5.2):
 *
 *  1. verify the transitive child closure's sources still exist in the app
 *  2. generate a `.build/{slug}.entry.{tsx,ts}` file mounting the component
 *     with its `data.json`
 *  3. run `vite build` with the app's `vite.build.config.ts` (single chunk,
 *     no css splitting, `FP_INSTRUMENT=1` for the data-fp-* instrumentation)
 *  4. inline JS + CSS into a plain HTML shell and store it on the previews
 *     disk at `{slug}/{version}/{framework}.html`
 *
 * The `.build/` working directory is always cleaned up afterwards. The
 * build/compose/runtime mechanics live in the shared
 * {@see PreviewArtifactBuilder} base (the fork rebuild runs the same steps).
 */
class PreviewBuilder extends PreviewArtifactBuilder
{
    /**
     * Build one framework artifact; returns the disk-relative artifact path.
     *
     * @throws PreviewBuildException
     */
    public function build(Component $component, string $framework): string
    {
        $appPath = $this->appPath($framework);
        $componentsPath = (string) config("library.{$framework}_path");

        if (! is_file($appPath.'/package.json') || ! is_file($appPath.'/vite.build.config.ts')) {
            throw PreviewBuildException::appNotBuildable($framework, $appPath);
        }

        $this->assertSourcesExist($component, $framework, $componentsPath);

        $token = str_replace('/', '--', $component->slug);
        $buildDir = $appPath.DIRECTORY_SEPARATOR.'.build';
        $entryFile = $buildDir.DIRECTORY_SEPARATOR.$token.($framework === 'vue' ? '.entry.ts' : '.entry.tsx');
        $outDir = $buildDir.DIRECTORY_SEPARATOR.'out'.DIRECTORY_SEPARATOR.$token;

        File::ensureDirectoryExists($buildDir);
        File::put($entryFile, $this->entrySource($component->slug, $framework));

        try {
            $this->runBuild($component->slug, $framework, $appPath, $entryFile, $outDir);

            $html = $this->composeHtml(e("{$component->name} · {$framework} · FrontendParts"), $framework, $component->slug, $outDir);

            $relativePath = "{$component->slug}/{$component->version}/{$framework}.html";

            Storage::disk($this->previewDisk())->put($relativePath, $html);

            return $relativePath;
        } finally {
            $this->cleanupBuildDir($buildDir, $entryFile, $outDir);
        }
    }

    /**
     * The component and every descendant in its composition closure must have
     * their source + data.json on disk before we spend a build.
     *
     * @throws PreviewBuildException
     */
    private function assertSourcesExist(Component $component, string $framework, string $componentsPath): void
    {
        $slugs = [$component->slug];

        if ($component->descendantIds() !== []) {
            $slugs = [...$slugs, ...Component::query()->whereIn('id', $component->descendantIds())->pluck('slug')->all()];
        }

        $indexFile = $framework === 'vue' ? 'index.vue' : 'index.tsx';

        foreach (array_unique($slugs) as $slug) {
            if (! is_file($componentsPath.'/'.$slug.'/'.$indexFile) || ! is_file($componentsPath.'/'.$slug.'/data.json')) {
                throw PreviewBuildException::missingSource($framework, (string) $slug);
            }
        }
    }

    private function entrySource(string $slug, string $framework): string
    {
        if ($framework === 'vue') {
            return <<<TS
            import { createApp, h } from 'vue';
            import Component from '../src/components/{$slug}/index.vue';
            import data from '../src/components/{$slug}/data.json';
            import '../src/app.css';

            createApp({ render: () => h(Component, data) }).mount('#root');

            TS;
        }

        return <<<TSX
        import { createRoot } from 'react-dom/client';
        import Component from '../src/components/{$slug}/index';
        import data from '../src/components/{$slug}/data.json';
        import '../src/app.css';

        createRoot(document.getElementById('root')!).render(<Component {...data} />);

        TSX;
    }
}

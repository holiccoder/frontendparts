<?php

namespace App\Services\Library;

use App\Models\ComponentFork;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the prebuilt preview artifact for a live-edit fork (SPEC §5.6 Save
 * to Project) — the SAME pipeline steps as the library preview build
 * ({@see PreviewBuilder}), sourcing from the fork's POSTED edited files
 * instead of the library tree:
 *
 *  1. materialize the fork's edited sources verbatim into a throwaway
 *     `.build/fork-{id}/` overlay inside the library app (posted relative
 *     paths are kept, so in-closure imports resolve inside the overlay and
 *     bare package imports resolve against the app's own node_modules)
 *  2. generate the entry module mounting the entry component with its data
 *  3. `vite build` with `FP_INSTRUMENT=1` (fork previews keep the data-fp-*
 *     outline instrumentation) via the shared {@see PreviewArtifactBuilder}
 *  4. inline JS + CSS into the HTML shell and store it on the previews disk
 *     at `forks/{id}/{framework}.html`
 *
 * The overlay directory is always removed afterwards; the library tree and
 * the original component are never touched.
 */
class ForkPreviewBuilder extends PreviewArtifactBuilder
{
    /**
     * Build the fork's framework artifact; returns the disk-relative path.
     *
     * @throws PreviewBuildException
     */
    public function build(ComponentFork $fork): string
    {
        $framework = $fork->framework;
        $appPath = $this->appPath($framework);

        if (! is_file($appPath.'/package.json') || ! is_file($appPath.'/vite.build.config.ts')) {
            throw PreviewBuildException::appNotBuildable($framework, $appPath);
        }

        $slug = "fork-{$fork->id}";
        $buildDir = $appPath.DIRECTORY_SEPARATOR.'.build'.DIRECTORY_SEPARATOR.$slug;
        $entryFile = $buildDir.DIRECTORY_SEPARATOR.($framework === 'vue' ? 'fork.entry.ts' : 'fork.entry.tsx');
        $outDir = $buildDir.DIRECTORY_SEPARATOR.'out';

        File::ensureDirectoryExists($buildDir);
        $this->materializeSources($fork, $buildDir);
        File::put($entryFile, $this->entrySource($fork));

        try {
            $this->runBuild($slug, $framework, $appPath, $entryFile, $outDir);

            $html = $this->composeHtml(
                e("{$fork->component->name} fork · {$framework} · FrontendParts"),
                $framework,
                $slug,
                $outDir,
            );

            $relativePath = "forks/{$fork->id}/{$framework}.html";

            Storage::disk($this->previewDisk())->put($relativePath, $html);

            return $relativePath;
        } finally {
            File::deleteDirectory($buildDir);

            // Leave no empty working directories behind.
            $parent = dirname($buildDir);

            if (is_dir($parent) && count(File::files($parent)) === 0 && count(File::directories($parent)) === 0) {
                File::deleteDirectory($parent);
            }
        }
    }

    /**
     * Write the posted edited sources into the overlay verbatim. Paths were
     * validated by the save endpoint and are re-checked here (defense in
     * depth) — the overlay never escapes its build directory.
     *
     * @throws PreviewBuildException
     */
    private function materializeSources(ComponentFork $fork, string $buildDir): void
    {
        foreach ($fork->files ?? [] as $path => $code) {
            if (! is_string($path) || ! is_string($code)
                || preg_match('#^(?!/)(?!.*(?:^|/)\.\.(?:/|$))[A-Za-z0-9._\-/]+$#', $path) !== 1) {
                throw PreviewBuildException::unsafePath($fork->framework, (string) $path);
            }

            $target = $buildDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);

            File::ensureDirectoryExists(dirname($target));
            File::put($target, $code);
        }
    }

    /**
     * The generated entry module. React forks keep the library-relative
     * layout (`{slug}/index.tsx` + `{slug}/data.json`); vue forks keep the
     * flat repl layout (`src/{PascalName}.vue` + `src/data.ts`, entry file
     * named by the payload). Both mirror the library pipeline's entry shape.
     *
     * @throws PreviewBuildException
     */
    private function entrySource(ComponentFork $fork): string
    {
        $files = $fork->files ?? [];

        if ($fork->framework === 'vue') {
            $entryFile = $fork->entry_file;

            if ($entryFile === null || ! array_key_exists($entryFile, $files)) {
                throw PreviewBuildException::missingSource('vue', (string) $entryFile);
            }

            $data = array_key_exists('src/data.ts', $files) ? "import data from './src/data';" : 'const data = {};';

            return <<<TS
            import { createApp, h } from 'vue';
            import Component from './{$entryFile}';
            {$data}
            import '../../src/app.css';

            createApp({ render: () => h(Component, data) }).mount('#root');

            TS;
        }

        $slug = $fork->component->slug;

        if (! array_key_exists("{$slug}/index.tsx", $files)) {
            throw PreviewBuildException::missingSource('react', $slug);
        }

        $data = array_key_exists("{$slug}/data.json", $files) ? "import data from './{$slug}/data.json';" : 'const data = {};';

        return <<<TSX
        import { createRoot } from 'react-dom/client';
        import Component from './{$slug}/index';
        {$data}
        import '../../src/app.css';

        createRoot(document.getElementById('root')!).render(<Component {...data} />);

        TSX;
    }
}

<?php

namespace App\Services\Library;

use App\Models\Component;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

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
 * The `.build/` working directory is always cleaned up afterwards.
 */
class PreviewBuilder
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

            $html = $this->composeHtml($component, $framework, $outDir);

            $relativePath = "{$component->slug}/{$component->version}/{$framework}.html";

            Storage::disk($this->previewDisk())->put($relativePath, $html);

            return $relativePath;
        } finally {
            File::delete($entryFile);
            File::deleteDirectory($outDir);

            // Leave no empty working directories behind.
            if (is_dir($buildDir.DIRECTORY_SEPARATOR.'out') && count(File::files($buildDir.DIRECTORY_SEPARATOR.'out')) === 0 && count(File::directories($buildDir.DIRECTORY_SEPARATOR.'out')) === 0) {
                File::deleteDirectory($buildDir.DIRECTORY_SEPARATOR.'out');
            }

            if (is_dir($buildDir) && count(File::files($buildDir)) === 0 && count(File::directories($buildDir)) === 0) {
                File::deleteDirectory($buildDir);
            }
        }
    }

    /**
     * The library app root: explicit config override, or derived from the
     * components path (`{app}/src/components` → `{app}`).
     */
    public function appPath(string $framework): string
    {
        $configured = config("library.{$framework}_app_path");

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return dirname((string) config("library.{$framework}_path"), 2);
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

    /**
     * @throws PreviewBuildException
     */
    private function runBuild(string $slug, string $framework, string $appPath, string $entryFile, string $outDir): void
    {
        $process = new Process(
            [$this->npmBinary(), 'run', 'build', '--', '--config', 'vite.build.config.ts'],
            $appPath,
            [
                'FP_ENTRY' => $this->normalizePath($entryFile),
                'FP_OUT_DIR' => $this->normalizePath($outDir),
                'FP_INSTRUMENT' => '1',
            ],
            null,
            300,
        );

        $process->run();

        if (! $process->isSuccessful()) {
            $output = trim($process->getErrorOutput()."\n".$process->getOutput());

            throw PreviewBuildException::buildFailed($framework, $slug, mb_strimwidth($output, 0, 1500, '…'));
        }
    }

    /**
     * Inline the built JS + CSS into the HTML shell (SPEC §5.2 step 3).
     *
     * @throws PreviewBuildException
     */
    private function composeHtml(Component $component, string $framework, string $outDir): string
    {
        $js = [];
        $css = [];

        foreach (File::allFiles($outDir) as $file) {
            $extension = strtolower($file->getExtension());

            if ($extension === 'js') {
                $js[] = $file->getContents();
            } elseif ($extension === 'css') {
                $css[] = $file->getContents();
            }
        }

        if ($js === []) {
            throw PreviewBuildException::noBundle($framework, $component->slug, $outDir);
        }

        // Keep the inline script from breaking out of its own tag.
        $script = str_replace('</script', '<\\/script', implode("\n", $js));
        $style = implode("\n", $css);

        $title = e("{$component->name} · {$framework} · FrontendParts");

        return <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$title}</title>
        <style>
        {$style}
        </style>
        </head>
        <body>
        <div id="root"></div>
        <script type="module">
        {$script}
        </script>
        </body>
        </html>
        HTML;
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

    private function npmBinary(): string
    {
        return (string) config('library.npm_binary', 'npm');
    }

    private function previewDisk(): string
    {
        return (string) config('library.preview_disk', 'previews');
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}

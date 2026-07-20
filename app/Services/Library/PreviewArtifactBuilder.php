<?php

namespace App\Services\Library;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Shared preview-artifact machinery (SPEC §5.2) behind both preview
 * pipelines: the library component pipeline ({@see PreviewBuilder}) and the
 * live-edit fork rebuild ({@see ForkPreviewBuilder}). Both run the SAME
 * steps — a generated entry module, `vite build` with the app's
 * `vite.build.config.ts` (single chunk, no css splitting, `FP_INSTRUMENT=1`
 * for the data-fp-* instrumentation), then JS + CSS inlined into a plain
 * HTML shell with the SPEC §5.3 postMessage runtime — differing only in
 * where the sources come from (library tree vs posted edited files).
 */
abstract class PreviewArtifactBuilder
{
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
     * @throws PreviewBuildException
     */
    protected function runBuild(string $slug, string $framework, string $appPath, string $entryFile, string $outDir): void
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
    protected function composeHtml(string $title, string $framework, string $slug, string $outDir): string
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
            throw PreviewBuildException::noBundle($framework, $slug, $outDir);
        }

        // Keep the inline script from breaking out of its own tag.
        $script = str_replace('</script', '<\\/script', implode("\n", $js));
        $style = implode("\n", $css);
        $runtime = $this->runtimeScript();

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
        <script>
        {$runtime}
        </script>
        <script type="module">
        {$script}
        </script>
        </body>
        </html>
        HTML;
    }

    /**
     * Remove the generated entry + output directory of one build, and the
     * `.build/` working directory itself once nothing is left in it.
     */
    protected function cleanupBuildDir(string $buildDir, string $entryFile, string $outDir): void
    {
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

    /**
     * Tiny dependency-free postMessage runtime (SPEC §5.3), identical for
     * react and vue builds:
     *
     *  - parent → iframe: fp:highlight `{type:'highlight', slug, instance:n|null}`
     *    soft-outlines every `[data-fp-c="<slug>"]` element (instance null) or
     *    strong-outlines the nth match (`data-fp-i`) and scrolls it into view;
     *    fp:clear `{type:'clear'}` removes all outlines; fp:theme
     *    `{type:'theme', mode:'dark'|'light'}` toggles `dark` on <html>.
     *  - iframe → parent: fp:ready `{type:'ready'}` on load, fp:height
     *    `{type:'height', px}` on load and on ResizeObserver size changes.
     *
     * Written without `$` or template literals so it can be inlined from a
     * PHP heredoc without escaping surprises.
     */
    protected function runtimeScript(): string
    {
        return <<<'JS'
        /* FrontendParts preview runtime (SPEC §5.3): fp:highlight fp:clear fp:theme in, fp:ready fp:height out. */
        (function () {
            'use strict';

            var ACCENT = '#6366f1';
            var touched = [];

            function restoreAll() {
                touched.forEach(function (entry) {
                    entry.el.style.outline = entry.outline;
                    entry.el.style.outlineOffset = entry.outlineOffset;
                    entry.el.style.boxShadow = entry.boxShadow;
                    entry.el.style.background = entry.background;
                });
                touched = [];
            }

            function paint(el, strong) {
                touched.push({
                    el: el,
                    outline: el.style.outline,
                    outlineOffset: el.style.outlineOffset,
                    boxShadow: el.style.boxShadow,
                    background: el.style.background
                });
                el.style.outline = '2px ' + (strong ? 'solid ' : 'dashed ') + ACCENT;
                el.style.outlineOffset = '2px';
                if (strong) {
                    el.style.boxShadow = '0 0 0 4px rgba(99, 102, 241, 0.25)';
                } else {
                    el.style.background = 'rgba(99, 102, 241, 0.06)';
                }
            }

            function highlight(slug, instance) {
                restoreAll();

                if (typeof slug !== 'string' || slug === '') {
                    return;
                }

                var matches = document.querySelectorAll('[data-fp-c="' + slug + '"]');

                if (instance === null || instance === undefined) {
                    matches.forEach(function (el) { paint(el, false); });
                    return;
                }

                matches.forEach(function (el) {
                    if (el.getAttribute('data-fp-i') === String(instance)) {
                        paint(el, true);
                        el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    }
                });
            }

            window.addEventListener('message', function (event) {
                var data = event.data;

                if (!data || typeof data !== 'object') {
                    return;
                }

                if (data.type === 'highlight') {
                    highlight(data.slug, data.instance);
                } else if (data.type === 'clear') {
                    restoreAll();
                } else if (data.type === 'theme') {
                    document.documentElement.classList.toggle('dark', data.mode === 'dark');
                }
            });

            function post(message) {
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage(message, '*');
                }
            }

            function reportHeight() {
                post({ type: 'height', px: Math.ceil(document.documentElement.scrollHeight) });
            }

            window.addEventListener('load', function () {
                post({ type: 'ready' });
                reportHeight();
            });

            if ('ResizeObserver' in window) {
                new ResizeObserver(reportHeight).observe(document.documentElement);
            }
        })();
        JS;
    }

    protected function npmBinary(): string
    {
        return (string) config('library.npm_binary', 'npm');
    }

    protected function previewDisk(): string
    {
        return (string) config('library.preview_disk', 'previews');
    }

    protected function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}

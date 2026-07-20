<?php

namespace App\Services\Library;

/**
 * Derives the composition graph from code (SPEC §2.2): any ES import in a
 * component's source file that resolves into another library component
 * directory (a `{level}/{slug}` folder containing params.json) registers a
 * child edge. npm packages, CSS and other non-component imports are ignored.
 */
class CompositionGraph
{
    /**
     * Extract child edges for one component source file.
     *
     * @return list<string> full slugs of imported library components
     */
    public function childEdges(string $filePath, string $source, string $componentsRoot): array
    {
        $edges = [];

        foreach ($this->importPaths($source) as $importPath) {
            $resolved = $this->resolveImport($importPath, $filePath, $componentsRoot);

            if ($resolved === null) {
                continue;
            }

            $edges[$resolved] = true;
        }

        return array_keys($edges);
    }

    /**
     * Build the full edge map for a set of scanned components.
     *
     * @param  array<string, ParsedComponent>  $components
     * @return array<string, list<string>> parent full slug => child full slugs
     */
    public function edges(array $components, string $componentsRoot): array
    {
        $edges = [];

        foreach ($components as $fullSlug => $component) {
            $children = array_filter(
                $this->childEdges($component->filePath, $component->source, $componentsRoot),
                fn (string $child): bool => $child !== $fullSlug && isset($components[$child]),
            );

            $edges[$fullSlug] = array_values($children);
        }

        return $edges;
    }

    /**
     * Validate the graph: cycle detection and max depth (root = depth 1).
     *
     * @param  array<string, list<string>>  $edges
     * @return array<string, list<string>> full slug => error messages
     */
    public function validate(array $edges, int $maxDepth = 10): array
    {
        $errors = [];

        foreach ($this->findCycles($edges) as $cycle) {
            $message = 'Composition cycle detected: '.implode(' → ', $cycle);

            foreach (array_unique($cycle) as $slug) {
                $errors[$slug][] = $message;
            }
        }

        foreach ($this->depths($edges) as $slug => $depth) {
            if ($depth > $maxDepth) {
                $errors[$slug][] = "Composition depth {$depth} at {$slug} exceeds the maximum of {$maxDepth}";
            }
        }

        return $errors;
    }

    /**
     * @return list<string> import path literals found in the source
     */
    private function importPaths(string $source): array
    {
        preg_match_all(
            '/import\s+(?:[^\'";]*?\s+from\s+)?[\'"](?<path>[^\'"]+)[\'"]/',
            $source,
            $matches,
        );

        return $matches['path'];
    }

    /**
     * Resolve an import path to a component full slug, or null when the
     * import is not a library component (npm package, css, missing file).
     */
    private function resolveImport(string $importPath, string $filePath, string $componentsRoot): ?string
    {
        $base = match (true) {
            str_starts_with($importPath, '@/') => dirname($componentsRoot).'/'.substr($importPath, 2),
            str_starts_with($importPath, '.') => dirname($filePath).'/'.$importPath,
            default => null,
        };

        if ($base === null) {
            return null;
        }

        $resolvedFile = $this->resolveFile($this->normalizePath($base));

        if ($resolvedFile === null) {
            return null;
        }

        return $this->componentSlugForFile($resolvedFile, $componentsRoot);
    }

    private function resolveFile(string $base): ?string
    {
        foreach ([$base.'.tsx', $base.'.ts', $base.'.vue', $base.'/index.tsx', $base.'/index.vue'] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Find the full slug of the component owning a file: the file's directory
     * (or an ancestor) must be a `{level}/{slug}` folder containing params.json
     * directly under the components root.
     */
    private function componentSlugForFile(string $file, string $componentsRoot): ?string
    {
        $root = $this->normalizePath($componentsRoot);
        $directory = $this->normalizePath(dirname($file));

        while (strlen($directory) > strlen($root) && str_starts_with($directory, $root.'/')) {
            if (is_file($directory.'/params.json')) {
                $relative = substr($directory, strlen($root) + 1);
                $segments = explode('/', $relative);

                if (count($segments) === 2) {
                    return $relative;
                }
            }

            $directory = dirname($directory);
        }

        return null;
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        $prefix = str_starts_with($path, '/') ? '/' : '';

        return $prefix.implode('/', $segments);
    }

    /**
     * @param  array<string, list<string>>  $edges
     * @return list<list<string>> each cycle as a path, e.g. [a, b, a]
     */
    private function findCycles(array $edges): array
    {
        $cycles = [];
        $state = [];
        $stack = [];

        $visit = function (string $node) use (&$visit, &$cycles, &$state, &$stack, $edges): void {
            $state[$node] = 'visiting';
            $stack[] = $node;

            foreach ($edges[$node] ?? [] as $child) {
                if (($state[$child] ?? null) === 'visiting') {
                    $start = array_search($child, $stack, true);
                    $cycles[] = [...array_slice($stack, (int) $start), $child];
                } elseif (($state[$child] ?? null) === null) {
                    $visit($child);
                }
            }

            array_pop($stack);
            $state[$node] = 'visited';
        };

        foreach (array_keys($edges) as $node) {
            if (($state[$node] ?? null) === null) {
                $visit($node);
            }
        }

        return $cycles;
    }

    /**
     * Longest-path depth per node (root = depth 1). Cycle-involved nodes are
     * skipped: they are already reported by cycle detection.
     *
     * @param  array<string, list<string>>  $edges
     * @return array<string, int>
     */
    private function depths(array $edges): array
    {
        $depths = [];
        $visiting = [];

        $depth = function (string $node) use (&$depth, &$depths, &$visiting, $edges): int {
            if (isset($depths[$node])) {
                return $depths[$node];
            }

            if (isset($visiting[$node])) {
                return 0;
            }

            $visiting[$node] = true;
            $max = 0;

            foreach ($edges[$node] ?? [] as $child) {
                $max = max($max, $depth($child));
            }

            unset($visiting[$node]);

            return $depths[$node] = $max + 1;
        };

        foreach (array_keys($edges) as $node) {
            $depth($node);
        }

        return $depths;
    }
}

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Component Library Paths (SPEC §8.1)
    |--------------------------------------------------------------------------
    |
    | The library holds two standalone Vite apps (react/ and vue/). The same
    | slug in both trees is the same component, twice implemented. All sync
    | code reads these paths via config so tests can point at fixture trees.
    |
    */

    'react_path' => env('LIBRARY_REACT_PATH', base_path('library/react/src/components')),

    'vue_path' => env('LIBRARY_VUE_PATH', base_path('library/vue/src/components')),

    'registry_path' => env('LIBRARY_REGISTRY_PATH', base_path('library/deps.registry.json')),

    /*
    |--------------------------------------------------------------------------
    | Preview Build Pipeline (SPEC §5.2, §2.3)
    |--------------------------------------------------------------------------
    |
    | node/npm binaries are configurable because npm may not be resolvable
    | from PHP processes on every machine (set LIBRARY_NPM_BINARY). The app
    | paths default to the parents of the component trees above; override
    | only when the app root lives elsewhere. preview_build_failures feeds
    | the admin system-health widget.
    |
    */

    'node_binary' => env('LIBRARY_NODE_BINARY', 'node'),

    'npm_binary' => env('LIBRARY_NPM_BINARY', 'npm'),

    'chrome_binary' => env('LIBRARY_CHROME_BINARY'),

    'react_app_path' => env('LIBRARY_REACT_APP_PATH'),

    'vue_app_path' => env('LIBRARY_VUE_APP_PATH'),

    'preview_disk' => env('LIBRARY_PREVIEW_DISK', 'previews'),

    'preview_csp' => env('LIBRARY_PREVIEW_CSP', 'sandbox allow-scripts'),

    'preview_cache_control' => env('LIBRARY_PREVIEW_CACHE_CONTROL', 'public, max-age=31536000, immutable'),

    'screenshot_widths' => [375, 768, 1280],

    /*
    |--------------------------------------------------------------------------
    | Composition Rules (SPEC §2.2)
    |--------------------------------------------------------------------------
    */

    'max_depth' => 10,

];

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
    | Composition Rules (SPEC §2.2)
    |--------------------------------------------------------------------------
    */

    'max_depth' => 10,

];

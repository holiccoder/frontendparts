<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Documentation Content Root (SPEC §13.2)
    |--------------------------------------------------------------------------
    |
    | File-based markdown documentation, versioned with the product. The
    | DocsRepository maps /docs/{section}/{page} to
    | {content_path}/{section}/{page}.md; tests point this at fixtures.
    |
    */

    'content_path' => env('DOCS_CONTENT_PATH', base_path('docs/content')),

];

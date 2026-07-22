<?php

/*
|--------------------------------------------------------------------------
| Documentation Navigation
|--------------------------------------------------------------------------
|
| Sidebar tree and ordering for the public docs site: section key =>
| { title, pages: { page key => title } }. Keys are kebab-case and map to
| docs/content/{section}/{page}.md. This config drives the sidebar, the
| prev/next footer links (flattened in declaration order), the /docs
| redirect target and the docs URLs in the sitemap. Add your product's own
| sections and pages here.
|
*/

return [

    'getting-started' => [
        'title' => 'Getting Started',
        'pages' => [
            'index' => 'Overview',
        ],
    ],

];

<?php

/*
|--------------------------------------------------------------------------
| Documentation Navigation (SPEC §13.2)
|--------------------------------------------------------------------------
|
| Sidebar tree and ordering for the public docs site: section key =>
| { title, pages: { page key => title } }. Keys are kebab-case and map to
| docs/content/{section}/{page}.md. This config drives the sidebar, the
| prev/next footer links (flattened in declaration order), the /docs
| redirect target and the docs URLs in the sitemap.
|
*/

return [

    'getting-started' => [
        'title' => 'Getting Started',
        'pages' => [
            'index' => 'Overview',
            'installation' => 'Installation & account',
        ],
    ],

    'install' => [
        'title' => 'Install',
        'pages' => [
            'react' => 'React',
            'vue' => 'Vue',
        ],
    ],

    'using-components' => [
        'title' => 'Using Components',
        'pages' => [
            'params-and-data' => 'Params & data',
            'customizing' => 'Customizing',
        ],
    ],

];

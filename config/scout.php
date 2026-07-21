<?php

use App\Models\Blog;
use App\Models\Component;
use App\Models\DocsPage;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Search Engine (SPEC §13.2, FR-1.3 — Meilisearch at P3)
    |--------------------------------------------------------------------------
    |
    | `collection` needs no infrastructure and powers local dev + tests: it
    | filters the searchable payload in PHP, so relation-derived attributes
    | (tags, categories, docs bodies) just work. Production sets
    | SCOUT_DRIVER=meilisearch with MEILISEARCH_HOST / MEILISEARCH_KEY below.
    | The `database` engine is unsuitable here on purpose: it can only LIKE
    | real table columns, while our searchable payload spans relations.
    |
    | Supported: "algolia", "meilisearch", "typesense",
    |            "database", "collection", "null"
    |
    */

    'driver' => env('SCOUT_DRIVER', 'collection'),

    /*
    |--------------------------------------------------------------------------
    | Index Prefix
    |--------------------------------------------------------------------------
    |
    | Here you may specify a prefix that will be applied to all search index
    | names used by Scout. This prefix may be useful if you have multiple
    | "tenants" or applications sharing the same search infrastructure.
    |
    */

    'prefix' => env('SCOUT_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Queue Data Syncing
    |--------------------------------------------------------------------------
    |
    | This option allows you to control if the operations that sync your data
    | with your search engines are queued. When this is set to "true" then
    | all automatic data syncing will get queued for better performance.
    |
    */

    'queue' => env('SCOUT_QUEUE', false),

    /*
    |--------------------------------------------------------------------------
    | Database Transactions
    |--------------------------------------------------------------------------
    |
    | This configuration option determines if your data will only be synced
    | with your search indexes after every open database transaction has
    | been committed, thus preventing any discarded data from syncing.
    |
    */

    'after_commit' => env('SCOUT_AFTER_COMMIT', false),

    /*
    |--------------------------------------------------------------------------
    | Chunk Sizes
    |--------------------------------------------------------------------------
    |
    | These options allow you to control the maximum chunk size when you are
    | mass importing data into the search engine. This allows you to fine
    | tune each of these chunk sizes based on the power of the servers.
    |
    */

    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Soft Deletes
    |--------------------------------------------------------------------------
    |
    | This option allows to control whether to keep soft deleted records in
    | the search indexes. Maintaining soft deleted records can be useful
    | if your application still needs to search for the records later.
    |
    */

    'soft_delete' => false,

    /*
    |--------------------------------------------------------------------------
    | Meilisearch Configuration
    |--------------------------------------------------------------------------
    |
    | Production search engine. Set SCOUT_DRIVER=meilisearch plus the host
    | and (admin) key, then import the existing records and push the index
    | settings on deploy:
    |
    |   php artisan scout:import "App\Models\Component"
    |   php artisan scout:import "App\Models\Blog"
    |   php artisan scout:import "App\Models\DocsPage"
    |   php artisan scout:sync-index-settings
    |
    | Index settings are keyed by model class. Site search applies no
    | engine-side wheres or orderings — published gating happens via
    | shouldBeSearchable() and ranking stays engine-native — so the entries
    | are empty placeholders for future filterable/sortable attributes.
    |
    */

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'index-settings' => [
            Component::class => [],
            Blog::class => [],
            DocsPage::class => [],
        ],
    ],

];

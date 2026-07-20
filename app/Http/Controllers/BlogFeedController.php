<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use Illuminate\Http\Response;

/**
 * RSS feed `/blog/feed` (SPEC §13.1, §15.6): the 20 most recent live posts
 * as RSS 2.0 with absolute URLs. Drafts and scheduled posts never appear.
 */
class BlogFeedController extends Controller
{
    public function __invoke(): Response
    {
        $posts = Blog::query()
            ->published()
            ->orderByDesc('published_at')
            ->limit(20)
            ->get();

        return response()
            ->view('blog.feed', ['posts' => $posts])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}

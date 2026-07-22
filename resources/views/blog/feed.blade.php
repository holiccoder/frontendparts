<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>{{ config('app.name') }} Blog</title>
        <link>{{ route('blog.index') }}</link>
        <description>Articles, guides and product updates from the {{ config('app.name') }} team.</description>
        <language>en-us</language>
        <lastBuildDate>{{ now()->toRfc2822String() }}</lastBuildDate>
@foreach ($posts as $post)
        <item>
            <title>{{ $post->title }}</title>
            <link>{{ $post->publicUrl() }}</link>
            <guid isPermaLink="true">{{ $post->publicUrl() }}</guid>
            <pubDate>{{ $post->published_at->toRfc2822String() }}</pubDate>
            <description>{{ $post->excerpt }}</description>
        </item>
@endforeach
    </channel>
</rss>

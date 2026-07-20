export interface BlogCategoryRef {
    name: string;
    slug: string;
    url: string;
}

export interface BlogCategoryWithCount extends BlogCategoryRef {
    posts_count: number;
}

export interface BlogTagRef {
    name: string;
    slug: string;
}

export interface BlogPostCard {
    title: string;
    slug: string;
    excerpt: string | null;
    url: string;
    published_at: string | null;
    reading_time: number;
    featured_image: string | null;
    categories: BlogCategoryRef[];
}

export interface BlogTocEntry {
    level: number;
    id: string;
    text: string;
}

export interface BlogArticle extends BlogPostCard {
    body_html: string;
    toc: BlogTocEntry[];
    author: string | null;
    tags: BlogTagRef[];
    published_at_iso: string | null;
    updated_at_iso: string | null;
}

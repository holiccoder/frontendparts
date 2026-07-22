# SaaS Skeleton

A reusable Laravel SaaS skeleton: auth, billing (Paddle + domestic payments),
team seats, lifecycle email, blog, docs, affiliate program, support tickets and
a Filament admin panel — ready to build a new product on.

**Read [SKELETON.md](SKELETON.md)** — it documents what's included, what was
removed, and how to start a new product from this branch.

## Stack

- PHP 8.4 · Laravel 13 · Filament 5.7
- Inertia.js v2 + React 19 + Tailwind CSS 4
- PHPUnit 12 · Playwright (smoke)

## Quick start

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
```

Dev server (PHP + Vite + queue worker): `npm run dev`

Tests (sqlite `:memory:`, no services needed): `php artisan test --compact`

Admin panel: `/admin` (see `AdminSeeder` for the local credentials).

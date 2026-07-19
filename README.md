# FrontendParts

A full-stack web application built on the Laravel React starter kit: a Laravel 13 backend serving a React 19 single-page application via Inertia.js, with a Filament 5 admin panel, MySQL database, and a Docker-based local development environment.

## Project Overview

FrontendParts combines a modern client-side rendered SPA frontend with Laravel's server-side patterns — no separate API layer required. Public visitors see a welcome page; registered users get access to an authenticated dashboard and account settings; administrators manage content through a dedicated Filament admin panel.

### Core Features

- **Public-facing SPA** — welcome page and full authentication flow rendered with React via Inertia.js
- **Authentication** — registration, login, email verification, password reset, and password confirmation
- **User dashboard** — authenticated area with shared app layout (sidebar/header, breadcrumbs, appearance switching)
- **Account settings** — profile management, password update, appearance (theme) preferences, account deletion
- **Admin panel** (`/admin`) — Filament 5 panel with dedicated `Admin` guard and developer logins, managing three resources:
  - **Users** — registered user accounts
  - **Blogs** — blog posts with title, slug, excerpt, body, featured image, status, and publish date (authored by users)
  - **Orders** — subscription-style orders with plan, status, billing period, amount/currency, and lifecycle timestamps
- **Domain enums** — `OrderPlan`, `OrderStatus`, `BillingPeriod` for type-safe order handling
- **Observers & notifications** — `OrderObserver` reacts to order lifecycle events; database notifications table included

## Technical Specifications

### Backend

| Component | Version / Details |
|---|---|
| PHP | ^8.2 (8.4 recommended) |
| Framework | Laravel 13 (streamlined structure, middleware in `bootstrap/app.php`) |
| Admin panel | Filament 5 (`filament/filament ^5.0`), built on Livewire 4 |
| Inertia adapter | `inertiajs/inertia-laravel` v2 |
| Route helper | `tightenco/ziggy` v2 (named routes exposed to JS) |
| AI / tooling | `laravel/ai`, `laravel/mcp`, `laravel/boost` |
| Auth model | `App\Models\User`; separate `App\Models\Admin` for panel access |
| Database | MySQL 8.4 (default), SQLite supported for quick starts |

### Frontend

| Component | Version / Details |
|---|---|
| React | 19 (`@inertiajs/react` v2) |
| Language | TypeScript 5.7 |
| Styling | Tailwind CSS 4 (`@tailwindcss/vite`), `tailwindcss-animate` |
| UI primitives | Radix UI suite + Headless UI, shadcn-style components (`components.json`) |
| Icons | `lucide-react` |
| Build tool | Vite 6 with `laravel-vite-plugin` and `@vitejs/plugin-react` |
| Class utilities | `clsx`, `tailwind-merge`, `class-variance-authority` |

### Infrastructure & Tooling

- **Docker** — Laravel Sail via `compose.yaml` (custom `docker/Dockerfile` app image, MySQL 8.4 service, configurable `APP_PORT`/`VITE_PORT`)
- **Testing** — PHPUnit 12 (feature tests for Auth, Dashboard, Settings; unit tests)
- **Code style** — Laravel Pint (PHP), Prettier 3 + ESLint 9 (JS/TS, `eslint-plugin-react`, `typescript-eslint`)
- **Dev services** — `composer run dev` starts server, queue worker, log tail (Pail), and Vite concurrently
- **Session / cache / queue** — database-backed drivers by default

## Project Structure

```
app/
├── Enums/               # BillingPeriod, OrderPlan, OrderStatus
├── Filament/Resources/  # Blogs, Orders, Users admin resources
├── Http/Controllers/    # Auth + Settings controllers
├── Models/              # User, Admin, Blog, Order
├── Notifications/       # App notifications
├── Observers/           # OrderObserver
└── Providers/
    └── Filament/        # AdminPanelProvider (panel id/path: admin)
resources/js/
├── components/          # App shell, navigation, ui/ primitives
├── layouts/             # Shared Inertia layouts
├── pages/
│   ├── auth/            # login, register, forgot/reset password, verify email
│   ├── settings/        # profile, password, appearance
│   ├── dashboard.tsx
│   └── welcome.tsx
routes/                  # web.php, auth.php, settings.php, console.php
database/migrations/     # users, cache, jobs, admins, orders, blogs, notifications
docker/                  # Dockerfile + runtime config for Sail
```

## Getting Started

### Prerequisites

- PHP 8.2+ with Composer
- Node.js 18+ with npm
- MySQL 8.4 (or use Docker)

### Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### Development

```bash
composer run dev   # server + queue + logs + Vite, all at once
```

Or with Docker (Sail):

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
```

### Quality & tests

```bash
php artisan test --compact   # PHPUnit test suite
vendor/bin/pint              # PHP formatting
npm run lint                 # ESLint
npm run format               # Prettier
npm run build                # Production frontend build
```

## Access Points

| Surface | URL | Notes |
|---|---|---|
| Public site | `/` | Welcome page |
| Dashboard | `/dashboard` | Requires authentication |
| Admin panel | `/admin` | Requires an `Admin` account (Filament guard) |

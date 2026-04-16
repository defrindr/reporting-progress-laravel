# AGENTS.md

## Stack
- **Laravel 12** (PHP 8.2+) + **Blade** (web) + **Vue 3** (`<script setup>` via Vite)
- **SQLite** for tests (`:memory:`), **SQLite** default locally, **MySQL** for production
- Packages: `spatie/laravel-permission`, `spatie/laravel-activitylog`, `spatie/laravel-medialibrary`
- CSS: Tailwind CSS v4 via Vite plugin

## Commands
```bash
# Setup (after clone)
composer setup

# Dev server (runs: php artisan serve + queue:listen + pail + npm run dev)
composer dev

# Test
composer test

# Frontend build
npm run build
```

## Architecture
- `app/Http/Controllers/Admin/` — Admin CRUD (roles, users, institutions, periods, projects, evaluation-lab)
- `app/Http/Controllers/Intern/` — Intern-facing (dashboard, logbook, project board)
- `app/Http/Controllers/` root — API controllers + auth session
- `app/Models/` — User, Institution, Period, Logbook, Project, ProjectSpec, Comment, GlobalHoliday
- `app/Support/SprintWindow.php` + `SprintPeriodResolver.php` — sprint/period auto-creation logic
- `resources/views/` — Blade templates (admin/, auth/, intern/, layouts/)
- `resources/js/Pages/` — Vue components (LogbookForm.vue, ProjectBoard.vue)

## Key Behaviors
- **Auth**: Session-based (web/blade). Login auto-creates a Sprint period for user's institution (`SprintPeriodResolver::resolveForInstitution`). Login is at `/login`.
- **RBAC**: Spatie roles: `Admin`, `Supervisor`, `Intern`. Middleware `role:Admin` gates admin routes.
- **Logbook**: Interns submit `done_tasks`, `next_tasks`, `appendix_link`. Rejects submission if `report_date` falls on a holiday (from Period's `holidays` JSON). Supervisor/Admin see intern logbook history, not the form.
- **Projects**: Admin creates `ProjectSpec` (assigned to multiple interns). Interns create own tasks (`Project`) derived from spec. Status is 2-way reversible (`todo ↔ doing ↔ done`). Activity log tracks status changes via Spatie.
- **Periods**: Two types — `internship` (fixed date range with holidays) and `sprint` (auto-generated 2-week windows via `SprintWindow`). Period belongs to Institution.
- **Sprint config**: `config/sprint.php` — `span_weeks` (default 2), `workdays_iso`, `reference_monday`.

## Testing
- Use `RefreshDatabase` trait. Tests run against SQLite `:memory:`.
- Create roles in setUp: `Role::findOrCreate('Admin', 'web')` etc.
- Tests live in `tests/Feature/` and `tests/Unit/`.

## Style
- 4-space indent (`.editorconfig`)
- Laravel Pint for formatting: `vendor/bin/pint`

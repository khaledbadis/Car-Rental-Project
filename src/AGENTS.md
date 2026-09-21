# Project conventions

Use Docker Compose from the repository root; PHP, Composer and Node run in containers. Do not install host runtimes. The initial Laravel Boost bootstrap has already been completed.

Business rules belong in app/Modules application actions and are shared by Livewire and /api/v1 controllers. Enforce authorization server-side. Use PostgreSQL for tests; do not substitute SQLite for transaction/constraint coverage. Keep all UI text in lang/fr, lang/ar, lang/en and verify RTL and both themes.

Run `docker compose exec app php artisan test` and `docker compose exec app vendor/bin/pint --test` after relevant changes. Build frontend assets with `docker compose run --rm node npm run build`. Do not implement later project phases without user instruction.

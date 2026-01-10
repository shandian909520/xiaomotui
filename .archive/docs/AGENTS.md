# Repository Guidelines

## Project Structure & Module Organization

- `api/` — ThinkPHP 8 backend (controllers, models, services, middleware, routes, config, public). Primary PHP code lives under `api/app/`.
- `miniprogram/` — WeChat Mini Program source (WIP).
- `uni-app/` and `admin/` — Frontend assets and admin tooling where applicable.
- Root test utilities — Node/Playwright scripts such as `test_login_page.js`, `test_localhost_*.js` for browser automation.
- Docs and ops — `deploy/`, environment samples in `api/.env*`, various implementation reports in repo root and `api/`.

## Build, Test, and Development Commands

- Install backend deps: `cd api && composer install`
- Run dev server: `cd api && php think run` (or point Nginx/Apache to `api/public`)
- Run PHP unit tests: `cd api && ./vendor/bin/phpunit`
- Install Playwright browsers: `npm i && npx playwright install`
- Run Playwright scripts: `node test_login_page.js` (others: `test_localhost_6151.js`, etc.)

## Coding Style & Naming Conventions

- PHP: Follow PSR-12. Classes `StudlyCase`, methods `camelCase`, constants `UPPER_SNAKE_CASE`.
- ThinkPHP: Place business logic in `api/app/service`, keep controllers thin; use `validate/` for request validation and `middleware/` for cross-cutting concerns.
- Database: Use table prefix `xmt_`; snake_case columns; include `create_time`, `update_time`, and `delete_time` for soft deletes where needed.
- JS/TS: 2-space indentation; prefer `const/let`; file names `kebab-case.js` for scripts.

## Testing Guidelines

- Backend: PHPUnit tests live in `api/tests`. Name test classes `*Test.php`. Run with `./vendor/bin/phpunit`.
- E2E/UI: Playwright scripts in repo root. Keep scenarios idempotent; add new scripts as `test_<scope>.js`.
- Coverage: Aim for critical-path services, controllers, and validators; add tests for bug fixes.

## Commit & Pull Request Guidelines

- Commits: Use clear, imperative messages (e.g., "Add content audit validator"). Group related changes; keep scope focused.
- PRs: Include purpose, summary of changes, test evidence (PHPUnit output and/or Playwright screenshots), and linked issues. Note config or migration changes.

## Security & Configuration Tips

- Never commit secrets. Copy `api/.env.example` to `.env` and set keys for DB, Redis, JWT, OSS, and AI providers.
- Validate and sanitize inputs; centralize auth with JWT middleware.
- For production, set `APP_DEBUG=false`, configure HTTPS, and review file permissions on `api/public`.


# Atom CMS

- Use the installed Laravel 13 documentation and Laravel Boost guidance. Keep the existing kernel and provider registration structure; it remains supported by Laravel.
- Preserve Arcturus and Ada support. Access emulator-owned data through `app/Emulator/Contracts` and the selected driver, and leave emulator-owned schemas intact.
- Keep CMS tables under the `website_` prefix. Arcturus, Ada and PlusEMU tests use separate disposable databases because their schemas share table names.
- Preserve both Atom and Dusk themes and existing translations. Build each theme with `npm run build:atom` and `npm run build:dusk`, Core views and `resources/css/global.css` with `npm run build:core`, and the admin panel with `npm run build:housekeeping` when changing their frontend assets; `npm run build:all` builds all four.
- Follow `pint.json` and the existing naming conventions. Run focused Pest tests for changed behavior, `composer lint`, and `composer analyse`; use `composer test` for changes to shared behavior.
- Configure three disposable test databases using `.env.testing.example` before running tests. `phpunit.xml` supplies `testing`, `testing_ada` and `testing_plus` before `.env.testing` loads; use those names or export `DB_DATABASE`, `DB_ADA_DATABASE` and `DB_PLUS_DATABASE` in the shell to override them. Tests recreate these databases; never point them at a hotel database.
- Keep documentation changes alongside behavior changes. Regenerate Boost guidance with `composer boost:update`; change this file for project-specific rules instead of editing generated guidance.

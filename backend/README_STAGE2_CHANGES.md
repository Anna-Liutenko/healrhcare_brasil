Stage 2 — Application layer (RenderPageHtml)

What changed

- A new use-case `Application/UseCase/RenderPageHtml.php` was added. It produces a deterministic static HTML string for a `Page` entity by fetching its blocks from the `BlockRepositoryInterface` and rendering block data.

- `PublishPage` now generates HTML during publication and saves it into the `rendered_html` field on the `Page` entity.

- `UpdatePage` now accepts `menu_title` (both `menu_title` and `menuTitle` keys in payload) and updates the `Page` entity accordingly.

How to run tests

1. Ensure composer dev deps are installed in `backend` (composer.phar present):

```powershell
Set-Location -Path 'C:\path\to\your\repo\backend'
if (-Not (Test-Path .\vendor)) {
    & 'C:\xampp\php\php.exe' composer.phar install --no-interaction --prefer-dist
}
```

2. Run PHPUnit with the test bootstrap to use the sqlite test DB created by the bootstrap:

```powershell
Set-Location -Path 'C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\backend'
.\vendor\bin\phpunit --bootstrap .\tests\_bootstrap.php .\tests\Unit --testdox
```

Notes for reviewers

- The `RenderPageHtml` implementation is intentionally small and suitable for unit testing. During review consider: replacing it with the project's production renderer or injecting a template rendering service.

- `backend/tests/Integration/schema/sqlite_schema.sql` was updated to include `menu_title` and `rendered_html` so unit tests (which use sqlite bootstrap) mirror the migrated DB schema.

- I recommend running migrations on staging before deploying these changes to production.

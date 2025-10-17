# PR: Stage 2 — Application layer (RenderPageHtml + Publish changes)

Branch suggestion: feature/publish-render-html

Summary

This change implements Stage 2 from the publish feature plan. It adds a new application use-case `RenderPageHtml` (generates static HTML for a Page), updates `PublishPage` to call the renderer and persist `rendered_html`, and updates `UpdatePage` to accept `menu_title` from payload. Unit tests for the new use-cases were added and run.

Files changed/added (high level)

- Added: `backend/src/Application/UseCase/RenderPageHtml.php`
- Updated: `backend/src/Application/UseCase/PublishPage.php`
- Updated: `backend/src/Application/UseCase/UpdatePage.php`
- Added tests: `backend/tests/Unit/RenderPageHtmlTest.php`, `backend/tests/Unit/PublishPageTest.php`
- Updated test schema: `backend/tests/Integration/schema/sqlite_schema.sql` (added `menu_title`, `rendered_html`)

Testing performed

1. Composer dependencies installed in `backend` (composer.phar)
2. PHPUnit run with test bootstrap so tests use sqlite test DB:

```powershell
Set-Location -Path 'C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\backend'
.\vendor\bin\phpunit --bootstrap .\tests\_bootstrap.php .\tests\Unit --testdox
```

Expected: All Unit tests pass (confirmed locally during change).

Risks & Notes

- The new renderer is intentionally minimal and deterministic for unit tests. You may want to replace or extend it with your real rendering pipeline (template renderer) before production usage.
- Be sure to run DB migration (Stage 1) in environments where you deploy these changes.

Suggested PR description (copy/paste)

Title: Stage 2 — Application: Add RenderPageHtml and persist rendered_html on publish

Body:
- Add `RenderPageHtml` use-case that generates a static HTML snapshot of a Page from its blocks.
- Update `PublishPage` to call the renderer and set `rendered_html` on the `Page` entity before saving.
- Update `UpdatePage` to accept `menu_title` from API payload.
- Add unit tests for RenderPageHtml and PublishPage.
- Update sqlite test schema used in tests to include `menu_title` and `rendered_html`.

Testing:
- Ran `phpunit` with test bootstrap — all backend unit tests pass locally.

---

If you want, I can generate a patch file or attempt to open a draft PR using `gh` if your environment has it.

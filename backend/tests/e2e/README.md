HTTP-level E2E script for publish/menu features

This folder contains a small PHP script that exercises the public API endpoints to validate the publish and menu behavior.

Files:
- `run_e2e_http.php` — script that POSTs a page, PUTs publish, GETs `/api/menu/public`, and GETs the public page by slug. It writes results to `../tmp/menu_result.json` by default.

Usage examples (from `backend/tests/e2e`):

```powershell
php run_e2e_http.php --base-url="http://localhost" --api-prefix="/api" --out="../tmp/menu_result.json"
```

Notes:
- The script assumes your local server (XAMPP or built-in PHP server) is running and the API routes are reachable at the provided base URL and prefix.
- The script will fail with non-zero exit code on any non-2xx response and print a helpful message.
- Use this to validate live behavior after deploying the changes to your local web server.

Additional options and examples

- `--created-by` (string) — When running against a deployed MySQL-backed instance (for example the XAMPP mirror), the `/api/pages` create endpoint requires a valid `createdBy` user id due to a foreign-key constraint. Provide an existing user id from your deployment using this flag. Example:

```powershell
php run_e2e_http.php --base-url="http://localhost/healthcare-cms-backend" --api-prefix="/api" --created-by="550e8400-e29b-41d4-a716-446655440000" --out="../tmp/menu_result.json"
```

- `--login-user` and `--login-pass` — Alternatively, if you have a test account, provide username and password to log in first. The script will use the returned bearer token for authenticated requests and (if `--created-by` is not provided) will use the logged-in user's id as `createdBy` automatically. Example:

```powershell
php run_e2e_http.php --base-url="http://localhost/healthcare-cms-backend" --api-prefix="/api" --login-user="e2e_test" --login-pass="password123" --out="../tmp/menu_result.json"
```

Output
- The script writes a JSON file (default `../tmp/menu_result.json`) with the following shape:

```json
{
	"pageId": "...",
	"slug": "...",
	"menu": [ /* public menu JSON */ ],
	"html": "..." /* public page HTML */
}
```

If you want me to make the script create a temporary user automatically during E2E runs, I can add that (note: creating users requires super-admin rights on the target instance and may be blocked on production-like deployments).

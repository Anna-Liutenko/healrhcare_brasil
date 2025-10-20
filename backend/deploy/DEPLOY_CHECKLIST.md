Deployment checklist — staging

1. Prepare repository
   - Pull latest changes on staging branch
   - Ensure composer deps installed: `composer install --no-dev --optimize-autoloader`

2. Build frontend (if applicable)
   - Run frontend build script and copy assets to backend/public or configured XAMPP folder

3. Sync to XAMPP
   - From repo root, run `.ackend\deploy\deploy-to-staging.ps1` (this will call `sync-to-xampp.ps1` and run smoke tests)

4. Smoke tests
   - Smoke tests are executed automatically by the deploy script. They check:
     - Site root returns expected HTML
     - Editor page is reachable

5. Post-deploy manual checks
   - Log in as editor and confirm collection editor updates a card image
   - Review `backend/logs/collection-changes.log` for audit entries

6. Rollback
   - If there are issues, revert the code on the staging server to the previous commit and re-run sync script.
   - Optionally restore database from latest backup if DB changes were applied.

Notes
- The smoke tests script (`SMOKE_TESTS.ps1`) uses `Invoke-WebRequest` and expects the staging host to be accessible from the machine running the script.
- For production deploys consider implementing zero-downtime strategies and more robust health checks.

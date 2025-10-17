# Post-deploy job snippets

This file contains example CI job snippets for GitLab CI and GitHub Actions that run the `deploy/hooks/post_deploy_uploads_check.sh` on the production host (via SSH), collect the `pages-images-report.json` as an artifact, and fail the pipeline when missing files are found.

Notes
- These examples assume you have SSH access from the CI runner to the production host (or to a deployment gateway). Use a deploy user (`deploy@prod.example.com`) with a private key configured in CI secrets.
- Adjust paths and hostnames to your environment. The hook script path used is `/var/www/healthcare-cms-backend/deploy/hooks/post_deploy_uploads_check.sh` and report path is `/var/www/healthcare-cms-backend/deploy-reports/pages-images-report.json`.
- The hook itself returns non-zero on error; the CI job checks for the report and uploads it as an artifact.

## GitLab CI example

Add this job after your deploy job (example `deploy:production`). It uses an SSH private key stored in CI variable `PROD_SSH_PRIVATE_KEY`.

```yaml
stages:
  - deploy
  - post-deploy-check

post_deploy_uploads_check:
  stage: post-deploy-check
  image: alpine:latest
  variables:
    TARGET_HOST: "prod.example.com"
    TARGET_USER: "deploy"
    HOOK_PATH: "/var/www/healthcare-cms-backend/deploy/hooks/post_deploy_uploads_check.sh"
    REPORT_PATH: "/var/www/healthcare-cms-backend/deploy-reports/pages-images-report.json"
  script:
    - apk add --no-cache openssh-client bash
    - mkdir -p ~/.ssh && chmod 700 ~/.ssh
    - echo "$PROD_SSH_PRIVATE_KEY" > ~/.ssh/id_rsa && chmod 600 ~/.ssh/id_rsa
    - ssh-keyscan -H "$TARGET_HOST" >> ~/.ssh/known_hosts
    - echo "Running post-deploy uploads check on $TARGET_HOST..."
    - ssh -o StrictHostKeyChecking=yes "$TARGET_USER@$TARGET_HOST" "sudo chmod +x '$HOOK_PATH' && sudo '$HOOK_PATH'"
    - echo "Fetching report from $TARGET_HOST..."
    - scp "$TARGET_USER@$TARGET_HOST:$REPORT_PATH" ./pages-images-report.json || echo "No report found"
  artifacts:
    when: always
    paths:
      - pages-images-report.json
    expire_in: 1 week
  allow_failure: false
  only:
    - main
```

Behavior:
- If the hook returns non-zero, the SSH step will return non-zero and the job fails, preventing the pipeline from proceeding.
- The job downloads the JSON report as an artifact for later inspection.

## GitHub Actions example

This example uses the `appleboy/ssh-action` to run the hook and `actions/upload-artifact` to upload the report.

```yaml
name: Post-deploy uploads check

on:
  workflow_dispatch: {}

jobs:
  post-deploy-check:
    runs-on: ubuntu-latest
    steps:
      - name: Run post-deploy hook via SSH
        uses: appleboy/ssh-action@v0.1.11
        with:
          host: ${{ secrets.PROD_HOST }}
          username: ${{ secrets.PROD_USER }}
          key: ${{ secrets.PROD_SSH_PRIVATE_KEY }}
          port: 22
          script: |
            sudo chmod +x /var/www/healthcare-cms-backend/deploy/hooks/post_deploy_uploads_check.sh
            sudo /var/www/healthcare-cms-backend/deploy/hooks/post_deploy_uploads_check.sh

      - name: Fetch report via scp
        run: |
          scp -i ${{ secrets.PROD_SSH_PRIVATE_KEY }} -o StrictHostKeyChecking=no ${{ secrets.PROD_USER }}@${{ secrets.PROD_HOST }}:/var/www/healthcare-cms-backend/deploy-reports/pages-images-report.json ./pages-images-report.json || true

      - name: Upload report artifact
        uses: actions/upload-artifact@v3
        with:
          name: pages-images-report
          path: pages-images-report.json

    timeout-minutes: 15

```

## Usage notes and environment variables
- `PROD_SSH_PRIVATE_KEY` (GitLab) / `PROD_SSH_PRIVATE_KEY` (GitHub Actions): private key for the `deploy` user (add to CI secrets).
- `PROD_HOST` / `TARGET_HOST`: your production host (or load balancer / ssh bastion).
- Ensure the `deploy` user has permission to run the hook (sudoers entry may be needed for non-interactive sudo).
- If your deploy process uses a bastion host, adjust SSH commands to proxy through it (ProxyJump) or run the hook from the bastion.

## Troubleshooting
- If the job cannot fetch the report, check permissions on `/var/www/healthcare-cms-backend/deploy-reports/` and that the hook wrote the JSON file.
- If SSH fails with host key mismatch, update the known_hosts entry in CI or use StrictHostKeyChecking=no carefully.

---

Once you confirm which CI system you use (GitLab CI, GitHub Actions, other), I can prepare a ready-to-paste job with your actual hostnames and paths.

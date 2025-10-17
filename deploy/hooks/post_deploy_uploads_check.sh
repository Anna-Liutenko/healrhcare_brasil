#!/usr/bin/env bash
## post_deploy_uploads_check.sh
## Run after deploy: ensure uploads ownership/permissions, run pages images check, and do a small smoke test.
set -euo pipefail

# Defaults - override by exporting env vars before running the script
UPLOADS_DIR="${UPLOADS_DIR:-/var/www/healthcare-cms-backend/public/uploads}"
REPORT_DIR="${REPORT_DIR:-/var/www/healthcare-cms-backend/deploy-reports}"
CHECK_SCRIPT="${CHECK_SCRIPT:-/var/www/healthcare-cms-backend/backend/scripts/check-pages-images.php}"

echo "Post-deploy uploads check"
echo "Uploads dir: $UPLOADS_DIR"
echo "Report dir: $REPORT_DIR"

if [ ! -d "$UPLOADS_DIR" ]; then
  echo "ERROR: uploads dir does not exist: $UPLOADS_DIR"
  exit 2
fi

mkdir -p "$REPORT_DIR"

echo "Fixing ownership and permissions (attempting to chown to www-data:www-data)..."
# chown may require sudo depending on deploy user; ignore failure but report it
if command -v sudo >/dev/null 2>&1; then
  sudo chown -R www-data:www-data "$UPLOADS_DIR" || echo "chown to www-data failed or not needed"
else
  chown -R www-data:www-data "$UPLOADS_DIR" 2>/dev/null || echo "chown not available or failed"
fi

echo "Setting directory permissions to 755 and files to 644"
find "$UPLOADS_DIR" -type d -exec chmod 755 {} \;
find "$UPLOADS_DIR" -type f -exec chmod 644 {} \;

echo "Running pages images check script..."
php "$CHECK_SCRIPT" --uploads-dir="$UPLOADS_DIR" --out="$REPORT_DIR"

REPORT_JSON="$REPORT_DIR/pages-images-report.json"
if [ ! -f "$REPORT_JSON" ]; then
  echo "ERROR: report not found: $REPORT_JSON"
  exit 4
fi

# Determine number of missing files using PHP to avoid requiring jq
MISSING_COUNT=$(php -r 'echo (int)json_decode(file_get_contents($argv[1]), true)["meta"]["missing_files_total"];' "$REPORT_JSON")
echo "Missing files referenced: $MISSING_COUNT"

if [ "$MISSING_COUNT" -gt 0 ]; then
  echo "ERROR: deployment contains pages that reference missing uploads. See $REPORT_JSON"
  exit 5
fi

echo "Performing a small smoke test on an example file (if available)..."
FIRST_OK=$(php -r '
  $r=json_decode(file_get_contents($argv[1]), true);
  foreach($r["pages"] as $p){
    foreach($p["images"] as $i){
      if($i["status"]==="ok"){
        echo $i["path"];
        exit(0);
      }
    }
  }
' "$REPORT_JSON")

if [ -n "$FIRST_OK" ] && [ -f "$FIRST_OK" ]; then
  echo "Sample file exists: $FIRST_OK"
  if command -v sudo >/dev/null 2>&1; then
    if sudo -u www-data test -r "$FIRST_OK"; then
      echo "Sample file readable by www-data"
    else
      echo "WARNING: sample file is not readable by www-data; permissions may be wrong"
      ls -l "$FIRST_OK"
    fi
  else
    echo "Note: sudo not available; checking file readability as current user"
    if [ -r "$FIRST_OK" ]; then
      echo "Sample file readable by current user"
    else
      echo "WARNING: sample file is not readable by current user"
      ls -l "$FIRST_OK"
    fi
  fi
else
  echo "No sample ok file found to smoke-test"
fi

echo "Post-deploy uploads check: OK"
exit 0

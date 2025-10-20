# Phase 3 — Enterprise Security (30 days)

Purpose: Execute an enterprise-grade hardening sprint focused on Penetration Testing, Subresource Integrity (SRI), and Automated Security Scanning with CI/CD gates. This prompt is designed so any engineer or LLM can follow it step-by-step. It aligns with October 2025 standards from MDN and OWASP.

- Standards used and verified (Oct 2025):
  - MDN Subresource Integrity (last modified Aug 18, 2025)
  - MDN Integrity-Policy and Reporting-Endpoints headers (2025)
  - OWASP Web Security Testing Guide (WSTG) stable v4.2; 5.0 in development
  - OWASP Cheat Sheets: Content Security Policy, CI/CD Security, Vulnerable Dependency Management, Third-Party JavaScript Management, XSS Prevention, SQL Injection Prevention, CSRF Prevention

- Prerequisites:
  - Phase 1 and Phase 2 completed and deployed to a staging environment.
  - CSP Nonce, CSP Reporting, DOMPurify, and Trusted Types (if enabled) are active from Phase 2.
  - Logging available and rotated for: web server access/error, application logs, CSP/cert/integrity violation logs.

- Success criteria:
  - Pentest completed with ROE signed, evidence captured, all high/critical issues fixed and retested.
  - All external scripts/styles use SRI sha384 + crossorigin, Integrity-Policy enforced (after report-only burn-in) with zero violations.
  - CI/CD gates: builds fail on high/critical vulnerabilities across npm, Composer, Snyk (optional), OWASP Dependency-Check.

- Timeline (target 4 weeks):
  - Week 1: Scope + ROE, staging prep, inventory of external resources, Report-Only Integrity-Policy, CI scaffolding
  - Week 2: Active pentest (round 1), SRI hashes + template updates, CI jobs wired in, initial fixes
  - Week 3: Fixes + retest, enforce Integrity-Policy, tighten CI thresholds, evidence collection
  - Week 4: Final regression, executive report, readiness sign-off

---

## Task 1 — Penetration Testing (OWASP WSTG)

Owner: Security Lead. Duration: 10–12 days including retest.

### 1.1 Define scope and Rules of Engagement (ROE)

Deliverables:
- ROE document covering: scope, in/out-of-scope, data handling, NDA, timing windows, reporting cadence, remediation SLAs.
- Test accounts and roles: admin, editor, viewer; seeded non-PII test content.
- Staging URL(s) and VPN if needed.
- Contacts for high/critical findings (24/7 during test window).

Scope must include (expand as needed):
- Public rendering endpoints (PublicPageController + templates)
- Editor app (editor.js) and its APIs
- UpdatePage API (including renderedHtml flows)
- Media upload endpoints and any file parsers
- Authentication/authorization flows (login, password reset)
- Configuration endpoints and CMS settings
- Any third-party integrations (CDNs, analytics if present)

References:
- OWASP WSTG stable v4.2 (link versioned, not latest), e.g., WSTG-v42 categories:
  - Input Validation & Injection: SQLi (WSTG-INPV-05), XSS (WSTG-INPV-01/DOM), Command Injection
  - Authentication/Session: CSRF (WSTG-SESS-05), Session Management
  - Authorization: IDOR (WSTG-ATHZ-01), Access Control
  - File Upload: Content-Type spoofing, polyglots, stored XSS via file names/EXIF
  - Business Logic: workflow bypasses, privilege escalations

### 1.2 Simulate attacks (must include these vectors)

Use these minimally invasive, evidence-capturing tests on staging. Never use production data.

- Re-test all 8 previously identified vectors from `XSS_SECURITY_AUDIT_CRITICAL.md` and confirm mitigations from Phases 1–2 are effective under adversarial conditions.

- XSS (reflected/stored/DOM-based): attempt across editor preview, public pages, and any HTML-returning APIs.
  - Payloads (examples):
    - <img src=x onerror=alert(1)>
    - "><svg/onload=alert(document.domain)>
    - <script>alert('xss')</script>
    - javascript:alert(1) in href/src contexts
  - Expected: Phase 1–2 defenses block execution; CSP/CSP-Report logs events.

- CSRF: Forge POST/PUT/DELETE requests against UpdatePage, Media uploads, Settings changes.
  - Method: Cross-origin auto-submitting form and fetch() with credentials.
  - Expected: CSRF defenses (tokens, same-site cookies) block.

- SQL Injection: Parameters in query strings, form fields, and JSON bodies.
  - Payloads: ' OR 1=1; ' UNION SELECT NULL; time-based injection like SLEEP(5)
  - Expected: Parameterized queries, no error leakage, no time delays.

- File Upload: Try HTML/JS payload in images (SVG, polyglots), oversized files, double extensions.
  - Expected: Server-side content-type and magic-bytes checks, size limits, randomized filenames, no execution.

- Access Control/IDOR: Access other users’ drafts/media via ID changes.
  - Expected: 403/404, strict owner checks.

- SSRF/External Calls (if exist): Attempt internal metadata IPs, localhost.
  - Expected: Blocked by allowlists; timeouts and logging.

Record for each attempt: request, response, headers, affected endpoint, evidence (screenshots), log references, reproduction steps, and severity.

### 1.3 Reporting and remediation

- Daily standups during active test.
- Interim critical alerts within 4 hours.
- Final report structure: Executive summary, Methodology (WSTG), Findings (CWE/CVSS v3.1+), Evidence, Risk, Fix recommendations, Retest results, Residual risk.
- Remediation window: High/Critical fixed within 5 business days; Medium within 20 days.
- Retest: Confirm fixed; attach proof.

Artifacts to commit under `docs/security/pentest/`:
- ROE.md
- Findings.xlsx or JSON (normalized)
- EVIDENCE/ screenshots
- FINAL_REPORT.md

---

## Task 2 — Subresource Integrity (SRI) + Integrity-Policy

Owner: Platform/Frontend. Duration: 4–5 days including burn-in.

### 2.1 Inventory third-party subresources

- Enumerate all external `<script src>` and `<link rel="stylesheet" href>` used in:
  - Editor HTML/template(s)
  - Public pages renderer outputs
  - Admin dashboards
- Typical items to include (from Phase 2): DOMPurify CDN, any polyfills, fonts, icons, analytics (if enabled).

Create `docs/security/sri-inventory.json` like:
```
[
  {
    "type": "script",
    "url": "https://cdn.jsdelivr.net/npm/dompurify@3.*/dist/purify.min.js",
    "owner": "frontend",
    "notes": "Used by editor sanitizeHTML"
  }
]
```

### 2.2 Generate SRI hashes (sha384 preferred)

Use one of the following methods on Windows (PowerShell v5.1):

- OpenSSL (recommended):
```
# Ensure openssl is installed and on PATH
Get-Content .\purify.min.js -Encoding Byte | openssl dgst -sha384 -binary | openssl base64 -A
# Prefix output with: sha384-
```

- Node.js (no external deps):
```
node -e "const fs=require('fs');const c=fs.readFileSync('purify.min.js');const h=require('crypto').createHash('sha384').update(c).digest('base64');console.log('sha384-'+h)"
```

Notes:
- Recompute hashes whenever the CDN URL/version changes. Hash mismatch will block loading.
- For cross-origin resources set `crossorigin="anonymous"` and ensure CDN serves `Access-Control-Allow-Origin: *` (per MDN SRI + CORS requirement).

### 2.3 Update HTML/templates to include SRI

Replace each external script/style tag with integrity and crossorigin attributes, for example:
```
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js"
        integrity="sha384-REPLACE_WITH_REAL_HASH"
        crossorigin="anonymous"></script>
```
For stylesheets:
```
<link rel="stylesheet" href="https://examplecdn.com/x.css"
      integrity="sha384-REPLACE_WITH_REAL_HASH"
      crossorigin="anonymous" />
```

Where tags are emitted dynamically (PHP controllers/templates), ensure attributes are included. Document each change in `docs/security/sri-change-log.md`.

### 2.4 Enable Integrity-Policy headers (Report-Only → Enforce)

Add headers at public page responses (same place as CSP headers from Phase 2):
- Week 2 (burn-in):
```
Integrity-Policy-Report-Only: blocked-destinations=(script style), endpoints=(integrity-endpoint)
Reporting-Endpoints: integrity-endpoint="/api/integrity-report"
```
- Week 3 (enforcement after zero reports for ≥72h):
```
Integrity-Policy: blocked-destinations=(script style), endpoints=(integrity-endpoint)
Reporting-Endpoints: integrity-endpoint="/api/integrity-report"
```

Server endpoint `/api/integrity-report` should accept JSON per Reporting API and log to `logs/security/integrity-violations.json` with timestamp, documentURL, blockedURL, destination, reportOnly flag.

### 2.5 Test plan

- Positive: Normal load succeeds, integrity reports = 0.
- Negative: Intentionally corrupt local copy checksum to simulate mismatch → verify resource blocked, report logged, UI degrades gracefully.
- CORS check: Confirm CDN responds with `Access-Control-Allow-Origin: *` for the resource.

Exit criteria: All external resources have valid SRI and zero Integrity-Policy violations during ≥72h burn-in; enforcement switched to blocking.

---

## Task 3 — Automated Security Scanning in CI/CD

Owner: DevOps/SecDevOps. Duration: 5–7 days including tuning.

Goal: Add automated gates to block deployments with high/critical vulnerabilities in dependencies or known insecure configurations.

### 3.1 Tools and thresholds

- npm audit: Fail build on `--audit-level=high` (or `critical` if risk appetite allows). Scope: production dependencies.
- Composer audit (PHP): Run `composer audit` and fail on high/critical advisories.
- OWASP Dependency-Check (v9+): Scan `composer.lock`, `package-lock.json`, and other manifests; fail on CVSS ≥ 7.0.
- Snyk (optional but recommended): `snyk test` with org policy; fail on high/critical. Requires SNYK_TOKEN secret.
- Dependabot (if hosted on GitHub): Enable for Composer and npm to auto-PR updates.

### 3.2 Example GitHub Actions workflow (reference)

Create `.github/workflows/security.yml`:
```
name: Security Gates
on:
  push:
    branches: [ main ]
  pull_request:
    branches: [ main ]

jobs:
  npm-audit:
    runs-on: windows-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '20' }
      - run: npm ci
      - run: npm audit --production --audit-level=high

  composer-audit:
    runs-on: windows-latest
    steps:
      - uses: actions/checkout@v4
      - uses: php-actions/composer@v6
        with: { php_version: '8.2' }
      - run: composer validate --no-check-publish
      - run: composer install --no-dev --no-interaction --no-progress
      - run: composer audit --format=json | tee composer-audit.json
      - name: Fail on high/critical
        shell: pwsh
        run: |
          $json = Get-Content composer-audit.json | ConvertFrom-Json
          $advisories = @($json.advisories)
          if ($advisories.Count -gt 0) {
            # Basic threshold: fail if any advisories present; adjust to filter severities when available
            Write-Error "Composer advisories found: $($advisories.Count)"; exit 1
          }

  dependency-check:
    runs-on: windows-latest
    steps:
      - uses: actions/checkout@v4
      - name: Dependency-Check
        uses: dependency-check/Dependency-Check_Action@main
        with:
          format: 'JSON'
          args: "--scan . --failOnCVSS 7.0 --enableRetired --disableAssembly"
      - name: Upload report
        uses: actions/upload-artifact@v4
        with:
          name: dependency-check-report
          path: build/reports/dependency-check-report.json

  snyk:
    if: ${{ secrets.SNYK_TOKEN }}
    runs-on: windows-latest
    steps:
      - uses: actions/checkout@v4
      - uses: snyk/actions/setup@master
      - name: Snyk test
        env:
          SNYK_TOKEN: ${{ secrets.SNYK_TOKEN }}
        run: snyk test --severity-threshold=high
```

Notes:
- Adjust OS runners to Linux if your project/tooling prefers; Windows runners shown to mirror local PowerShell environment.
- Tune thresholds to your risk appetite. Start at `high`, move to `medium` later.

### 3.3 Local PowerShell helpers (optional)

Document the following in `docs/security/ci-local-helpers.md` so developers can replicate failures locally:
```
# npm audit
npm ci
npm audit --production --audit-level=high

# composer audit
composer install --no-dev
composer audit

# dependency-check (if installed locally)
dependency-check.bat --scan . --failOnCVSS 7.0 --format JSON

# snyk (requires auth)
snyk auth
snyk test --severity-threshold=high
```

### 3.4 Dependabot configuration (GitHub)

Add `.github/dependabot.yml`:
```
version: 2
updates:
  - package-ecosystem: "npm"
    directory: "/"
    schedule: { interval: "weekly" }
  - package-ecosystem: "composer"
    directory: "/backend"
    schedule: { interval: "weekly" }
```

### 3.5 Evidence and reporting

- Archive CI reports: `build/security/` artifacts for 90 days.
- Track trends: count of high/critical over time; MTTR for vulnerability fixes.
- Gate policy: no production deployment if any job fails.

---

## Implementation details for this repository

- Backend PHP: Add Integrity-Policy and Reporting-Endpoints headers alongside CSP in `PublicPageController` (or equivalent central response helper). Keep Report-Only for ≥72h.
- Frontend/editor: For any CDN libraries (e.g., DOMPurify from Phase 2), add `integrity` and `crossorigin` attributes. Verify with MDN SRI guidance.
- Logging: Create `logs/security/` folder with rotation for `csp-violations.json` and `integrity-violations.json`.
- Documentation: Under `docs/security/` add ROE, pentest artifacts, sri-inventory.json, sri-change-log.md, ci-local-helpers.md.

---

## Verification checklist (execute before sign-off)

- Penetration Test
  - [ ] ROE signed; test accounts prepared; data non-PII
  - [ ] WSTG-aligned test plan executed; evidence collected
  - [ ] All High/Critical remediated and retested

- SRI + Integrity-Policy
  - [ ] All external scripts/styles have sha384 integrity + crossorigin
  - [ ] Burn-in (Report-Only) shows 0 violations for ≥72h; Enforcement active
  - [ ] Negative test blocked and logged

- CI/CD Gates
  - [ ] npm audit blocks on high/critical
  - [ ] composer audit clean (or acceptable risk documented)
  - [ ] Dependency-Check report clean for CVSS ≥ 7.0
  - [ ] Snyk passing (if enabled)

- Docs & Logs
  - [ ] Reports archived; logs rotating; dashboard reviewed

---

## References (Oct 2025)

- MDN: Subresource Integrity — last modified Aug 18, 2025
- MDN: Integrity-Policy, Integrity-Policy-Report-Only, and Reporting-Endpoints headers (2025)
- OWASP WSTG — stable v4.2 (versioned links recommended)
- OWASP Cheat Sheets: CSP, CI/CD Security, Vulnerable Dependency Management, Third-Party JS Management, XSS Prevention, SQL Injection Prevention, CSRF Prevention

---

## Notes

- Integrity-Policy is a 2025-era feature; start in Report-Only to avoid regressions. Continue using CSP nonces from Phase 2 — CSP and Integrity-Policy complement each other.
- SRI prevents compromised CDN risks but doesn’t sanitize runtime DOM changes. Keep DOMPurify + Trusted Types in place.
- Keep gates fast: parallelize CI jobs; cache dependency databases (dependency-check) for speed.

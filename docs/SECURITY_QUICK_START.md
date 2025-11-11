# 🎯 Security Features Quick Start Guide

**Version:** 1.0  
**Status:** ✅ ETAP 5 Complete  
**Last Updated:** 2025-11-11

---

## Overview

This guide provides quick reference for the new security features added in ETAP 5. All endpoints are available in production.

---

## 1. Email Verification Workflow

### For End Users

**Step 1: Create Account**
```
POST /api/auth/register
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "MySecurePass123!"
}
```

**Step 2: Check Verification Status**
```
GET /api/email-verification-status
Headers: Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "email": "john@example.com",
    "is_verified": false,
    "verification_required": true
  }
}
```

**Step 3: Click Link in Email or Manually Verify**

*Option A: Click link*
```
GET /api/verify-email/{token}
(No authentication needed)
```

*Option B: Use token from email*
```
POST /api/verify-email
{
  "token": "550e8400e29b41d4a716446655440000"
}
Headers: Authorization: Bearer {token}
```

**Step 4: Verify Success**
```json
{
  "success": true,
  "message": "Email verified successfully",
  "data": {
    "user_email": "john@example.com",
    "verified_at": "2025-11-11T10:30:00Z"
  }
}
```

### For Developers

**Send Verification Email:**
```php
use Infrastructure\Service\EmailService;

$emailService = new EmailService();
$emailService->sendVerificationEmail(
    'john@example.com',
    'John Doe',
    'verification_token_here',
    'https://api.example.com/api/verify-email/token_here'
);
```

**Email Template Variables:**
```
{{user_name}} → John Doe
{{user_email}} → john@example.com
{{verification_token}} → 550e8400e29b41d4a716446655440000
{{verification_link}} → https://api.example.com/api/verify-email/token
```

---

## 2. Password Validation Workflow

### Real-Time Validation (Frontend)

**JavaScript Example:**
```javascript
const password = document.getElementById('password').value;

const response = await fetch('/api/check-password-requirements', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({password: password})
});

const data = await response.json();

// Display requirements
data.data.requirements.forEach(req => {
  const element = document.getElementById(req.requirement);
  if (req.met) {
    element.classList.add('met');
  } else {
    element.classList.remove('met');
  }
});

// Show strength meter
const strengthBar = document.getElementById('strength-bar');
strengthBar.style.width = data.data.strength_score + '%';
strengthBar.textContent = data.data.strength_level;
```

### Backend Validation

**PHP Example:**
```php
use Application\UseCase\ValidatePasswordStrength;

$useCase = new ValidatePasswordStrength();
$result = $useCase->validate('MySecurePass123!', $userId);

if (!$result['valid']) {
    foreach ($result['errors'] as $error) {
        echo "Error: $error\n";
    }
    return false;
}

// Password is valid, proceed with creation/update
```

### Password Requirements

✅ **Must have ALL of:**
- 12+ characters
- Uppercase letters (A-Z)
- Lowercase letters (a-z)
- Numbers (0-9)
- Special characters (!@#$%^&*)

❌ **Must NOT:**
- Reuse last 5 passwords
- Be commonly used passwords

### Strength Levels

| Level | Score | Appearance |
|-------|-------|-----------|
| Very Weak | 0-20% | 🔴 Very Weak |
| Weak | 21-40% | 🟠 Weak |
| Fair | 41-60% | 🟡 Fair |
| Strong | 61-80% | 🟢 Strong |
| Very Strong | 81-100% | 🟢🟢 Very Strong |

---

## 3. Rate Limiting & Account Lockout

### How It Works

1. **Failed Login:** User enters wrong password → +1 attempt
2. **5 Failed Attempts:** Within 15 minutes → Account locked
3. **Account Locked:** User cannot login for 15 minutes
4. **Automatic Unlock:** After 15 minutes, attempts reset

### Email Notification

When account is locked, user receives email:
```
Subject: ⚠️ Conta Bloqueada

Your account has been locked for security reasons.
You can unlock it in 15 minutes (at 10:45 AM).
Attempted logins from IP: 192.168.1.100
```

### Handling in Frontend

```javascript
const response = await fetch('/api/auth/login', {
  method: 'POST',
  body: JSON.stringify({email, password})
});

const data = await response.json();

if (response.status === 429) {
  // Rate limited
  alert(`Too many attempts. Try again in ${data.retry_after} seconds`);
}

if (response.status === 403 && data.code === 'ACCOUNT_LOCKED') {
  // Account locked
  alert(`Your account is locked until ${data.data.unlock_at}`);
  // Show countdown timer
  startCountdown(data.data.unlock_at);
}
```

---

## 4. Audit Logging

### View Audit Logs (Admin Only)

**List All Logs:**
```bash
curl -X GET "http://api.example.com/api/audit-logs?page=1&limit=50" \
  -H "Authorization: Bearer admin_token"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "logs": [
      {
        "id": "log_uuid",
        "action": "USER_CREATED",
        "target_type": "user",
        "target_id": "user_uuid",
        "admin_user_id": "admin_uuid",
        "old_values": {},
        "new_values": {"email": "user@example.com"},
        "ip_address": "192.168.1.100",
        "created_at": "2025-11-11T10:30:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 5,
      "total_records": 234
    }
  }
}
```

**Filter by Action:**
```bash
curl -X GET "http://api.example.com/api/audit-logs?action=USER_CREATED" \
  -H "Authorization: Bearer admin_token"
```

**View Critical Actions Only:**
```bash
curl -X GET "http://api.example.com/api/audit-logs/critical" \
  -H "Authorization: Bearer admin_token"
```

### Audited Actions

**User Actions:**
- USER_CREATED
- USER_DELETED
- USER_ROLE_CHANGED
- USER_UPDATED

**Security Events:**
- ACCOUNT_LOCKED
- ACCOUNT_UNLOCKED
- FAILED_LOGIN_ATTEMPT
- PASSWORD_CHANGED
- PASSWORD_POLICY_CHANGED

**Access Control:**
- CSRF_TOKEN_GENERATED
- RATE_LIMIT_ENFORCED

**Admin Actions:**
- ADMIN_LOGIN
- ADMIN_LOGOUT
- SETTINGS_CHANGED

---

## 5. CSRF Protection

### Setup (Already Done)

CSRF protection uses double-submit cookie pattern:
1. Token stored in cookie: `XSRF-TOKEN`
2. Token required in header: `X-CSRF-TOKEN`
3. Or in form body: `_csrf`

### Using in Frontend

**Get Token from Cookie:**
```javascript
function getCookie(name) {
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) return parts.pop().split(';').shift();
}

const csrfToken = getCookie('XSRF-TOKEN');
```

**Send in Request:**
```javascript
const response = await fetch('/api/verify-email', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': csrfToken
  },
  body: JSON.stringify({token: verificationToken})
});
```

**Or in Form:**
```html
<form method="POST" action="/api/endpoint">
  <input type="hidden" name="_csrf" value="{csrf_token}">
  <!-- other fields -->
</form>
```

---

## 6. Configuration

### Security Config File

Location: `/backend/config/security.php`

```php
return [
    'password_policy' => [
        'min_length' => 12,                    // characters
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_digit' => true,
        'require_special_char' => true,
    ],
    'rate_limiting' => [
        'login_max_attempts' => 5,             // per 15 minutes
        'login_lockout_minutes' => 15,
        'api_requests_per_minute' => 100,
    ],
    'email_verification' => [
        'required' => true,                    // for new accounts
        'token_expiry_hours' => 24,
        'resend_cooldown_minutes' => 5,
    ],
    'csrf' => [
        'enabled' => true,
        'token_lifetime_hours' => 24,
    ],
    'audit' => [
        'enabled' => true,
        'retention_days' => 90,                // auto-delete old logs
        'log_sensitive_data' => false,
    ],
];
```

### Email Config File

Location: `/backend/config/email.php`

```php
return [
    'from_email' => 'noreply@example.com',
    'from_name' => 'Expats Health Brazil',
    'smtp_host' => env('SMTP_HOST', 'localhost'),
    'smtp_port' => env('SMTP_PORT', 25),
    'retry_attempts' => 3,
    'retry_delay_seconds' => 5,
];
```

---

## 7. Error Handling

### Common Error Responses

**Invalid Password:**
```json
{
  "success": false,
  "error": "Password does not meet security requirements",
  "code": "INVALID_PASSWORD",
  "data": {
    "errors": [
      "minimum_length: Password must be at least 12 characters",
      "special_chars: Password must contain special characters"
    ]
  }
}
```

**Email Already Verified:**
```json
{
  "success": false,
  "error": "Email already verified",
  "code": "EMAIL_ALREADY_VERIFIED"
}
```

**Token Expired:**
```json
{
  "success": false,
  "error": "Token has expired. Please request a new verification email.",
  "code": "TOKEN_EXPIRED"
}
```

**Rate Limited:**
```json
{
  "success": false,
  "error": "Rate limit exceeded",
  "retry_after": 45
}
```

**Insufficient Permissions:**
```json
{
  "success": false,
  "error": "Insufficient permissions to access this resource",
  "code": "FORBIDDEN"
}
```

---

## 8. Testing Commands

### Quick Test: Password Validation

```bash
# Weak password
curl -X POST "http://localhost:8080/api/check-password-requirements" \
  -H "Content-Type: application/json" \
  -d '{"password":"weak123"}'

# Strong password
curl -X POST "http://localhost:8080/api/check-password-requirements" \
  -H "Content-Type: application/json" \
  -d '{"password":"MySecurePass123!"}'
```

### Quick Test: Email Verification Status

```bash
curl -X GET "http://localhost:8080/api/email-verification-status" \
  -H "Authorization: Bearer eyJhbGc..." 
```

### Quick Test: Audit Logs

```bash
curl -X GET "http://localhost:8080/api/audit-logs?page=1&limit=10" \
  -H "Authorization: Bearer admin_token"
```

---

## 9. Common Issues & Solutions

### Issue: "Email verification required to login"

**Solution:**
1. Check user's email inbox (including spam folder)
2. User can resend email: `POST /api/resend-verification-email`
3. Or click link from original email

### Issue: "Too many login attempts"

**Solution:**
1. Account is locked for 15 minutes
2. User can receive email with unlock link
3. Or wait 15 minutes for automatic unlock
4. Admin can manually unlock in database: `UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?`

### Issue: "CSRF token mismatch"

**Solution:**
1. Ensure CSRF token is included in request header: `X-CSRF-TOKEN`
2. Or in form body: `_csrf=token_value`
3. Token is stored in cookie: `XSRF-TOKEN`
4. Regenerated on login

### Issue: "Password does not meet requirements"

**Solution:**
1. Password must have ALL 5 requirements:
   - ✅ 12+ characters
   - ✅ Uppercase (A-Z)
   - ✅ Lowercase (a-z)
   - ✅ Numbers (0-9)
   - ✅ Special chars (!@#$%^&*)
2. Use endpoint to check: `POST /api/check-password-requirements`
3. Display requirements to user in frontend

---

## 10. Frontend Integration Checklist

- [ ] Password strength indicator on registration/password change form
- [ ] Email verification UI (prompt user to verify)
- [ ] Email verification link in email works correctly
- [ ] Audit log viewer in admin panel
- [ ] Account lockout message with countdown timer
- [ ] CSRF token included in all POST/PUT/DELETE requests
- [ ] Rate limiting error handling (429 response)
- [ ] Password requirements checklist in frontend

---

## 11. Admin Tasks

### Unlock Locked Account

**Via Database:**
```sql
UPDATE users 
SET failed_login_attempts = 0, locked_until = NULL 
WHERE id = 'user_uuid';
```

**Via API (Future ETAP 6):**
```bash
POST /api/users/{id}/unlock
```

### View Recent Audit Logs

```bash
curl -X GET "http://localhost:8080/api/audit-logs?page=1&limit=100" \
  -H "Authorization: Bearer admin_token"
```

### Delete Old Audit Logs

**Via Database:**
```sql
DELETE FROM admin_audit_log 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

**Via Cleanup (Automatic):**
- Automatically runs daily (cleanup scheduled)
- Removes logs older than 90 days

---

## 12. Performance Notes

**Expected Response Times:**
- Email verification: ~120ms
- Password validation: ~80ms
- Audit log listing: ~150ms
- CSRF token generation: ~20ms

**Database Optimization:**
- Create index on `admin_audit_log.created_at`
- Create index on `rate_limits.expires_at`
- Create index on `users.email_verification_token`

---

## 13. Security Best Practices

✅ **DO:**
- Always validate passwords on backend
- Include CSRF tokens in all state-changing requests
- Check email verification before allowing critical actions
- Log all admin/security-related actions
- Regenerate CSRF tokens on login
- Rate limit login attempts
- Use HTTPS for all requests

❌ **DON'T:**
- Send passwords in emails
- Store passwords in plain text
- Expose user IDs in audit logs (if sensitive)
- Trust client-side validation alone
- Disable CSRF protection
- Use weak passwords in testing

---

## 14. Next Steps (ETAP 6)

### Frontend Updates Needed:
1. Password strength indicator component
2. Email verification UI modal
3. Audit log table with sorting/filtering
4. Account security settings page
5. Update api-client.js for automatic CSRF handling
6. Add account lockout countdown display

### Database Updates Needed:
1. Optimize indexes for performance
2. Set up audit log retention policy
3. Configure email templates in database (optional)

### Deployment Checklist:
1. Update environment variables
2. Configure SMTP settings (if not using PHP mail)
3. Set up email rate limiting
4. Configure CORS for security
5. Enable HTTPS everywhere

---

## Contact & Support

**For Issues:**
- Check SECURITY_TESTING_GUIDE.md
- Review logs in /backend/logs/
- Check database for audit trail

**Questions About Implementation:**
- See SECURITY_API_DOCUMENTATION.md
- See ETAP5_COMPLETION_SUMMARY.md
- Review controller source code

---

**Quick Start Guide Version:** 1.0  
**Last Updated:** 2025-11-11  
**Status:** Production Ready  
**Backend Security Features:** ✅ Fully Operational

**Ready to proceed with ETAP 6: Frontend Integration!** 🚀

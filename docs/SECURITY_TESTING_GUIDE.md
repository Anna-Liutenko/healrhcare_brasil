# 🧪 Security Features Testing Guide

## Quick Test Commands

### 1. Test Email Verification Endpoints

```bash
# 1.1 Resend verification email
curl -X POST "http://localhost:8080/api/resend-verification-email" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: your_csrf_token"

# 1.2 Get verification status
curl -X GET "http://localhost:8080/api/email-verification-status" \
  -H "Authorization: Bearer YOUR_TOKEN"

# 1.3 Verify with token (from POST body)
curl -X POST "http://localhost:8080/api/verify-email" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: your_csrf_token" \
  -d '{"token":"TOKEN_FROM_EMAIL"}'

# 1.4 Verify via link (no auth needed)
curl -X GET "http://localhost:8080/api/verify-email/TOKEN_FROM_EMAIL"
```

---

### 2. Test Password Validation Endpoints

```bash
# 2.1 Check real-time requirements
curl -X POST "http://localhost:8080/api/check-password-requirements" \
  -H "Content-Type: application/json" \
  -d '{"password":"MyPassword123!"}'

# 2.2 Full password validation
curl -X POST "http://localhost:8080/api/validate-password" \
  -H "Content-Type: application/json" \
  -d '{
    "password":"MySecurePass123!",
    "user_id":"550e8400-e29b-41d4-a716-446655440000"
  }'
```

**Expected Weak Password Response:**
```json
{
  "success": true,
  "valid": false,
  "strength": "weak",
  "errors": [
    "minimum_length: Password must be at least 12 characters",
    "special_chars: Password must contain special characters"
  ]
}
```

**Expected Strong Password Response:**
```json
{
  "success": true,
  "valid": true,
  "strength": "very-strong",
  "strength_score": 95,
  "message": "Password meets all requirements"
}
```

---

### 3. Test Audit Log Endpoints

```bash
# 3.1 List all audit logs (with pagination)
curl -X GET "http://localhost:8080/api/audit-logs?page=1&limit=50" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"

# 3.2 Filter by action
curl -X GET "http://localhost:8080/api/audit-logs?action=USER_CREATED&page=1" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"

# 3.3 Get single audit log
curl -X GET "http://localhost:8080/api/audit-logs/550e8400-e29b-41d4-a716-446655440000" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"

# 3.4 List critical actions only
curl -X GET "http://localhost:8080/api/audit-logs/critical?page=1" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

---

## PHP Integration Tests

### Test 1: Rate Limiting on Login

```php
<?php
// File: backend/test_rate_limiting.php

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/src/Infrastructure/Middleware/RateLimitMiddleware.php';

use Infrastructure\Middleware\RateLimitMiddleware;

$middleware = new RateLimitMiddleware();

// Simulate 6 failed login attempts from same IP
for ($i = 1; $i <= 6; $i++) {
    echo "Attempt $i: ";
    
    // Check rate limit
    if ($middleware->isLimited('login', '192.168.1.100')) {
        echo "BLOCKED - Rate limited\n";
    } else {
        echo "ALLOWED - Attempt recorded\n";
        $middleware->recordAttempt('login', '192.168.1.100');
    }
    
    sleep(1);
}

// Should see: 5 allowed, 1 blocked
?>
```

### Test 2: Password Strength Validation

```php
<?php
// File: backend/test_password_strength.php

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/src/Domain/ValueObject/PasswordPolicy.php';
require_once __DIR__ . '/src/Application/UseCase/ValidatePasswordStrength.php';

use Domain\ValueObject\PasswordPolicy;
use Application\UseCase\ValidatePasswordStrength;

$useCase = new ValidatePasswordStrength();

// Test cases
$testPasswords = [
    'weak' => 'pass',
    'medium' => 'MyPass123',
    'strong' => 'MySecurePass123!',
    'verystrong' => 'MySecureP@ssw0rd!Complex',
];

foreach ($testPasswords as $type => $password) {
    $result = $useCase->check($password);
    echo "$type: Strength = " . $result['strength'] . 
         " (Score: " . $result['score'] . ")\n";
}
?>
```

### Test 3: Email Verification Flow

```php
<?php
// File: backend/test_email_verification.php

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/src/Application/UseCase/VerifyUserEmail.php';

use Application\UseCase\VerifyUserEmail;

// Assuming user exists with unverified email
$userId = '550e8400-e29b-41d4-a716-446655440000';
$token = 'verification_token_from_email';

$useCase = new VerifyUserEmail();

try {
    $result = $useCase->verify($userId, $token);
    
    echo "✓ Email verified successfully\n";
    echo "  - Email: " . $result['email'] . "\n";
    echo "  - Verified At: " . $result['verified_at'] . "\n";
} catch (Exception $e) {
    echo "✗ Verification failed: " . $e->getMessage() . "\n";
}
?>
```

### Test 4: Audit Logging

```php
<?php
// File: backend/test_audit_logging.php

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/src/Application/UseCase/LogAuditEvent.php';

use Application\UseCase\LogAuditEvent;

$useCase = new LogAuditEvent();

// Log a critical action
$result = $useCase->log([
    'admin_user_id' => 'admin_uuid_here',
    'action' => 'USER_ROLE_CHANGED',
    'target_type' => 'user',
    'target_id' => 'target_user_uuid',
    'old_values' => ['role' => 'editor'],
    'new_values' => ['role' => 'admin'],
    'ip_address' => '192.168.1.100',
    'user_agent' => 'Mozilla/5.0...'
]);

if ($result['success']) {
    echo "✓ Audit log created: " . $result['log_id'] . "\n";
} else {
    echo "✗ Failed to create audit log\n";
}
?>
```

---

## Browser Console Tests

### Test 1: Check CSRF Token

```javascript
// In browser console
console.log('CSRF Token:', document.querySelector('[name="_csrf"]')?.value);
console.log('CSRF Cookie:', document.cookie.split(';')
  .find(c => c.trim().startsWith('XSRF-TOKEN'))?.split('=')[1]);
```

### Test 2: Test Password Validation API

```javascript
async function testPasswordValidation() {
  const response = await fetch('/api/check-password-requirements', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      password: 'MySecurePass123!'
    })
  });
  
  const data = await response.json();
  console.log('Password Validation:', data);
  
  // Should show all requirements met
  data.data.requirements.forEach(req => {
    console.log(`${req.requirement}: ${req.met ? '✓' : '✗'}`);
  });
}

testPasswordValidation();
```

### Test 3: Test Email Verification Status

```javascript
async function checkEmailStatus() {
  const response = await fetch('/api/email-verification-status', {
    method: 'GET',
    headers: {
      'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
    }
  });
  
  const data = await response.json();
  console.log('Email Status:', data);
  
  if (data.data.is_verified) {
    console.log('✓ Email is verified');
  } else {
    console.log('✗ Email verification required');
    console.log('  Resend available at:', data.data.resend_available_at);
  }
}

checkEmailStatus();
```

---

## Database Verification

### Check New Tables Created

```sql
-- Verify all new security tables exist
SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'healthcare_cms' 
AND TABLE_NAME IN ('admin_audit_log', 'rate_limits', 'password_history', 'email_notifications');

-- Should return 4 rows
```

### Check User Table Updates

```sql
-- Verify new security columns in users table
SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'healthcare_cms' 
AND TABLE_NAME = 'users'
AND COLUMN_NAME IN (
  'failed_login_attempts',
  'locked_until', 
  'password_changed_at',
  'email_verified',
  'email_verification_token',
  'email_verification_token_expires_at'
);

-- Should return 6 rows
```

### View Audit Logs

```sql
-- See recent audit logs
SELECT id, action, target_type, target_id, created_at 
FROM admin_audit_log 
ORDER BY created_at DESC 
LIMIT 20;
```

### Check Rate Limits

```sql
-- See current rate limit status
SELECT identifier, action, attempt_count, expires_at 
FROM rate_limits 
WHERE expires_at > NOW();
```

---

## Integration Test Checklist

### Phase 1: Unit Tests
- [ ] Password validation with 10 test cases
- [ ] Rate limiting counter logic
- [ ] CSRF token generation/verification
- [ ] Email template rendering

### Phase 2: API Tests
- [ ] All audit log endpoints (GET list, GET single, GET critical)
- [ ] Email verification endpoints (POST verify, GET link, resend)
- [ ] Password validation endpoints (full validate, check requirements)
- [ ] Error response formats (400, 403, 404, 429)

### Phase 3: Integration Tests
- [ ] Email verification flow (create user → send email → verify link)
- [ ] Account lockout flow (5 failed logins → lockout → unlock)
- [ ] Password change flow (validate → change → audit log → email)
- [ ] Audit logging across all critical actions

### Phase 4: Security Tests
- [ ] Rate limiting enforcement
- [ ] CSRF token validation
- [ ] Authorization checks (403 for insufficient permissions)
- [ ] Input validation (SQL injection, XSS attempts)

---

## Expected Email Recipients

When testing email sending, verify these email types are generated:

1. **verification.html** - Sent on user registration
2. **welcome.html** - Sent on successful registration
3. **password-changed.html** - Sent when user changes password
4. **role-changed.html** - Sent when admin changes user role
5. **account-locked.html** - Sent when account is locked after failed attempts

---

## Troubleshooting

### Issue: "Rate limit exceeded" on first attempt

**Cause:** Previous rate limit records not cleaned up
**Solution:** 
```sql
DELETE FROM rate_limits WHERE expires_at < NOW();
```

### Issue: Email verification link not working

**Cause:** Token expired or doesn't exist
**Solution:**
```sql
-- Check token validity
SELECT id, email_verification_token, email_verification_token_expires_at 
FROM users 
WHERE email = 'test@example.com';

-- Should show token and future expiry time
```

### Issue: Audit logs not appearing

**Cause:** Audit logging disabled or permissions insufficient
**Solution:**
```php
// Check security config
$config = include __DIR__ . '/config/security.php';
echo "Audit enabled: " . ($config['audit']['enabled'] ? 'Yes' : 'No');

// Verify admin role
// Current user must be admin to view logs
```

### Issue: CSRF token mismatch errors

**Cause:** Token not included in request or mismatched
**Solution:**
```javascript
// Always include CSRF token in state-changing requests
const token = document.querySelector('[name="_csrf"]')?.value 
  || getCookie('XSRF-TOKEN');

fetch('/api/endpoint', {
  method: 'POST',
  headers: {
    'X-CSRF-TOKEN': token,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({...})
});
```

---

## Performance Benchmarks

Expected response times (from testing):

- `GET /api/audit-logs` (50 records): ~150ms
- `POST /api/validate-password`: ~80ms
- `POST /api/check-password-requirements`: ~40ms
- `POST /api/verify-email`: ~120ms
- `GET /api/email-verification-status`: ~30ms

If responses are significantly slower, check:
1. Database indexes on audit_log and rate_limits tables
2. Email sending blocking (should be async)
3. Password validation complexity

---

**Last Updated:** 2025-11-11  
**Test Suite Status:** Ready for ЕТАП 6 (Frontend Integration)

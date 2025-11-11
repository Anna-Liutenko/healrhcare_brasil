# 🔐 Security Features API Documentation

## Overview

This document describes the new security feature endpoints added in ЭТАП 5 of the implementation. All endpoints require authentication (JWT token in `Authorization: Bearer` header) except where noted.

---

## 1. Audit Logs API

### 1.1 List Audit Logs

**Endpoint:** `GET /api/audit-logs`

**Authentication:** Required (Admin/Manager role)

**Query Parameters:**
- `page` (int, optional, default: 1) - Page number for pagination
- `limit` (int, optional, default: 50) - Records per page (max: 500)
- `action` (string, optional) - Filter by action type
- `admin_user_id` (uuid, optional) - Filter by admin user who performed the action

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "logs": [
      {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "admin_user_id": "550e8400-e29b-41d4-a716-446655440001",
        "action": "USER_CREATED",
        "target_type": "user",
        "target_id": "550e8400-e29b-41d4-a716-446655440002",
        "old_values": {},
        "new_values": {
          "email": "user@example.com",
          "role": "editor"
        },
        "ip_address": "192.168.1.100",
        "user_agent": "Mozilla/5.0...",
        "created_at": "2025-11-11T10:30:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 5,
      "total_records": 234,
      "per_page": 50
    }
  }
}
```

**Error (403 Forbidden):**
```json
{
  "success": false,
  "error": "Insufficient permissions to view audit logs"
}
```

---

### 1.2 Get Single Audit Log

**Endpoint:** `GET /api/audit-logs/{id}`

**Authentication:** Required (Admin role)

**Path Parameters:**
- `id` (uuid) - Audit log ID

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "admin_user_id": "550e8400-e29b-41d4-a716-446655440001",
    "action": "USER_CREATED",
    "target_type": "user",
    "target_id": "550e8400-e29b-41d4-a716-446655440002",
    "old_values": {},
    "new_values": {
      "email": "user@example.com",
      "role": "editor"
    },
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "created_at": "2025-11-11T10:30:00Z"
  }
}
```

**Error (404 Not Found):**
```json
{
  "success": false,
  "error": "Audit log not found"
}
```

---

### 1.3 List Critical Audit Logs

**Endpoint:** `GET /api/audit-logs/critical`

**Authentication:** Required (Admin role)

**Query Parameters:**
- `page` (int, optional, default: 1) - Page number
- `limit` (int, optional, default: 50) - Records per page

**Critical Actions Included:**
- USER_CREATED
- USER_DELETED
- USER_ROLE_CHANGED
- ACCOUNT_LOCKED
- ACCOUNT_UNLOCKED
- PASSWORD_POLICY_CHANGED
- CSRF_TOKEN_GENERATED
- RATE_LIMIT_ENFORCED
- FAILED_LOGIN_ATTEMPT
- PASSWORD_CHANGED

**Response:** Same format as 1.1

---

## 2. Email Verification API

### 2.1 Verify Email with Token

**Endpoint:** `POST /api/verify-email`

**Authentication:** Required (any authenticated user)

**Request Body:**
```json
{
  "token": "550e8400e29b41d4a716446655440000"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Email verified successfully",
  "data": {
    "user_id": "550e8400-e29b-41d4-a716-446655440000",
    "email": "user@example.com",
    "verified_at": "2025-11-11T10:30:00Z"
  }
}
```

**Error (400 Bad Request):**
```json
{
  "success": false,
  "error": "Invalid or expired verification token"
}
```

---

### 2.2 Verify Email via Link

**Endpoint:** `GET /api/verify-email/{token}`

**Authentication:** Not required

**Path Parameters:**
- `token` (uuid) - Verification token from email link

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Email verified successfully",
  "data": {
    "user_email": "user@example.com",
    "verified_at": "2025-11-11T10:30:00Z"
  }
}
```

**Error (400 Bad Request):**
```json
{
  "success": false,
  "error": "Token has expired. Please request a new verification email."
}
```

---

### 2.3 Resend Verification Email

**Endpoint:** `POST /api/resend-verification-email`

**Authentication:** Required (user must be unverified)

**Request Body:** (empty or optional)
```json
{}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Verification email sent successfully",
  "data": {
    "email": "user@example.com",
    "resent_at": "2025-11-11T10:35:00Z"
  }
}
```

**Error (400 Bad Request):**
```json
{
  "success": false,
  "error": "Email already verified"
}
```

---

### 2.4 Get Email Verification Status

**Endpoint:** `GET /api/email-verification-status`

**Authentication:** Required

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "email": "user@example.com",
    "is_verified": true,
    "verified_at": "2025-11-11T10:30:00Z",
    "verification_required": false
  }
}
```

**Or if not verified:**
```json
{
  "success": true,
  "data": {
    "email": "user@example.com",
    "is_verified": false,
    "verified_at": null,
    "verification_required": true,
    "resend_available_at": "2025-11-11T10:45:00Z"
  }
}
```

---

## 3. Password Validation API

### 3.1 Validate Full Password

**Endpoint:** `POST /api/validate-password`

**Authentication:** Not required

**Request Body:**
```json
{
  "password": "MySecurePass123!",
  "user_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Response (200 OK - Valid):**
```json
{
  "success": true,
  "valid": true,
  "strength": "very-strong",
  "strength_score": 95,
  "message": "Password meets all requirements",
  "data": {
    "requirements": [
      {
        "requirement": "minimum_length",
        "description": "At least 12 characters",
        "met": true
      },
      {
        "requirement": "uppercase",
        "description": "Contains uppercase letters",
        "met": true
      },
      {
        "requirement": "lowercase",
        "description": "Contains lowercase letters",
        "met": true
      },
      {
        "requirement": "numbers",
        "description": "Contains numbers",
        "met": true
      },
      {
        "requirement": "special_chars",
        "description": "Contains special characters (!@#$%^&*)",
        "met": true
      },
      {
        "requirement": "not_used_before",
        "description": "Password not used before",
        "met": true
      }
    ]
  }
}
```

**Response (200 OK - Invalid):**
```json
{
  "success": true,
  "valid": false,
  "strength": "weak",
  "strength_score": 30,
  "message": "Password does not meet security requirements",
  "errors": [
    "minimum_length: Password must be at least 12 characters (currently 8)",
    "special_chars: Password must contain special characters (!@#$%^&*)",
    "not_used_before: This password has been used before"
  ],
  "data": {
    "requirements": [
      {
        "requirement": "minimum_length",
        "description": "At least 12 characters",
        "met": false
      },
      {
        "requirement": "uppercase",
        "description": "Contains uppercase letters",
        "met": true
      },
      {
        "requirement": "lowercase",
        "description": "Contains lowercase letters",
        "met": true
      },
      {
        "requirement": "numbers",
        "description": "Contains numbers",
        "met": true
      },
      {
        "requirement": "special_chars",
        "description": "Contains special characters (!@#$%^&*)",
        "met": false
      },
      {
        "requirement": "not_used_before",
        "description": "Password not used before",
        "met": false
      }
    ]
  }
}
```

---

### 3.2 Check Real-Time Password Requirements

**Endpoint:** `POST /api/check-password-requirements`

**Authentication:** Not required

**Request Body:**
```json
{
  "password": "MySecure123!"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "requirements": [
      {
        "requirement": "minimum_length",
        "description": "At least 12 characters",
        "met": false
      },
      {
        "requirement": "uppercase",
        "description": "Contains uppercase letters",
        "met": true
      },
      {
        "requirement": "lowercase",
        "description": "Contains lowercase letters",
        "met": true
      },
      {
        "requirement": "numbers",
        "description": "Contains numbers",
        "met": true
      },
      {
        "requirement": "special_chars",
        "description": "Contains special characters (!@#$%^&*)",
        "met": true
      }
    ],
    "strength_score": 80,
    "strength_level": "strong",
    "all_requirements_met": false
  }
}
```

---

## 4. Security Headers

All API responses include the following security headers:

```
Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Permissions-Policy: microphone=(), camera=(), payment=()
```

---

## 5. Rate Limiting

Rate limiting is enforced per IP address:

- **Login attempts:** 5 failures per 15 minutes → Account locked for 15 minutes
- **API endpoints:** 100 requests per minute
- **Password validation:** 50 requests per minute

**Rate Limit Headers:**
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 87
X-RateLimit-Reset: 1699691400
```

**When limit exceeded (429):**
```json
{
  "success": false,
  "error": "Rate limit exceeded",
  "retry_after": 45
}
```

---

## 6. CSRF Protection

All state-changing requests (POST, PUT, DELETE) require a CSRF token:

**Get CSRF token:**
```javascript
// Available as cookie: XSRF-TOKEN
// Or from response header: X-CSRF-TOKEN
```

**Include in requests:**
```javascript
// In header
X-CSRF-TOKEN: token_value

// Or in form data
_csrf: token_value
```

---

## 7. Error Responses

### Standard Error Format

```json
{
  "success": false,
  "error": "Error message",
  "code": "ERROR_CODE",
  "data": {}
}
```

### HTTP Status Codes

- **200 OK** - Request successful
- **201 Created** - Resource created successfully
- **400 Bad Request** - Invalid input or validation failed
- **401 Unauthorized** - Missing or invalid authentication
- **403 Forbidden** - Insufficient permissions
- **404 Not Found** - Resource not found
- **429 Too Many Requests** - Rate limit exceeded
- **500 Internal Server Error** - Server error

---

## 8. Authentication Example

```bash
# Using curl with token in header
curl -X GET "https://api.example.com/api/audit-logs" \
  -H "Authorization: Bearer eyJhbGc..." \
  -H "X-CSRF-TOKEN: csrf_token_value"
```

---

## 9. Email Templates

Email templates are located in `/backend/templates/emails/`:

- `verification.html` - Email verification (24-hour token expiry)
- `welcome.html` - Welcome email for new users
- `password-changed.html` - Password change notification
- `role-changed.html` - Role/permission change notification
- `account-locked.html` - Account lockout notification

**Template Variables:**
- `{{user_name}}` - User's full name
- `{{user_email}}` - User's email address
- `{{verification_token}}` - Email verification token
- `{{verification_link}}` - Full verification URL
- `{{changed_at}}` - Date/time of change
- `{{current_year}}` - Current year for copyright

---

## 10. Implementation Notes

### Database Schema

**New Tables:**
- `admin_audit_log` - Audit trail of admin actions
- `rate_limits` - Track rate limit usage by IP
- `password_history` - Store hashed password history
- `email_notifications` - Track email sending status

**New User Columns:**
- `failed_login_attempts` - Counter for failed login attempts
- `locked_until` - Timestamp when account is unlocked
- `password_changed_at` - Last password change date
- `email_verified` - Boolean flag
- `email_verification_token` - Token for verification
- `email_verification_token_expires_at` - Token expiry date

### Security Features

1. **Rate Limiting:**
   - 5 failed login attempts → 15-minute lockout
   - IP-based tracking
   - Automatic cleanup of expired limits

2. **Password Policy:**
   - Minimum 12 characters
   - Requires uppercase, lowercase, digit, special character
   - 5-level strength rating
   - Password history (not reusing last 5 passwords)

3. **Email Verification:**
   - 24-hour token expiry
   - Resend capability with cooldown
   - Required for new accounts

4. **CSRF Protection:**
   - Double-submit cookie pattern
   - Token regeneration on login
   - Configurable via environment

5. **Audit Logging:**
   - All critical actions tracked
   - IP address and User-Agent logged
   - Old/new value comparison stored
   - 90-day retention by default

---

## 11. Configuration

Settings in `/backend/config/security.php`:

```php
return [
    'password_policy' => [
        'min_length' => 12,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_digit' => true,
        'require_special_char' => true,
    ],
    'rate_limiting' => [
        'login_max_attempts' => 5,
        'login_lockout_minutes' => 15,
        'api_requests_per_minute' => 100,
    ],
    'email_verification' => [
        'required' => true,
        'token_expiry_hours' => 24,
        'resend_cooldown_minutes' => 5,
    ],
    'csrf' => [
        'enabled' => true,
        'token_lifetime_hours' => 24,
    ],
    'audit' => [
        'enabled' => true,
        'retention_days' => 90,
        'log_sensitive_data' => false,
    ],
];
```

---

## 12. Next Steps (ЭТАП 6)

Frontend integration tasks:
- Update login form with email verification check
- Add password strength indicator
- Create email verification UI
- Add audit log viewer in admin panel
- Update api-client.js for CSRF token handling
- Implement account lockout countdown UI

---

**Document Version:** 1.0  
**Last Updated:** 2025-11-11  
**Status:** ЭТАП 5 Complete - Ready for Frontend Integration

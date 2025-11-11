# ETAP 6: Frontend Integration - Implementation Plan

**Status:** ✅ STARTED  
**Date:** 2025-11-11  
**Backend:** 100% Ready (All 11 endpoints deployed)

---

## 📋 Tasks Breakdown

### Task 1: Update api-client.js with CSRF token handling
**File:** `/frontend/api-client.js`
**What to do:**
- Add `getCsrfToken()` method to extract CSRF token from cookie
- Modify `request()` method to automatically include CSRF token in all POST/PUT/DELETE requests
- Handle 429 (Rate Limit) responses specifically
- Add security methods for new endpoints

**Estimated Time:** 30 minutes

---

### Task 2: Add CSRF Token Cookie Handler
**File:** `/frontend/js/csrf-handler.js` (NEW)
**What to do:**
- Create utility to manage CSRF token lifecycle
- Get token from cookie on page load
- Refresh token on login
- Export for use in api-client.js

**Estimated Time:** 20 minutes

---

### Task 3: Create Password Strength Indicator Component
**File:** `/frontend/components/password-strength.html` (NEW)
**What to do:**
- Create reusable HTML component for password strength indicator
- Include requirement checklist (12+ chars, uppercase, lowercase, digit, special)
- Real-time validation display
- Color-coded strength levels (very weak, weak, fair, strong, very strong)

**Estimated Time:** 45 minutes

---

### Task 4: Add Password Strength to Registration/Login Forms
**Files Modified:** 
- `/frontend/index.html` - Add strength indicator to user creation form
- `/frontend/editor.html` - Add strength indicator to password change form

**What to do:**
- Integrate password strength component in user management
- Call API `/api/check-password-requirements` on input change
- Display requirement status in real-time
- Prevent submission if password invalid

**Estimated Time:** 60 minutes

---

### Task 5: Add Email Verification UI to Login Form
**Files Modified:** 
- `/frontend/index.html` (login modal)
- `/frontend/editor.html` (login modal)

**What to do:**
- After login, check email verification status
- If not verified, show modal with verification prompt
- Provide "Resend verification email" button
- Allow user to resend email or proceed (depending on config)

**Estimated Time:** 60 minutes

---

### Task 6: Create Account Security Settings Page
**File:** `/frontend/account-security.html` (NEW)
**What to do:**
- Create new admin page for account security settings
- Show email verification status
- Show email address with verification indicator
- Option to resend verification email
- Link to change password form
- Show last login timestamp
- Show active sessions info

**Estimated Time:** 90 minutes

---

### Task 7: Create Audit Log Viewer (Admin Only)
**File:** `/frontend/audit-logs.html` (NEW)
**What to do:**
- Create new admin page for viewing audit logs
- Admin-only access check
- List audit logs with pagination
- Filter by action type
- Filter by admin user
- Show IP address and timestamp
- Show old/new values for data changes
- Export audit logs option

**Estimated Time:** 120 minutes

---

### Task 8: Add Account Lockout Handling
**Files Modified:**
- `/frontend/index.html` (login modal)
- `/frontend/editor.html` (login modal)

**What to do:**
- Handle 403 response with "ACCOUNT_LOCKED" code
- Show lockout message with unlock time
- Display countdown timer
- Show message about resending verification email
- Prevent login attempts during lockout

**Estimated Time:** 45 minutes

---

### Task 9: Add Rate Limiting Error Handling
**Files Modified:**
- `/frontend/api-client.js`
- Global error handler

**What to do:**
- Handle 429 (Too Many Requests) responses
- Show user-friendly error message with retry time
- Implement retry mechanism with exponential backoff
- Log rate limiting events

**Estimated Time:** 45 minutes

---

### Task 10: Add Navigation to Security Features
**File Modified:** `/frontend/index.html` (admin header/menu)

**What to do:**
- Add "Account Security" link in user menu
- Add "Audit Logs" link in admin menu (admin-only)
- Update navigation to include new pages

**Estimated Time:** 30 minutes

---

## 📊 Implementation Order

**Phase 1 (Foundation):** Tasks 1, 2, 9
- Update API client with CSRF and error handling
- Implement rate limiting error handling
- **Duration:** 1.5 hours

**Phase 2 (Password & Email):** Tasks 3, 4, 5
- Create password strength component
- Add to forms
- Add email verification UI
- **Duration:** 2.5 hours

**Phase 3 (Admin Features):** Tasks 6, 7
- Create account security page
- Create audit log viewer
- **Duration:** 3.5 hours

**Phase 4 (Polish):** Tasks 8, 10
- Add lockout handling
- Add navigation
- **Duration:** 1.5 hours

**Total Estimated Time:** ~9 hours (spread over 2-3 days)

---

## 🎯 Key Implementation Details

### CSRF Token Handling
```javascript
// In api-client.js request() method
if (method !== 'GET') {
  const csrfToken = getCsrfToken(); // From cookie
  if (csrfToken) {
    headers['X-CSRF-TOKEN'] = csrfToken;
  }
}
```

### Rate Limiting Response
```javascript
if (response.status === 429) {
  const retryAfter = response.headers.get('Retry-After') || 60;
  showRateLimitError(`Too many requests. Try again in ${retryAfter} seconds`);
  throw new RateLimitError(retryAfter);
}
```

### Password Validation Real-Time
```javascript
password.addEventListener('input', async (e) => {
  const response = await apiClient.post('/api/check-password-requirements', {
    password: e.target.value
  });
  updateStrengthIndicator(response.data);
});
```

### Email Verification Check
```javascript
// After successful login
const status = await apiClient.get('/api/email-verification-status');
if (!status.data.is_verified) {
  showEmailVerificationModal(status.data);
}
```

---

## 🔒 Security Considerations

✅ **CSRF Protection:**
- All POST/PUT/DELETE requests include CSRF token
- Token refreshed on login
- Stored in HttpOnly cookie (server-side)

✅ **Password Security:**
- Real-time validation with server-side enforcement
- Strength requirements clearly shown
- No passwords sent in plain text (always HTTPS)

✅ **Email Verification:**
- Required for sensitive operations
- Token expires after 24 hours
- Resend has rate limiting

✅ **Rate Limiting:**
- 5 failed login attempts → 15 min lockout
- 100 API requests per minute
- Clear error messages to users

✅ **Audit Logging:**
- All admin actions logged
- IP addresses tracked
- Accessible only to admins

---

## 📝 Files to Create

1. `/frontend/js/csrf-handler.js` - CSRF token management
2. `/frontend/components/password-strength.html` - Password strength component
3. `/frontend/account-security.html` - Account security page
4. `/frontend/audit-logs.html` - Audit log viewer page

---

## 📝 Files to Modify

1. `/frontend/api-client.js` - Add new endpoints, CSRF handling, error handling
2. `/frontend/index.html` - Add security features to login/user forms
3. `/frontend/editor.html` - Add security features to editor

---

## ✨ Expected Outcomes

After ETAP 6 completion:
- ✅ Frontend fully integrated with all 11 backend security endpoints
- ✅ CSRF tokens automatically included in all requests
- ✅ Password strength validation with real-time feedback
- ✅ Email verification workflow with UI
- ✅ Account security management page
- ✅ Admin audit log viewer
- ✅ Rate limiting error handling with user feedback
- ✅ Account lockout detection and countdown
- ✅ Professional security UX

---

## 🚀 Ready to Start?

All 7 backend security features are production-ready:
- ✅ API endpoints (11 total)
- ✅ Email templates (5 total)
- ✅ Security middleware
- ✅ Database schema
- ✅ Configuration

**Frontend development can begin immediately!**

---

**Next:** Start with Phase 1 (CSRF + Rate Limiting)

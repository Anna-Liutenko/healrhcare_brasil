
# Healthcare CMS - API Contract

> NOTE: The UI feature to rename blocks (user-facing "rename block" action) has been removed from the visual editor. The `custom_name` field remains in the API and database for backwards compatibility and import/export, but the admin UI no longer exposes block renaming. Use-case and implementation details in the editor should treat `custom_name` as a read-only compatibility field unless an explicit requirement to re-enable renaming is requested.

**Version:** 1.0
**Last Updated:** 2025-10-04
**Base URL:** `http://localhost/healthcare-cms/backend/public`

---

## Table of Contents

1. [Authentication](#authentication)
2. [Pages](#pages)
3. [Blocks](#blocks)
4. [Users](#users)
5. [Media](#media)
6. [Menu](#menu)
7. [Settings](#settings)
8. [Data Types & Enums](#data-types--enums)
9. [Error Codes](#error-codes)
10. [Field Naming Conventions](#field-naming-conventions)

---

## Authentication

### POST `/api/auth/login`

**Description:** Login to the system

**Request:**
```json
{
  "username": "admin",
  "password": "password123"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "username": "admin",
    "email": "admin@example.com",
    "role": "super_admin",
    "is_active": true,
    "created_at": "2025-01-01T00:00:00Z",
    "last_login_at": "2025-10-04T10:30:00Z"
  }
}
```

**Error (401 Unauthorized):**
```json
{
  "success": false,
  "error": {
    "code": "UNAUTHORIZED",
    "message": "Invalid username or password"
  }
}
```

---

### POST `/api/auth/logout`

**Description:** Logout from the system

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

### GET `/api/auth/me`

**Description:** Get current authenticated user

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):** ⭐ Returns user object directly, NOT wrapped
```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "username": "admin",
  "email": "admin@example.com",
  "role": "super_admin",
  "is_active": true,
  "created_at": "2025-01-01T00:00:00Z",
  "last_login_at": "2025-10-04T10:30:00Z"
}
```

**Error (401 Unauthorized):**
```json
{
  "success": false,
  "error": {
    "code": "UNAUTHORIZED",
    "message": "Token is invalid or expired"
  }
}
```

---

## Pages

### GET `/api/pages`

**Description:** Get all pages (without blocks for performance)

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
[
  {
    "id": "75f53538-dd6c-489a-9b20-d0004bb5086b",
    "title": "Home Page",
    "slug": "home",
    "status": "published",
    "type": "regular",
    "created_at": "2025-01-15T10:00:00Z",
    "updated_at": "2025-01-20T14:30:00Z",
    "published_at": "2025-01-20T14:30:00Z",
    "created_by": "550e8400-e29b-41d4-a716-446655440000"
  }
]
```

---

### GET `/api/pages/:id`

**Description:** Get single page by ID with all blocks

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "page": {
    "id": "75f53538-dd6c-489a-9b20-d0004bb5086b",
    "title": "Home Page",
    "slug": "home",
    "status": "published",
    "type": "regular",
    "seo": {
      "meta_title": "Home - Healthcare Brazil",
      "meta_description": "Healthcare services in Brazil",
      "meta_keywords": "healthcare, brazil, medical"
    },
    "tracking": {
      "page_specific_code": "<!-- GA4 custom event -->"
    },
    "created_at": "2025-01-15T10:00:00Z",
    "updated_at": "2025-01-20T14:30:00Z",
    "published_at": "2025-01-20T14:30:00Z",
    "created_by": "550e8400-e29b-41d4-a716-446655440000"
  },
  "blocks": [
    {
      "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "page_id": "75f53538-dd6c-489a-9b20-d0004bb5086b",
      "type": "main-screen",
      "position": 0,
      "custom_name": "Hero Section",
      "data": {
        "title": "Healthcare in Brazil",
        "subtitle": "Quality medical services",
        "backgroundImage": "https://example.com/hero.jpg"
      }
    }
  ]
}
```

**Error (404 Not Found):**
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Page not found",
    "details": {
      "page_id": "invalid-uuid"
    }
  }
}
```

---

### GET `/api/pages/slug/:slug`

**Description:** Get single page by slug with all blocks (for frontend rendering)

**Response (200 OK):**
```json
{
  "page": { /* same as GET /api/pages/:id */ },
  "blocks": [ /* array of blocks */ ]
}
```

**Error (404 Not Found):**
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Page not found",
    "details": {
      "slug": "non-existent-page"
    }
  }
}
```

---

### POST `/api/pages`

**Description:** Create new page with blocks ⭐ Blocks are saved in the same transaction

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request:**
```json
{
  "title": "About Us",
  "slug": "about-us",
  "type": "regular",
  "status": "draft",
  "seo": {
    "meta_title": "About Us - Healthcare Brazil",
    "meta_description": "Learn about our healthcare services",
    "meta_keywords": "about, healthcare, brazil"
  },
  "tracking": {
    "page_specific_code": ""
  },
  "blocks": [
    {
      "type": "page-header",
      "position": 0,
      "custom_name": "Page Header",
      "data": {
        "title": "About Us"
      }
    },
    {
      "type": "text-block",
      "position": 1,
      "custom_name": null,
      "data": {
        "content": "<p>We provide quality healthcare...</p>"
      }
    }
  ]
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "page_id": "new-uuid-here"
}
```

**Error (400 Validation Error):**
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Slug must contain only lowercase letters, numbers, and hyphens",
    "details": {
      "field": "slug",
      "value": "About Us!",
      "constraint": "Must match pattern /^[a-z0-9-]+$/",
      "received_type": "string with invalid characters"
    }
  }
}
```

**Error (409 Conflict):**
```json
{
  "success": false,
  "error": {
    "code": "CONFLICT",
    "message": "Slug 'about-us' already exists",
    "details": {
      "field": "slug",
      "value": "about-us",
      "existing_page_id": "75f53538-dd6c-489a-9b20-d0004bb5086b"
    }
  }
}
```

---

### PUT `/api/pages/:id`

**Description:** Update existing page ⭐ Replaces all blocks (DELETE old → INSERT new)

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request:** Same format as POST

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Page updated successfully"
}
```

---

### PUT `/api/pages/:id/publish`

**Description:** Publish a page (sets status to 'published', updates published_at)

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Page published successfully"
}
```

---

### DELETE `/api/pages/:id`

**Description:** Delete page ⭐ Cascading deletes all blocks

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Page deleted successfully"
}
```

---

## Blocks

### POST `/api/blocks`

**Description:** Create individual block (optional, if not using page-level bulk create)

**Request:**
```json
{
  "page_id": "75f53538-dd6c-489a-9b20-d0004bb5086b",
  "type": "text-block",
  "position": 2,
  "custom_name": "Introduction",
  "data": {
    "content": "<p>Welcome...</p>"
  }
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "block_id": "new-block-uuid"
}
```

---

### PUT `/api/blocks/:id`

**Description:** Update individual block

**Request:**
```json
{
  "custom_name": "Updated Block Name",
  "data": {
    "content": "<p>Updated content...</p>"
  }
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Block updated successfully"
}
```

---

### DELETE `/api/blocks/:id`

**Description:** Delete individual block

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Block deleted successfully"
}
```

---

### PUT `/api/blocks/reorder`

**Description:** Reorder blocks within a page

**Request:**
```json
{
  "page_id": "75f53538-dd6c-489a-9b20-d0004bb5086b",
  "block_ids": [
    "block-uuid-1",
    "block-uuid-2",
    "block-uuid-3"
  ]
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Blocks reordered successfully"
}
```

---

## Users

### GET `/api/users`

**Description:** Get all users (super_admin only)

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
[
  {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "username": "admin",
    "email": "admin@example.com",
    "role": "super_admin",
    "is_active": true,
    "created_at": "2025-01-01T00:00:00Z",
    "last_login_at": "2025-10-04T10:30:00Z"
  }
]
```

**Error (403 Forbidden):**
```json
{
  "error": "Forbidden"
}
```

**Error (401 Unauthorized):**
```json
{
  "error": "Unauthorized"
}
```

---

### POST `/api/users`

**Description:** Create new user (super_admin only)

**Request:**
```json
{
  "username": "editor1",
  "email": "editor1@example.com",
  "password": "securePassword123",
  "role": "editor",
  "is_active": true
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "user_id": "new-user-uuid"
}
```

---

### PUT `/api/users/:id`

**Description:** Update user (super_admin only)

**Request:**
```json
{
  "email": "newemail@example.com",
  "role": "admin",
  "is_active": false
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "User updated successfully"
}
```

**Error (404 Not Found):**
```json
{
  "error": "User not found"
}
```

---

### DELETE `/api/users/:id`

**Description:** Delete user (super_admin only)

**Response (200 OK):**
```json
{
  "success": true,
  "message": "User deleted successfully"
}
```

**Error (400 Bad Request):**
```json
{
  "error": "Cannot delete the primary super admin"
}
```

---

## Media

### GET `/api/media`

**Description:** Get all media files

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
[
  {
    "id": "media-uuid-1",
    "filename": "hero-image.jpg",
    "url": "https://example.com/uploads/hero-image.jpg",
    "type": "image",
    "size": 245678,
    "uploaded_by": "550e8400-e29b-41d4-a716-446655440000",
    "uploaded_at": "2025-01-15T10:00:00Z"
  }
]
```

---

### POST `/api/media/upload`

**Description:** Upload new media file

**Headers:**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Request:**
```
Form Data:
- file: (binary file)
```

**Response (201 Created):**
```json
{
  "success": true,
  "file_id": "media-uuid-new",
  "file_url": "https://example.com/uploads/filename.jpg"
}
```

---

### DELETE `/api/media/:id`

**Description:** Delete media file

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Media file deleted successfully"
}
```

---

## Menu

### GET `/api/menu`

**Description:** Get menu structure

**Response (200 OK):**
```json
{
  "id": "menu-uuid",
  "name": "main-menu",
  "display_name": "Main Navigation",
  "items": [
    {
      "id": "item-uuid-1",
      "label": "Home",
      "page_id": "75f53538-dd6c-489a-9b20-d0004bb5086b",
      "position": 0,
      "parent_id": null
    },
    {
      "id": "item-uuid-2",
      "label": "About",
      "page_id": "another-uuid",
      "position": 1,
      "parent_id": null
    }
  ]
}
```

---

### POST `/api/menu`

**Description:** Create menu item

**Request:**
```json
{
  "label": "Services",
  "page_id": "page-uuid",
  "position": 2,
  "parent_id": null
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "menu_item_id": "new-menu-item-uuid"
}
```

---

### PUT `/api/menu/:id`

**Description:** Update menu item

**Request:**
```json
{
  "label": "Updated Label",
  "position": 3
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Menu item updated successfully"
}
```

---

### DELETE `/api/menu/:id`

**Description:** Delete menu item

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Menu item deleted successfully"
}
```

---

### PUT `/api/menu/reorder`

**Description:** Reorder items within a menu

**Request:**
```json
{
  "menu_id": "menu-uuid",
  "ordered_ids": [
    "item-uuid-3",
    "item-uuid-1",
    "item-uuid-2"
  ]
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Menu items reordered successfully"
}
```

---

## Settings

### GET `/api/settings`

**Description:** Get global settings

**Response (200 OK):**
```json
{
  "general": {
    "site_name": "Healthcare Brazil",
    "site_description": "Navigating Brazilian Healthcare for Expats",
    "site_domain": "expats-health.com.br"
  },
  "header": {
    "logo_text": "Expats Health Brazil",
    "logo_url": "https://example.com/logo.svg"
  },
  "footer": {
    "logo_text": "Expats Health Brazil",
    "copyright": "© 2025 Expats Health Brazil",
    "privacy_link": "/privacy",
    "privacy_text": "Privacy Policy"
  },
  "cookie_banner": {
    "enabled": true,
    "message": "Мы используем cookie для улучшения работы сайта...",
    "accept_text": "Принять",
    "details_text": "Подробнее"
  },
  "tracking": {
    "global_tracking_code": "<!-- Google Analytics -->"
  },
  "widgets": {
    "global_widgets_code": "<!-- Chat widget -->"
  }
}
```

---

### PUT `/api/settings`

**Description:** Update global settings

**Request:**
```json
{
  "general": {
    "site_name": "Expats Health Brazil",
    "site_description": "Navigating Brazilian Healthcare for Expats"
  },
  "cookie_banner": {
    "enabled": false,
    "message": "Мы используем cookie только для аналитики"
  },
  "tracking": {
    "global_tracking_code": "<!-- Updated tracking -->"
  }
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Settings updated successfully"
}
```

**Response (400 Bad Request):**
```json
{
  "error": "Unknown setting field \"general.unknown_field\""
}
```

---

## Data Types & Enums

### PageType
```typescript
enum PageType {
  REGULAR = 'regular',
  ARTICLE = 'article',
  GUIDE = 'guide',
  COLLECTION = 'collection'
}
```

### PageStatus
```typescript
enum PageStatus {
  DRAFT = 'draft',
  PUBLISHED = 'published',
  ARCHIVED = 'archived'
}
```

### BlockType
```typescript
enum BlockType {
  MAIN_SCREEN = 'main-screen',
  TEXT_BLOCK = 'text-block',
  SERVICE_CARDS = 'service-cards',
  ARTICLE_CARDS = 'article-cards',
  ABOUT_SECTION = 'about-section',
  PAGE_HEADER = 'page-header',
  CTA_SECTION = 'cta-section',
  FAQ_BLOCK = 'faq-block'
}
```

### UserRole
```typescript
enum UserRole {
  SUPER_ADMIN = 'super_admin',
  ADMIN = 'admin',
  EDITOR = 'editor'
}
```

---

## Error Codes

| HTTP Status | Code | Description |
|-------------|------|-------------|
| 400 | VALIDATION_ERROR | Request data failed validation |
| 401 | UNAUTHORIZED | Not authenticated or token invalid |
| 403 | FORBIDDEN | Authenticated but no permission |
| 404 | NOT_FOUND | Resource not found |
| 409 | CONFLICT | Resource conflict (e.g., duplicate slug) |
| 500 | SERVER_ERROR | Internal server error |

---

## Field Naming Conventions

⭐ **CRITICAL:** Different naming conventions between frontend and backend

### Backend (PHP, Database, API)
- **Format:** `snake_case`
- **Examples:** `custom_name`, `created_at`, `meta_title`, `page_specific_code`

### Frontend (JavaScript, Vue.js)
- **Format:** `camelCase`
- **Examples:** `customName`, `createdAt`, `metaTitle`, `pageSpecificCode`

### URL Slugs
- **Format:** `kebab-case`
- **Examples:** `about-us`, `my-page`, `healthcare-services`
- **Pattern:** `/^[a-z0-9-]+$/` (only lowercase letters, numbers, hyphens)

### Conversion
**Always use mapper functions:**
- `blockToAPI(block)` - converts camelCase → snake_case before sending to API
- `blockFromAPI(block)` - converts snake_case → camelCase after receiving from API
- `generateSlug(title)` - converts any string to valid slug with transliteration

---

## Validation Rules

### Page

**title:**
- Required: Yes
- Type: string
- Min length: 1
- Max length: 255

**slug:**
- Required: Yes
- Pattern: `/^[a-z0-9-]+$/`
- Unique: Yes (across all pages)
- Auto-generate: `transliterate(title)` if empty
- Error message: "Slug must contain only lowercase letters, numbers, and hyphens"

**type:**
- Required: Yes
- Enum: `['regular', 'article', 'guide', 'collection']`
- Error message: "Type must be one of: regular, article, guide, collection"

**created_by:**
- Required: Yes
- Format: UUID v4
- Must exist in `users` table
- Error message: "CreatedBy must be a valid UUID of existing user"

### Block

**type:**
- Required: Yes
- Enum: BlockType values
- Error message: "Type must be one of: main-screen, text-block, service-cards, etc."

**position:**
- Required: Yes
- Type: integer
- Min: 0
- Unique: Yes (within page_id scope)

**custom_name:**
- Optional: Yes
- Max length: 255
- Note: `snake_case` in API, `camelCase` on frontend

---

**End of API Contract**

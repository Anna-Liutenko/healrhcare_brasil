# API Endpoints - Шпаргалка

**Дата создания:** 5 октября 2025  
**База URL:** `http://localhost/healthcare-cms-backend/public`

---

## 🔐 Authentication

### Login
```bash
POST /api/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "admin123"
}
```

**Response:**
```json
{
  "success": true,
  "session_id": "uuid",
  "user": {
    "id": "uuid",
    "username": "admin",
    "email": "admin@healthcare-brazil.com",
    "role": "admin"
  }
}
```

### Logout
```bash
POST /api/auth/logout
```

### Get Current User
```bash
GET /api/auth/me
```

---

## 📄 Pages

### List All Pages
```bash
GET /api/pages
```

### Get Page by ID
```bash
GET /api/pages/{id}
```

### Create Page
```bash
POST /api/pages
Content-Type: application/json

{
  "title": "New Page",
  "slug": "new-page",
  "status": "draft",
  "type": "regular"
}
```

### Update Page
```bash
PUT /api/pages/{id}
Content-Type: application/json

{
  "title": "Updated Title",
  "status": "published"
}
```

### Publish Page
```bash
PUT /api/pages/{id}/publish
```

### Delete Page
```bash
DELETE /api/pages/{id}
```

---

## 👥 Users

### List All Users
```bash
GET /api/users
```

### Create User
```bash
POST /api/users
Content-Type: application/json

{
  "username": "newuser",
  "email": "user@example.com",
  "password": "password123",
  "role": "editor"
}
```

### Update User
```bash
PUT /api/users/{id}
Content-Type: application/json

{
  "username": "updated_username",
  "email": "newemail@example.com",
  "role": "admin"
}
```

### Delete User
```bash
DELETE /api/users/{id}
```

---

## 📁 Media

### List All Media Files
```bash
GET /api/media
```

**Response:**
```json
[
  {
    "id": "uuid",
    "filename": "image.jpg",
    "url": "/uploads/uuid.jpg",
    "type": "image",
    "size": 195198,
    "human_size": "190.62 KB",
    "uploaded_by": "user-uuid",
    "uploaded_at": "2025-10-05 04:43:52"
  }
]
```

### Upload Media File
```bash
POST /api/media/upload
Content-Type: multipart/form-data

file: [binary data]
```

**Response:**
```json
{
  "success": true,
  "file_id": "uuid",
  "file_url": "/uploads/filename.jpg",
  "filename": "original.jpg",
  "type": "image",
  "size": 123456,
  "human_size": "120.56 KB"
}
```

**PowerShell Example:**
```powershell
curl.exe -X POST -F "file=@C:\path\to\image.jpg" http://localhost/healthcare-cms-backend/public/api/media/upload
```

### Delete Media File
```bash
DELETE /api/media/{id}
```

**Response:**
```json
{
  "success": true,
  "message": "Media file deleted successfully"
}
```

---

## 🧭 Menu

### List Menu Items
```bash
GET /api/menu
```

### Create Menu Item
```bash
POST /api/menu
Content-Type: application/json

{
  "label": "New Item",
  "url": "/new-page",
  "order": 1,
  "parent_id": null
}
```

### Update Menu Item
```bash
PUT /api/menu/{id}
Content-Type: application/json

{
  "label": "Updated Label",
  "order": 2
}
```

### Reorder Menu Items
```bash
PUT /api/menu/reorder
Content-Type: application/json

{
  "items": [
    {"id": "uuid1", "order": 1},
    {"id": "uuid2", "order": 2}
  ]
}
```

### Delete Menu Item
```bash
DELETE /api/menu/{id}
```

---

## ⚙️ Settings

### Get All Settings
```bash
GET /api/settings
```

**Response:**
```json
{
  "site_name": "Healthcare Brazil",
  "site_description": "...",
  "contact_email": "contact@healthcare-brazil.com",
  ...
}
```

### Update Settings
```bash
PUT /api/settings
Content-Type: application/json

{
  "site_name": "New Site Name",
  "contact_email": "newemail@example.com"
}
```

---

## 🏥 Health Check

### Server Health
```bash
GET /api/health
```

**Response:**
```json
{
  "status": "ok",
  "service": "Expats Health Brazil CMS API",
  "version": "1.0.0"
}
```

---

## 📊 Response Formats

### Success Response
```json
{
  "success": true,
  "data": { ... },
  "message": "Optional success message"
}
```

### Error Response
```json
{
  "error": "Error message description"
}
```

**HTTP Status Codes:**
- `200 OK` - Successful GET/PUT
- `201 Created` - Successful POST
- `400 Bad Request` - Validation error
- `401 Unauthorized` - Authentication required
- `404 Not Found` - Resource not found
- `500 Internal Server Error` - Server error

---

## 📝 Logs

**Location:** `backend/logs/`

### Request Log
```
backend/logs/api-requests.log
```
Format: JSON с timestamp, method, URI, IP, headers, body

### Response Log
```
backend/logs/api-responses.log
```
Format: JSON с timestamp, status, duration_ms, body

### Error Log
```
backend/logs/errors.log
```
Format: JSON с timestamp, message, context, exception (message, code, file, line, trace)

**View Recent Logs (PowerShell):**
```powershell
Get-Content backend\logs\api-requests.log -Tail 10
Get-Content backend\logs\api-responses.log -Tail 10
Get-Content backend\logs\errors.log -Tail 10
```

---

## 🧪 Testing Examples (PowerShell)

### Get Health Status
```powershell
Invoke-WebRequest -Uri http://localhost/healthcare-cms-backend/public/api/health `
  -Method GET | Select-Object -ExpandProperty Content
```

### Get All Pages
```powershell
Invoke-WebRequest -Uri http://localhost/healthcare-cms-backend/public/api/pages `
  -Method GET | Select-Object -ExpandProperty Content
```

### Create Page
```powershell
$body = @{
  title = "Test Page"
  slug = "test-page"
  status = "draft"
  type = "regular"
} | ConvertTo-Json

Invoke-WebRequest -Uri http://localhost/healthcare-cms-backend/public/api/pages `
  -Method POST -Body $body -ContentType 'application/json' `
  | Select-Object -ExpandProperty Content
```

### Upload Image
```powershell
curl.exe -X POST `
  -F "file=@C:\path\to\image.jpg" `
  http://localhost/healthcare-cms-backend/public/api/media/upload
```

---

## 🔑 Test Users

**Username:** `admin`  
**Password:** `admin123`  
**Role:** `admin`  
**ID:** `550e8400-e29b-41d4-a716-446655440001`

**Username:** `editor`  
**Password:** `admin123`  
**Role:** `editor`  
**ID:** `550e8400-e29b-41d4-a716-446655440002`

**Username:** `anna`  
**Role:** `super_admin`  
**ID:** `7dac7651-a0a0-11f0-95ed-84ba5964b1fc`

---

**Последнее обновление:** 5 октября 2025

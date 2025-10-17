$ErrorActionPreference = 'Stop'
$base = 'http://localhost/healthcare-cms-backend/public'
Write-Output "API base: $base"

try {
    $login = Invoke-RestMethod -Uri "$base/api/auth/login" -Method Post -Body (@{ username = 'admin@example.com'; password = 'password123' } | ConvertTo-Json) -ContentType 'application/json'
    Write-Output "LOGIN_OK"
    # Print a short token preview
    if ($login.token) { Write-Output "TOKEN_LEN=$($login.token.Length)" }
} catch {
    Write-Output "LOGIN_FAIL: $($_.Exception.Message)"
    exit 2
}

$token = $login.token
$headers = @{ Authorization = "Bearer $token" }

# Create page
$slug = 'smoke-menu-test-' + (Get-Date -UFormat %s)
$createBody = @{ 
    title = "Smoke Menu Test $slug"; 
    slug = $slug; 
    type = 'regular'; 
    status = 'published'; 
    seoTitle=''; 
    seoDescription=''; 
    seoKeywords=''; 
    show_in_menu = 1; 
    menu_position = 0; 
    menu_title = 'Smoke Test'; 
    blocks = @() 
}

try {
    $create = Invoke-RestMethod -Uri "$base/api/pages" -Method Post -Headers $headers -Body ($createBody | ConvertTo-Json -Depth 10) -ContentType 'application/json'
    Write-Output "CREATE_OK"
} catch {
    Write-Output "CREATE_FAIL: $($_.Exception.Message)"
    exit 3
}

# Determine page id
$id = $null
if ($create.pageId) { $id = $create.pageId }
elseif ($create.id) { $id = $create.id }
elseif ($create.page -and $create.page.id) { $id = $create.page.id }

Write-Output "PAGE_ID=$id"

# Publish (idempotent)
if ($id) {
    try {
        Invoke-RestMethod -Uri "$base/api/pages/$id/publish" -Method Put -Headers $headers -ErrorAction Stop | Out-Null
        Write-Output "PUBLISH_OK"
    } catch {
        Write-Output "PUBLISH_FAIL: $($_.Exception.Message)"
    }
} else {
    Write-Output "NO_PAGE_ID, skipping publish"
}

# Fetch public menu
try {
    $menu = Invoke-RestMethod -Uri "$base/api/menu/public" -Method Get -ErrorAction Stop
    $menu | ConvertTo-Json -Depth 10 | Out-File -FilePath menu_result.json -Encoding utf8
    Write-Output "MENU_OK - saved to menu_result.json"
} catch {
    Write-Output "MENU_FAIL: $($_.Exception.Message)"
    exit 4
}

# Print a short extract
try {
    $menu.data | Select-Object -First 10 | ConvertTo-Json -Depth 5 | Write-Output
} catch { }

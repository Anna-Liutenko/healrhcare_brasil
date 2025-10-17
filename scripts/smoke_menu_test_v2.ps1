# Smoke test v2: create page with menu fields via API and verify /api/menu/public

$base = 'http://localhost/healthcare-cms-backend/public'
$adminUser = 'admin'
$adminPass = 'password123' # adjust if different

function Log($msg) { Write-Host "[SMOKE] $msg" }

# 1. Login
$login = Invoke-RestMethod -Uri "$base/api/auth/login" -Method Post -Body (@{ username = $adminUser; password = $adminPass } | ConvertTo-Json)
if (-not $login.token) { Log "LOGIN_FAIL"; exit 1 }
$token = $login.token
Log "LOGIN_OK token len=$($token.Length)"

# Helper for authenticated calls
function Api($method, $path, $body = $null) {
    $headers = @{ Authorization = "Bearer $token" }
    if ($null -ne $body) {
        return Invoke-RestMethod -Uri "$base$path" -Method $method -Headers $headers -Body ($body | ConvertTo-Json -Depth 10) -ContentType 'application/json'
    } else {
        return Invoke-RestMethod -Uri "$base$path" -Method $method -Headers $headers
    }
}
# Fetch current user to get ID for createdBy
$me = Invoke-RestMethod -Uri "$base/api/auth/me" -Method GET -Headers @{ Authorization = "Bearer $token" }
$userId = $me.id
Log "ME_OK id=$userId"

# 2. Create page with menu fields
$slug = "smoke-menu-" + [int][double]::Parse((Get-Date -UFormat %s))
$createBody = @{
    title = "Smoke Menu Test"
    slug = $slug
    type = 'regular'
    createdBy = $userId
    show_in_menu = 1
    menu_title = 'Smoke Test Menu'
    menu_position = 5
}

$createRes = Api 'POST' '/api/pages' $createBody
if (-not $createRes.page_id -and -not $createRes.pageId -and -not $createRes.id) { Log "CREATE_FAIL"; exit 1 }
$createdId = $null
if ($createRes.page_id) { $createdId = $createRes.page_id }
elseif ($createRes.pageId) { $createdId = $createRes.pageId }
elseif ($createRes.id) { $createdId = $createRes.id }
Log "CREATE_OK id=$createdId"

# 3. Optionally update page to ensure fields persisted (some APIs might ignore fields on create)
$updateBody = @{
    show_in_menu = 1
    menu_title = 'Smoke Test Menu'
    menu_position = 5
}
$null = Api 'PUT' "/api/pages/$createdId" $updateBody
Log "UPDATE_OK"

# 4. Publish
$null = Api 'PUT' "/api/pages/$createdId/publish"
Log "PUBLISH_OK"

# 5. Fetch menu
$menu = Invoke-RestMethod -Uri "$base/api/menu/public" -Method GET
if ($menu.success -and $menu.data) {
    $found = $menu.data | Where-Object { $_.slug -eq $slug }
    if ($found) { Log "MENU_OK found slug=$slug"; $menu.data | ConvertTo-Json | Out-File -FilePath "scripts\menu_result.json"; exit 0 }
}
Log "MENU_FAIL"
exit 2

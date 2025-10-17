$base='http://localhost/healthcare-cms-backend/public'
try {
  $login = Invoke-RestMethod -Uri "$base/api/auth/login" -Method Post -Body (@{ username='admin'; password='password123'} | ConvertTo-Json) -ContentType 'application/json' -ErrorAction Stop
  Write-Output "LOGIN_OK"
} catch { Write-Output "LOGIN_FAIL: $($_.Exception.Message)"; exit 1 }

$token = $login.token
$userId = $login.user.id
Write-Output "USER_ID=$userId TOKEN_LEN=$($token.Length)"
$headers = @{ Authorization = "Bearer $token" }

$slug = 'smoke-menu-' + ([int](Get-Date -UFormat %s))
$createBody = @{ title = "Smoke Menu $slug"; slug = $slug; type = 'regular'; status = 'published'; seoTitle = ''; seoDescription = ''; seoKeywords = ''; show_in_menu = 1; menu_position = 0; menu_title = 'Smoke Test'; blocks = @(); createdBy = $userId }

try {
  $create = Invoke-RestMethod -Uri "$base/api/pages" -Method Post -Headers $headers -Body ($createBody | ConvertTo-Json -Depth 10) -ContentType 'application/json' -ErrorAction Stop
  Write-Output "CREATE_OK"
} catch { Write-Output "CREATE_FAIL: $($_.Exception.Message)"; exit 2 }

$id = $null
if ($create.page_id) { $id = $create.page_id } elseif ($create.pageId) { $id = $create.pageId } elseif ($create.page -and $create.page.id) { $id = $create.page.id } elseif ($create.id) { $id = $create.id }
Write-Output "CREATED_PAGE_ID=$id"

if ($id) {
  try {
    Invoke-RestMethod -Uri "$base/api/pages/$id/publish" -Method Put -Headers $headers -ErrorAction Stop | Out-Null
    Write-Output "PUBLISH_OK"
  } catch { Write-Output "PUBLISH_FAIL: $($_.Exception.Message)" }
} else { Write-Output "NO_ID, skipping publish" }

try {
  $menu = Invoke-RestMethod -Uri "$base/api/menu/public" -Method Get -ErrorAction Stop
  $menu | ConvertTo-Json -Depth 10 | Out-File menu_result.json -Encoding utf8
  Write-Output "MENU_OK: count=$($menu.data.Count)"
  $menu.data | Select-Object -First 10 | ConvertTo-Json -Depth 5 | Write-Output
} catch { Write-Output "MENU_FAIL: $($_.Exception.Message)"; exit 3 }

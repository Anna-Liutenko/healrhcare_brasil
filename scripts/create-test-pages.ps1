param(
    [string]$Type = 'article',
    [int]$Count = 12
)

$ErrorActionPreference = 'Stop'

$base = 'http://localhost/healthcare-cms-backend/public'

Write-Output ("Login to " + $base + "/api/auth/login")
try {
    $login = Invoke-RestMethod -Uri ($base + '/api/auth/login') -Method Post -Body (@{ username='admin'; password='password' } | ConvertTo-Json) -ContentType 'application/json' -ErrorAction Stop
} catch {
    Write-Output "Login failed with message: $($_.Exception.Message)"
    exit 1
}

$token = $login.token
$userId = $login.user.id
if (-not $token) {
    Write-Output "No token received. Aborting."
    exit 1
}
Write-Output "Login OK, token length: $($token.Length); userId: $userId"

# Create 12 test pages
for ($i = 1; $i -le $Count; $i++) {
    $ts = Get-Date -Format 'yyyyMMddHHmmss'
    $slug = "test-auto-page-$ts-$i"
    $payload = @{
        title = "Auto Test Page $i"
        slug = $slug
        status = 'published'
        type = $Type
        seoDescription = ('Auto generated test page ' + $i)
        created_by = $userId
    } | ConvertTo-Json

    try {
    $resp = Invoke-RestMethod -Uri ($base + '/api/pages') -Method Post -Body $payload -ContentType 'application/json' -Headers @{ Authorization = ("Bearer " + $token) } -ErrorAction Stop
        Write-Output ("Created page #{0} -> pageId: {1}" -f $i, $resp.pageId)
    } catch {
        $msg = $_.Exception.Message -replace "\r|\n", ' '
        Write-Output ("Failed to create page #{0}: {1}" -f $i, $msg)
    }
    Start-Sleep -Milliseconds 400
}

# Check collection items count for page 1 and page 2
$collectionId = '4b970956-6f44-4922-8b45-faad71252e9d'
try {
    $r1 = Invoke-RestMethod -Uri ($base + '/api/pages/' + $collectionId + '/collection-items?page=1&limit=12') -Method Get -ErrorAction Stop
    $count1 = 0
    if ($r1.data.sections -and $r1.data.sections[0].items) { $count1 = $r1.data.sections[0].items.Count }
    Write-Output "Collection page 1 items: $count1"

    $r2 = Invoke-RestMethod -Uri ($base + '/api/pages/' + $collectionId + '/collection-items?page=2&limit=12') -Method Get -ErrorAction Stop
    $count2 = 0
    if ($r2.data.sections -and $r2.data.sections[0].items) { $count2 = $r2.data.sections[0].items.Count }
    Write-Output "Collection page 2 items: $count2"
} catch {
    Write-Output "Failed to fetch collection-items: $($_.Exception.Message)"
}

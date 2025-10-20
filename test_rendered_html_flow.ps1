# Test: Create page via API with article block and verify rendered_html

$apiBase = "http://localhost/healthcare-cms-backend/api"
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$pageSlug = "test-article-$timestamp"
$pageTitle = "Тестовая статья $timestamp"

Write-Host "=== Testing rendered_html workflow ===" -ForegroundColor Cyan
Write-Host "Slug: $pageSlug`n"

# Page data with article block
$pageData = @{
    title = $pageTitle
    slug = $pageSlug
    status = "draft"
    type = "article"
    createdBy = "anna@test.local"
    blocks = @(
        @{
            type = "text-block"
            position = 0
            data = @{
                content = "<h2>Заголовок тестовой статьи</h2><p>Это тестовый контент статьи для проверки rendered_html.</p>"
                title = ""
                alignment = "left"
                containerStyle = "article"
            }
        }
    )
    renderedHtml = '<section class="article-block"><div class="article-container"><div class="article-content text-left"><div><h2>Заголовок тестовой статьи</h2><p>Это тестовый контент статьи для проверки rendered_html.</p></div></div></div></section>'
}

$jsonData = $pageData | ConvertTo-Json -Depth 10

Write-Host "Creating page..." -ForegroundColor Green
Write-Host "Payload size: $($jsonData.Length) bytes`n"

$response = Invoke-WebRequest -Uri "$apiBase/pages" `
    -Method POST `
    -Headers @{
        'Content-Type' = 'application/json'
    } `
    -Body $jsonData `
    -ErrorAction Stop

Write-Host "Response Status: $($response.StatusCode)" -ForegroundColor Green

$result = $response.Content | ConvertFrom-Json

if ($result.pageId) {
    Write-Host "✓ Page created: $($result.pageId)`n" -ForegroundColor Green
    $pageId = $result.pageId
    
    # Check database
    Write-Host "Checking database..." -ForegroundColor Yellow
    
    $query = @"
SELECT 
    id, 
    title, 
    slug,
    CHAR_LENGTH(rendered_html) as rendered_html_length,
    (rendered_html IS NOT NULL) as has_rendered,
    SUBSTRING(rendered_html, 1, 100) as rendered_snippet
FROM pages 
WHERE id = '$pageId'
"@
    
    # Use PHP to query (since PowerShell doesn't have native MySQL)
    $php = @"
<?php
@`$pdo = new PDO('mysql:host=localhost;dbname=healthcare_cms', 'root', '');
@`$stmt = `$pdo->prepare('SELECT id, title, slug, rendered_html FROM pages WHERE id = ?');
@`$stmt->execute(['$pageId']);
if (`$row = `$stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . `$row['id'] . "\n";
    echo "Title: " . `$row['title'] . "\n";
    echo "Slug: " . `$row['slug'] . "\n";
    if (`$row['rendered_html']) {
        echo "rendered_html: YES (" . strlen(`$row['rendered_html']) . " bytes)\n";
        echo "First 200 chars:\n" . substr(`$row['rendered_html'], 0, 200) . "...\n";
    } else {
        echo "rendered_html: NO (NULL or empty)\n";
    }
} else {
    echo "Page not found!\n";
}
?>
"@
    
    $php | php
    
} else {
    Write-Host "✗ Failed to create page" -ForegroundColor Red
    Write-Host $result
}

# enable-rewrite-and-restart-apache.ps1
# Safe script to enable mod_rewrite and AllowOverride All in XAMPP Apache
# Run this script in PowerShell AS ADMINISTRATOR

$ErrorActionPreference = 'Stop'

function Assert-Admin {
    $isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    if (-not $isAdmin) {
        Write-Error 'This script must be run as Administrator. Open PowerShell as Administrator and re-run.'
        exit 1
    }
}

Assert-Admin

$timestamp = Get-Date -Format yyyyMMddHHmmss
$confDir = 'C:\xampp\apache\conf'
$httpd = Join-Path $confDir 'httpd.conf'
$httpdVhosts = Join-Path $confDir 'extra\httpd-vhosts.conf'
$errorLog = 'C:\xampp\apache\logs\error.log'

if (-not (Test-Path $httpd)) {
    Write-Error "httpd.conf not found at $httpd. Is XAMPP installed at C:\xampp?"
    exit 1
}

# Make backups
$httpdBak = "$httpd.bak.$timestamp"
Copy-Item -Path $httpd -Destination $httpdBak -Force
Write-Host "Backup created: $httpdBak"

if (Test-Path $httpdVhosts) {
    $vhostsBak = "$httpdVhosts.bak.$timestamp"
    Copy-Item -Path $httpdVhosts -Destination $vhostsBak -Force
    Write-Host "Backup created: $vhostsBak"
}

# Read httpd.conf
$content = Get-Content -Path $httpd -Raw -Encoding UTF8

# Uncomment LoadModule rewrite_module line
$content = $content -replace '(?m)^[#\s]*(LoadModule\s+rewrite_module\s+modules/mod_rewrite\.so)', '$1'

# Replace AllowOverride None -> AllowOverride All in htdocs Directory block
$content = $content -replace '(?ms)(<Directory\s+"C:/xampp/htdocs">.*?AllowOverride\s+)None', '$1All'

# If project-specific Directory block is not present, append it
$projectPath = 'C:/xampp/htdocs/healthcare-cms-backend/public'
if ($content -notmatch [regex]::Escape($projectPath)) {
    $projectBlock = @"

<Directory "$projectPath">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
"@
    $content = $content + $projectBlock
    Write-Host "Appended project <Directory> block for $projectPath"
} else {
    Write-Host "Project directory block already present in httpd.conf"
}

# Write modified content back
Set-Content -Path $httpd -Value $content -Encoding UTF8
Write-Host "Updated $httpd"

# Test Apache config
$httpdExe = 'C:\xampp\apache\bin\httpd.exe'
if (-not (Test-Path $httpdExe)) {
    Write-Warning "httpd.exe not found at $httpdExe. Restart Apache via XAMPP Control Panel."
} else {
    Write-Host 'Testing Apache config (httpd -t)...'
    $testOut = & $httpdExe -t 2>&1
    $testOut | ForEach-Object { Write-Host $_ }

    if ($testOut -notmatch 'Syntax OK') {
        Write-Error 'httpd -t failed. Check error.log:'
        if (Test-Path $errorLog) {
            Get-Content -Path $errorLog -Tail 50
        }
        exit 1
    }

    # Restart Apache
    Write-Host 'Restarting Apache...'
    $svc = Get-Service -Name 'Apache2.4' -ErrorAction SilentlyContinue
    if ($null -ne $svc) {
        try {
            & $httpdExe -k restart 2>&1 | ForEach-Object { Write-Host $_ }
        } catch {
            Write-Warning "Restart failed: $($_.Exception.Message)"
        }
    } else {
        Write-Warning "No Apache service detected. Stopping httpd processes and starting manually..."
        Get-Process -Name httpd -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 1
        try {
            Start-Process -FilePath $httpdExe -ArgumentList '-k','start' -NoNewWindow
            Write-Host "Started httpd.exe"
        } catch {
            Write-Warning "Failed to start: $($_.Exception.Message)"
            Write-Warning "Please restart Apache using XAMPP Control Panel."
        }
    }
    Start-Sleep -Seconds 2
}

# Show error log tail
if (Test-Path $errorLog) {
    Write-Host "`nLast 50 lines of error.log:"
    Get-Content -Path $errorLog -Tail 50
}

# Quick HTTP check
$testUrl = 'http://localhost/healthcare-cms-backend/public/p/e2e-playwright-test-slug'
Write-Host "`nTesting public URL: $testUrl"
try {
    $response = Invoke-WebRequest -Uri $testUrl -UseBasicParsing -TimeoutSec 10
    $response.Content | Out-File "$env:TEMP\temp_public_test.html" -Encoding UTF8
    Write-Host "SUCCESS: Request returned $($response.StatusCode)"
    Write-Host "Response preview (first 500 chars):"
    Write-Host $response.Content.Substring(0, [Math]::Min($response.Content.Length, 500))
} catch {
    Write-Host "FAILED: $($_.Exception.Message)"
    $err = $_.Exception
    if ($null -ne $err.Response) {
        $stream = $err.Response.GetResponseStream()
        $reader = New-Object System.IO.StreamReader($stream)
        $body = $reader.ReadToEnd()
        $body | Out-File "$env:TEMP\temp_public_test.html" -Encoding UTF8
        Write-Host "Error response saved. First 500 chars:"
        Write-Host $body.Substring(0, [Math]::Min($body.Length, 500))
    }
}

Write-Host "`nScript finished. Check output above for results."

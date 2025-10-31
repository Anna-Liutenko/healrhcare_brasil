# Use script location to determine repository root (avoids embedding localized paths)
$repo = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $repo

$human = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
$branch = 'tests-cleanup-' + (Get-Date -Format 'yyyyMMdd-HHmmss')
Write-Host "Branch name: $branch"

# Create branch (or switch if exists)
$createOut = git checkout -b $branch 2>&1
Write-Host $createOut
if ($LASTEXITCODE -ne 0) {
    Write-Host "Could not create branch (it may already exist). Attempting to switch to it..."
    git checkout $branch 2>&1 | Write-Host
}

Write-Host "Staging changes..."
git add -A 2>&1 | Write-Host

Write-Host "Committing with timestamp: $human"
$commitOut = git commit -m "Cleanup: remove temporary tests/logs and add PHP test harness - committed at $human" 2>&1
Write-Host $commitOut

Write-Host "Pushing branch to origin: $branch"
$pushOut = git push -u origin $branch 2>&1
Write-Host $pushOut

Write-Host "Script finished."

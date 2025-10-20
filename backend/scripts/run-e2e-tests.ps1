Param(
    [string]$BaseUrl = "http://127.0.0.1:8089",
    [string]$Slug = "testovaya-e2e-run"
)

$script = Join-Path -Path $PSScriptRoot -ChildPath "e2e_publish_and_check.ps1"
if (-not (Test-Path $script)) {
    Write-Error "e2e script not found at $script"
    exit 2
}

Write-Output "Running E2E publish-and-check with BaseUrl=$BaseUrl Slug=$Slug"
& $script -BaseUrl $BaseUrl -Slug $Slug
$exit = $LASTEXITCODE
if ($exit -eq 0) {
    Write-Output "E2E script succeeded."
    exit 0
} else {
    Write-Error "E2E script failed with exit code $exit"
    exit $exit
}

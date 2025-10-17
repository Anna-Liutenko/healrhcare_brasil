param(
  [string]$BaseUrl = 'http://localhost/visual-editor-standalone-test/backend',
  [string]$OutDir = 'backend/tests/tmp'
)

# Try to find msedge or chrome on PATH first, then common Program Files locations.
$edgeCmd = Get-Command msedge -ErrorAction SilentlyContinue
$chromeCmd = Get-Command chrome -ErrorAction SilentlyContinue

$edgePath = if ($edgeCmd) { $edgeCmd.Source } else {
  $candidates = @("C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe","C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe")
  $candidates | Where-Object { Test-Path $_ } | Select-Object -First 1
}

$chromePath = if ($chromeCmd) { $chromeCmd.Source } else {
  $candidates = @("C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe","C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe")
  $candidates | Where-Object { Test-Path $_ } | Select-Object -First 1
}

if (-not (Test-Path $OutDir)) { New-Item -ItemType Directory -Path $OutDir | Out-Null }

function Capture($url, $outFile, $width=1200, $height=900) {
  $full = "$BaseUrl/$url"
  if ($edgePath) {
    & $edgePath --headless --disable-gpu --hide-scrollbars --no-sandbox --window-size=$width,$height --screenshot=$outFile $full
  } elseif ($chromePath) {
    & $chromePath --headless --disable-gpu --hide-scrollbars --no-sandbox --window-size=$width,$height --screenshot=$outFile $full
  } else {
    Write-Error "No msedge or chrome found on PATH or in common install locations. Install Edge/Chrome or add to PATH."
  }
}

# Capture before snapshot (local file served via file://) - but headless browsers often can't open file:// URLs with --screenshot reliably when called like this.
# So capture the live endpoints instead and the side-by-side HTML which references local files.

Capture 'public/index.php?path=/p/test' "$OutDir\page_p_test_live.png"
# For side-by-side, we need to serve the side-by-side HTML via the test htdocs. We'll copy the side-by-side file to test htdocs tmp and capture it.
# Use the script folder as the source for side-by-side HTML
$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Definition
$sbSrc = Join-Path $scriptRoot 'page_p_test_side_by_side.html'
$sbDst = 'C:\\xampp\\htdocs\\visual-editor-standalone-test\\page_p_test_side_by_side.html'
if (Test-Path $sbSrc) {
  Copy-Item -Path $sbSrc -Destination $sbDst -Force
} else {
  Write-Error "Side-by-side source file not found: $sbSrc"
}
Capture 'page_p_test_side_by_side.html' "$OutDir\page_p_test_side_by_side.png" 1600 900

Write-Output "Screenshots saved to: $OutDir"; exit 0

# Convert UPDATE ... SET data = 'json' statements into hex-literal SQL and apply to DB
# Note: do not use Join-Path -Resolve because it errors when the target file doesn't exist.
$in = Join-Path (Split-Path -Parent $MyInvocation.MyCommand.Path) '..\database\seeds\update_blocks_utf8.sql'
$out = Join-Path (Split-Path -Parent $MyInvocation.MyCommand.Path) '..\database\seeds\update_blocks_hex.sql'
Write-Host "Input SQL file: $in"
if (-not (Test-Path -LiteralPath $in)) {
    Write-Error "Input file not found: $in"
    exit 1
}
$bytes = [System.IO.File]::ReadAllBytes($in)
# try decode as UTF8 and check round-trip equality
$utf8 = [System.Text.Encoding]::UTF8
$decodedUtf8 = $utf8.GetString($bytes)
$reencoded = $utf8.GetBytes($decodedUtf8)
if ([Convert]::ToBase64String($reencoded) -eq [Convert]::ToBase64String($bytes)) {
    $content = $decodedUtf8
    Write-Host "Input file detected as UTF-8"
} else {
    # fallback to Windows-1251 (common on Cyrillic Windows)
    $cp1251 = [System.Text.Encoding]::GetEncoding(1251)
    $content = $cp1251.GetString($bytes)
    Write-Host "Input file appears not UTF-8; decoded as Windows-1251"
}

$pattern = "UPDATE\s+blocks\s+SET\s+data\s*=\s*'(?<json>.*?)'\s+WHERE\s+id\s*=\s*'(?<id>[^']+)';"
$regex = [regex]$pattern
$idx = 0
$sb = New-Object System.Text.StringBuilder

$pos = 0
foreach($m in $regex.Matches($content)){
    $start = $m.Index
    $length = $m.Length
    # append text before match
    $sb.Append($content.Substring($pos, $start - $pos)) | Out-Null
    $json = $m.Groups['json'].Value
    $id = $m.Groups['id'].Value
    # compute UTF8 hex
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($json)
    $hex = ($bytes | ForEach-Object { $_.ToString('x2') }) -join ''
    $sb.Append("UPDATE blocks SET data = 0x$hex WHERE id = '$id';`n") | Out-Null
    $pos = $start + $length
    $idx++
}
# append rest
if($pos -lt $content.Length){ $sb.Append($content.Substring($pos)) | Out-Null }

# write out (ensure directory exists)
$outDir = Split-Path -Parent $out
if (-not (Test-Path -LiteralPath $outDir)) { New-Item -ItemType Directory -Path $outDir -Force | Out-Null }
Set-Content -LiteralPath $out -Value $sb.ToString() -Encoding UTF8
Write-Host "Wrote $idx updates to $out"

# apply to DB
Write-Host "Applying $out to DB via mysql client"
Get-Content -Raw -Encoding UTF8 -LiteralPath $out | & 'C:\xampp\mysql\bin\mysql.exe' --default-character-set=utf8mb4 -u root healthcare_cms

Write-Host "Done. Verifying samples: "
& 'C:\xampp\mysql\bin\mysql.exe' --default-character-set=utf8mb4 -u root healthcare_cms -e "SELECT id, page_id, type, LEFT(data,200) as sample FROM blocks WHERE page_id IN ('a1b2c3d4-e5f6-7890-abcd-ef1234567891','b2c3d4e5-f6g7-8901-bcde-f23456789012','c3d4e5f6-g7h8-9012-cdef-345678901234','d4e5f6g7-h8i9-0123-def0-456789012345') ORDER BY page_id, position;"

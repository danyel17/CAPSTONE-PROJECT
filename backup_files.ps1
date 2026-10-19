# Simple Backup Script for Market Clearance System
# Run from the project root (same folder as this script), or: powershell -File backup_files.ps1

$root = $PSScriptRoot
if (-not $root) { $root = Get-Location }
Set-Location $root

$timestamp = Get-Date -Format 'yyyy-MM-dd_HH-mm-ss'
$backupFolder = Join-Path $root "backup_$timestamp"

Write-Host "Creating backup folder: $backupFolder" -ForegroundColor Green
New-Item -ItemType Directory -Path $backupFolder -Force | Out-Null

Write-Host "Backing up PHP files..." -ForegroundColor Yellow
Get-ChildItem -Path $root -Filter "*.php" -File -ErrorAction SilentlyContinue | ForEach-Object {
    Copy-Item -LiteralPath $_.FullName -Destination $backupFolder -Force
}

Write-Host "Backing up CSS files..." -ForegroundColor Yellow
Get-ChildItem -Path $root -Filter "*.css" -File -ErrorAction SilentlyContinue | ForEach-Object {
    Copy-Item -LiteralPath $_.FullName -Destination $backupFolder -Force
}

Write-Host "Backing up JavaScript files (root)..." -ForegroundColor Yellow
Get-ChildItem -Path $root -Filter "*.js" -File -ErrorAction SilentlyContinue | ForEach-Object {
    Copy-Item -LiteralPath $_.FullName -Destination $backupFolder -Force
}

Write-Host "Backing up JSON files..." -ForegroundColor Yellow
Get-ChildItem -Path $root -Filter "*.json" -File -ErrorAction SilentlyContinue | ForEach-Object {
    Copy-Item -LiteralPath $_.FullName -Destination $backupFolder -Force
}

# Root-level images referenced by index.php and similar (not inside assets/)
$rootImageNames = @('announcementicon.jpg', 'customerservice.jpg')
foreach ($imgName in $rootImageNames) {
    $imgPath = Join-Path $root $imgName
    if (Test-Path -LiteralPath $imgPath) {
        Write-Host "Backing up root image: $imgName ..." -ForegroundColor Yellow
        Copy-Item -LiteralPath $imgPath -Destination $backupFolder -Force
    }
}

$folders = @('assets', 'includes', 'mapbox-gl-js-main', 'cam-no-map-master', 'cesium-main')
foreach ($name in $folders) {
    $src = Join-Path $root $name
    if (Test-Path $src) {
        Write-Host "Backing up folder: $name ..." -ForegroundColor Yellow
        Copy-Item -LiteralPath $src -Destination (Join-Path $backupFolder $name) -Recurse -Force
    }
}

$fileCount = (Get-ChildItem -Path $backupFolder -File -Recurse -ErrorAction SilentlyContinue).Count
Write-Host "`nBackup completed successfully!" -ForegroundColor Green
Write-Host "Backup location: $backupFolder" -ForegroundColor Cyan
Write-Host "Total files backed up: $fileCount" -ForegroundColor Cyan

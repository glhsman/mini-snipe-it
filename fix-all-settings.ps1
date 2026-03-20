#!/usr/bin/env pwsh

$settingsRequire = "require_once __DIR__ . '/../src/Helpers/Settings.php';" + "`n"

$files = @(
    'asset_add.php', 'asset_batch_add.php', 'asset_bookings.php', 'asset_checkin.php',
    'asset_checkout.php', 'asset_delete.php', 'asset_edit.php', 'asset_request_update.php',
    'asset_requests.php', 'assets.php', 'category_create.php', 'category_delete.php',
    'category_edit.php', 'forgot_password.php', 'import_assets.php', 'import_locations.php',
    'import_users.php', 'index.php', 'location_create.php', 'location_delete.php',
    'location_edit.php', 'locations.php', 'login.php', 'lookup_action.php',
    'manufacturer_create.php', 'manufacturer_delete.php', 'manufacturer_edit.php',
    'model_create.php', 'model_delete.php', 'model_edit.php', 'profile.php',
    'reset_password.php', 'settings_general.php', 'settings.php', 'temp_set_status_ausgegeben.php',
    'user_create.php', 'user_delete.php', 'user_edit.php', 'user_protocol.php', 'users.php'
)

$count = 0
foreach ($file in $files) {
    $filePath = "public\$file"
    if (-not (Test-Path $filePath)) { continue }
    
    $lines = @(Get-Content $filePath)
    
    # Überspringe wenn bereits vorhanden
    $hasSettingsRequire = $false
    foreach ($line in $lines) {
        if ($line -match "Settings\.php") { $hasSettingsRequire = $true; break }
    }
    
    if ($hasSettingsRequire) { 
        Write-Host "[SKIP] ${file}: bereits vorhanden"
        continue 
    }
    
    # Überspringe wenn nicht verwendet
    $content = $lines -join "`n"
    if ($content -notmatch "Settings::") { 
        Write-Host "[SKIP] ${file}: Settings wird nicht verwendet"
        continue 
    }
    
    # Finde die Zeile mit dem letzten require_once
    $lastRequireIdx = -1
    for ($i = 0; $i -lt $lines.Count; $i++) {
        if ($lines[$i] -match "require_once") {
            $lastRequireIdx = $i
        }
    }
    
    if ($lastRequireIdx -ge 0) {
        # Füge nach dieser Zeile ein
        [System.Collections.ArrayList]$newLines = $lines
        $newLines.Insert($lastRequireIdx + 1, $settingsRequire)
        $newContent = ($newLines -join "`n") + "`n"
        Set-Content -Path $filePath -Value $newContent -Encoding UTF8 -NoNewline
        Write-Host "[FIXED] ${file}"
        $count++
    }
}

Write-Host ""
Write-Host "[DONE] $count Dateien repariert"

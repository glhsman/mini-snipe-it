#!/usr/bin/env pwsh

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

foreach ($file in $files) {
    $filePath = "public\$file"
    if (-not (Test-Path $filePath)) { continue }
    
    $content = Get-Content $filePath -Raw -Encoding UTF8
    
    # Skip wenn bereits vorhanden
    if ($content -match "require_once.*Settings\.php") {
        Write-Host "[OK] ${file}: require_once existiert bereits"
        continue
    }
    
    # Nur reparieren wenn use App\Helpers\Settings vorhanden ist
    if ($content -notmatch "use App\\Helpers\\Settings") { continue }
    
    # Ersetze: das letzte require_once (before use statements) durch require_once + Settings.php require
    $content = $content -replace '((?:require_once[^\n]*\n)*)(\s*use)', "require_once __DIR__ . '/../src/Helpers/Settings.php';" + "`n`$1`$2"
    
    Set-Content -Path $filePath -Value $content -Encoding UTF8 -NoNewline
    Write-Host "[FIXED] ${file}"
}

Write-Host "`n[DONE]"

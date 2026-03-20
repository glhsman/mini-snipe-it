#!/usr/bin/env pwsh
<#
.SYNOPSIS
    Repariert alle PHP-Dateien - fügt require_once für Settings.php hinzu
#>

$publicDir = 'public'
$files = Get-ChildItem -Path $publicDir -Name '*.php' -Exclude @('*.bak', '*.csv.bak', '*.dup.bak', '*.headerfix.bak', '*.vorgesetzterfix.bak')

foreach ($file in $files) {
    $filePath = "$publicDir\$file"
    $content = Get-Content -Path $filePath -Raw -Encoding UTF8
    $originalContent = $content
    
    # Nur reparieren, wenn Settings verwendet wird, aber require_once fehlt
    if ($content -match "use App\\Helpers\\Settings" -and $content -notmatch "require_once.*Settings\.php") {
        # Füge require_once nach dem letzten require_once hinzu
        $content = $content -replace "(require_once.*?\n)(\n*use )", "`$1require_once __DIR__ . '/../src/Helpers/Settings.php';" + "`n`$2"
        Write-Host "[FIXED] ${file}: require_once hinzugefuegt"
    }
    
    if ($content -ne $originalContent) {
        Set-Content -Path $filePath -Value $content -Encoding UTF8 -NoNewline
        Write-Host "[SAVED] ${file}"
    }
}

Write-Host ""
Write-Host "[DONE] Alle Dateien repariert!"

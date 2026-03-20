#!/usr/bin/env pwsh
<#
.SYNOPSIS
    Automatisches Update aller PHP-Dateien auf dynamische Browser-Tab-Titel
.DESCRIPTION
    Ersetzt alle hardcodierten "Mini-Snipe" Titel durch dynamische Titel, die
    den site_name aus den Settings nutzen.
.EXAMPLE
    .\update-titles.ps1
#>

$publicDir = 'public'
$files = Get-ChildItem -Path $publicDir -Name '*.php' -Exclude @('*.bak', '*.csv.bak', '*.dup.bak', '*.headerfix.bak', '*.vorgesetzterfix.bak')

foreach ($file in $files) {
    $filePath = "$publicDir\$file"
    $content = Get-Content -Path $filePath -Raw -Encoding UTF8
    $originalContent = $content

    # 1. Füge Settings Helper require_once am Anfang hinzu (falls nicht bereits vorhanden)
    if ($content -match "require_once.*Auth\.php" -and $content -notmatch "require_once.*Settings\.php") {
        $content = $content -replace "(require_once.*Helpers/Auth\.php[^\n]*\n)", "`$1require_once __DIR__ . '/../src/Helpers/Settings.php';" + "`n"
    }

    # 2. Füge Settings use-Statement hinzu (falls nicht bereits vorhanden)
    if ($content -match "use App\\Helpers\\Auth;" -and $content -notmatch "use App\\Helpers\\Settings;") {
        $content = $content -replace "(use App\\Helpers\\Auth;)", "`$1`nuse App\Helpers\Settings;"
    }

    # 3. Füge Settings::load() hinzu (falls nicht bereits vorhanden)
    if ($content -match '\$db\s*=\s*Database::getInstance\(\)' -and $content -notmatch 'Settings::load') {
        $content = $content -replace '(\$db\s*=\s*Database::getInstance\(\);)', "`$1`nSettings::load(`$db);"
    }

    # 4. Ersetze Title-Tags - verschiedene Muster
    $patterns = @(
        @{ pattern = '<title>Assets - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Assets"); ?></title>' },
        @{ pattern = '<title>Asset anlegen - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Asset anlegen"); ?></title>' },
        @{ pattern = '<title>Massen-Einbuchung - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Massen-Einbuchung"); ?></title>' },
        @{ pattern = '<title>Buchungen - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Buchungen"); ?></title>' },
        @{ pattern = '<title>Asset ausgeben - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Asset ausgeben"); ?></title>' },
        @{ pattern = '<title>Asset erhalten - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Asset erhalten"); ?></title>' },
        @{ pattern = '<title>Asset bearbeiten - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Asset bearbeiten"); ?></title>' },
        @{ pattern = '<title>Anforderungen - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Anforderungen"); ?></title>' },
        @{ pattern = '<title>Geraet anfordern - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Geraet anfordern"); ?></title>' },
        @{ pattern = '<title>Standorte - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Standorte"); ?></title>' },
        @{ pattern = '<title>Standort anlegen - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Standort anlegen"); ?></title>' },
        @{ pattern = '<title>Standort bearbeiten - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Standort bearbeiten"); ?></title>' },
        @{ pattern = '<title>Benutzer - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Benutzer"); ?></title>' },
        @{ pattern = '<title>Benutzer anlegen - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Benutzer anlegen"); ?></title>' },
        @{ pattern = '<title>Benutzer bearbeiten - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Benutzer bearbeiten"); ?></title>' },
        @{ pattern = '<title>Profil & Einstellungen - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Profil & Einstellungen"); ?></title>' },
        @{ pattern = '<title>Globale Einstellungen - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Globale Einstellungen"); ?></title>' },
        @{ pattern = '<title>Login - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Login"); ?></title>' },
        @{ pattern = '<title>Passwort vergessen - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Passwort vergessen"); ?></title>' },
        @{ pattern = '<title>Kategorie anlegen - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Kategorie anlegen"); ?></title>' },
        @{ pattern = '<title>Kategorie bearbeiten - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Kategorie bearbeiten"); ?></title>' },
        @{ pattern = '<title>Hersteller anlegen - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Hersteller anlegen"); ?></title>' },
        @{ pattern = '<title>Hersteller bearbeiten - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Hersteller bearbeiten"); ?></title>' },
        @{ pattern = '<title>Asset-Modell anlegen - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Asset-Modell anlegen"); ?></title>' },
        @{ pattern = '<title>Asset-Modell bearbeiten - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Asset-Modell bearbeiten"); ?></title>' },
        @{ pattern = '<title>Assets importieren - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Assets importieren"); ?></title>' },
        @{ pattern = '<title>Standorte importieren - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Standorte importieren"); ?></title>' },
        @{ pattern = '<title>Benutzer importieren - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Benutzer importieren"); ?></title>' },
        @{ pattern = '<title>Asset-Anforderungen - Mini-Snipe</title>'; replacement = '<title><?php echo Settings::getPageTitle("Asset-Anforderungen"); ?></title>' }
    )

    foreach ($item in $patterns) {
        if ($content -match [regex]::Escape($item.pattern)) {
            $content = $content -replace [regex]::Escape($item.pattern), $item.replacement
            Write-Host "[OK] ${file}: Title aktualisiert"
        }
    }

    # Speichere nur, wenn Änderungen vorhanden sind
    if ($content -ne $originalContent) {
        Set-Content -Path $filePath -Value $content -Encoding UTF8 -NoNewline
        Write-Host "  [SAVE] ${file} gespeichert"
    }
}

Write-Host ""
Write-Host "[DONE] Fertig! Browser-Tab-Titel auf allen Seiten werden nun dynamisch angezeigt."

# Mini-Snipe IT

Eine vereinfachte, leichtgewichtige Version von Snipe-IT für das Asset-Management. Entwickelt mit PHP und MySQL/MariaDB für den Betrieb auf XAMPP.

## Features

-   **Dashboard**: Schneller Überblick über Gesamtzahl, zugewiesene und verfügbare Assets sowie detaillierte Status-Zähler (z.B. "Einsatzbereit", "Ausgegeben").
-   **Asset-Management**: Anlegen, Bearbeiten und Löschen von Assets mit Seriennummern, Kaufdatum und Notizen.
-   **Automatische Asset-Tags**: Generierung von Tags basierend auf Standort- und Kategorie-Kürzeln (z.B. `MUNB0001`).
-   **Stammdatenverwaltung**:
    -   Standorte (Locations) mit Kürzeln
    -   Kategorien (Categories) mit Kürzeln (z.B. Notebooks -> NB)
    -   Hersteller (Manufacturers)
    -   Asset-Modelle (Asset Models)
-   **Benutzerverwaltung**: Rollenbasiertes System (Admin, Editor, User) mit wählbarer Rolle und optionalem Web-Login bei der Erstellung.
-   **Asset Check-in / Check-out**: Assets direkt aus Dashboard und Assetliste einem Benutzer zuweisen (Check-out) oder die Zuweisung aufheben (Check-in).
    -   Check-out nur fuer einsatzbereite Assets verfuegbar (UI + serverseitige Pruefung)
-   **Asset-Suche & Filter**: Freitextsuche nach Asset-Tag, Seriennummer und Name sowie Filterung nach Asset-Modell in der Assetliste.
-   **Session-Sicherheit**: Automatischer Logout nach 2 Stunden Inaktivitaet.
-   **Hardware-Anforderungen**: Bei Statuswechsel auf *In Arbeit* oder *Abgelehnt* wird der anfordernde Benutzer automatisch per E-Mail informiert (falls gueltige E-Mail hinterlegt).
-   **Profilverwaltung & Designs**:
    -   **Dark & Light Mode**: Modernes Design mit standardmäßigem Dark Mode, umschaltbar auf Light Mode.
    -   **Profilseite**: Benutzer können ihr eigenes Passwort ändern und das Design-Theme auswählen.
-   **CSV-Importcenter in den Einstellungen**:
    -   Import für Standorte, Benutzer und Assets
    -   Robuste Imports mit Update-Logik bei Duplikaten (Matchen über Seriennummer/Tag oder Username)
-   **Pagination in Listenansichten**:
    -   Asset-Verwaltung: 25/50/100/250 pro Seite
    -   Benutzer-Verwaltung: 25/50/100/250 pro Seite

## Voraussetzungen

-   **XAMPP** (mit PHP 8.x und MySQL/MariaDB)
-   **Optional für erweiterten SMTP-Test**: Composer (für PHPMailer)

## Installation & Setup

1.  **Dateien kopieren**: Kopieren Sie das Repository in Ihr `htdocs`-Verzeichnis (z.B. `C:\xampp\htdocs\minisnipeit`).
2.  **Datenbank erstellen**:
    -   Erstellen Sie eine leere Datenbank (z.B. `snipeit`).
3.  **Setup-Assistent starten**:
    -   Öffnen Sie `http://localhost/minisnipeit/public/setup.php`.
    -   Tragen Sie Host, Datenbankname, Benutzer und Passwort ein.
    -   Der Assistent führt `database.sql` automatisch aus und erstellt die `.env`.
4.  **Anmelden**:
    -   Startseite: `http://localhost/minisnipeit/public/`.

### Optional: PHPMailer installieren (empfohlen)

Für den E-Mail-Test in `Einstellungen -> Sendmail-Konfiguration` wird die SMTP-Konfiguration aus der `.env` im Projekt-Root gelesen. Bevorzugt wird **PHPMailer** genutzt, wenn es installiert ist.

1.  In das Projektverzeichnis wechseln (z.B. `D:\xampp\htdocs\minisnipeit`)
2.  Abhängigkeit installieren:

```bash
composer require phpmailer/phpmailer
```

3.  Sicherstellen, dass `vendor/autoload.php` vorhanden ist

Ohne PHPMailer verwendet die Anwendung einen direkten SMTP-Fallback. PHPMailer ist jedoch robuster bei TLS/Authentifizierung und liefert verständlichere Fehlermeldungen.

### Hinweis zu SMTP-Zertifikaten

Wenn der SMTP-Host nicht zum Zertifikat passt, schlägt die TLS-Verbindung fehl (z.B. Zertifikat zeigt auf einen anderen CN).

-   In diesem Fall den Hostnamen verwenden, der zum Zertifikat passt
-   Oder den Provider um ein passendes Zertifikat für den gewünschten Host bitten

## Update bestehender Installationen

Wenn Sie eine ältere Version aktualisieren möchten, führen Sie die folgende Migrationsdatei auf Ihrer Datenbank aus (z.B. via phpMyAdmin oder CLI):

-   `db_migration.sql`

Hinweis: Fuer die zuletzt ergaenzten Funktionen (Inaktivitaets-Timeout, Check-out-Statuslogik, E-Mail-Benachrichtigung bei Anforderungen) sind keine neuen SQL-Tabellen oder Spalten notwendig.

### Empfohlener Rollout (Produktiv)

Für produktive Systeme wird folgende Reihenfolge empfohlen:

1.  **Backup erstellen**
    -   Vor dem Update immer einen vollständigen Datenbank-Dump erstellen.
2.  **Migration ausführen**
    -   Datei: `db_migration.sql`
3.  **Verifikation ausführen**
    -   Datei: `db_verify.sql`
4.  **Ergebnisse prüfen**
    -   Kritische Checks sollten `OK` liefern.
    -   Problemzähler sollten idealerweise `0` sein.
5.  **Funktionstest durchführen**
    -   Ausgabeprotokoll für einen Benutzer erstellen.
    -   Mehrere Assets per Checkbox auswählen und Sammel-Rückgabe ausführen.
    -   Rückgabeprotokoll prüfen (Auswahl korrekt, Drucklayout korrekt).

### Erwartete Verify-Ergebnisse

Nach `db_verify.sql` sollten insbesondere folgende Prüfungen auf `OK` stehen:

-   Tabelle `settings` vorhanden
-   Tabelle `assets` vorhanden
-   Tabelle `asset_assignments` vorhanden
-   Spalte `settings.company_address` vorhanden
-   Spalte `settings.protocol_header_text` vorhanden
-   Spalte `settings.protocol_footer_text` vorhanden
-   Spalte `assets.os_version` ist `INT`

Diese Checks sollten idealerweise `0` liefern:

-   Kategorien mit leerem/NULL-Kürzel
-   Doppelte Kategorien-Kürzel
-   Benutzer ohne Username
-   Benutzer mit ungültiger Rolle
-   Benutzer mit ungültigem `can_login`
-   Benutzer mit `can_login=1` aber ohne Passwort
-   Benutzer mit nicht existierendem Standort
-   Offene Zuordnungen ohne aktuell zugewiesenes Asset
-   Assets mit mehr als einer offenen Zuordnung

## CSV-Import: Empfohlene Reihenfolge

Damit Zuordnungen zwischen Standorten, Benutzern und Assets korrekt aufgelöst werden, müssen die Dateien in dieser Reihenfolge importiert werden:

1.  **Standorte**
2.  **Benutzer**
3.  **Assets**

*Hinweis*: Die erwartete Spaltenstruktur für die CSVs wird direkt in den Import-Karten im Menü *Einstellungen* angezeigt.

## Standard-Anmeldedaten (Seed)

-   **Benutzer**: `admin`
-   **Passwort**: `password`

## Lizenz

Dieses Projekt steht unter der MIT-Lizenz.

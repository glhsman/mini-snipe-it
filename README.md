# Mini-Snipe IT

Eine vereinfachte, leichtgewichtige Version von Snipe-IT für das Asset-Management. Entwickelt mit PHP und MySQL/MariaDB für den Betrieb auf XAMPP.

## Features

-   **Dashboard**: Schneller Überblick über Gesamtzahl, zugewiesene und verfügbare Assets.
-   **Asset-Management**: Anlegen, Bearbeiten und Löschen von Assets mit Seriennummern, Kaufdatum und Notizen.
-   **Automatische Asset-Tags**: Generierung von Tags basierend auf Standort- und Kategorie-Kürzeln (z.B. `MUNB0001`).
-   **Stammdatenverwaltung**:
    -   Standorte (Locations) mit Kürzeln
    -   Kategorien (Categories) mit Kürzeln (z.B. Notebooks -> NB)
    -   Hersteller (Manufacturers)
    -   Asset-Modelle (Asset Models)
-   **Benutzerverwaltung**: Rollenbasiertes System (Admin, Editor, User) mit wählbarer Rolle und optionalem Web-Login bei der Erstellung.
-   **Asset Check-in / Check-out**: Assets direkt aus Dashboard und Assetliste einem Benutzer zuweisen (Check-out) oder die Zuweisung aufheben (Check-in).
-   **Asset-Suche & Filter**: Freitextsuche nach Asset-Tag, Seriennummer und Name sowie Filterung nach Asset-Modell in der Assetliste.
-   **CSV-Importcenter in den Einstellungen**:
    -   Import fuer Standorte, Benutzer und Assets
    -   Download von Musterdateien (`sample_locations.csv`, `sample_users.csv`, `sample_assets.csv`)
    -   Importberichte mit Erfolg/Fehler/Uebersprungen
-   **Robuste CSV-Imports**:
    -   Asset-Import mit Seriennummer und Benutzerzuordnung per `assigned_username`
    -   Duplikate bei Asset-Tags werden als "uebersprungen" behandelt (kein Abbruch)
    -   Standort-Import ueberspringt bereits vorhandene Standorte
    -   User-Import setzt `can_login` standardmaessig auf `0` (Web-Login deaktiviert)
-   **Pagination in Listenansichten**:
    -   Asset-Verwaltung: 25/50/100/250 pro Seite
    -   Benutzer-Verwaltung: 25/50/100/250 pro Seite

## Voraussetzungen

-   **XAMPP** (mit PHP 8.x und MySQL/MariaDB)

## Installation & Setup

1.  **Dateien kopieren**: Kopieren Sie das Repository in Ihr `htdocs`-Verzeichnis (z.B. `C:\xampp\htdocs\minisnipeit`).
2.  **Datenbank erstellen**:
    -   Erstellen Sie eine leere Datenbank (z.B. `snipeit`).
3.  **Setup-Assistent starten**:
    -   Öffnen Sie `http://localhost/minisnipeit/public/setup.php`.
    -   Tragen Sie Host, Datenbankname, Benutzer und Passwort ein.
    -   Der Assistent führt `database.sql` automatisch aus und erstellt die `.env`.
4.  **Anmelden**:
    -   Nach erfolgreichem Setup: `http://localhost/minisnipeit/public/`.

## Update Bestehender Installationen

Wenn Ihre Installation älter ist und beim Anlegen von Kategorien/Usern Fehler auftreten, führen Sie folgende Skripte auf der bestehenden Datenbank aus:

1.  **Schema-Nachmigration**:
    -   `db_migration.sql`
    -   Ergänzt fehlende Spalte `categories.kuerzel` und aktualisiert bestehende Kategorie-Daten.
2.  **Verifikation**:
    -   `db_verify.sql`
    -   Prüft Schema und Datenqualität (u.a. `categories.kuerzel`, `users.role`, `users.location_id`, `users.can_login`).

Beispielausführung per MySQL-CLI:

```bash
mysql -h <host> -u <user> -p <database> < db_migration.sql
mysql -h <host> -u <user> -p <database> < db_verify.sql
```

Hinweis:
-   In der Benutzerverwaltung führt der Button **"Benutzer anlegen"** auf `user_create.php`.
-   Kategorien verwenden ein 2-stelliges Kürzel (`kuerzel`) für die Asset-Tag-Generierung.
-   Bei Benutzern steuert die Checkbox **"Web-Login erlauben"**, ob eine Anmeldung möglich ist.
-   Ist Web-Login aktiviert, ist ein Passwort Pflicht. Ist Web-Login deaktiviert, ist kein Passwort erforderlich.

## CSV-Import: Empfohlene Reihenfolge

Damit Zuordnungen bei Benutzer/Assets korrekt aufgeloest werden, sollten CSVs in dieser Reihenfolge importiert werden:

1.  **Standorte** (`import_locations.php`)
2.  **Benutzer** (`import_users.php`)
3.  **Assets** (`import_assets.php`)

### Erwartete CSV-Spalten

-   **Standorte**: `name;address;city;kuerzel`
-   **Benutzer**: `username;email;first_name;last_name;location_name`
-   **Assets**: `asset_tag;name;serial;model_name;manufacturer_name;category_name;status_name;location_name;assigned_username;assigned_first_name;assigned_last_name`

Hinweise zum Asset-Import:
-   `assigned_username` wird fuer die Zuordnung verwendet.
-   `assigned_first_name` und `assigned_last_name` sind informational (kein Lookup).
-   Bei erneutem Import bereits vorhandener Asset-Tags werden diese Eintraege uebersprungen.

## Troubleshooting

-   **Fehler:** `fgetcsv(): the $escape parameter must be provided`
    **Lösung:** Projektstand mit den aktuellen Importskripten verwenden. In den Importern wird `fgetcsv(..., '"', "")` genutzt (kompatibel mit aktuellen PHP-Deprecation-Hinweisen).

-   **Fehler:** `SQLSTATE[42S22]: Column not found: Unknown column 'kuerzel' in 'field list'`
    **Lösung:** `db_migration.sql` ausführen oder manuell:
    ```sql
    ALTER TABLE categories ADD COLUMN kuerzel VARCHAR(2) NULL AFTER name;
    UPDATE categories SET kuerzel = UPPER(LEFT(TRIM(name), 2)) WHERE kuerzel IS NULL OR kuerzel = '';
    ```

-   **Fehler:** Kategorien lassen sich nicht anlegen
    **Prüfung:** `db_verify.sql` ausführen und auf den Check `Spalte categories.kuerzel vorhanden` achten.

-   **Fehler:** User können nicht angelegt werden
    **Prüfung 1:** In `public/users.php` muss der Button "Benutzer anlegen" auf `user_create.php` verlinken.
    **Prüfung 2:** In der DB müssen `users.role` und `users.location_id` vorhanden sein (via `db_verify.sql`).

-   **Fehler:** Setup schlägt bei Neuinstallation fehl
    **Lösung:** Sicherstellen, dass die Ziel-Datenbank bereits existiert und über `public/setup.php` eingerichtet wird.

-   **Fehler:** Login mit Standarddaten funktioniert nicht
    **Lösung:** Prüfen, ob der Admin-Seed in `database.sql` erfolgreich importiert wurde (`username = admin`).

## Standard-Anmeldedaten

-   **Benutzer**: `admin`
-   **Passwort**: `password`

## Lizenz

Dieses Projekt steht unter der MIT-Lizenz.

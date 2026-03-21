# Mini-Snipe IT

Eine vereinfachte, leichtgewichtige Version von Snipe-IT für das Asset-Management. Entwickelt mit PHP und MySQL/MariaDB für den Betrieb auf XAMPP.

## Features

-   **Dashboard**: Schneller Überblick über Gesamtzahl, zugewiesene und verfügbare Assets sowie detaillierte Status-Zähler (z.B. "Einsatzbereit", "Ausgegeben").
-   **Asset-Management**: Anlegen, Bearbeiten und Löschen von Assets mit Seriennummern, Kaufdatum und Notizen.
    -   Bei Modellen ohne Seriennummer-Pflicht kann statt Seriennummer eine Stückzahl erfasst werden; eindeutige NA-Seriennummern werden serverseitig erzeugt.
-   **Automatische Asset-Tags**: Generierung von Tags basierend auf Standort- und Kategorie-Kürzeln (z.B. `MUNB0001`).
-   **Stammdatenverwaltung**:
    -   Standorte (Locations) mit Kürzeln
    -   Kategorien (Categories) mit Kürzeln (z.B. Notebooks -> NB)
    -   Hersteller (Manufacturers)
    -   Asset-Modelle (Asset Models)
-   **Benutzerverwaltung**: Rollenbasiertes System (Admin, Editor, User) mit wählbarer Rolle und optionalem Web-Login bei der Erstellung.
-   **Asset Check-in / Check-out**: Assets direkt aus Dashboard und Assetliste einem Benutzer zuweisen (Check-out) oder die Zuweisung aufheben (Check-in).
    -   Check-out nur fuer einsatzbereite Assets verfuegbar (UI + serverseitige Pruefung)
-   **Buchungs- und Aktivitätsprotokolle**:
    -   Login-Protokoll mit Bereinigung (Anzahl der neuesten Einträge behalten)
    -   Asset-Umbuchungsprotokoll (Ausgabe/Rückgabe aus `asset_assignments`) mit eigener Bereinigungsfunktion
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

Fuer eine erfolgreiche Neuinstallation auf einem leeren Zielsystem werden folgende Punkte vorausgesetzt:

-   **PHP 8.x**
-   **MySQL oder MariaDB**
-   **Webserver** wie Apache, z.B. ueber XAMPP
-   **PHP-Module**:
    -   `PDO`
    -   `pdo_mysql`
-   **Projektstruktur auf dem Zielsystem**:
    -   Ordner `config`
    -   Ordner `public`
    -   Ordner `src`
    -   Ordner `vendor`
-   **Schutzdateien fuer Apache**:
    -   `.htaccess` im Projekt-Root
    -   `.htaccess` in `config/`
    -   `.htaccess` in `src/`
-   **Dateien im Projekt-Root**:
    -   `database.sql`
    -   `db_migration.sql`
    -   `db_verify.sql`
    -   optional `.env.example`
-   **Schreibrechte** im Projektverzeichnis, damit die Datei `.env` angelegt werden kann
-   **Leere Datenbank** sowie ein Benutzer mit Rechten auf diese Datenbank
-   **Optional fuer erweiterten SMTP-Test oder Nachinstallation von Abhaengigkeiten**: Composer

Wichtige Hinweise:

-   Wenn beim Setup `could not find driver` erscheint, fehlt in der Regel `pdo_mysql`
-   Maildaten sind fuer die Erstinstallation nicht erforderlich
-   Bei Apache/XAMPP sollten die vorhandenen `.htaccess`-Dateien mitkopiert werden, damit sensible Bereiche und Dateien geschuetzt bleiben

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

### Detaillierte Installationsanleitung fuer ein leeres Zielsystem

Die Kurzfassung oben reicht fuer bestehende XAMPP-Setups oft aus. Fuer ein komplett neues Test- oder Zielsystem sollte die Installation in dieser Reihenfolge erfolgen.

#### 1. Projektdateien vollstaendig kopieren

Folgende Ordner muessen vorhanden sein:

-   `config`
-   `public`
-   `src`
-   `vendor`

Zusätzlich sollten im Projekt-Root mindestens diese Dateien vorhanden sein:

-   `database.sql`
-   `db_migration.sql`
-   `db_verify.sql`
-   optional `.env.example`


#### 2. PHP-Voraussetzungen pruefen

Erforderlich sind insbesondere diese PHP-Module:

-   `PDO`
-   `pdo_mysql`

Pruefung per CLI:

```bash
php -m
```

Wenn beim Setup der Fehler `could not find driver` erscheint, sind in der Regel nicht die Zugangsdaten falsch, sondern `pdo_mysql` fehlt oder ist nicht aktiviert.

Bei XAMPP die `php.ini` pruefen:

```ini
extension=pdo_mysql
```

Danach Apache bzw. PHP neu starten.

#### 3. Datenbank vorbereiten

-   Eine leere Datenbank anlegen, z.B. `minisnipeit`
-   Einen Benutzer mit Rechten auf diese Datenbank anlegen
-   Host, Datenbankname, Benutzer und Passwort notieren

#### 4. Schreibrechte sicherstellen

Das Projekt muss im Root-Verzeichnis die Datei `.env` schreiben duerfen. Wenn der Webserver dort keine Schreibrechte hat, kann das Setup die Konfiguration nicht speichern.

#### 5. Setup-Assistent aufrufen

Im Browser:

```text
http://localhost/minisnipeit/public/setup.php
```

Dann:

1.  DB Host eintragen
2.  DB Name eintragen
3.  DB User eintragen
4.  DB Passwort eintragen
5.  Mail-Felder nur dann befuellen, wenn SMTP wirklich genutzt werden soll

#### 6. Setup-Schritte durchlaufen

Der Assistent arbeitet in drei Schritten:

1.  Verbindung testen
2.  Tabellen und Vorbelegungen anlegen
3.  `.env` speichern

#### 7. Mail ist optional

SMTP-Daten sind keine Pflicht fuer die Erstinstallation.

-   Wenn kein Mailversand genutzt wird, alle Mail-Felder leer lassen
-   Erst wenn Mail konfiguriert werden soll, muessen Host und numerischer Port korrekt gesetzt sein

#### 8. Login nach erfolgreicher Installation

Nach erfolgreichem Setup:

-   `http://localhost/minisnipeit/public/login.php`

oder:

-   `http://localhost/minisnipeit/public/`

#### 9. Typische Fehler bei Neuinstallationen

**Fehler:** `Datenbankverbindung fehlgeschlagen: could not find driver`

Bedeutung:

-   `pdo_mysql` ist auf dem Zielsystem nicht installiert oder nicht aktiviert

**Fehler:** Zugangsdaten funktionieren extern, aber im Setup nicht

Typische Ursachen:

-   `pdo_mysql` fehlt
-   falscher Hostname aus Sicht des Webservers
-   Firewall / DNS / Container-Netzwerk

**Fehler:** `.env` kann nicht geschrieben werden

Ursache:

-   fehlende Schreibrechte im Projektverzeichnis

#### 10. Installation ohne mitkopiertes `vendor`

Wenn `vendor/` nicht mitkopiert wurde, muessen die PHP-Abhaengigkeiten auf dem Zielsystem installiert werden:

```bash
composer install
```

Danach pruefen, ob `vendor/autoload.php` vorhanden ist.

### Hinweis zu SMTP-Zertifikaten

Wenn der SMTP-Host nicht zum Zertifikat passt, schlägt die TLS-Verbindung fehl (z.B. Zertifikat zeigt auf einen anderen CN).

-   In diesem Fall den Hostnamen verwenden, der zum Zertifikat passt
-   Oder den Provider um ein passendes Zertifikat für den gewünschten Host bitten

## Update bestehender Installationen

Wenn Sie eine ältere Version aktualisieren möchten, führen Sie die folgende Migrationsdatei auf Ihrer Datenbank aus (z.B. via phpMyAdmin oder CLI):

-   `db_migration.sql`

Hinweis: Der aktuelle Schema-Stand fuer neue und bestehende Installationen ist in `database.sql`, `db_migration.sql` und `db_verify.sql` abgebildet. Bei Updates immer `db_migration.sql` und anschließend `db_verify.sql` ausführen.

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

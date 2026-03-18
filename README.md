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
    -   Robuste Imports mit Update-Logik bei Duplikaten (Matchen über Serial/Tag oder Username)
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
    -   Startseite: `http://localhost/minisnipeit/public/`.

## Update bestehender Installationen

Wenn Sie eine ältere Version aktualisieren möchten, führen Sie die folgende Migrationsdatei auf Ihrer Datenbank aus (z.B. via phpMyAdmin oder CLI):

-   `db_migration.sql`

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

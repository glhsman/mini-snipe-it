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
-   **Benutzerverwaltung**: Rollenbasiertes System (Admin, Editor, User).

## Voraussetzungen

-   **XAMPP** (mit PHP 8.x und MySQL/MariaDB)

## Installation & Setup

1.  **Dateien kopieren**: Kopieren Sie das Repository in Ihr `htdocs`-Verzeichnis (z.B. `C:\xampp\htdocs\minisnipeit`).
2.  **Datenbank**: 
    -   Erstellen Sie eine neue Datenbank (z.B. `snipeit`).
    -   Importieren Sie die Datei `database.sql` in die Datenbank.
3.  **Konfiguration**:
    -   Kopieren Sie `.env.example` oder `.env.template` zu `.env` (falls nicht bereits geschehen).
    -   Passen Sie die Datenbank-Zugangsdaten in der `.env` an:
        ```env
        DB_HOST=localhost
        DB_NAME=snipeit
        DB_USER=snipeit
        DB_PASS=IhrPasswort
        ```
4.  **Aufrufen**: Öffnen Sie `http://localhost/minisnipeit/public/` im Browser.

## Standard-Anmeldedaten

-   **Benutzer**: `admin`
-   **Passwort**: `password`

## Lizenz

Dieses Projekt steht unter der MIT-Lizenz.

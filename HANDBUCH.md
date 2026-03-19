# Mini-Snipe IT Handbuch

Version: 19.03.2026

## 1. Zweck und Geltungsbereich

Mini-Snipe IT ist ein webbasiertes Asset-Management-System fuer IT-Hardware.
Das System unterstuetzt die Erfassung, Verwaltung, Ausgabe, Ruecknahme und Protokollierung von Assets sowie die Pflege von Stammdaten.

Dieses Handbuch richtet sich an:
- Administratoren
- Editoren
- Normale Benutzer mit Lesezugriff

## 2. Systemueberblick

### 2.1 Kernfunktionen

- Dashboard mit Kennzahlen und Diagrammen
- Asset-Verwaltung (Anlegen, Bearbeiten, Loeschen)
- Check-out und Check-in mit Historie
- Benutzerverwaltung mit Rollenmodell
- Standorte, Kategorien, Hersteller und Modelle als Stammdaten
- Hardware-Lookups (RAM, SSD, Cores, OS)
- CSV-Import fuer Standorte, Benutzer und Assets
- Ausgabe- und Rueckgabeprotokolle
- Login-Protokoll inkl. erfolgreicher, fehlgeschlagener und gesperrter Anmeldungen
- Theme-Umschaltung (Dark/Light)
- Kollabierbare Sidebar mit persistenter Einstellung

### 2.2 Rollen und Rechte

- Admin:
  - Vollzugriff auf alle Bereiche
  - Zugriff auf Standorte, Stammdatenverwaltung, globale Einstellungen
  - Zugriff auf Login-Protokoll und Branding-Einstellungen
- Editor:
  - Zugriff auf operative Arbeit mit Assets (inkl. Check-in/Check-out)
  - Kein Zugriff auf Admin-Bereiche
- User:
  - Lesen von freigegebenen Bereichen
  - Keine administrativen Aenderungen

## 3. Navigation

Die Hauptnavigation erfolgt ueber die linke Sidebar.

Hauptpunkte:
- Dashboard
- Assets
- User
- Standorte (nur Admin)
- Verwaltung (nur Admin)
- Einstellungen (nur Admin)
- Abmelden

Hinweise:
- Die Sidebar kann ein- und ausgeklappt werden.
- Auf mobilen Geraeten wird die Sidebar ueber das Hamburger-Menue geoeffnet.

## 4. Anmeldung und Sicherheit

### 4.1 Anmeldung

- Benutzer melden sich ueber die Login-Seite mit Benutzername und Passwort an.
- Bei erfolgreicher Anmeldung wird ein Login-Event protokolliert.

### 4.2 Protokollierung von Login-Ereignissen

Folgende Events werden im Login-Protokoll gespeichert:
- login (erfolgreich)
- logout
- login_failed (fehlgeschlagen)
- login_blocked (gesperrt, z. B. Login deaktiviert)

Zusatzinformationen:
- Benutzername
- ggf. Benutzer-ID
- Grundfeld (reason), z. B. invalid_password, unknown_username, login_disabled
- IP-Adresse
- User-Agent
- Zeitstempel

### 4.3 Passwort und Profil

- Benutzer koennen auf der Profilseite ihr Passwort aendern.
- Theme-Umschaltung (Dark/Light) ist benutzerbezogen.

## 5. Arbeiten mit Assets

### 5.0 Buchungen fuer Bearbeiter (neu)

Berechtigte Bearbeiter koennen im Menuepunkt Buchungen alle Ausgaben und Ruecknahmen einsehen.

Ziel:
- Transparenz ueber Asset-Bewegungen
- Nachvollziehbarkeit, wer wann welches Asset einem Benutzer zugewiesen oder zurueckgenommen hat
- Schnellere Auskunft bei Rueckfragen aus Fachbereichen

Inhalt der Uebersicht:
- Buchungs-ID
- Asset (Asset-Tag/Seriennummer/Name)
- Benutzer
- Ausgabezeitpunkt und Bearbeiter
- Ruecknahmezeitpunkt und Bearbeiter
- Status (Ausgegeben oder Zurueckgenommen)

Die Suchfunktion in der Buchungsansicht erlaubt Filter auf Asset-Tag, Seriennummer, Asset-Name und Benutzer.

### 5.1 Asset anlegen

Pflichtfelder und verfuegbare Felder koennen je nach Konfiguration variieren.
Typische Felder:
- Name
- Asset-Tag
- Seriennummer
- Modell
- Status
- Standort
- Benutzerzuweisung
- technische Detailfelder

Das Asset-Tag kann leer sein, falls intern so gewuenscht.

### 5.2 Asset suchen und filtern

In der Asset-Liste stehen Suche und Modellfilter zur Verfuegung.
Dadurch lassen sich auch grosse Datenbestaende effizient durchsuchen.

### 5.3 Asset ausgeben (Check-out)

Ablauf:
1. Asset in Dashboard oder Asset-Liste auswaehlen
2. Ausgabe starten
3. Benutzer ueber Suchfeld finden
4. Auswahl bestaetigen

Besonderheit bei vielen Benutzern:
- Im Checkout-Dialog steht eine Live-Suche zur Verfuegung.
- Auswahl erfolgt in einer gefilterten Dropdown-Liste.

### 5.4 Asset zuruecknehmen (Check-in)

Ablauf:
1. Ruecknahme am Asset ausloesen
2. System schliesst offene Zuordnung
3. Historie wird aktualisiert

## 6. Benutzerverwaltung

Funktionen:
- Benutzer anlegen
- Benutzer bearbeiten
- Benutzer loeschen (nur wenn keine aktiven Asset-Zuweisungen bestehen)
- Rolle setzen (admin, editor, user)
- Login-Berechtigung steuern (can_login)

Wichtig:
- Benutzer mit can_login = 0 koennen sich nicht anmelden.
- Solche Versuche erscheinen als login_blocked im Login-Protokoll.

## 7. Stammdatenverwaltung (Admin)

Bereiche:
- Asset-Modelle
- Kategorien
- Hersteller
- Hardware-Optionen (Lookup-Tabellen)

Zweck:
- Einheitliche Datenbasis
- Vermeidung freier Texteingaben bei technischen Feldern
- Konsistente Auswertungen

## 8. Standorte (Admin)

Standorte werden zentral gepflegt und koennen Benutzern sowie Assets zugewiesen werden.
Kuerzel sind fuer interne Prozesse wie Asset-Tag-Generierung relevant.

## 9. Globale Einstellungen (Admin)

In den globalen Einstellungen koennen u. a. verwaltet werden:
- Seitenname
- Branding-Typ
- Logo
- Favicon
- Firmenadresse
- Header-/Footer-Texte fuer Protokolle
- Login-Protokoll-Ansicht

## 10. Protokolle

### 10.1 Ausgabe- und Rueckgabeprotokolle

Nach Check-out/Check-in koennen Protokolle erzeugt werden.
Diese enthalten typischerweise:
- Benutzerdaten
- Asset-Liste
- Zeitbezug
- konfigurierbare Header-/Footer-Texte

### 10.2 Login-Protokoll

Im Bereich Einstellungen einsehbar (Admin).
Empfohlen fuer:
- Sicherheitsmonitoring
- Erkennung von Fehlversuchen
- Nachvollziehbarkeit von Nutzeraktivitaet

## 11. CSV-Import

### 11.1 Empfohlene Reihenfolge

1. Standorte
2. Benutzer
3. Assets

Diese Reihenfolge ist wichtig, damit Abhaengigkeiten sauber aufgeloest werden.

### 11.2 Allgemeine Hinweise

- Vor jedem Massenimport ein Datenbank-Backup erstellen
- CSV-Formate gem. Import-Hinweisen in der Anwendung verwenden
- Nach dem Import Stichproben in Listenansichten pruefen

## 12. Installation und Update

### 12.1 Neuinstallation

1. Projekt in Webserver-Verzeichnis kopieren
2. Leere Datenbank erstellen
3. Setup-Assistent oeffnen
4. DB-Zugangsdaten eintragen
5. Installation abschliessen und Admin-Login testen

### 12.2 Update bestehender Instanzen

Empfohlener Ablauf:
1. Vollstaendiges Backup
2. Migration ausfuehren
3. Verifikation ausfuehren
4. Funktionstest durchfuehren

Verwendete SQL-Dateien:
- database.sql (Basis)
- db_migration.sql (Updatepfad)
- db_verify.sql (Pruefungen)

## 13. Betriebsempfehlungen

- Regelmaessige Backups (taeglich, je nach Kritikalitaet)
- Pruefung der Login-Logs auf Auffaelligkeiten
- Rollenrechte sparsam vergeben (Least Privilege)
- Testsystem vor Produktiv-Rollout verwenden
- Temporare Wartungsskripte nach Nutzung sofort entfernen

## 14. Temporare Skripte und Sonderfaelle

Es koennen temporaere Wartungsskripte fuer Einmalaktionen eingesetzt werden.
Beispiel:
- Bereinigung von Lager-Assets mit IT-Sammelbenutzern

Sicherer Einsatz:
1. Zuerst Dry-Run
2. Ergebnis pruefen
3. Erst dann Execute-Modus
4. Skript danach loeschen

## 15. Fehlerbilder und Troubleshooting

### 15.1 Login funktioniert nicht

Pruefen:
- Benutzername korrekt?
- Passwort korrekt?
- can_login aktiv?
- Login-Protokoll auf reason-Werte pruefen

### 15.2 Assets koennen Benutzer nicht zugeordnet werden

Pruefen:
- Besteht der Benutzer?
- Hat Asset bereits offene Zuordnung?
- Ist der Workflow Check-out statt direkter Bearbeitung korrekt genutzt?

### 15.3 SQL-Update lief durch, aber Funktionen fehlen

Pruefen:
- Wurde db_migration.sql komplett ausgefuehrt?
- Wurde db_verify.sql ausgewertet?
- Gibt es mehrere Deploy-Pfade mit unterschiedlichen Dateistaenden?

## 16. Begriffe

- Asset: Ein inventarisiertes IT-Geraet
- Check-out: Ausgabe eines Assets an einen Benutzer
- Check-in: Ruecknahme eines Assets
- Stammdaten: Grunddaten wie Kategorien, Modelle, Hersteller
- Lookup: Vordefinierte Auswahlliste fuer technische Werte

## 17. Aenderungs- und Pflegehinweis

Dieses Handbuch beschreibt den Stand der Anwendung zum oben genannten Datum.
Bei funktionalen Erweiterungen sollte das Handbuch zeitnah aktualisiert werden.

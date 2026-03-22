# Implementationplan: Mobile Offline-First Inventur

## Ziel
Alle aktuell fest im Code hinterlegten Stammdaten (z. B. Gesellschaften, Asset-Modelle, weitere Lookups) werden dynamisch aus der bestehenden MariaDB bereitgestellt.

Zusatzanforderung:
- Beim Start der App ist eine Online-Verbindung erforderlich (Initial-Login + Initial-Sync).
- Wenn keine Verbindung besteht, wird ein klarer Hinweis angezeigt und der Startfluss entsprechend blockiert.

## Rahmenbedingungen
- Bestehender Code des Asset-Managements bleibt unverändert.
- Fokus nur auf Mobile_offlineFirstInventur und neue, isolierte API-Endpunkte.
- Ordner für die App ist fest: Projektordner/pwa/
- Benötigte Dateien werden aus den Unterordnern von Mobile_offlineFirstInventur in Projektordner/pwa/ kopiert.
- Alle weiteren Codeänderungen für die neue App erfolgen ausschließlich in Projektordner/pwa/, nicht im Originalordner Mobile_offlineFirstInventur.
- Zielarchitektur: Offline-first nach erfolgreichem Initial-Online-Start.

## Umsetzung in Phasen

### Phase 1 - Analyse und Scope
Status: in Arbeit

1. Alle statischen Datenquellen im Code inventarisieren:
   - Gesellschaften
   - Asset-Modelle
   - ggf. weitere statische Lookup-Listen
2. Für jede Liste festlegen:
   - Quelltabelle/View in MariaDB
   - benötigte Felder
   - Filterregeln (z. B. aktiv)
   - Sortierung
3. Ergebnis dokumentieren (Mapping):
   - Hardcoded-Quelle -> API-Endpunkt -> lokales Cache-Modell

#### Phase 1 - Aktueller Ergebnisstand (Kickoff)

Inventarisierte Hardcodings in der neuen App unter Projektordner/pwa/:

1. Gesellschaften
   - Quelle aktuell: pwa/js/companies.js
   - Nutzung aktuell: pwa/js/app.js (Company-Auswahl und Filter)

2. Asset-Modelle
   - Quelle aktuell: pwa/js/models.js
   - Nutzung aktuell: pwa/js/app.js (Model-Dropdown, Tabellenanzeige)

3. Weitere statische Lookup-Listen
   - Aktuell keine zusätzliche feste Lookup-Datei in pwa/js gefunden.
   - Laufende Prüfung: Inline-Werte und Fallback-Strings bleiben funktionale UI-Texte, keine Stammdatenlisten.

Vorläufiges Mapping (Umsetzungsbasis):

| Hardcoded-Quelle | Ziel-API | DB-Quelle (MariaDB) | Benötigte Felder | Filter/Sortierung | Lokaler Cache |
| --- | --- | --- | --- | --- | --- |
| pwa/js/companies.js | GET /mobile/v1/companies | locations | id, name, address, city, kuerzel | Sort: name ASC | IndexedDB Store companies |
| pwa/js/models.js | GET /mobile/v1/asset-models | asset_models | id, name, category_id, manufacturer_id, has_sim_fields, has_hardware_fields | Sort: name ASC | IndexedDB Store asset_models |

Wichtiger Befund fuer die Migration:

1. Die aktuell hardcodierten Gesellschafts-IDs in pwa/js/companies.js entsprechen nicht den IDs in locations.
2. Beim Umstieg auf dynamische Daten muss daher zwingend die DB-ID aus locations als einzige Referenz verwendet werden (keine Alt-ID-Mischung).
3. Falls bestehende lokale Bestandsdaten alte company_id-Werte enthalten, ist eine einmalige Migrationsroutine notwendig (Name-basiertes Re-Mapping auf locations.id).

Offene Verifikation in Step 1:

1. Aktiv-/Sichtbarkeitsregeln fuer locations und asset_models fachlich final festlegen (falls notwendig).
2. Antwortschema je Endpoint finalisieren (version, updated_at, items).
3. Migrationsstrategie fuer vorhandene lokale company_id-Altwerte festschreiben.

## Phase 2 - API-Schicht (isoliert)
Status: in Arbeit

1. Neue mobile API-Endpunkte bereitstellen (ohne Eingriff in bestehende Asset-Management-Logik):
   - POST /mobile/v1/auth/login
   - GET /mobile/v1/bootstrap/meta
   - GET /mobile/v1/companies
   - GET /mobile/v1/asset-models
   - optional: GET /mobile/v1/lookups/*
2. Standardisiertes Response-Format:
   - version
   - updated_at
   - items
3. Optional für Performance/Sync:
   - ETag und/oder version_id pro Ressource

#### Phase 2 - Bereits umgesetzt

Neue API-Dateien wurden angelegt unter public/api/mobile/v1:

1. public/api/mobile/v1/_bootstrap.php
2. public/api/mobile/v1/auth/login.php
3. public/api/mobile/v1/bootstrap/meta.php
4. public/api/mobile/v1/companies.php
5. public/api/mobile/v1/asset-models.php

Umgesetzt in diesem Schritt:

1. Einheitliche JSON-Antworten inkl. Fehlercodes.
2. Rollenpruefung fuer mobile Endpunkte (editor/admin erforderlich).
3. Login-Endpunkt auf Basis der bestehenden Benutzerlogik.
4. Dynamische Datenquellen aus MariaDB fuer Gesellschaften (locations) und Asset-Modelle (asset_models).
5. Version/updated_at-Metadaten fuer Bootstrap und Ressourcen.

## Phase 3 - Login und Berechtigungen
1. Login-Pflicht vor dem Einstieg in die Inventur.
2. Rollen-/Rechteprüfung für Inventur-Benutzer. Bestehende Rolle 'Bearbeiter' und 'Administrator' verwenden.
3. Session-Handling:
   - kurzlebiges Access-Token
   - optional Refresh-Token
4. Fehlerbehandlung:
   - ungültige Zugangsdaten
   - 401/403
   - abgelaufene Session

## Phase 4 - Startup-Gate mit Online-Pflicht
1. Beim App-Start zuerst Netzwerkstatus prüfen.
2. Falls offline und kein gültiger Initialstand vorhanden:
   - Start blockieren
   - Hinweis anzeigen: "Initiale Anmeldung und Datenabgleich erfordern Internetverbindung. Bitte verbinden und erneut starten."
3. Falls offline, aber bereits gültiger lokaler Stand vorhanden:
   - Offline-Modus erlauben
   - deutlich als Offline markieren

## Phase 5 - Bootstrap-Flow
Startreihenfolge:
1. Netzwerk prüfen
2. Login durchführen
3. Bootstrap-Metadaten laden
4. Gesellschaften laden + lokal cachen
5. Asset-Modelle laden + lokal cachen
6. Inventuransicht freigeben

Zusatz:
- Jeder Schritt mit Timeout und klaren Fehlermeldungen.
- Teilfehler robust behandeln (Fallback auf letzten validen Cache, wenn vorhanden).

## Phase 6 - Lokaler Cache und Datenregeln
1. Lokale Persistenz für Stammdaten (z. B. IndexedDB im Web/PWA, Room im Android-Teil).
2. Pro Ressource speichern:
   - last_sync
   - version
   - source
3. Cache-Strategie:
   - Online beim Start: Refresh erzwingen
   - Bei Erfolg: Cache atomar ersetzen
   - Bei Fehlschlag: letzten gültigen Stand nutzen (falls vorhanden)
4. Stale-Hinweis anzeigen, wenn Daten zu alt sind.

## Phase 7 - UI-Umstellung auf dynamische Daten
1. Alle Dropdowns/Picker ausschließlich aus lokalem Cache befüllen (keine statischen Arrays).
2. Ladezustände einführen:
   - lädt
   - leer
   - fehlerhaft
3. Speichern verhindern, wenn notwendige Stammdaten fehlen.
4. Einheitliche und verständliche Fehlermeldungen bereitstellen.

## Phase 8 - Offline-First Sync für Inventurdaten
1. Stammdaten-Refresh und Inventurdatensync trennen.
2. Inventureingaben lokal puffern (Queue), wenn offline.
3. Sync bei Verbindung:
   - Retry mit Backoff
   - idempotente Requests (z. B. client_generated_id)
4. Konfliktstrategie definieren und dokumentieren.
5. Bei jedem App-Start Sync-Status prüfen:
   - Wenn nicht synchronisierte Datensätze vorhanden sind, Hinweis anzeigen.
   - Wenn alle Datensätze synchronisiert sind, lokale Datenbank automatisch leeren.
6. Manueller Offline-Recovery-Flow:
   - In der App gibt es einen Button "Online prüfen & Sync".
   - Der Button prüft die Online-Erreichbarkeit und stößt anschließend den Sync offener Datensätze an.

Hinweis zur UX-Anpassung:
- Export via JSON/CSV ist entfernt und wird nicht mehr verwendet.

## Phase 9 - Sicherheit
1. Keine DB-Credentials in der App.
2. Serverseitige Validierung aller Eingaben.
3. Login-Rate-Limiting.
4. Audit-Logging für Login und relevante Inventuraktionen.

## Phase 10 - Testplan
Funktional:
1. Online-Start mit frischen Daten funktioniert.
2. Offline-Start ohne Initialstand blockiert korrekt inkl. Hinweis.
3. Offline-Start mit vorhandenem Stand funktioniert.
4. Cache-Refresh aktualisiert Gesellschaften und Modelle korrekt.

Security:
1. Ungültiger Login wird korrekt abgefangen.
2. Abgelaufener Token wird korrekt behandelt.
3. Fehlende Rolle verhindert Inventurzugriff.

Resilience:
1. API-Timeouts und 5xx führen zu definiertem Verhalten.
2. Partieller Bootstrap-Fehler wird sauber angezeigt.

Regression:
1. Bestehendes Asset-Management bleibt unverändert funktionsfähig.

## Phase 11 - Rollout
1. Phase A: Login + dynamische Gesellschaften
2. Phase B: dynamische Asset-Modelle + Cache-Invalidierung
3. Phase C: vollständiger Bootstrap + Offline-UX
4. Phase D: Monitoring, Telemetrie, Stabilisierung

## Spaeter umzusetzen (Asset-Management UI)
1. In assets.php muss das Feld Raum angezeigt werden (separat von Gesellschaft/Mandant).
2. In assets.php muss ein Readonly-Feld fuer das Datum der letzten Inventur angezeigt werden (last_inventur).
3. Diese Punkte sind geplant, werden aber bewusst erst in einem spaeteren Schritt umgesetzt.

## Definition of Done
1. Keine produktiv genutzten Hardcoded-Stammdaten mehr in der Mobile-App.
2. Erststart ohne Internet zeigt den definierten Hinweis und blockiert korrekt.
3. Nach erfolgreichem Online-Start sind Gesellschaften und Asset-Modelle offline nutzbar.
4. Nur berechtigte Benutzer können Inventur durchführen.
5. Bestehende Asset-Management-Funktionen sind unverändert.

## Nachverfolgung (Status)
- [ ] Phase 1 abgeschlossen
- [ ] Phase 2 abgeschlossen
- [ ] Phase 3 abgeschlossen
- [ ] Phase 4 abgeschlossen
- [ ] Phase 5 abgeschlossen
- [ ] Phase 6 abgeschlossen
- [ ] Phase 7 abgeschlossen
- [ ] Phase 8 abgeschlossen
- [ ] Phase 9 abgeschlossen
- [ ] Phase 10 abgeschlossen
- [ ] Phase 11 abgeschlossen

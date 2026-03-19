---
name: "Allgemeine Projekt-Skills"
description: "Notizen und Anweisungen für dieses Projekt"
---
# Allgemeine Anweisungen

- **Dateien kopieren**: Alle Änderungen an PHP/HTML/CSS-Dateien im Workspace müssen **auch** in das lokale XAMPP-Verzeichnis kopiert werden: `D:\xampp\htdocs\minisnipeit`
  - Dies gilt für alle `.php`, `.css`, `.js`, `.html` Dateien.
  - Befehl zum Kopieren: `xcopy /Y /S /I /E <Datei/Ordner> D:\xampp\htdocs\minisnipeit\<Pfad>`
- **Verifizierung**: Nach Änderungen an der Logik (z.B. SQL-Abfragen) sollte eine Verifizierung per PHP CLI durchgeführt werden, um die Korrektheit der Daten zu prüfen.
- **Datenbank-Schema**: Das Schema befindet sich in `database.sql`. Bei Änderungen an Tabellenstrukturen muss diese Datei aktualisiert werden (oder Migrations erstellt werden).
Antworte immer auf deutsch.
Vor größeren Änderungen bitte immer .bak Dateien erstellen.
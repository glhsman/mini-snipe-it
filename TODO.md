# TODO – Mini-Snipe IT

---

## Sidebar ein-/ausklappbar

### Ziel
Die linke Sidebar soll per Klick kollabieren (nur Icons, kein Text) und sich wieder
ausklappen. Der Zustand soll über Seitenladungen hinweg gespeichert werden.

### Aufgaben

- [x] **CSS – Collapsed-State definieren** *(✅ erledigt)*
  - Sidebar-Breite kollabiert: 64 px (Desktop) / 260 px override auf Mobile
  - `.main-content` margin-left: `260px` → `64px` mit smooth transition
  - `.nav-label` und `.logo-text` ausblenden via `opacity + max-width` Transition
  - Smooth-Transition 0.25s ease auf `width`, `padding`, `margin-left`
  - Klasse `.sidebar-collapsed` auf `<body>` (verhindert Flash beim Laden)

- [x] **Toggle-Button eingebaut** *(✅ erledigt)*
  - Button oben rechts im `.logo`-Bereich der Sidebar
  - Icon: `fa-chevron-left` (ausgeklappt) / `fa-chevron-right` (eingeklappt)
  - Nur in `includes/sidebar.php` gepflegt, auf Mobile ausgeblendet

- [x] **JavaScript – Toggle-Logik** *(✅ erledigt)*
  - Klick → `.sidebar-collapsed` auf `<body>` toggeln
  - Zustand in `localStorage` gespeichert (`sidebar_collapsed = 1/0`)
  - Anti-Flash: inline Script in `sidebar.php` setzt Klasse sofort vor dem Render

- [x] **Sidebar in eigenes Include auslagern** *(✅ erledigt)*
  - `public/includes/sidebar.php` erstellt – dynamischer `active`-Link über `$activePage`
  - Alle 24 Seiten auf das Include umgestellt
  - Abmelden-Link war auf 22 Seiten fehlend → jetzt überall konsistent vorhanden
  - Admin-Links konsequent mit `Auth::isAdmin()`-Guard gesichert

- [x] **Tooltip bei kollabierten Icons** *(✅ erledigt)*
  - CSS `::after`-Tooltip via `content: attr(title)` auf `.nav-link:hover`
  - Erscheint rechts neben dem Icon, backdrop-filter + glass-border Styling
  - Light-Mode Override vorhanden

- [x] **Mobile / Responsive** *(✅ erledigt)*
  - Auf < 768 px: Sidebar als Overlay (`transform: translateX(-100%)` → `0`)
  - Hamburger-Button in Top-Navbar aktiviert das Overlay
  - Klick außerhalb schließt die Sidebar (`document.addEventListener('click', ...)`)
  - Collapsed-State auf Mobile deaktiviert (immer voll sichtbar)

- [ ] **Alle Seiten testen**
  - Assets, Users, Locations, Settings, Dashboard, Login
  - Übergangsanimation auf Ruckeln prüfen
  - localStorage-Persistenz über Seitenladungen testen
  - Mobile Overlay + Hamburger testen (< 768 px)

---

## Weitere offene Punkte

*(Hier können weitere Themen ergänzt werden)*


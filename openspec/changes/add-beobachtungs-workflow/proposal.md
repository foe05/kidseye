## Why

Grundschullehrkräfte beobachten ihre Schüler:innen fortlaufend, dokumentieren aber unsystematisch — auf Zetteln, in Kalendern, im Kopf. Vor Elterngesprächen und Zeugniskonferenzen muss die pädagogische Einschätzung dann aus Erinnerung rekonstruiert statt aus Belegen abgeleitet werden. Gleichzeitig sind Schülerdaten in Hessen datenschutzrechtlich streng gebunden, was Cloud-Produkte ausschließt.

kidseye ist eine Nextcloud-App, mit der eine Lehrkraft **rund 20 Beobachtungen pro Tag in jeweils unter 10 Sekunden** erfasst, diese automatisch an das hessische Kerncurriculum bindet und daraus belastbare Auswertungen für Elterngespräche, Zeugnisse und Förderplanung erzeugt — vollständig auf der selbst gehosteten Nextcloud der Schule.

Der Workflow wurde im Juli 2026 einer Grundschullehrkraft vorgelegt und anhand ihrer Rückmeldungen überarbeitet. Vier Annahmen wurden dabei korrigiert; die Änderungen sind unten gekennzeichnet und in `design.md` begründet.

## What Changes

Der Workflow gliedert sich in drei Schleifen mit stark unterschiedlicher Taktung:

- **Schleife 1 – Erfassen** (mehrmals täglich, Sekunden, iPad/Handy im Unterricht): Die Lehrkraft startet einmal pro Unterrichtsstunde einen Kontext (Klasse, Fach, optional Inhaltsfeld). Jede Beobachtung erbt diesen Kontext. Erfassung per Ein-Tap-Schnellmarker, Freitext oder Foto einer Arbeitsprobe.
- **Schleife 2 – Kuratieren** (wöchentlich, Minuten, Desktop): Nur Beobachtungen, die noch etwas brauchen, landen in einer Inbox. Schnellmarker-Einträge sind bereits vollständig und werden **nicht** nachbearbeitet.
- **Schleife 3 – Auswerten** (punktuell): Zeitleiste pro Kind, Kompetenz-Heatmap, PDF-Export, DSGVO-Auskunft.

Konkret entsteht:

- **Unterrichtskontext als Sitzung** — eine Stunde wird gestartet, läuft maximal 90 Minuten, endet automatisch. Jede Beobachtung erbt Klasse, Kontext und Inhaltsfeld ohne zusätzlichen Tap.
- **Unterrichtskontexte umfassen Schulfächer und Lernformen** *(geändert)* — Freiarbeit ist kein Schulfach, aber ein eigener Kontext mit Schwerpunkt Arbeitsverhalten. Kontexte der Art `lernform` tragen keinen Fachbezug und bieten ausschließlich die überfachliche Achse an.
- **Schnellmarker als vorkonfigurierte Kerncurriculum-Zuordnung** — ein Marker („hilft anderen") trägt seine Zuordnung auf eine überfachliche Kompetenzdimension (Sozialkompetenz / Rücksichtnahme und Solidarität) in seiner Definition. Ein Tap erzeugt damit eine bereits vollständig zugeordnete Beobachtung. Markersätze sind je Unterrichtskontext frei konfigurierbar; die Verwaltung gehört zum Umfang.
- **Verwendungszwecke** *(neu)* — eine Beobachtung kann schon bei der Aufnahme für ein späteres Dokument vorgemerkt werden, etwa einen Förderplan. Die Liste der Zwecke ist konfigurierbar; ein Schnellmarker kann einen Zweck mitführen, sodass die Vormerkung ohne zusätzlichen Tap geschieht. Je Kind und Zweck entsteht eine Mappe mit eigenem PDF.
- **Klassenbild statt Sitzplan oder Liste** *(geändert)* — ein frei anordenbares, optional gruppiertes Kachelraster ohne Raumgeometrie. Es liefert das räumliche Wiederfinden und die Sichtbarkeit von Beobachtungslücken, ohne den Pflegeaufwand eines maßstabsgetreuen Sitzplans.
- **Hessisches Kerncurriculum Primarstufe als versionierte Referenzdaten** — 4 überfachliche Kompetenzbereiche mit 15 Dimensionen (fächerübergreifend identisch), plus fachliche Kompetenzbereiche, Bildungsstandards und Inhaltsfelder je Fach. Nicht im Code, sondern als importierbarer, versionierter Datenbestand.
- **Datenschutz-Lebenszyklus, in v1 im Einzelbetrieb** *(geändert)* — das Modell kennt `privat` → `klassenteam` → `akte`, einbahnig ab `akte`. In der ersten Ausbaustufe ist ausschließlich die erfassende Lehrkraft zugriffsberechtigt; `klassenteam` bleibt im Modell, ist aber nicht erreichbar. Aufbewahrungsfristen und Löschritual zum Schuljahresende bleiben unverändert im Umfang.
- **Offline-First-Erfassung** — jede Beobachtung wird lokal geschrieben und im Hintergrund synchronisiert. Speichern wartet niemals auf das Netz.
- **Nextcloud als Fundament** — Authentifizierung und Lehrkräfte über Nextcloud-Nutzer, Fotos in einem schuleigenen Gruppenordner (nicht im Nutzerordner), Zugriff über Nextcloud-Gruppen.
- **Schüler:innen sind app-eigene Entitäten**, keine Nextcloud-Nutzer.
- **Berichte auch auf Handybreite erzeugbar** *(geändert)* — die Auswahlmasken und der PDF-Auslöser sind ab 375 px bedienbar; nur die Heatmap bleibt eine waagerecht scrollende Tabelle.

Nicht enthalten (bewusst später): Mehrbenutzerbetrieb und Freigabe an das Klassenteam, Stundenplan-Anbindung, Spracherkennung, LUSD-/Untis-Import, vom System formulierte Zeugnis- oder Förderplantexte, Elternzugang.

## Capabilities

### New Capabilities

- `kompetenzrahmen`: Struktur, Versionierung und Import des hessischen Kerncurriculums Primarstufe (überfachliche Kompetenzen, fachliche Kompetenzbereiche, Bildungsstandards, Inhaltsfelder) als Referenzdatenbestand.
- `schueler-und-klassen`: Schuljahre, Klassen, Schüler:innen, Lehraufträge (Lehrkraft × Klasse × Unterrichtskontext), Klassenbild, CSV-Import und Schuljahres-Rollover.
- `unterrichtskontext`: Unterrichtskontexte als Stammdaten (Schulfächer und Lernformen) sowie Starten, Laufen und automatisches Beenden einer Unterrichtsstunde als Kontext, den Beobachtungen erben.
- `verwendungszwecke`: Konfigurierbare Zielformate, für die Beobachtungen vorgemerkt werden — bei der Erfassung, automatisch über Marker oder nachträglich — samt Mappe und PDF je Kind und Zweck.
- `beobachtung-erfassen`: Schnellerfassung im Unterricht — Schnellmarker, Freitext, Foto einer Arbeitsprobe, Sammelbeobachtung, Offline-Warteschlange, Undo.
- `beobachtung-kuratieren`: Inbox für nachbearbeitungsbedürftige Beobachtungen, Zuordnung zu Bildungsstandards, Sichtbarkeitsentscheidung, Lücken-Radar.
- `datenschutz-und-aufbewahrung`: Sichtbarkeitsstufen, Übergangsregeln, Aufbewahrungsfristen, Löschung, DSGVO-Auskunft, Protokollierung.
- `auswertung-und-export`: Zeitleiste pro Kind, Kompetenz-Heatmap, PDF-Export für Elterngespräch und Zeugniskonferenz.
- `nextcloud-integration`: Authentifizierung, Rollen über Nextcloud-Gruppen, Dateiablage im schuleigenen Gruppenordner, Sessionverhalten.

### Modified Capabilities

Keine — kidseye ist ein Neuprojekt ohne bestehende Specs.

## Impact

- **Neu**: Nextcloud-App `kidseye` (Server-App: PHP/OCP + Vue-Frontend), eigene Datenbanktabellen via Doctrine-Migrationen, REST-Endpunkte unter `/apps/kidseye/api/v1/`.
- **Nextcloud-Abhängigkeiten**: Nutzer- und Gruppenverwaltung (`IUserManager`, `IGroupManager`), Dateizugriff (`IRootFolder`), Gruppenordner (App `groupfolders`) für die Ablage von Arbeitsproben.
- **Client**: Offline-fähiges Frontend (IndexedDB + Service Worker), als PWA auf dem Home-Bildschirm installierbar, iPad-first und auf Handybreite bedienbar.
- **Referenzdaten**: Kerncurriculum-Datensatz für zunächst 4 Fächer der Primarstufe, abgeleitet aus den amtlichen HKM-Dokumenten (2011).
- **Rechtlich**: Aufbewahrungsfristen und Zulässigkeit der Sichtbarkeitsstufen müssen vor Produktivbetrieb mit der/dem schulischen Datenschutzbeauftragten abgestimmt werden. kidseye macht sie konfigurierbar und setzt keine Fristen normativ.
- **Geklärt**: Die Installierbarkeit einer eigenen PWA innerhalb einer Nextcloud-App ist verifiziert — Nextcloud unterstützt Per-App-Manifeste eingebaut, ein mitgeliefertes `img/manifest.json` verdrängt das Theming-Manifest. Siehe D14 in `design.md`.
- **Verbleibendes Risiko**: Unter iOS hat eine über „Zum Home-Bildschirm" installierte Anwendung einen eigenen Cookie-Bereich; der erste Start verlangt eine separate Anmeldung. Siehe D15.

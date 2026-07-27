## 0. Vorklärungen (blockierend)

- [x] 0.1 **Spike PWA in Nextcloud** — *geklärt, siehe D14.* Nextcloud unterstützt Per-App-Manifeste eingebaut: `layout.user.php` verlinkt `image_path($appid, 'manifest.json')`, und `ThemingDefaults::replaceImagePath()` tritt zurück, sobald die App ein eigenes `img/manifest.json` mitbringt. Kein Blocker.
- [ ] 0.2 **Spike Sitzungsverhalten**: Prüfen, wie sich eine abgelaufene Nextcloud-Sitzung im installierten Vollbildmodus verhält und mit welchem Mechanismus (langlebige Sitzung, App-Token) die Erfassung weiterläuft. *Zusätzlich zu prüfen (D15): Der erste Start aus dem Home-Bildschirm-Symbol verlangt unter iOS eine eigene Anmeldung, da der Speicherbereich von Safari getrennt ist.* → Prüfprotokoll liegt bereit: `tests/protokolle/geraetepruefung.md`, Abschnitte B und C.
- [ ] 0.2b Auf einem echten iPad verifizieren: Symbol, Startziel, Vollbild, Verhalten nach Sitzungsablauf. → `tests/protokolle/geraetepruefung.md`, Abschnitt A.
- [x] 0.3 **Unterrichtskontexte festgelegt.** Schulfächer: Deutsch, Mathematik, Sachunterricht, Kunst, Ethik. Fachneutral: Freiarbeit, Sozial- und Arbeitsverhalten. Alle fünf Kerncurriculum-Dokumente sind beschafft und extrahiert.
- [~] 0.4 Rechtsgrundlage recherchiert und im Code hinterlegt: § 10 Abs. 1/3/4, § 3 Abs. 2 SchDSV, § 72 Abs. 5 HSchG. Voreinstellung `privat` = 12 Monate folgt § 10 Abs. 3. **Die abschließende Abstimmung mit der schulischen Datenschutzbeauftragung steht aus** und kann nicht ersetzt werden. → Vorlage für die Datenschutzbeauftragung liegt bereit: `tests/protokolle/datenschutz-vorlage.md` mit fünf konkreten Entscheidungsfragen.
- [ ] 0.5 Marker-Vokabular je Unterrichtskontext erarbeiten (max. 6, beschreibend statt bewertend), inklusive Zuordnung auf überfachliche Dimensionen und optionalem Verwendungszweck. Für Lernformen mit Schwerpunkt Arbeitsverhalten. → Erfassungsbogen in `tests/protokolle/praxistest.md`.
- [ ] 0.6 Weitere Verwendungszwecke für den Auslieferungszustand festlegen. „Förderplan" (manuell) und „Sozial- und Arbeitsverhalten" (regelbasiert) stehen fest. → Erfassungsbogen in `tests/protokolle/praxistest.md`.

*Rückmeldungen 1–10 der Grundschullehrkraft sind eingearbeitet; siehe Tabelle „Rückmeldung aus der Praxis" in `design.md`.*

## 1. Gerüst der Nextcloud-App

- [x] 1.1 App-Skelett `kidseye` anlegen: `appinfo/info.xml`, Routen, Bootstrap, Berechtigungen
- [x] 1.2 Doctrine-Migrationen: zwei Schritte, 21 Tabellen — Kompetenzrahmen, Kontexte, Klassen, Schüler, Lehraufträge, Klassenbild, Stunden, Beobachtungen, Marker, Zwecke, Nachträge, Protokoll, Skala
- [x] 1.3 Vue-Frontend-Build einrichten, zwei Einstiegspunkte: Nextcloud-Oberfläche und Vollbild-Unterrichtsmodus
- [x] 1.4 Rollenprüfung über Nextcloud-Gruppen (`kidseye-lehrkraft`, `kidseye-leitung`), Verwaltungsseite für die Gruppennamen
- [x] 1.5 Gruppenordner-Anbindung inklusive Prüfung auf Vorhandensein und Schreibbarkeit

## 2. Kompetenzrahmen

- [x] 2.1 Datenmodell: `rahmen`, `rahmen_version`, `rahmen_knoten` (Art, Ebene, Fach, Bezugsstufe, Elternknoten), `knoten_korrespondenz` (n:m)
- [x] 2.2 Import- und Exportformat: Validierung auf Pflichtfelder, Arten, Ebenen, Fachbezug, Zyklen; alles-oder-nichts. Export über `RahmenService::exportiere()`
- [x] 2.3 Seed „Hessen Primarstufe 2011", Teil A: 4 überfachliche Bereiche mit 15 Dimensionen, fachneutral
- [x] 2.4 Seed Mathematik: 6 Kompetenzbereiche, 5 Inhaltsfelder, 26 Bildungsstandards (Bezugsstufe jgst_4)
- [x] 2.5 Seed Deutsch: 4 Kompetenzbereiche, **12 Inhaltsfelder** (drei je Kompetenzbereich, aus Kap. 6.1) und 65 Bildungsstandards
- [x] 2.6 Seed Sachunterricht: 3 Kompetenzbereiche, 5 Inhaltsfelder, 28 Bildungsstandards
- [x] 2.7 Seed Kunst: 3 Kompetenzbereiche, 6 Inhaltsfelder, 3 Kernbereiche als `leitstruktur`, 15 Bildungsstandards
- [x] 2.7b Seed Ethik: 5 Kompetenzbereiche, 5 Inhaltsfelder, 21 Bildungsstandards
- [x] 2.7c Knotenart `leitstruktur` mit fachspezifischer Bezeichnung (Leitideen / Basiskonzepte / Kernbereiche / Leitperspektiven) — wird geseedet, in v1 aber nicht zur Zuordnung angeboten
- [x] 2.8 Korrespondenzen Bildungsstandard ↔ Inhaltsfeld je Fach befüllen
- [x] 2.9 Sperre umsetzen: keine Skala und keine Einstufung auf überfachlichen Dimensionen
- [x] 2.10 Schuleigene Einschätzungsskala für fachliche Standards, in Oberfläche und Export als schuleigen gekennzeichnet

## 3. Schüler:innen, Klassen, Unterrichtskontexte

- [x] 3.1 Datenmodell: `schuljahr`, `klasse`, `schueler`, `klassenzugehoerigkeit`, `lehrauftrag`, `klassenbild`
- [x] 3.2 Datenmodell `unterrichtskontext` mit `art` (`schulfach` oder `fachneutral`) und optionalem Verweis auf ein Fach des Kompetenzrahmens
- [x] 3.3 Verwaltungsoberfläche für Klassen, Schüler:innen, Unterrichtskontexte, Lehraufträge, Klassenbild, Marker, Verwendungszwecke und Einrichtungsstand
- [x] 3.4 CSV-Import mit Vorschau, Dublettenerkennung und Bestätigungsschritt
- [x] 3.5 Klassenbild: alphabetische Voreinstellung, Umsortieren per Ziehen, optionale benannte Gruppen — ohne Raumgeometrie
- [x] 3.6 Schuljahres-Rollover mit Vorschlag der Folgeklassen und Bestätigung je Kind
- [x] 3.7 Zugriffsprüfung: in v1 auf „ist Ersteller" verkürzt, Lehrauftrag steuert die Auswahl beim Stundenstart

## 4. Unterrichtskontext

- [x] 4.1 Datenmodell `unterrichtsstunde` und Endpunkte zum Starten und Beenden
- [x] 4.2 Startdialog mit Vorbelegung der zuletzt genutzten Kombination und optionalem Inhaltsfeld
- [x] 4.3 Automatisches Ende nach 90 Minuten und beim Start einer neuen Stunde
- [x] 4.4 Nachfrage bei einem Kontext, der älter als zwei Stunden ist
- [x] 4.5 Direkteinstieg: Anwendung öffnet bei laufender Stunde unmittelbar den Erfassungsbildschirm

## 5. Erfassung (Schleife 1)

- [x] 5.1 Datenmodell `beobachtung` und `beobachtung_knoten` (n:m), inklusive Kennzeichen „kuratierungsbedürftig"
- [x] 5.2 Datenmodell `schnellmarker` mit Verknüpfung auf überfachliche Dimensionen und optionalem Verwendungszweck, je Unterrichtskontext konfigurierbar, Höchstzahl 6
- [x] 5.2b Verwaltungsoberfläche für Schnellmarker je Unterrichtskontext (Pflichtbestandteil, nicht Nachrüstung)
- [x] 5.3 Kindauswahl über das Klassenbild — eine Ansicht, keine Umschaltung
- [x] 5.4 Zweispaltige Darstellung ab 700 px, einspaltig mit einfahrendem Bereich darunter
- [x] 5.5 Ein-Tap-Erfassung über Schnellmarker: speichert sofort, schließt sofort, ohne zweiten Bestätigungsschritt
- [x] 5.6 Freitexterfassung, markiert die Beobachtung als kuratierungsbedürftig
- [x] 5.7 Fotoerfassung aus dem Kinddialog und als eigenständiger Einstieg mit nachgelagerter Zuordnung
- [x] 5.8 Clientseitige Verkleinerung auf max. 2000 px und Entfernung von EXIF- und Ortsdaten
- [x] 5.9 Einmaliger Hinweis auf unbeteiligte Kinder beim ersten Foto
- [x] 5.10 Sammelbeobachtung: Mehrfachauswahl erzeugt je Kind eine eigene Beobachtung
- [x] 5.11 Offline-Speicher (IndexedDB) und Synchronisationswarteschlange mit Dublettenschutz
- [x] 5.12 Warteschlange übersteht Authentifizierungsfehler und liefert nach erneuter Anmeldung nach
- [x] 5.13 Synchronisationszähler in der Kopfleiste, kein Wartezustand beim Speichern
- [x] 5.14 Entwurfsspeicherung bei Gerätesperre
- [x] 5.15 Rückgängig-Funktion für mindestens fünf Sekunden nach dem Speichern
- [x] 5.16 Suche über alle Kinder mit Lehrauftrag (Gastkinder)
- [x] 5.17 Kachelzustand nach Beobachtungsalter: heute, diese Woche, älter als zwei Wochen
- [x] 5.18 Liste der heutigen Beobachtungen im Erfassungsbereich
- [x] 5.19 `img/manifest.json` mitliefern mit `start_url` auf den Erfassungsbildschirm, `scope` auf `/apps/kidseye/`, `display: standalone` und eigenen Icons
- [x] 5.20 Unterrichtsmodus als `RENDER_AS_BASE` rendern und Manifest-Link sowie `apple-mobile-web-app-capable` und `apple-mobile-web-app-title` per `\OCP\Util::addHeader()` einhängen
- [x] 5.21 Einrichtungsanleitung „Zum Home-Bildschirm hinzufügen" inklusive Hinweis auf die einmalige separate Anmeldung unter iOS (D15)
- [x] 5.22 Beim Einrichten prüfen, ob `theming.standalone_window.enabled` aktiv ist, und andernfalls in der Verwaltung melden

- [x] 5.23 Optionale Verwendungszweck-Leiste im Erfassungsdialog, nie vorausgewählt, nie Pflichtfeld
- [x] 5.24 Automatische Vormerkung über die Marker-Definition, ohne zusätzlichen Tap

## 5b. Verwendungszwecke

- [x] 5b.1 Datenmodell `verwendungszweck` (mit optionaler Regel), `verwendungszweck_knoten` und `beobachtung_verwendung` (n:m)
- [x] 5b.2 Verwaltung der Verwendungszwecke: anlegen, umbenennen, deaktivieren — Deaktivieren erhält bestehende Vormerkungen
- [x] 5b.3 Auslieferungszustand: „Förderplan" (manuell) und „Sozial- und Arbeitsverhalten" (regelbasiert über Sozialkompetenz, Lernkompetenz, Personale Kompetenz)
- [x] 5b.3b Regelauswertung: Mappe eines regelbasierten Zwecks wird bei Abruf aus den Kompetenzzuordnungen berechnet, manuelle Vormerkungen kommen hinzu
- [x] 5b.4 Vormerkung nachträglich setzen und entfernen, gesperrt ab Stufe `akte`
- [x] 5b.5 Mappe je Kind und Verwendungszweck, filterbar nach Zeitraum und Unterrichtskontext
- [x] 5b.6 PDF-Ausgabe einer Mappe, optional mit Arbeitsproben, ohne systemseitige Formulierung

## 6. Kuratieren (Schleife 2)

- [x] 6.1 Inbox: nur kuratierungsbedürftige Beobachtungen, gruppiert nach Woche
- [x] 6.2 Zuordnung zu Kompetenzknoten mit Vorschlägen aus dem Fachkontext der Beobachtung
- [x] 6.3 Sammelbearbeitung: gemeinsame Zuordnung, Freigabe, Erledigung
- [x] 6.4 Erledigen ohne Zuordnung
- [x] 6.5 Umhängen einer Beobachtung auf ein anderes Kind, gesperrt ab Stufe `akte`
- [x] 6.6 Lücken-Radar je Klasse, aufgeschlüsselt nach Fach, mit konfigurierbarem Zeitraum

## 7. Datenschutz und Aufbewahrung

- [x] 7.1 Sichtbarkeitsstufen `privat`, `klassenteam`, `akte` im Modell, Voreinstellung `privat`; in v1 ist `klassenteam` in der Oberfläche nicht erreichbar
- [x] 7.2 Übergangsregeln: einbahnig nach `akte`; der Übergang nach `klassenteam` wird erst in v2 freigeschaltet
- [x] 7.3 Unveränderlichkeit ab `akte` und Nachtragsmechanismus für Korrekturen
- [x] 7.4 Sichtbarkeitsfilter in allen Abfragen (Zeitleiste, Heatmap, Inbox, Export)
- [x] 7.5 Nicht veränderbares Protokoll für Sichtbarkeitswechsel, Löschungen und Auskünfte
- [x] 7.6 Konfigurierbare Aufbewahrungsfristen mit Hinweis auf die erforderliche Abstimmung
- [x] 7.7 Kennzeichnung abgelaufener Beobachtungen, Löschung erst nach Bestätigung
- [x] 7.8 Schuljahresende-Ritual mit Löschvorschlag für private Rohbeobachtungen
- [x] 7.9 DSGVO-Auskunftsbericht als PDF, ohne private Beobachtungen, inklusive Arbeitsproben
- [x] 7.10 Löschung entfernt nicht mehr referenzierte Dateien im Gruppenordner

## 8. Auswerten (Schleife 3)

- [x] 8.1 Zeitleiste pro Kind mit Filtern, Voreinstellung acht Wochen
- [x] 8.2 Kompetenz-Heatmap Klasse × Kompetenzknoten mit Belegzahlen
- [x] 8.3 Kennzeichnung, dass Belegzahlen keine Bewertung darstellen
- [x] 8.4 Trennung der Auswertung nach Rahmenversion, Hinweis bei Versionswechsel
- [x] 8.5 PDF-Bericht für Elterngespräch und Zeugniskonferenz, ohne systemseitige Einschätzung — eigener `PdfService`, ohne zusätzliche Abhängigkeit
- [x] 8.6 Einbindung von Arbeitsproben in den Bericht
- [x] 8.7 Berichtsauswahl und PDF-Auslöser ab 375 px bedienbar; Heatmap scrollt in ihrem eigenen Bereich

## 9. Absicherung

- [x] 9.1 Tests für Zugriffsprüfung über Lehraufträge und Sichtbarkeitsstufen — ausgeführt (PHP 8.3 im Container)
- [x] 9.2 Tests für Kontextvererbung, automatisches Stundenende und Zeitstempel — ausgeführt
- [x] 9.3 Tests für Offline-Warteschlange: Netzabbruch, Sitzungsablauf, Dublettenschutz
- [x] 9.4 Tests für Rahmenversionierung: Zuordnungen bleiben nach Import einer neuen Version stabil
- [ ] 9.5 Test des Zeitbudgets auf echtem Gerät: Erfassung per Schnellmarker in unter 10 Sekunden ab gesperrtem Gerät → Messbogen mit Zerlegung in `tests/protokolle/geraetepruefung.md`, Abschnitt D.
- [x] 9.6 Lasttest mit 5.000 Beobachtungen je Lehrkraft (`tests/last/lasttest.py`): alle acht Abfrageformen unter 8 ms, Indizes greifen. Zusätzlich bei 20.000 und 100.000 gemessen — **die Heatmap kippt bei rund 70.000**, siehe D17
- [x] 9.7 Bedienbarkeit prüfen bei 375 px und 1180 px, Auswahlflächen mindestens 44 px
- [ ] 9.8 Praxistest über zwei Wochen mit einer Lehrkraft, Messung der tatsächlichen Erfassungszahlen und des Inbox-Anteils → vollständiges Protokoll: `tests/protokolle/praxistest.md`.
- [x] 9.9 **Neu:** Syntaxprüfung aller PHP-Dateien und Strukturprüfung des erzeugten PDF gegen einen fremden Leser (pypdf).

---

*Der Lasttest hat eine Voraussetzung für den Mehrbenutzerbetrieb ergeben —
eine vorberechnete Zwischentabelle für die Heatmap. Sie ist als D17 in
`design.md` festgehalten und gehört zu v2. Das Proposal führt den
Mehrbenutzerbetrieb ausdrücklich unter „nicht enthalten", deshalb steht sie
nicht als offene Aufgabe dieses Changes.*

*Zu den verbleibenden sieben Aufgaben liegen ausführbare Prüfprotokolle
bereit: `tests/protokolle/`.*

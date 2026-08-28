## Why

Die App lief zum ersten Mal auf einer Nextcloud. Was dabei auffiel, ließ sich
durchweg im Code nachweisen — es war nichts dabei, das nur ein Eindruck gewesen
wäre.

**Der Unterrichtsmodus blieb leer.** Der Startdialog füllt Klasse und Kontext
ausschließlich aus den Lehraufträgen. Ohne Auftrag sind beide Felder leer, und
das sieht aus wie ein kaputter Bildschirm statt nach einem fehlenden Eintrag.
Angelegt wurde ein Auftrag über ein Formular, das **jede Zeichenkette** als
Nextcloud-Kennung annahm: ein Auftrag auf „Frau Müller" statt auf `mueller`
landet in der Datenbank, taucht aber in keiner Ansicht auf, weil jede Abfrage
nach der eigenen Kennung filtert.

**Die Verwaltungsoberfläche hatte keinen Arbeitsgrund und keine Rollbalken.**
`templates/index.php` hängte die App frei in `#content` statt in `#app-content`.
Erst diese Hülle trägt den hellen Grund, die abgerundete Fläche und
`overflow-y: auto`; `#content` selbst steht auf `overflow: hidden`. Was über die
Fensterhöhe hinausragte, war damit nicht bloß unschön, sondern unerreichbar.

**Kein PDF ließ sich herunterladen.** Beide Druckendpunkte werden über einen
gewöhnlichen Verweis geöffnet, trugen aber kein `NoCSRFRequired`. Eine
Seitennavigation führt kein `requesttoken` mit — die SecurityMiddleware wies ab,
bevor `PdfService` überhaupt lief. Dass die Erzeugung selbst geprüft war, half
nicht: die Antwort kam nie dort an.

**Schrift stand hell auf hellem Grund.** 34 Regeln nahmen Text über `opacity`
zurück, mehrere auf .55 bis .65. Auf weißem Grund landet `#222` bei .6 auf rund
`#8e8e8e` — 3,0:1 statt der nötigen 4,5:1. Die Werte stapelten sich zudem: ein
Hinweisblock auf .8 mit einem `<small>` auf .7 darin steht effektiv auf .56.
Im Erfassungsbildschirm kam eine zweite Ursache dazu — er bezog Themenfarben
**ohne Rückfallwert**, läuft aber über `RENDER_AS_BASE`, wo die
Nextcloud-Variablen nicht zwingend definiert sind.

**Die Schnellmarker waren nicht zu bedienen.** Fünf Spalten nebeneinander, die
Zuordnungen als `select multiple size="4"`. Eine native Mehrfachauswahl verlangt
auf einem Tablet gedrückte Zusatztasten — auf dem Gerät, für das der Bildschirm
gedacht ist, praktisch unbedienbar. Die Beschriftungen lagen nur als
`aria-label` vor, sichtbar war nichts.

**Lehraufträge waren im Regelfall am teuersten.** An einer Grundschule beobachtet
die Klassenlehrkraft in allen Fächern. Einzeln angelegt sind das sieben Formulare
mit siebenmal derselben Kennung — genau dort entstehen die Tippfehler von oben.

**Klassen ließen sich anlegen, aber nicht löschen** — auch namenlose nicht: das
Feld war leer absendbar.

**Und die Auswertungen ließen sich nicht beurteilen.** Nicht, weil sie falsch
wären, sondern weil auf leerer Datenbank bei jedem Kind „noch nie beobachtet"
steht und in jeder Zelle „·". Dasselbe gilt für Darstellungsfehler, die erst mit
Inhalt entstehen — eine Filterzeile mit langen Kindernamen bricht ohne Daten nie
um.

## What Changes

- **Lehraufträge als Satz.** Ein Ankreuzraster über alle Kontexte einer Klasse
  mit „alle" und „keine", die eigene Kennung vorbelegt. Unbekannte Kennungen
  werden gegen Nextcloud geprüft und abgewiesen. Ein Kontext, in dem schon
  Stunden gehalten wurden, wird beim Lösen des Satzes **behalten** — ohne
  Lehrauftrag käme die Lehrkraft an die eigenen Beobachtungen nicht mehr heran.
- **Der leere Startdialog erklärt sich.** Er nennt die Kennung, auf die
  vergeblich gesucht wurde, und verweist auf die Stelle in der Verwaltung.
- **Nebentext bekommt Farbe statt Deckkraft.** Ein Token `--ke-leise` auf Basis
  von `--color-text-maxcontrast`, in beiden Themen gegen den Arbeitsgrund
  geprüft. Deckkraft bleibt nur, wo sie ein ganzes Bedienelement zurücknimmt und
  ein zweites Merkmal die Aussage trägt.
- **Der Erfassungsbildschirm bezieht jede Themenfarbe über einen Token mit
  Rückfall.** Er ist der Einstiegspunkt ohne verlässliches Nextcloud-CSS.
- **Die Verwaltungsoberfläche hängt in `#app-content`.**
- **Die Druckendpunkte tragen `NoCSRFRequired`.**
- **Schnellmarker als Karte je Marker.** Textfeld über volle Breite, die
  Zuordnungen als offen stehende Ankreuzfelder mit 44 px Zeilenhöhe, benannte
  Gruppen statt `aria-label`. Der Zähler „x von 6 sichtbar" steht oben, wo die
  Grenze zu beachten ist, nicht unten bei den Knöpfen.
- **Klassen lassen sich löschen** — aber nur ohne Beobachtungen und Stunden.
  Namenlose Klassen entstehen nicht mehr.
- **Der Erfassungsbildschirm trägt das Querformat mit Tastatur.** Die sichtbare
  Höhe wird aus `window.visualViewport` nachgeführt; unter 560 px greift eine
  flache Anordnung, die umlegt statt zu verkleinern — die 44 px bleiben.
- **`occ kidseye:beispieldaten`** erzeugt eine vollständige Beispielklasse mit
  Verlauf und räumt sie wieder weg.
- **Nicht enthalten:** Der Lücken-Radar und die Kompetenz-Übersicht bleiben
  inhaltlich, wie sie sind. Ob sie taugen, ist erst mit Daten zu entscheiden —
  das war der Befund, nicht ein Urteil über die Darstellung.

## Capabilities

### New Capabilities

- `lesbarkeit-und-kontrast`: Textfarben, Kontrast und Themenrückfälle in beiden
  Einstiegspunkten; Bedienbarkeit des Erfassungsbildschirms bei knapper Höhe.
- `stammdatenpflege`: Anlegen und Löschen von Klassen, Lehraufträge als Satz je
  Lehrkraft und Klasse.
- `beispieldaten`: Erzeugter Datenbestand, mit dem sich Auswertungen und
  Darstellung ohne Echtbetrieb beurteilen lassen.

### Modified Capabilities

Keine. `openspec/specs/` ist weiterhin leer.

## Impact

| Bereich | Betroffen |
|---|---|
| Neu | `lib/Service/BeispieldatenService.php`, `lib/Command/Beispieldaten.php` |
| Geändert (PHP) | `PageController` unberührt; `AuswertungController` (CSRF), `VerwaltungController` (Kennungsprüfung, Satz, Löschen), `StammdatenService`, `StundeService`, `BeobachtungService` |
| Geändert (Oberfläche) | `templates/index.php`, `css/kidseye.css`, `Stammdaten.vue`, `MarkerVerwaltung.vue`, `Unterricht.vue`, `StundeStart.vue`, `Auswertung.vue`, `Einrichtung.vue`, `Inbox.vue`, `Klassenbild.vue`, `src/api.js` |
| Geändert (Routen) | `klasseLoeschen` (DELETE), `lehrauftraegeSetzen` (PUT) |
| Tests | `tests/js/formular.spec.js` — drei neue Prüfungen; eine bestehende Prüfung hielt die abgeblendete Beschriftung fest und wandert mit |
| Nicht betroffen | Datenmodell, Migrationen, Kompetenzrahmen, Offline-Warteschlange |
| Abhängigkeiten | Keine neuen |

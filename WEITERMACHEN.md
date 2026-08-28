# Weitermachen

Übergabe für die nächste Sitzung. Stand: **28. August 2026**.

Wenn du hier neu einsteigst, lies zuerst die drei Abschnitte
[Wo es klemmt](#wo-es-klemmt), [Was als Nächstes dran ist](#was-als-nächstes-dran-ist)
und [Was du wissen musst, bevor du etwas änderst](#was-du-wissen-musst-bevor-du-etwas-änderst).
Alles Weitere ist Nachschlagewerk.

---

## In einem Satz

kidseye ist eine Nextcloud-App zur Schülerbeobachtung in der Grundschule.
Sie hat am 28. August zum ersten Mal auf einer laufenden Nextcloud gestanden.
Was dabei sichtbar wurde, ist behoben — **geprüft ist die Behebung noch nicht.**

---

## Wo es klemmt

**Die Befunde des ersten Durchgangs sind umgesetzt, aber nicht gegengeprüft.**
Der Code ist geändert, die Tests sind grün, das Bündel ist gebaut. Ob es auf
der Instanz tut, was es soll, hat noch niemand gesehen. Das ist der nächste
Schritt und die einzige verbliebene offene Flanke.

Der Durchgang hat sieben Befunde gebracht — nachzulesen mit Begründung in
`openspec/changes/erste-deployment-befunde/`:

| Befund | Ursache |
|---|---|
| Unterrichtsmodus: Startdialog blieb leer | Das Lehrauftrags-Formular nahm **jede Zeichenkette** als Nextcloud-Kennung. Ein Auftrag auf „Frau Müller" statt `mueller` steht in der Datenbank, taucht aber in keiner Ansicht auf — jede Abfrage filtert nach der eigenen Kennung |
| Verwaltung: kein Arbeitsgrund, keine Rollbalken | `templates/index.php` hängte die App frei in `#content` statt in `#app-content`. Erst diese Hülle trägt hellen Grund und `overflow-y: auto`; `#content` steht auf `overflow: hidden` |
| Kein PDF ließ sich laden | Beide Druckendpunkte werden über einen Verweis geöffnet, trugen aber kein `NoCSRFRequired`. Eine Seitennavigation führt kein `requesttoken` mit — abgewiesen, bevor `PdfService` überhaupt lief |
| Helle Schrift auf hellem Grund | 34 Regeln blendeten Text über `opacity` ab. Dazu bezog der Erfassungsbildschirm Themenfarben **ohne Rückfallwert**, läuft aber über `RENDER_AS_BASE` |
| Schnellmarker unbedienbar | Zuordnungen als `select multiple` — auf dem iPad braucht Mehrfachauswahl gedrückte Zusatztasten |
| Lehraufträge im Regelfall am teuersten | Die Klassenlehrkraft beobachtet in allen Fächern; einzeln waren das sieben Formulare mit siebenmal derselben Kennung |
| Klassen: namenlos anlegbar, nicht löschbar | Keine Prüfung, kein Löschweg |

**Der lehrreiche Teil: keiner dieser Befunde hat einen Test rot gemacht.**
Alle 132 Prüfungen waren grün, während der Unterrichtsmodus leer blieb, kein
PDF ankam und Nebentext unter dem Kontrastminimum stand. Dasselbe Muster wie
beim Manifest-Befund vom 21. August, bei dem `toContain` grün blieb, während
der Wert kaputt war. **Die Prüfungen dieses Projekts lesen Quelltext; was sie
nicht ausdrücklich behaupten, prüfen sie nicht.** Drei neue Prüfungen decken
jetzt die Fehlerklassen ab, die sich wiederholen können — Kontrast, fehlende
Rückfallwerte, Deckkraft auf Text.

**Neu: `occ kidseye:beispieldaten`.** Der zweite Befund war kein Fehler,
sondern ein blinder Fleck: Lücken-Radar und Kompetenz-Übersicht ließen sich auf
leerer Datenbank nicht beurteilen — dort steht bei jedem Kind „noch nie
beobachtet" und in jeder Zelle „·".

```bash
occ kidseye:beispieldaten --nutzer <kennung>   # Klasse „3d (Beispiel)"
occ kidseye:beispieldaten --entfernen          # räumt restlos weg
```

22 Kinder, acht Wochen Stunden, Lehraufträge für alle Kontexte. Geschrieben
wird über `BeobachtungService::erfassen()` — denselben Pfad wie ein Tap auf dem
Gerät, nicht per Direkteinfügung. Die Verteilung ist **absichtlich schief**:
zwei Kinder ohne jede Beobachtung, drei mit sehr wenigen, Marker ungleich über
die Dimensionen, ein Fünftel mit Freitext. Eine Gleichverteilung wäre so
nutzlos wie eine leere Datenbank.

---

## Was als Nächstes dran ist

In dieser Reihenfolge:

1. **Die Behebungen gegenprüfen** — `openspec/changes/erste-deployment-befunde/tasks.md`,
   Abschnitt 9.6 bis 9.12. Sieben Punkte, rund 45 Minuten. Vorher die
   Beispieldaten erzeugen, sonst ist die Hälfte davon nicht zu sehen.
2. **Entscheiden, ob Lücken-Radar und Kompetenz-Übersicht taugen** (9.12).
   Das ist der einzige Punkt ohne erwartetes Ergebnis — er stand bisher nur
   deshalb offen, weil er ohne Daten nicht zu entscheiden war. Jetzt ist er es.
3. **Die vier Sichtprüfungen aus `formularelemente-vereinheitlichen`** (6.3–6.6).
   Sie brauchten dieselbe gefüllte Datenbank und sind damit fällig.
4. **Geräteprüfung auf dem iPad** — `tests/protokolle/geraetepruefung.md`,
   rund 30 Minuten. Abschnitt D misst das Zeitbudget: **im Querformat mit
   ausgefahrener Tastatur messen**, das ist die Lage, für die der Bildschirm
   jetzt gebaut ist.
5. **Marker-Wortlisten von der Lehrkraft holen** — die 42 mitgelieferten sind
   ausdrücklich nur Vorschläge. Erfassungsbogen in
   [`tests/protokolle/praxistest.md`](tests/protokolle/praxistest.md).
6. **Datenschutz klären** — ausfüllfertige Vorlage in
   [`tests/protokolle/datenschutz-vorlage.md`](tests/protokolle/datenschutz-vorlage.md).

Schritt 1 und 2 sind der eigentliche Lackmustest.

---

## Was du wissen musst, bevor du etwas änderst

**Kein PHPUnit lokal.** PHP 8.3 ist inzwischen da, aber ohne `dom` und
`xmlwriter` — PHPUnit läuft nicht damit. Der Docker-Weg bleibt:

```bash
docker run --rm -v "$PWD":/app -w /app php:8.3-cli sh -c '
  php -r "copy(\"https://phar.phpunit.de/phpunit-10.5.phar\",\"/tmp/p.phar\");"
  php /tmp/p.phar --configuration phpunit-regeln.xml'
```

Für einen schnellen Zwischenstand reicht `php -l` lokal.

**Die Regeln stehen in den Diensten, nicht in der Oberfläche.** Wer eine
Prüfung umgeht, indem er sie im Frontend ändert, hat sie nicht umgangen —
`RahmenService::verlangeBewertbar()`, `ZugriffService`,
`StammdatenService::klasseAnlegen()` und `klasseLoeschen()` werfen weiterhin.
Ein gesperrter Knopf ist ein Hinweis, keine Prüfung.

**Drei Dinge sind bewusst so und keine Nachlässigkeit:**

- Überfachliche Kompetenzen tragen **keine Skala**. Das Kerncurriculum sagt
  selbst, dass sie sich „weitgehend einer Normierung und empirischen
  Überprüfung" entziehen.
- Beobachtung ↔ Kompetenzknoten ist **n:m**, und Bildungsstandards und
  Inhaltsfelder sind **zwei gekreuzte Achsen**, keine Hierarchie.
- Die Stufe `klassenteam` ist im Datenmodell vorhanden, in der Oberfläche
  aber **nicht erreichbar**. Einzelbetrieb war ausdrücklich gewünscht.

Die Begründungen im Volltext: `add-beobachtungs-workflow/design.md`, D1 bis
D17; die neuen D18 bis D24 in `erste-deployment-befunde/design.md`.

**Stolperfallen, die schon einmal Zeit gekostet haben:**

- **Deckkraft ist kein Werkzeug, um Text abzublenden** (D18). `opacity` mischt
  gegen den Elternhintergrund: `#222` auf Weiß bei `.6` ergibt rund `#8e8e8e`
  — 3,0:1 statt 4,5:1, und der Wert ist am Geschriebenen nicht abzulesen.
  Schlimmer: die Werte stapeln sich über Schachtelungsebenen hinweg. Nebentext
  trägt `var(--ke-leise)`. Eine Prüfung schlägt an, wenn wieder eine
  Deckkraftregel auftaucht; die zwei erlaubten Fälle stehen dort namentlich.
- **Im Erfassungsbildschirm jede Farbe mit Rückfall** (D19). Er läuft über
  `RENDER_AS_BASE`; dort ist `--color-main-text` nicht verlässlich definiert,
  und eine Farbangabe ohne Rückfall fällt still auf die geerbte Farbe zurück.
  Alle Farben laufen über die `--ke-*`-Token aus `css/kidseye.css`.
- **`100dvh` deckt die Bildschirmtastatur nicht ab** (D23). Es folgt der
  Browserleiste, nicht der Tastatur. Die sichtbare Höhe kommt aus
  `window.visualViewport` und steht als `--ke-sicht`; `--ke-unten` ist, was
  unten verdeckt ist.
- **XML-Kommentare dürfen kein `--` enthalten.** Ein `occ app:enable --force`
  im Kommentar der `info.xml` macht die Datei ungültig.
- **Relative Pfade im Manifest lösen gegen den Ort des Manifests auf**, nicht
  gegen die App-Wurzel. Deshalb kommen `start_url`, `scope` und die
  Symbolpfade aus dem `PageController`.
- **Prüfungen mit `toContain` auf Pfaden sind zu schwach.** Der falsche Wert
  `../apps/kidseye/unterricht` enthält `/apps/kidseye/unterricht`.
- **`max-version` in der `info.xml` lässt sich nicht weglassen** — das
  offizielle Schema führt sie als `use="required"`. Steht auf 35.
- **Ein Endpunkt, der über einen Verweis geöffnet wird, braucht
  `NoCSRFRequired`.** Eine Seitennavigation führt kein `requesttoken` mit.
  Betrifft die beiden Druckendpunkte; wer einen dritten anlegt, denkt daran.

---

## Was gebaut ist

```
lib/          ~8.100 Zeilen PHP   25 Tabellen, 22 Dienste, 5 Controller, 5 occ-Befehle
src/          ~3.200 Zeilen       Vue 2, eigenes Bundle, keine @nextcloud/vue-Komponenten
css/          ~270 Zeilen         ausgelieferte Stildatei, beide Einstiegspunkte
tests/        ~2.400 Zeilen       135 JS-Tests, 56 PHP-Tests, Lasttest, 3 Protokolle
data/rahmen/  231 Knoten          Hessisches Kerncurriculum als JSON
openspec/                         4 Changes, 16 Capabilities
```

**Der tragende Mechanismus:** Ein Schnellmarker trägt seine
Kompetenzzuordnung in der Definition. Zusammen mit dem Stundenkontext ergibt
ein einziger Fingertipp eine auf beiden Achsen zugeordnete, fertige
Beobachtung — rund 70 Prozent brauchen deshalb keine Nacharbeit. Das ist der
Grund, warum der Rest funktioniert; wer daran etwas ändert, ändert alles.

**Verifiziert (28. August):**

| | |
|---|---|
| JS-Tests | 135 grün (`npm test`) |
| PHP-Tests | 56 grün, 269 Assertions (Docker) |
| PHP-Syntax | 38 von 38 Dateien in `lib/` |
| Build | beide Bündel, keine Fehler |
| `info.xml` | wohlgeformt, Schema am 21. August validiert |
| Lasttest | 8 Abfrageformen, alle unter 8 ms beim Jahresbestand |

**Nicht verifiziert:** dass die Behebungen auf der Instanz tun, was sie sollen.

---

## Aufgabenstand

**`erste-deployment-befunde`** — 59 von 66 erledigt. Offen sind 9.6 bis 9.12:
die Gegenprüfung auf der Instanz und dem iPad. Alles davon braucht die
laufende Nextcloud und die erzeugten Beispieldaten.

**`installations-und-geraetehaertung`** — 43 von 45. Offen 8.5 und 8.6. Der
erste Durchgang hat stattgefunden, ist aber nicht protokolliert und der
Rückgabewert von `occ kidseye:pruefen` nicht festgehalten.

**`formularelemente-vereinheitlichen`** — 30 von 34. Offen 6.3 bis 6.6, alles
Sichtprüfungen. Zwei Aufgaben dieses Changes sind inzwischen überholt und im
Dokument mit Verweis vermerkt: die Beschriftung steht nicht mehr auf
`opacity: .8` (3.1), und die beiden Mehrfachauswahlen sind doch ersetzt (2.7 —
der Vorbehalt „gehört in einen eigenen Change" ist eingelöst).

**`add-beobachtungs-workflow`** — 93 von 100, 1 teilweise (0.4), 6 offen. Alle
verbleibenden brauchen etwas außerhalb der Entwicklung:

| | braucht |
|---|---|
| 0.2, 0.2b, 9.5 | ein iPad mit Sperrbildschirm und die laufende Nextcloud |
| 9.8 | zwei Wochen Unterricht |
| 0.5, 0.6 | die Wortlisten der Lehrkraft |
| 0.4 | Unterschrift der Datenschutzbeauftragung |

Ein Punkt gehört zu v2 und steht deshalb nicht in der Aufgabenliste: Die
**Heatmap kippt bei rund 70.000 Beobachtungen** über 150 ms. Im Einzelbetrieb
(~3.800 im Jahr) unkritisch, aber bevor `klassenteam` freigeschaltet wird,
braucht sie eine vorberechnete Zwischentabelle. Begründung und Messwerte: D17.

---

## Woher die fachliche Grundlage kommt

Nichts davon ist geraten. Die Quellen liegen im Netz und sind im Code zitiert:

- **Kerncurricula Primarstufe**, Hessisches Kultusministerium 2011 — für
  Deutsch, Mathematik, Sachunterricht, Kunst und Ethik aus den PDFs
  extrahiert. Ergebnisse in `data/rahmen/hessen-primarstufe-2011.json`,
  geprüft durch `tests/js/rahmen.spec.js`.
- **SchDSV** — Verordnung über die Verarbeitung personenbezogener Daten in
  Schulen, 4. Februar 2009. § 10 Abs. 1/3/4 und § 3 Abs. 2 sind im
  `AufbewahrungService` wörtlich zitiert.
- **Rückmeldung einer Grundschullehrkraft**, Juli 2026. Zehn Annahmen
  abgefragt, sechs bestätigt, vier korrigiert — Freiarbeit als eigener
  Kontext, das Klassenbild und der Einzelbetrieb kommen alle daher.

---

## Wenn etwas nicht läuft

Erste Anlaufstelle ist die Fehlertabelle am Ende von
[`docs/INSTALLATION.md`](docs/INSTALLATION.md). Die häufigsten Fälle:

| Symptom | Ursache |
|---|---|
| Weiße Seite | `js/` fehlt → `npm run build` |
| Startdialog leer, „kein Lehrauftrag" | Der Schritt, den man am ehesten vergisst. Seit dem 28. August nennt die Meldung die gesuchte Kennung — stimmt sie nicht mit deinem Anmeldenamen überein, liegt der Auftrag auf einer anderen |
| Änderungen sind nicht zu sehen | Wird die App **kopiert** statt symgelinkt, müssen `lib/`, `templates/`, `css/` und `js/` neu rüber. Danach im Browser hart neu laden |
| Kein Foto-Knopf | Gruppenordner fehlt → Reiter „Einrichtung" in der App |
| Zähler `⟳` geht nicht auf 0 | Kein Netz oder Sitzung abgelaufen. Die Beobachtungen sind **nicht verloren** |

Protokoll:

```bash
sudo -u www-data php occ log:tail -f | grep kidseye
```

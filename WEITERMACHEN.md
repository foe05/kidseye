# Weitermachen

Übergabe für die nächste Sitzung. Stand: **21. August 2026**.

Wenn du hier neu einsteigst, lies zuerst die drei Abschnitte
[Wo es klemmt](#wo-es-klemmt), [Was als Nächstes dran ist](#was-als-nächstes-dran-ist)
und [Was du wissen musst, bevor du etwas änderst](#was-du-wissen-musst-bevor-du-etwas-änderst).
Alles Weitere ist Nachschlagewerk.

---

## In einem Satz

kidseye ist eine Nextcloud-App zur Schülerbeobachtung in der Grundschule.
Der Code ist vollständig, getestet und gepusht — aber **noch nie auf einer
laufenden Nextcloud installiert worden.** Das ist der nächste Schritt.

---

## Wo es klemmt

**Die App ist noch nie auf einer laufenden Nextcloud installiert worden.**
Das ist die einzige verbliebene offene Flanke — und die wichtigste
Information dieses Dokuments.

Am 21. August ist der Change `installations-und-geraetehaertung` umgesetzt
worden. Er hat drei Fehler behoben, die im Repo nachweisbar waren und die
Erstinstallation gekostet hätten:

| Befund | War | Ist |
|---|---|---|
| Symbol für den Home-Bildschirm | `img/manifest.json` verlangte `favicon-touch.png`, die Datei fehlte. iOS hätte einen Bildschirmabzug als Symbol vergeben | Symbol liegt bei, erzeugt mit `tools/symbol-erzeugen.mjs` |
| `start_url` und `scope` | Standen als `../apps/kidseye/…` im Manifest und lösten gegen dessen Ort zu **`/apps/kidseye/apps/kidseye/unterricht`** auf — das Symbol wäre auf einer 404 gelandet | Kommen aus `PageController::manifest()` über `linkToRoute`, Route `/manifest.webmanifest` |
| Sitzungsablauf | Wurde nur an `status === 401 \|\| 403` erkannt. Eine Umleitung auf die Anmeldeseite (HTML mit 200) galt als geglückte Übertragung von null Einträgen | Drei Merkmale in `istAbgemeldet()`, dazu `X-Requested-With` als Vorbeugung. Der Hinweis unterscheidet jetzt „nicht angemeldet" von „keine Verbindung" |

Der zweite Befund ist der lehrreiche: Der bestehende Test prüfte
`toContain('/apps/kidseye/unterricht')` — und der falsche Wert **enthält**
diese Zeichenkette. Der Test war grün, während der Wert kaputt war.

**Neu: `occ kidseye:pruefen`.** Ein Befehl, der nichts verändert und jede
Betriebsvoraussetzung einzeln ausweist — Tabellen, Rahmen, Schuljahr,
Gruppen, Lehraufträge, Ablage, Vollbild-Option, Symbol. Zu jedem offenen
Punkt steht die Abhilfe daneben. Rückgabewert 0, wenn alles erfüllt ist,
sonst 1. Das ist ab sofort der erste Schritt nach `occ app:enable kidseye`.

Der Codestand ist seit dem 11. August vollständig auf `main`. Der frühere
Hinweis „zwei Commits fehlen auf `main`" ist erledigt: der Zweig
`add-beobachtungs-workflow` wurde auf `main` rebasiert und gemerged.

Dabei war eine Doppelarbeit aufzulösen — auf zwei Rechnern war unabhängig
derselbe Fehler repariert worden:

| | |
|---|---|
| `\OC_App::getAppPath()` | Behalten wurde die Variante über **Dependency Injection** (`IAppManager` im Konstruktor, `mitgelieferterPfad()` als Instanzmethode), nicht die über `\OCP\Server::get()`. Die beiden Aufrufer in `lib/Command/` sind entsprechend umgestellt. |
| `package-lock.json` | Kam mit dem Build-Setup und listete noch `@nextcloud/vue` und `vue-router`, die zwischenzeitlich aus `package.json` geflogen waren. Neu erzeugt; beide stehen jetzt korrekt als Peer-Abhängigkeit von `@nextcloud/dialogs` im Baum. |

Verifiziert nach dem Zusammenführen: `npm ci` (Exit 0), `npm test`
(65 grün), `npm run build` (erzeugt `kidseye-main.js` und
`kidseye-unterricht.js`). PHP-Tests nicht erneut gelaufen — kein PHP in der
Umgebung, siehe unten.

---

## Was als Nächstes dran ist

In dieser Reihenfolge:

1. **Auf der Nextcloud installieren** — Anleitung: [`docs/INSTALLATION.md`](docs/INSTALLATION.md).
   Zwölf Schritte, rund eine Stunde. Vorher Datenbank sichern, die App legt
   **25** Tabellen an (die frühere Angabe „21" war falsch).
   Nach dem Aktivieren `occ kidseye:pruefen` — der Befehl sagt, was noch fehlt.
2. **Geräteprüfung auf dem iPad** — [`tests/protokolle/geraetepruefung.md`](tests/protokolle/geraetepruefung.md).
   Rund 30 Minuten. Das ist der Test, der über den gesamten
   Erfassungs-Workflow entscheidet: startet das Symbol im Vollbild, übersteht
   die Warteschlange einen Sitzungsablauf, bleibt die Erfassung unter zehn
   Sekunden?
3. **Marker-Wortlisten von der Lehrkraft holen** — die 42 mitgelieferten sind
   ausdrücklich nur Vorschläge. Erfassungsbogen in
   [`tests/protokolle/praxistest.md`](tests/protokolle/praxistest.md).
4. **Datenschutz klären** — ausfüllfertige Vorlage in
   [`tests/protokolle/datenschutz-vorlage.md`](tests/protokolle/datenschutz-vorlage.md),
   fünf Entscheidungsfragen mit Ankreuzfeldern.

Schritt 1 und 2 sind der eigentliche Lackmustest. Alles davor ist Theorie.

---

## Was du wissen musst, bevor du etwas änderst

**Kein PHP in der Entwicklungsumgebung.** Die PHP-Tests laufen über Docker:

```bash
docker run --rm -v "$PWD":/app -w /app php:8.3-cli sh -c '
  php -r "copy(\"https://phar.phpunit.de/phpunit-10.5.phar\",\"/tmp/p.phar\");"
  php /tmp/p.phar --configuration phpunit-regeln.xml'
```

Das war anfangs übersehen worden; ein Teil der Arbeit galt zunächst als
„nicht prüfbar", war es aber.

**Die Regeln stehen in den Diensten, nicht in der Oberfläche.** Wer eine
Prüfung umgeht, indem er sie im Frontend ändert, hat sie nicht umgangen —
`RahmenService::verlangeBewertbar()` und `ZugriffService` werfen weiterhin.

**Drei Dinge sind bewusst so und keine Nachlässigkeit:**

- Überfachliche Kompetenzen tragen **keine Skala**. Das Kerncurriculum sagt
  selbst, dass sie sich „weitgehend einer Normierung und empirischen
  Überprüfung" entziehen. Wer dort eine Bewertung einbaut, widerspricht der
  fachlichen Grundlage.
- Beobachtung ↔ Kompetenzknoten ist **n:m**, und Bildungsstandards und
  Inhaltsfelder sind **zwei gekreuzte Achsen**, keine Hierarchie. Auch das
  steht wörtlich im Kerncurriculum.
- Die Stufe `klassenteam` ist im Datenmodell vorhanden, in der Oberfläche
  aber **nicht erreichbar**. Einzelbetrieb war ausdrücklich gewünscht; das
  Modell bleibt dreistufig, damit v2 keine Migration über zehntausende
  Datensätze braucht.

Die Begründungen im Volltext:
`openspec/changes/add-beobachtungs-workflow/design.md`, Entscheidungen D1
bis D17.

**Stolperfallen, die schon einmal Zeit gekostet haben:**

- XML-Kommentare dürfen kein `--` enthalten. Ein `occ app:enable --force` im
  Kommentar der `info.xml` macht die Datei ungültig.
- `npm ci` scheiterte früher an Peer-Dependencies. Seit die `package-lock.json`
  im Repo liegt und mit `package.json` abgeglichen ist, läuft es durch —
  `--legacy-peer-deps` ist nicht mehr nötig.
- **Relative Pfade im Manifest lösen gegen den Ort des Manifests auf**, nicht
  gegen die App-Wurzel. Deshalb kommen `start_url`, `scope` und die
  Symbolpfade aus dem `PageController` und nicht aus der JSON-Datei. Was dort
  noch steht — Name, Farben, Anzeigeart, Symbolliste — ist instanzunabhängig.
- **Prüfungen mit `toContain` auf Pfaden sind zu schwach.** Der falsche Wert
  `../apps/kidseye/unterricht` enthält `/apps/kidseye/unterricht`. Wo es auf
  einen Pfad ankommt, gegen die aufgelöste Adresse prüfen, nicht gegen eine
  Teilzeichenkette.
- `max-version` in der `info.xml` lässt sich **nicht weglassen** — das
  offizielle Schema führt sie als `use="required"`. Aktuell steht sie auf 35,
  geprüft gegen die Critical Changes von 34 und 35.

---

## Was gebaut ist

```
lib/          7.256 Zeilen PHP    25 Tabellen, 21 Dienste, 5 Controller, 4 occ-Befehle
src/          2.751 Zeilen        Vue 2, eigenes Bundle, keine @nextcloud/vue-Komponenten
tests/        2.484 Zeilen        107 JS-Tests, 56 PHP-Tests, Lasttest, 3 Protokolle
data/rahmen/  231 Knoten          Hessisches Kerncurriculum als JSON
openspec/                         Entwurf, 9 Capabilities, 101 Aufgaben
```

**Der tragende Mechanismus:** Ein Schnellmarker trägt seine
Kompetenzzuordnung in der Definition. Zusammen mit dem Stundenkontext ergibt
ein einziger Fingertipp eine auf beiden Achsen zugeordnete, fertige
Beobachtung — rund 70 Prozent brauchen deshalb keine Nacharbeit. Das ist der
Grund, warum der Rest funktioniert; wer daran etwas ändert, ändert alles.

**Verifiziert:**

| | |
|---|---|
| JS-Tests | 107 grün (`npm test`) |
| PHP-Tests | 56 grün, 269 Assertions (Docker, siehe oben) |
| PHP-Syntax | 45 von 45 Dateien |
| Lasttest | 8 Abfrageformen, alle unter 8 ms beim Jahresbestand (`python3 tests/last/lasttest.py`) |
| PDF | Erzeugt, von `pypdf` gegengelesen: 4 Seiten, Umlaute korrekt |
| `info.xml` | Gegen das Schema von apps.nextcloud.com validiert |

**Nicht verifiziert:** dass irgendetwas davon auf einer echten Nextcloud
läuft.

---

## Aufgabenstand

**`installations-und-geraetehaertung`** — 40 von 42 erledigt. Offen sind nur
8.5 und 8.6: der Durchgang auf einer echten Nextcloud und die Geräteprüfung
auf dem iPad. Beide brauchen Hardware.

**`formularelemente-vereinheitlichen`** — 30 von 34 erledigt. Die App liefert
jetzt eine eigene Stildatei aus (`css/kidseye.css`, über `Util::addStyle` an
beiden Einstiegspunkten); die neun verschiedenen Mindesthöhen sind auf 44 px
zurückgeführt, mit einer namentlich geführten Ausnahme (`.kb-gruppe`, 28 px).
Offen sind nur 6.3 bis 6.6 — Sichtprüfungen, Protokoll in
[`tests/protokolle/formulardarstellung.md`](tests/protokolle/formulardarstellung.md).

Zwei Befunde daraus gehören hierher, weil sie beide lautlos gewesen wären:

- **Vue 2 ersetzt das Mount-Element.** Aus `<div id="kidseye-main">` in
  `templates/` wird beim Einhängen das Wurzelelement der Komponente — ohne die
  ID. Ein Selektor `#kidseye-main …` hätte zur Laufzeit auf nichts getroffen.
  Die IDs stehen deshalb an den Wurzelelementen von `App.vue` und
  `Unterricht.vue`.
- **Spezifität ist hier eine Rechnung, keine Geschmacksfrage.** Alle Regeln
  stehen unter `:where(#kidseye-main, #kidseye-unterricht)`, das null
  Spezifität beiträgt. Ein Wurzelselektor mit ID (1,0,1) schlüge jeden scoped
  Komponentenstil (0,2,0) — und damit ausgerechnet den durchgestalteten
  Erfassungsbildschirm.

**`add-beobachtungs-workflow`** — 93 von 101 erledigt, 1 teilweise, 6 offen —
nachzulesen in `openspec/changes/add-beobachtungs-workflow/tasks.md`.

Die verbleibenden sieben brauchen alle etwas, das außerhalb der Entwicklung
liegt:

| | braucht |
|---|---|
| 0.2, 0.2b, 9.5 | ein iPad mit Sperrbildschirm und die laufende Nextcloud |
| 9.8 | zwei Wochen Unterricht |
| 0.5, 0.6 | die Wortlisten der Lehrkraft |
| 0.4 | Unterschrift der Datenschutzbeauftragung |

Für jedes davon liegt ein Protokoll unter `tests/protokolle/`. Sie sind
Ausführung, keine Entwicklung.

Ein weiterer Punkt gehört zu v2 und steht deshalb nicht in der Aufgabenliste:
Die **Heatmap kippt bei rund 70.000 Beobachtungen** über 150 ms. Im
Einzelbetrieb (~3.800 im Jahr) unkritisch, aber bevor `klassenteam` und damit
der Mehrbenutzerbetrieb freigeschaltet wird, braucht sie eine vorberechnete
Zwischentabelle. Begründung und Messwerte: D17 in `design.md`.

---

## Woher die fachliche Grundlage kommt

Nichts davon ist geraten. Die Quellen liegen im Netz und sind im Code zitiert:

- **Kerncurricula Primarstufe**, Hessisches Kultusministerium 2011 — für
  Deutsch, Mathematik, Sachunterricht, Kunst und Ethik aus den PDFs
  extrahiert. Der Extraktor liegt nicht im Repo; die Ergebnisse in
  `data/rahmen/hessen-primarstufe-2011.json`, geprüft durch
  `tests/js/rahmen.spec.js`.
- **SchDSV** — Verordnung über die Verarbeitung personenbezogener Daten in
  Schulen, 4. Februar 2009. § 10 Abs. 1/3/4 und § 3 Abs. 2 sind im
  `AufbewahrungService` wörtlich zitiert und begründen die Voreinstellungen.
- **Rückmeldung einer Grundschullehrkraft**, Juli 2026. Zehn Annahmen
  abgefragt, sechs bestätigt, vier korrigiert — die Korrekturen stehen in der
  Tabelle „Rückmeldung aus der Praxis" ganz oben in `design.md`. Freiarbeit
  als eigener Kontext, das Klassenbild und der Einzelbetrieb kommen alle
  daher.

---

## Wenn etwas nicht läuft

Erste Anlaufstelle ist die Fehlertabelle am Ende von
[`docs/INSTALLATION.md`](docs/INSTALLATION.md). Die häufigsten Fälle:

| Symptom | Ursache |
|---|---|
| Weiße Seite | `js/` fehlt → `npm run build` |
| „kein Lehrauftrag" | Der Schritt, den man am ehesten vergisst — Anleitung Schritt 7 |
| Kein Foto-Knopf | Gruppenordner fehlt → Reiter „Einrichtung" in der App |
| Zähler `⟳` geht nicht auf 0 | Kein Netz oder Sitzung abgelaufen. Die Beobachtungen sind **nicht verloren** |

Protokoll:

```bash
sudo -u www-data php occ log:tail -f | grep kidseye
```

# Weitermachen

Übergabe für die nächste Sitzung. Stand: **3. August 2026**.

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

**Zwei Commits liegen noch nicht auf `main`.** Das ist die wichtigste
Information dieses Dokuments.

```
origin/main                  003cc6b  Merge pull request #1
                             c8e7566  Beobachtungs-Workflow umsetzen        ✔ auf main
                             ca6fbfb  Initial commit

add-beobachtungs-workflow    21d3d6d  Versionsbereich bis Nextcloud 35      ✘ fehlt auf main
                             3c6b57a  Nextcloud 34, private APIs abgelöst   ✘ fehlt auf main
                             c8e7566  ← ab hier gemeinsam
```

Der Pull Request #1 wurde am 2. August gemerged, danach kamen noch zwei
Commits auf dem Branch dazu.

**Praktische Folge:** Wer die App jetzt von `main` installiert, bekommt
`max-version="33"` in der `info.xml` und vier Aufrufe in Nextclouds privatem
Namensraum. Auf einer Nextcloud 34 bricht `occ app:enable` mit
„App is not compatible" ab.

Zusammenführen geht nicht mehr per Fast-Forward, weil `main` durch den
Merge-Commit einen eigenen Kopf hat. Zwei Wege:

- Pull Request: https://github.com/foe05/kidseye/pull/new/add-beobachtungs-workflow
- oder lokal: `git checkout main && git pull && git merge add-beobachtungs-workflow && git push`

Inhaltlich konfliktfrei — der Merge-Commit hat nichts geändert, was danach
angefasst wurde.

---

## Was als Nächstes dran ist

In dieser Reihenfolge:

1. **Die beiden Commits nach `main` bringen** (siehe oben)
2. **Auf der Nextcloud installieren** — Anleitung: [`docs/INSTALLATION.md`](docs/INSTALLATION.md).
   Zehn Schritte, rund eine Stunde. Vorher Datenbank sichern, die App legt
   21 Tabellen an.
3. **Geräteprüfung auf dem iPad** — [`tests/protokolle/geraetepruefung.md`](tests/protokolle/geraetepruefung.md).
   Rund 30 Minuten. Das ist der Test, der über den gesamten
   Erfassungs-Workflow entscheidet: startet das Symbol im Vollbild, übersteht
   die Warteschlange einen Sitzungsablauf, bleibt die Erfassung unter zehn
   Sekunden?
4. **Marker-Wortlisten von der Lehrkraft holen** — die 42 mitgelieferten sind
   ausdrücklich nur Vorschläge. Erfassungsbogen in
   [`tests/protokolle/praxistest.md`](tests/protokolle/praxistest.md).
5. **Datenschutz klären** — ausfüllfertige Vorlage in
   [`tests/protokolle/datenschutz-vorlage.md`](tests/protokolle/datenschutz-vorlage.md),
   fünf Entscheidungsfragen mit Ankreuzfeldern.

Schritt 2 und 3 sind der eigentliche Lackmustest. Alles davor ist Theorie.

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
- `npm ci` scheitert an Peer-Dependencies; `npm install --legacy-peer-deps`
  funktioniert.
- `max-version` in der `info.xml` lässt sich **nicht weglassen** — das
  offizielle Schema führt sie als `use="required"`. Aktuell steht sie auf 35,
  geprüft gegen die Critical Changes von 34 und 35.

---

## Was gebaut ist

```
lib/          6.737 Zeilen PHP    21 Tabellen, 18 Dienste, 5 Controller, 3 occ-Befehle
src/          2.667 Zeilen        Vue 2, eigenes Bundle, keine @nextcloud/vue-Komponenten
tests/        1.453 Zeilen        65 JS-Tests, 28 PHP-Tests, Lasttest, 3 Protokolle
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
| JS-Tests | 65 grün (`npm test`) |
| PHP-Tests | 28 grün, 172 Assertions (Docker, siehe oben) |
| PHP-Syntax | 39 von 39 Dateien |
| Lasttest | 8 Abfrageformen, alle unter 8 ms beim Jahresbestand (`python3 tests/last/lasttest.py`) |
| PDF | Erzeugt, von `pypdf` gegengelesen: 4 Seiten, Umlaute korrekt |
| `info.xml` | Gegen das Schema von apps.nextcloud.com validiert |

**Nicht verifiziert:** dass irgendetwas davon auf einer echten Nextcloud
läuft.

---

## Aufgabenstand

93 von 101 erledigt, 1 teilweise, 6 offen — nachzulesen in
`openspec/changes/add-beobachtungs-workflow/tasks.md`.

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

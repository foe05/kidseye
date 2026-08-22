## Context

kidseye ist fertig gebaut: 6.737 Zeilen PHP, 25 Tabellen, 65 JS- und 28 PHP-Tests,
alle grün. Verifiziert ist damit die innere Stimmigkeit — nicht, dass die App auf
einer laufenden Nextcloud startet. Der Entwurf `add-beobachtungs-workflow` hat das
Verhalten für Installation und Gerät bereits festgelegt (D9 Offline-First, D14
Per-App-Manifest, D15 einmalige Anmeldung im installierten Modus); dieser Change
schließt die Lücken zwischen dieser Festlegung und dem Auslieferstand.

Drei Lücken sind im Repo nachweisbar, ohne dass eine Nextcloud dafür laufen muss:

| Befund | Beleg | Folge vor Ort |
|---|---|---|
| Das Manifest verlangt `favicon-touch.png`, die Datei fehlt | `img/` enthält nur `app.svg` und `manifest.json` | iOS ersetzt das Symbol durch einen Bildschirmabzug; Prüfschritt A3 fällt durch |
| Sitzungsablauf wird nur an `status === 401 \|\| 403` erkannt | `src/offline.js`, `synchronisieren()` | Leitet Nextcloud stattdessen auf die Anmeldeseite um, bleibt `blockiert` falsch; C4 zeigt „keine Verbindung" statt „nicht angemeldet" |
| Die Anleitung nennt 21 Tabellen | 25 `createTable`-Aufrufe in `lib/Migration/` | Die Kontrolle in Schritt 2 schlägt scheinbar fehl, obwohl alles stimmt |

Der Dublettenschutz ist dagegen vorhanden und trägt: `BeobachtungService::erfassen()`
sucht bei gesetzter `clientUuid` einen vorhandenen Eintrag und gibt ihn zurück,
statt neu anzulegen. Was fehlt, ist die Rückmeldung darüber — Prüfschritt C6 kann
heute nur zählen, nicht belegen.

**Randbedingungen:**

- Kein PHP in der Entwicklungsumgebung. PHP-Tests laufen über
  `docker run --rm -v "$PWD":/app -w /app php:8.3-cli` mit der PHPUnit-Phar.
- Keine laufende Nextcloud verfügbar. Alles, was dieser Change verspricht, muss
  ohne Server prüfbar sein — oder als Prüfschritt im Protokoll stehen, nicht als
  Behauptung im Code.
- Nextcloud 30 bis 35, PHP ab 8.1. Keine privaten APIs (in
  `add-beobachtungs-workflow` bereits abgelöst).
- Vue 2 mit eigenem Bundle, keine `@nextcloud/vue`-Komponenten.

## Goals / Non-Goals

**Goals:**

- Die Installation auf einer echten Nextcloud scheitert nicht an Fehlern, die im
  Repo bereits sichtbar sind.
- Ein einziger Befehl sagt, was am Einrichtungsstand fehlt — auch ohne Browser
  und auch, wenn jemand aus der Ferne unterstützt.
- Der kritischste Fall des Entwurfs, der Sitzungsablauf mitten im Unterricht,
  wird erkannt und richtig benannt, unabhängig davon, in welcher Form der Server
  die fehlende Anmeldung mitteilt.
- Die Geräteprüfung prüft auf dem Gerät nur noch, was Hardware verlangt. Alles
  andere ist vorher automatisiert geprüft.

**Non-Goals:**

- Keine Änderung am Erfassungsmechanismus. Marker trägt Kompetenzzuordnung,
  Stundenkontext ergänzt die zweite Achse — daran wird nichts angefasst.
- Keine Schemaänderung. Keine neue Tabelle, keine neue Spalte, keine dritte
  Migration.
- Keine Service-Worker-Registrierung und kein echter Offline-Betrieb der
  Programmhülle. Die Warteschlange in IndexedDB deckt den Fall ab, um den es geht.
- Die Heatmap-Zwischentabelle (D17) bleibt v2.
- Die Stufe `klassenteam` bleibt in der Oberfläche unerreichbar.

## Decisions

### E1 — `kidseye:pruefen` als eigener occ-Befehl, nicht als Erweiterung von `kidseye:einrichten`

Prüfen und Einrichten werden getrennt gehalten. `kidseye:einrichten` schreibt,
`kidseye:pruefen` liest ausschließlich und ist deshalb jederzeit gefahrlos
aufrufbar — auch auf einer Produktivinstallation, auch mehrfach, auch von jemandem,
der gerade nicht sicher ist, was der Befehl tut.

Der Rückgabewert trägt die Aussage: 0, wenn alle Pflichtpunkte erfüllt sind, sonst
1. Damit ist der Befehl in einem Installationsskript verwendbar.

*Erwogen und verworfen:* ein Flag `--pruefen` an `kidseye:einrichten`. Das gibt es
bei `kidseye:rahmen:import` bereits und ist dort richtig, weil es denselben
Vorgang im Trockenlauf zeigt. Hier ginge es um etwas anderes — nicht um einen
Trockenlauf des Einrichtens, sondern um den Zustand des Systems.

### E2 — Ein Prüfregister im Dienst, zwei Ausgabewege

Die Prüfpunkte werden einmal beschrieben — in einem `DiagnoseService` als Liste
von Punkten mit Kennung, Ermittlung, Pflichtcharakter und Abhilfetext. Der
occ-Befehl und der Endpunkt `GET /api/v1/einrichtung` lesen beide aus dieser
Liste.

Das ist der Grund, warum der Reiter „Einrichtung" und `kidseye:pruefen` nicht
auseinanderlaufen können: Wer einen Punkt ergänzt, ergänzt ihn an einer Stelle.
Der bestehende Endpunkt behält seine heutige Struktur nach außen und wird intern
aus dem Register gefüllt, damit die Oberfläche nicht umgebaut werden muss.

*Erwogen und verworfen:* die Prüfungen im Befehl zu belassen und die Oberfläche so
zu lassen, wie sie ist. Zwei Wahrheiten über denselben Zustand, die beim ersten
neuen Prüfpunkt auseinanderfallen.

### E3 — Tabellenprüfung gegen eine Liste, die aus den Migrationen stammt

Der Prüfpunkt „Tabellen vollständig" vergleicht gegen eine im Dienst gepflegte
Namensliste. Ein Test hält diese Liste gegen die `createTable`-Aufrufe in
`lib/Migration/` — weicht sie ab, schlägt der Test fehl.

So kann die Liste nicht veralten, ohne dass es auffällt, und dieselbe Zahl steht
in Anleitung, Prüfbefehl und Migration. Der heutige Widerspruch (21 gegen 25) ist
genau der Fehler, den diese Kopplung ausschließt.

*Erwogen und verworfen:* die Tabellen zur Laufzeit aus dem Schema-Diff abzuleiten.
Das prüft nichts — es gibt zurück, was da ist.

### E4 — „Nicht angemeldet" wird an drei Merkmalen erkannt, nicht an einem

`src/offline.js` wertet heute nur den HTTP-Status. Künftig gilt eine Antwort als
„nicht angemeldet", wenn eines zutrifft:

1. Status 401 oder 403,
2. die Antwort ist HTML, wo JSON erwartet wurde (Umleitung auf die Anmeldeseite,
   der axios folgt und die mit 200 endet),
3. die Endadresse der Antwort trägt den Anmeldepfad.

Fehlt eine Antwort ganz, bleibt es bei „keine Verbindung". Die Unterscheidung ist
kein Schönheitsfehler: Sie entscheidet, ob die Lehrkraft im Unterricht das WLAN
sucht oder sich neu anmeldet.

Damit Fall 2 gar nicht erst entsteht, sendet die Warteschlange zusätzlich
`X-Requested-With: XMLHttpRequest` — Nextcloud antwortet darauf mit 401 statt mit
einer Umleitung. Der Header ist die Vorbeugung, die drei Merkmale sind die
Absicherung für den Fall, dass sie nicht greift.

*Erwogen und verworfen:* `maxRedirects: 0` zu setzen und jede Umleitung als
fehlende Anmeldung zu werten. Zu grob — eine Umleitung kann auch von einem
vorgeschalteten Server kommen.

### E5 — Der Sync-Endpunkt meldet je Eintrag `neu` oder `bereits vorhanden`

`BeobachtungService::erfassen()` erkennt eine Dublette bereits, gibt aber den
vorhandenen Datensatz zurück, ohne den Unterschied zu benennen. Künftig führt der
Rückgabewert ein Merkmal mit, das der Controller in die Antwort übernimmt:
`uebernommen[].zustand` ist `neu` oder `bereits_vorhanden`.

Beides zählt als Erfolg, beides räumt den Eintrag aus der Warteschlange. Der
Unterschied ist für die Prüfung da: Prüfschritt C6 kann damit belegen, dass der
Schutz gegriffen hat, statt nur festzustellen, dass nichts doppelt zu sehen ist.

*Erwogen und verworfen:* HTTP 409 für die Dublette. Das würde die Warteschlange
zwingen, einen Fehlerfall als Erfolg umzudeuten — genau die Verwechslung, die im
Frontend zu verlorenen Einträgen führt.

### E6 — Das Symbol wird als PNG mitgeliefert und aus `app.svg` erzeugt

`img/favicon-touch.png` entsteht einmalig aus `img/app.svg` in 512×512 mit
deckendem Hintergrund (iOS unterlegt transparente Symbole schwarz) und wird als
Datei ins Repo gelegt, nicht zur Bauzeit erzeugt. Grund: Das Frontend wird laut
Anleitung auf einem anderen Rechner gebaut und per `rsync` übertragen; ein
Symbol, das nur beim Bauen entsteht, fehlt bei jedem Weg, der `js/` fertig
mitbringt.

Ein Test hält jeden im Manifest genannten Pfad gegen das Dateisystem. Der heutige
Zustand — ein Manifest, das auf eine nicht vorhandene Datei zeigt — kann damit
nicht zurückkehren.

*Erwogen und verworfen:* nur das SVG im Manifest zu führen. Safari auf iOS wertet
SVG-Symbole für den Home-Bildschirm nicht zuverlässig aus; das PNG ist der Pfad,
der trägt.

### E7 — Was Hardware braucht, bleibt im Protokoll

Fünf der Prüfschritte der Geräteprüfung lassen sich vorab automatisieren
(Manifest verlinkt, Symbolpfade auflösbar, `start_url` und `scope` korrekt,
Sitzungsablauf-Erkennung, Dublettenschutz). Der Rest — Vollbild, Anmeldung im
eigenen Speicherbereich, Zeitbudget unter zehn Sekunden, Daumenbedienung —
braucht ein Gerät und bleibt im Protokoll.

Das Protokoll wird entsprechend gekennzeichnet: je Schritt steht dabei, ob er
vorab automatisiert geprüft ist. Wer vor Ort dreißig Minuten hat, weiß dann, was
er prüfen muss und was schon gesichert ist.

## Risks / Trade-offs

| Risiko | Abhilfe |
|---|---|
| Die Merkmale in E4 lassen sich ohne laufende Nextcloud nur gegen nachgestellte Antworten prüfen. Wie der Server im installierten Modus wirklich antwortet, zeigt erst Prüfschritt C4 | Die drei Merkmale decken alle drei bekannten Antwortformen ab. C4 bleibt der Beleg; das Protokoll fordert die tatsächlich beobachtete Antwort als Notiz ein |
| `kidseye:pruefen` kann Zustände melden, die auf einer bestimmten Installation anders zu bewerten sind — etwa eine bewusst anders benannte Ablage | Jeder Punkt trägt seinen Abhilfetext und nennt die Einstellung, die ihn beeinflusst. Kein Punkt bricht ab, alle werden ausgegeben |
| Ein zusätzlicher Prüfpunkt „Lehraufträge" im Einrichtungsstand fragt Stammdaten ab und verlängert den Aufruf | Es ist eine Zählabfrage auf `kidseye_lehrauftrag`, im Lasttest die günstigste Abfrageform. Der Reiter wird nicht im Unterricht geöffnet |
| Das PNG im Repo ist eine Binärdatei, die niemand mehr aus dem SVG nachzieht, wenn das Zeichen sich ändert | Der Erzeugungsbefehl steht als Kommentar im Manifest-Test. Ändert sich `app.svg`, ist das Symbol von Hand nachzuziehen — bewusst in Kauf genommen gegenüber einer Bauzeit-Abhängigkeit |
| `X-Requested-With` könnte von einem vorgeschalteten Server entfernt werden | Deshalb bleibt es bei drei Merkmalen und nicht bei dem Header allein |

## Migration Plan

Keine Datenmigration. Keine dritte Migrationsklasse. Der Change ist auf einer
bereits installierten kidseye-Instanz durch Austausch der Dateien und
`occ app:disable kidseye && occ app:enable kidseye` wirksam — nötig nur, damit der
Autoloader den neuen Befehl kennt.

Reihenfolge für die Erstinstallation, die dieser Change herstellt:

1. Repo holen, `npm ci && npm run build`
2. `occ app:enable kidseye`
3. `occ kidseye:pruefen` — meldet, was fehlt
4. `occ kidseye:einrichten --schuljahr 2026/27`
5. Gruppen, Ablage, Lehraufträge nach Anleitung
6. `occ kidseye:pruefen` — muss jetzt mit 0 enden
7. Erst dann aufs iPad und die Geräteprüfung durchgehen

Rücknahme: Die Änderungen sind additiv bis auf die Erkennung in `src/offline.js`.
Fällt sie aus, verhält sich die Warteschlange wie heute — Einträge gehen auch dann
nicht verloren, nur der Hinweistext ist ungenau.

## Open Questions

- **Wie antwortet Nextcloud 30–35 im installierten Modus tatsächlich auf einen
  Sync mit abgelaufener Sitzung?** Nachzutragen, sobald Prüfschritt C4 gelaufen
  ist. Ist die Antwort in allen Versionen ein sauberes 401, sind die Merkmale 2
  und 3 aus E4 Vorsorge ohne Fall — sie bleiben trotzdem stehen.
- **Reicht ein 512×512-PNG, oder verlangt iOS zusätzlich ein
  `apple-touch-icon`-Element im Kopfbereich?** Der Weg über das Manifest sollte
  genügen; ergibt Prüfschritt A3 etwas anderes, kommt das Element in
  `PageController::unterricht()` dazu, wo die übrigen `apple-`Metaangaben schon
  stehen.
- **Soll `kidseye:pruefen` auch die Aufbewahrungsfristen als Punkt führen?** Sie
  sind gesetzt, aber ihre Angemessenheit ist eine Entscheidung der Schule, keine
  technische Voraussetzung. Vorerst nicht, bis die Datenschutzvorlage ausgefüllt
  ist.

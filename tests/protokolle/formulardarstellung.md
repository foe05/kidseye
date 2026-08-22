# Sichtprüfung der Formularelemente

Deckt die Aufgaben **6.3**, **6.4**, **6.5** und **6.6** des Changes
`formularelemente-vereinheitlichen` ab. Alles andere an diesem Change ist
automatisiert geprüft — diese vier nicht, und zwar aus einem Grund, der im
Entwurf ausdrücklich steht: **die Prüfungen prüfen Quelltext, nicht
Erscheinung.** jsdom rechnet kein Layout; eine Prüfung über
`getBoundingClientRect` wäre immer grün (design.md E2).

**Dauer:** rund 20 Minuten
**Voraussetzung:** `occ kidseye:pruefen` endet mit 0, und `npm run build` ist
nach der letzten Änderung gelaufen. Ausgeliefert werden müssen **`js/` und
`css/`** — `css/kidseye.css` ist neu und fehlt in einem alten Auslieferstand.

| Zeichen | Bedeutung |
|---|---|
| ✓ vorab | Automatisiert geprüft in `tests/js/formular.spec.js`. Schlägt es hier trotzdem fehl, fehlt die Stildatei im Auslieferstand oder eine Nextcloud-Regel überschreibt sie |
| — | Braucht das Auge oder das Gerät |

---

## A · Verwaltungsoberfläche, helles Thema (6.3)

`…/apps/kidseye/` — Reiter „Auswertung", „Klassen & Kinder", „Marker".

| # | Schritt | Erwartet | vorab | ✓ |
|---|---|---|---|---|
| A1 | Stildatei kommt an | `curl -s '…/apps/kidseye/' \| grep 'kidseye.*\.css'` liefert eine Zeile | ✓ vorab | ☐ |
| A2 | Auswahlfeld, Textfeld und Knopf nebeneinander (Reiter „Klassen & Kinder", Zeile „Kinder in …") | Gleiche Höhe, gleicher Rahmen, gleicher Radius | — | ☐ |
| A3 | Auswahlfelder | Tragen einen **eigenen** Pfeil rechts, nicht den des Betriebssystems | ✓ vorab | ☐ |
| A4 | Mit der Tabulatortaste durch die Filterzeile der Auswertung | Bei jedem Schritt ein sichtbarer Ring **außerhalb** des Rahmens, dazu ein gefärbter Rahmen | ✓ vorab | ☐ |
| A5 | Knopf „übernehmen" im Reiter „Einrichtung" ohne Vorschau | Sichtbar gesperrt, und zwar **gestrichelt** — nicht nur blasser | ✓ vorab | ☐ |
| A6 | Reiter „Marker": die beiden Mehrfachauswahlen | Rahmen und Radius wie die übrigen Felder, aber **kein** Pfeil und weiterhin als Liste bedienbar | ✓ vorab | ☐ |

**Wenn A1 fehlschlägt:** `css/` fehlt im Auslieferstand. Das ist der
wahrscheinlichste Fehler überhaupt bei diesem Change.

**Wenn A2 fehlschlägt, die Felder aber gestaltet aussehen:** Dann gewinnt
Nextcloud. Die Regeln stehen bewusst auf null Spezifität
(`:where(#kidseye-main, …)`), damit sie die Komponentenstile nicht
überschreiben — gegenüber Nextcloud entscheidet dadurch die Ladereihenfolge.
Im Element-Inspektor nachsehen, welche Regel gewinnt.

## B · Verwaltungsoberfläche, dunkles Thema (6.3)

Nextcloud-Einstellungen → Erscheinungsbild → Dunkel.

| # | Schritt | Erwartet | vorab | ✓ |
|---|---|---|---|---|
| B1 | Dieselben Felder wie in A | Hintergrund und Schrift folgen dem **Nextcloud**-Thema, nicht den hellen Vorgabewerten des Betriebssystems | ✓ vorab | ☐ |
| B2 | Pfeil im Auswahlfeld | Weiterhin erkennbar (er trägt ein neutrales Grau, das in beiden Themen trägt) | — | ☐ |
| B3 | Ein Auswahlfeld aufklappen | Die Liste selbst ist ein Systemelement. Sie folgt dem **Betriebssystem**, nicht dem Nextcloud-Thema — steht das OS auf hell, klappt sie hell auf. Das ist bekannt und in `css/kidseye.css` begründet | — | ☐ |

**Wenn B3 stört:** Es gibt kein CSS-Signal für „Nextcloud steht auf dunkel".
Ein `prefers-color-scheme`-Zweig würde denselben Fehler in die andere Richtung
machen. Der Fall verschwindet, sobald OS und Thema übereinstimmen.

## C · Erfassungsbildschirm (6.4)

`…/apps/kidseye/unterricht` — der Bildschirm im Basis-Layout, in dem
Nextcloud deutlich weniger Formular-CSS mitliefert.

| # | Schritt | Erwartet | vorab | ✓ |
|---|---|---|---|---|
| C1 | Startdialog „Stunde starten" | Die drei Auswahlfelder sehen aus wie die der Verwaltung — gleiche Höhe, gleicher Rahmen, gleicher Radius | ✓ vorab | ☐ |
| C2 | Feld „Kontext" vor der Wahl einer Klasse | Gesperrt und gestrichelt | ✓ vorab | ☐ |
| C3 | Feld „Kontext" mit „bitte wählen" | Zurückhaltender dargestellt als nach der Wahl eines Eintrags | ✓ vorab | ☐ |
| C4 | Knopf „Loslegen" | Höher als die Felder darüber (48 gegen 44 px) und farbig abgesetzt | ✓ vorab | ☐ |
| C5 | Stunde starten, ein Kind antippen | Kacheln, Marker, Notizfeld und Foto-Knopf sehen aus **wie vorher**. Die Stildatei darf hier nichts überschreiben | ✓ vorab | ☐ |
| C6 | Im Panel die „Zwecke" ansehen | Die Umschaltknöpfe sind jetzt 44 px hoch statt 32 px — die einzige beabsichtigte Änderung an diesem Bildschirm | — | ☐ |
| C7 | Dunkles Thema | Wie B1 | — | ☐ |

**Wenn C5 fehlschlägt** — der Bildschirm sieht anders aus als vorher —, dann
greift der Wurzelselektor zu stark. Dann steht in `css/kidseye.css` irgendwo
`#kidseye-unterricht …` statt `:where(#kidseye-unterricht, …)`;
`tests/js/formular.spec.js` schlägt in dem Fall ebenfalls an.

## D · iPad (6.5)

| # | Schritt | Erwartet | vorab | ✓ |
|---|---|---|---|---|
| D1 | Startdialog mit dem **Daumen** bedienen | Jedes Auswahlfeld ohne Zielen treffbar | — | ☐ |
| D2 | Pfeil im Auswahlfeld | Sichtbar, auch bei Sonnenlicht auf dem Display | — | ☐ |
| D3 | Ein Feld antippen, Auswahl treffen, danebentippen | **Kein** Fokusring bleibt stehen | — | ☐ |
| D4 | Mit angeschlossener Tastatur durchtabben | Ring erscheint wieder | — | ☐ |
| D5 | Verwaltungsoberfläche auf dem iPad, Reiter „Marker" | Der Entfernen-Knopf ✕ ist treffbar (44 × 44 px) | ✓ vorab | ☐ |

**D3 und D4 gehören zusammen:** Sie prüfen dieselbe Regel von zwei Seiten —
`:focus-visible` soll bei der Tastatur greifen und bei der Berührung nicht.
Fällt D3 durch, greift Safari auf `:focus` zurück.

## E · Gefüllte Datenbank (6.6)

Der Fall, der bei leerer Datenbank nicht auffällt — und bei der ersten
Installation trotzdem da war.

| # | Schritt | Erwartet | vorab | ✓ |
|---|---|---|---|---|
| E1 | Eine Klasse mit mindestens 20 Kindern, darunter lange Doppelnamen | — | — | ☐ |
| E2 | Auswertung → Ansicht „Einzelnes Kind" | Die vier Filterfelder sind **gleich breit** und brechen gleichmäßig um | — | ☐ |
| E3 | Ein Kind mit langem Namen wählen | Der Name wird im Feld **abgeschnitten** dargestellt; das Feld wächst nicht mit | ✓ vorab | ☐ |
| E4 | Fensterbreite verkleinern | Die Zeile bricht um, ohne dass ein Feld aus der Reihe fällt | — | ☐ |
| E5 | Klassenbild mit 20 Kindern | Kacheln unverändert kompakt; das kleine Gruppenfeld darin bleibt bei 28 px | ✓ vorab | ☐ |

**Zu E5:** `.kb-gruppe` ist die einzige namentlich geführte Ausnahme vom
Mindestmaß 44 px. Die Begründung steht am Ort der Regel in `Klassenbild.vue`,
die Ausnahmeliste in `tests/js/formular.spec.js`. Wer sie für falsch hält,
ändert beides — nicht nur die Prüfung.

---

## Ergebnis

| | |
|---|---|
| Geprüft am | |
| Nextcloud-Version | |
| Gerät / Browser | |
| A–E vollständig ☐ | Offene Punkte: |

Schlägt etwas fehl, gehört der Befund in einen neuen Change und nicht in eine
schnelle Korrektur an der Stildatei: die Prüfungen in
`tests/js/formular.spec.js` halten den Zustand, und eine Änderung, die an
ihnen vorbeigeht, ist keine Korrektur.

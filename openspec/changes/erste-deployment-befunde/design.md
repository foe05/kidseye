## Context

Die Befunde stammen aus dem ersten Durchgang auf einer laufenden Nextcloud. Sie
haben eine Gemeinsamkeit, die für die weitere Arbeit wichtiger ist als jeder
einzelne Fehler: **keiner davon war auf einer leeren Datenbank oder ohne
laufende Instanz zu sehen, und keiner hat einen Test rot gemacht.** Alle 132
Prüfungen waren grün, während der Unterrichtsmodus leer blieb, kein PDF ankam
und Nebentext unter dem Kontrastminimum stand.

Das ist dasselbe Muster wie bei dem Befund aus `installations-und-geraetehaertung`,
bei dem `toContain('/apps/kidseye/unterricht')` grün blieb, während der Wert
`../apps/kidseye/unterricht` kaputt war. Die Prüfungen dieses Projekts lesen
Quelltext; was sie nicht ausdrücklich behaupten, prüfen sie nicht.

## Goals / Non-Goals

**Goals**

- Jeden Befund an seiner Ursache beheben, nicht an seiner Erscheinung.
- Für die Fehlerklassen, die sich wiederholen können — Kontrast, fehlende
  Rückfallwerte —, eine Prüfung hinterlassen, die beim nächsten Mal anschlägt.
- Den Zustand herstellen, in dem sich die Auswertungen überhaupt beurteilen
  lassen.

**Non-Goals**

- Kein Urteil über Lücken-Radar und Kompetenz-Übersicht. Der Befund war „auf
  leerer Datenbank nicht beurteilbar" — die Antwort darauf sind Daten, keine
  Umgestaltung.
- Keine Komponentenbibliothek, kein Präprozessor, keine neue Abhängigkeit.

## Decisions

### D18 — Nebentext trägt Farbe, nicht Deckkraft

`opacity` mischt die Textfarbe gegen den *Elternhintergrund*. Zwei Folgen, die
beide zugeschlagen haben:

1. **Der Wert ist nicht nachrechenbar.** `#222` auf Weiß bei `.6` ergibt rund
   `#8e8e8e` — 3,0:1. Fließtext braucht 4,5:1. Wer `.6` schreibt, sieht dem Wert
   nicht an, welchen Kontrast er erzeugt.
2. **Die Werte stapeln sich.** Ein Block auf `.8` mit einem `<small>` auf `.7`
   darin steht effektiv auf `.56`. Beide Regeln stehen an verschiedenen Stellen
   der Datei; niemand rechnet das beim Schreiben nach.

Dazu nimmt Deckkraft Rahmen und Hintergrund desselben Elements mit — bei
`.aw-null { opacity: .3 }` war der Punkt für „keine Beobachtung" praktisch
unsichtbar.

Ersetzt durch `--ke-leise: var(--color-text-maxcontrast, #6b6b6b)`. Nextcloud
führt diesen Wert selbst und prüft ihn in beiden Themen gegen den Arbeitsgrund.

Deckkraft bleibt an genau zwei Stellen, beide namentlich in
`tests/js/formular.spec.js` geführt: das gesperrte Bedienelement (zusätzlich
gestrichelter Rahmen und `not-allowed`) und die Kachel eines lange nicht
beobachteten Kindes (D12; das Signal ist der gestrichelte Rahmen). Die Kachel
stand auf `.6` und war damit ausgerechnet bei den Kindern schwer zu lesen, um
die es dort geht — jetzt `.85`.

### D19 — Der Erfassungsbildschirm bezieht jede Themenfarbe über einen Token

Er läuft über `RENDER_AS_BASE`. Dort ist nicht verlässlich, dass
`--color-main-text` definiert ist. `color: var(--color-main-text)` ohne Rückfall
ist dann *ungültig zum Berechnungszeitpunkt* und fällt auf die geerbte Farbe
zurück — im Zweifel auf die des Browsers. Das ist kein sichtbarer Fehler im
Quelltext und war deshalb nur auf der Instanz zu finden.

Alle Farben laufen jetzt über die Token aus `css/kidseye.css`, die ihren Rückfall
in sich tragen. Eine Prüfung hält fest, dass in `Unterricht.vue` kein
`var(--color-…)` ohne Rückfall mehr steht.

### D20 — Der Lehrauftrag wird als Satz gesetzt, nicht als Einzeleintrag

Das Datenmodell bleibt das Tripel Lehrkraft × Klasse × Kontext (D13) — daran
ändert sich nichts. Was sich ändert, ist die Einheit, in der bearbeitet wird.

Der Regelfall an einer Grundschule ist die Klassenlehrkraft, die in allen
Kontexten ihrer Klasse beobachtet. Einzeln angelegt sind das sieben Formulare mit
siebenmal derselben Kennung. Genau dort entsteht der Tippfehler, der später als
leerer Startdialog erscheint — und zwar unbemerkt, weil ein Auftrag auf einer
fremden Kennung in keiner Ansicht auftaucht.

Zwei Leitplanken:

- **Die Kennung wird gegen `IUserManager` geprüft**, bevor geschrieben wird.
- **Ein Kontext mit gehaltenen Stunden wird beim Lösen behalten.** Ohne
  Lehrauftrag verweigert `ZugriffService` den Zugriff auf die eigenen
  Beobachtungen. Ein versehentlich entfernter Haken darf keine Daten
  unerreichbar machen. Was behalten wurde, steht in der Rückmeldung.

Für eine **fremde** Kennung wird der bestehende Satz nicht vorbelegt:
`lehrauftraege()` gibt ausschließlich die eigenen Aufträge heraus. Eine geratene
Vorbelegung sähe leer aus und löste beim Übernehmen fremde Aufträge. Stattdessen
steht dort der Hinweis, dass gesetzt wird, was angekreuzt ist.

### D21 — Eine Klasse mit Beobachtungen wird nicht gelöscht

Beobachtungen hängen am Kind, nicht an der Klasse (D13). Die Klasse steht an
ihnen trotzdem als Kontext, und Auswertung wie Berichte verbinden über sie —
`StundeService::nachId()` etwa mit einem `innerJoin` auf `kidseye_klasse`.

Verschwände die Zeile unter einer bestehenden Beobachtung, fiele diese aus jeder
Auswertung heraus, **ohne gelöscht zu sein**. Ein stiller Datenverlust, den
niemand bemerkt, ist schlechter als eine abgewiesene Handlung. Deshalb wird
abgewiesen.

Die Kinder überleben das Löschen: sie sind eigene Datensätze und können in einer
anderen Klasse weitergeführt werden. Gelöscht werden nur Zuordnungen,
Klassenbild und Lehraufträge dieser Klasse.

`BeispieldatenService::entferne()` räumt bewusst tiefer — dort ist bekannt, dass
die Daten erzeugt sind. Die Sicherung dagegen ist das Kennzeichen „(Beispiel)"
im Klassennamen: was nicht so heißt, wird dort nicht angefasst.

### D22 — Beispieldaten entstehen über den echten Erfassungspfad

Geschrieben wird über `BeobachtungService::erfassen()`, nicht per
Direkteinfügung. Ein Fehler in der Zuordnung auf den beiden Achsen fällt damit
beim Erzeugen genauso an wie bei einem Tap auf dem Gerät; ein Erzeuger mit
eigenem SQL hätte seine eigene Wahrheit.

Dafür waren zwei kleine Öffnungen nötig:

| | |
|---|---|
| `erfassen()` nimmt einen ausdrücklichen Stundenkontext | `laufende()` schließt jede Stunde nach 90 Minuten. Eine drei Wochen alte gäbe es nicht mehr — die Beobachtung verlöre Klasse, Kontext und damit die fachliche Achse |
| `starten()` nimmt einen rückwirkenden Beginn | Sonst lägen alle erzeugten Stunden auf heute, und der Lücken-Radar hätte nichts zu zeigen |

Beide Parameter sind optional und im Normalbetrieb `null`.

**Die Verteilung ist absichtlich schief.** Eine Gleichverteilung erzeugt eine
Datenbank, an der sich genauso wenig beurteilen lässt wie an einer leeren: der
Lücken-Radar zeigte entweder alle oder keinen, die Kompetenz-Übersicht eine
Fläche statt eines Musters. Zwei Kinder ohne jede Beobachtung, drei mit sehr
wenigen, die Marker über eine grobe Normalverteilung auf die Dimensionen, rund
ein Fünftel mit Freitext (der Anteil, der laut D4 in die Inbox geht). Die Namen
enthalten Doppelnamen und einen sehr langen Nachnamen — der Fall, an dem
Filterzeilen brechen.

`mt_srand` mit festem Wert: ein zweiter Aufruf ergibt dieselbe Klasse, und ein
Befund von gestern ist heute noch nachvollziehbar.

### D23 — Der Erfassungsbildschirm folgt der sichtbaren Höhe, nicht der Fensterhöhe

Auf einem Tablet im Querformat nimmt die eingeblendete Tastatur gut die Hälfte.
`100dvh` hilft dagegen nicht: es folgt der ein- und ausfahrenden Browserleiste,
die Tastatur zählt nicht dazu. Der Erfassungsbereich stand damit zur Hälfte
hinter der Tastatur — und mit ihm der Knopf „Sichern".

`window.visualViewport` führt zwei Werte nach: `--ke-sicht` (verbleibende Höhe)
und `--ke-unten` (was unten verdeckt ist, für die eingeblendeten Meldungen).
Beide Spalten rollen in sich statt der Seite; rollte die Seite, schöbe die
Tastatur den Erfassungsbereich unter den Rand.

Unter 560 px sichtbarer Höhe greift eine flache Anordnung. Sie **legt um statt
zu verkleinern**: Kacheln in sechs statt vier Spalten, die sechs Marker
zweispaltig. In der Breite ist auf einem Querformat-Tablet reichlich Platz; die
44 px Antippmaß bleiben unangetastet, sie sind der Grund, warum die Erfassung
mit dem Daumen funktioniert (D10). „Heute" ist Nachschlagewerk und weicht als
Erstes.

### D24 — Die Marker stehen als Karte, die Zuordnungen offen

Eine native Mehrfachauswahl (`select multiple`) verlangt zum Mehrfachwählen eine
gedrückte Zusatztaste. Auf dem iPad — dem Gerät, für das dieser Bildschirm
gepflegt wird — ist sie damit praktisch nicht zu bedienen. Dazu stand die
Auswahl in einer Liste mit `size="4"`, die man erst rollen muss, um zu sehen,
was gewählt ist, und die Beschriftungen lagen nur als `aria-label` vor.

Ankreuzfelder mit 44 px Zeilenhöhe lösen alle drei Punkte auf einmal. Der
Kopfkommentar in `css/kidseye.css` zu `select[multiple]` („werden ausdrücklich
nicht ersetzt — das gehört in einen eigenen Change") ist damit eingelöst; dies
ist der Change.

## Risks / Trade-offs

| Risiko | Umgang |
|---|---|
| `visualViewport` fehlt in älteren Browsern | Rückfall auf `100dvh`; die Anordnung bleibt die bisherige |
| `:has()` für `:has(input:checked)` | Fehlt es, entfällt nur die Hervorhebung des Angekreuzten — das Kästchen selbst trägt die Aussage |
| Der Satz-Knopf kann Aufträge lösen | Kontexte mit gehaltenen Stunden werden behalten und benannt; nur wirklich ungenutzte Aufträge gehen |
| Erzeugte Daten in einer echten Instanz | Kennzeichen „(Beispiel)" im Klassennamen, `--entfernen` räumt restlos weg, `entferne()` weigert sich bei allem ohne Kennzeichen |
| `--ke-leise` ist blasser als der Fließtext | Das ist beabsichtigt und bleibt über 4,5:1 — anders als die abgelösten Deckkraftwerte |

## Migration Plan

Keine Schemaänderung, keine Migration. Nach dem Einspielen:

```bash
occ app:update kidseye        # oder Dateien tauschen
occ kidseye:pruefen           # unverändert der erste Schritt
occ kidseye:beispieldaten --nutzer <kennung>
```

Die JS-Bundles müssen neu gebaut sein (`npm run build`); im Browser einmal hart
neu laden.

## Open Questions

- Ob Lücken-Radar und Kompetenz-Übersicht in ihrer heutigen Form taugen, ist mit
  den Beispieldaten jetzt zu entscheiden — und noch nicht entschieden.
- Die Heatmap steht fest auf der überfachlichen Ebene und der Radar fest auf
  14 Tagen. Ob das reicht, hängt an derselben Sichtprüfung.

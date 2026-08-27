## Context

Der Auslöser ist eine Beobachtung aus der ersten Installation: die Auswahlfelder
sahen nicht gut aus. Der Befund im Code ist eindeutiger als der Eindruck.

**kidseye liefert keine Stildatei aus.** `grep -rn "addStyle" lib/ src/` findet
nichts. Alle Stile stehen in `<style scoped>`-Blöcken der neun Komponenten und
werden zur Laufzeit von webpack eingefügt. Zu den zwölf `<select>`-Elementen steht
darin insgesamt:

```
StundeStart.vue:151      .feld select      { min-height: 44px; width: 100%; }
MarkerVerwaltung.vue:132 .mv-feld select   { min-height: 40px; }
Klassenbild.vue:115      .kb-feld select   { min-height: 40px; }
Auswertung.vue:206       .aw-filter select { min-height: 40px; min-width: 10rem; }
```

Vier Regeln, alle nur Maße. Kein Rahmen, kein Radius, kein Innenabstand, keine
Farbe, kein `appearance`. Das Aussehen kommt vom Browser und von dem, was das
umgebende Nextcloud-Layout zufällig mitliefert — und das ist an den beiden
Einstiegspunkten nicht dasselbe:

| Einstiegspunkt | Layout | Folge |
|---|---|---|
| `/apps/kidseye/` | volles Nextcloud-Layout | Nextcloud gestaltet Formularelemente mit, aber in 30 bis 35 unterschiedlich; die Mindesthöhe der App kollidiert mit dem Innenabstand von Nextcloud |
| `/apps/kidseye/unterricht` | `RENDER_AS_BASE`, Basis-Layout | Deutlich weniger Nextcloud-CSS. Hier steht der Startdialog mit drei Auswahlfeldern — auf dem Gerät, das im Unterricht in der Hand liegt |

Dazu neun verschiedene Mindesthöhen für dieselbe Art Bedienelement: 28, 32, 34,
36, 40, 44, 48 px. Der Entwurf führt 44 px als Mindestmaß (D10); geprüft wird das
in `tests/js/layout.spec.js`, aber nur für die Kacheln des Klassenbilds — nie für
Formularelemente.

**Randbedingung, die den Weg bestimmt:** `tests/js/layout.spec.js` prüft nicht
gerendertes Layout, sondern **Quelltext per Regex**:

```js
expect(quelle).toMatch(/\.ke-kachel\s*\{[^}]*min-height:\s*56px/)
```

Wer Regeln aus `Unterricht.vue` in eine globale Datei verschiebt, bricht diese
Prüfungen still. Das ist keine Formalie — es ist der Grund für E2 unten.

Weitere Randbedingungen: Vue 2, eigenes Bundle, keine `@nextcloud/vue`-Komponenten
(bewusst, D10). Kein CSS-Präprozessor im Build. Nextcloud 30 bis 35.

## Goals / Non-Goals

**Goals:**

- Ein Auswahlfeld sieht an beiden Einstiegspunkten gleich aus, und zwar
  absichtlich gestaltet statt zufällig geerbt.
- Was neben einem Auswahlfeld steht — Textfeld, Knopf — trägt dieselbe Höhe,
  denselben Rahmen, denselben Radius.
- Kein Bedienelement mehr unter 44 px.
- Der Dunkelmodus wirkt auch auf Auswahlfelder.
- Die bestehenden Maßprüfungen greifen nach dem Umbau unverändert weiter.

**Non-Goals:**

- Kein neues Aussehen für die App. Es geht darum, dass die Formularelemente dem
  folgen, was schon da ist — nicht um eine Gestaltungsrunde.
- Keine Komponentenbibliothek. `@nextcloud/vue` ist in
  `add-beobachtungs-workflow` bewusst abgewählt worden; das bleibt so.
- Kein Ersatz der beiden nativen Mehrfachauswahlen in der Markerverwaltung. Sie
  werden gestaltet, nicht ersetzt.
- Kein Umbau von `Unterricht.vue`. Der Erfassungsbildschirm ist das eine Stück
  Oberfläche, das durchgestaltet ist.
- Kein CSS-Präprozessor.

## Decisions

### E1 — Eine ausgelieferte Stildatei, nicht ein neunmal wiederholter Block

`css/kidseye.css` wird über `Util::addStyle(Application::APP_ID, 'kidseye')` in
`PageController::index()` **und** `PageController::unterricht()` eingebunden. Das
ist der einzige Weg, an dem beide Einstiegspunkte dieselbe Grundlage bekommen —
`<style scoped>` kann das nicht, weil scoped Stile je Komponente gelten und ein
Selektor wie `select` dort ein Attribut-Suffix bekommt.

Die Datei beschreibt Formularelemente über Elementselektoren (`select`, `input`,
`textarea`, `button`) innerhalb eines App-Wurzelselektors, damit nichts an der
umgebenden Nextcloud-Oberfläche verändert wird.

*Erwogen und verworfen:* eine gemeinsame CSS-Datei per `@import` in jede
Komponente zu ziehen. Das erzeugt neun Kopien im Bundle und wirkt wegen der
scoped-Attribute trotzdem nicht auf Kindelemente.

### E2 — Maße bleiben, wo die Tests sie suchen

`tests/js/layout.spec.js` prüft Quelltext. Zwei Regeln daraus:

1. Regeln, auf die ein bestehender Test zeigt — insbesondere `.ke-kachel` mit
   56 px in `Unterricht.vue` — bleiben unverändert an ihrem Platz.
2. Die neue Stildatei bekommt ihren **eigenen** Test `tests/js/formular.spec.js`,
   der nach demselben Muster gegen `css/kidseye.css` prüft.

Zusätzlich prüft der neue Test das, was heute niemand prüft: dass in keiner
Komponente und in keiner Stildatei eine Mindesthöhe unter 44 px für ein
Bedienelement steht. Das ist eine Prüfung, die den Zustand hält, statt ihn nur
einmal herzustellen.

*Erwogen und verworfen:* die Maßprüfungen auf gerendertes Layout umzustellen
(jsdom, `getBoundingClientRect`). jsdom rechnet kein Layout; das ergäbe eine
Prüfung, die immer grün ist.

### E3 — Farben nur über Nextcloud-Variablen, immer mit Rückfallwert

Jede Farbangabe lautet `var(--color-…, <Rückfall>)`. Der Rückfall ist kein
Schönheitsfehler, sondern die Absicherung für das Basis-Layout des
Erfassungsbildschirms, in dem nicht garantiert ist, welche Variablen definiert
sind.

Der Bestand nutzt Variablen bereits an 85 Stellen, teils schon mit Rückfall
(`var(--color-error, #8b2a2a)`). Die Stildatei setzt das fort, statt eine zweite
Farbwelt aufzumachen.

*Erwogen und verworfen:* eigene Farbtokens für kidseye. Dann folgt die App dem
Nextcloud-Thema nicht mehr — und ein Thema, dem die App nicht folgt, fällt genau
so auf wie ein ungestaltetes Auswahlfeld.

### E4 — `appearance: none` und ein eingebetteter Pfeil

Ein Auswahlfeld folgt dem Thema nur, wenn die native Darstellung abgeschaltet ist.
Damit entfällt auch der native Pfeil und muss ersetzt werden — als
`background-image` mit einem `data:`-URI, kein zusätzlicher Netzabruf, keine
zweite Datei, die beim Ausliefern fehlen kann.

Das Auswahlmenü selbst — die aufklappende Liste — bleibt nativ. Es ist auf dem
iPad ein Systemelement, das sich nicht gestalten lässt, und das ist gut so: Es ist
das, was die Lehrkraft von jeder anderen Anwendung kennt.

Ausdrücklich mitgesetzt wird `color-scheme`, damit das native Menü im dunklen
Thema dunkel aufklappt.

*Erwogen und verworfen:* ein eigenes Auswahlmenü in Vue. Damit fängt man sich
Tastaturbedienung, Vorlesewerkzeuge und Berührbedienung selbst ein — für einen
Gewinn, den niemand verlangt hat.

### E5 — 44 px durchgehend, 48 px für die bestätigende Handlung

Die neun heutigen Werte werden auf zwei zurückgeführt. 44 px ist das Maß aus D10
und aus den Apple-Gestaltungsrichtlinien; 48 px hebt die bestätigende Handlung
heraus, wie es `StundeStart.vue` mit `.primary` schon tut.

Betroffen sind vor allem die Verwaltungsbildschirme, die heute auf 40 px oder
darunter stehen — sie sind seltener im Gebrauch, werden aber auf demselben iPad
bedient.

Ausnahmen mit Begründung: `.kb-gruppe` (28 px) in `Klassenbild.vue` ist keine
Bedienfläche, sondern eine Anzeige. Solche Fälle werden im Stil kommentiert,
damit die Prüfung aus E2 sie kennt.

### E6 — Eine Klasse für Beschriftung plus Feld

Heute gibt es drei Anordnungen: `.feld`, `.mv-feld`/`.kb-feld` und `.aw-filter
label`. Sie tun dasselbe. Eine gemeinsame Klasse `ke-feld` in der Stildatei
ersetzt sie; die Komponenten behalten ihre eigenen Klassen nur dort, wo sie mehr
tun als Beschriftung und Feld untereinander zu setzen.

Die bestehende Auszeichnung — Feld liegt im `<label>` — bleibt. Sie ordnet
Beschriftung und Feld ohne `for`/`id` einander zu und funktioniert für
Vorlesewerkzeuge.

### E7 — Lange Einträge schneiden ab, statt das Layout zu sprengen

`.aw-filter select { min-width: 10rem }` in einer umbrechenden Flex-Zeile: Sobald
ein Kindername oder „Kompetenz-Übersicht" breiter ist, wächst das Feld und die
Zeile bricht ungleichmäßig um. Künftig gilt für Felder in Filterzeilen eine
Breitenspanne mit `max-width`, und der Text wird abgeschnitten dargestellt.

Das ist der Punkt, der aus „ungewohnt" ein „unaufgeräumt" macht — und der ohne
Daten in der Datenbank nicht auffällt. Bei der ersten Installation gab es sie.

## Risks / Trade-offs

| Risiko | Abhilfe |
|---|---|
| Ohne laufende Nextcloud lässt sich nicht sehen, wie es am Ende aussieht. Die Prüfungen prüfen Quelltext, nicht Erscheinung | Die Abnahme verlangt einen Blick auf beide Einstiegspunkte in hellem und dunklem Thema. Bis dahin gilt der Change als umgesetzt, nicht als bestätigt |
| Ein App-Wurzelselektor, der zu weit greift, verändert die Nextcloud-Oberfläche drumherum | Alle Regeln stehen unter `#kidseye-main` beziehungsweise `#kidseye-unterricht` — den beiden Wurzelelementen aus `templates/` |
| `appearance: none` nimmt Auswahlfeldern auf manchen Geräten Bedienhinweise | Der eingebettete Pfeil ersetzt den einzigen, der wegfällt. Das aufklappende Menü bleibt nativ |
| Höhere Bedienelemente brauchen mehr Platz; Filterzeilen könnten enger werden | Betroffen sind Verwaltungsbildschirme mit reichlich Platz. Der Erfassungsbildschirm steht bereits auf 44 px |
| Die Prüfung „keine Mindesthöhe unter 44 px" schlägt bei berechtigten Ausnahmen an | Ausnahmen werden im Stil als solche gekennzeichnet und in der Prüfung namentlich zugelassen — sichtbar, statt die Prüfung aufzuweichen |

## Migration Plan

Kein Datenbezug, keine Migration, keine API-Änderung. Wirksam nach `npm run build`
und Austausch von `js/` und `css/`.

Reihenfolge:

1. `css/kidseye.css` anlegen, `Util::addStyle` an beiden Einstiegspunkten
2. `tests/js/formular.spec.js` anlegen — die Prüfung schlägt zunächst fehl
3. Komponentenstile Stück für Stück auf die Stildatei zurückführen, bis die
   Prüfung grün ist
4. `npm test` und `npm run build`
5. Sichtprüfung an beiden Einstiegspunkten in hellem und dunklem Thema

Rücknahme: Die Stildatei nicht einbinden. Der Zustand ist dann der heutige — die
Komponentenstile haben ihre Maße behalten.

**Verhältnis zum Change `installations-und-geraetehaertung`:** kein
Dateikonflikt. Dort wird `Einrichtung.vue` um zwei Statuszeilen ergänzt (die
Komponente enthält kein Auswahlfeld) und `PageController::unterricht()` bleibt
unberührt; hier kommt dort eine Zeile `Util::addStyle` hinzu. Beide Changes sind
unabhängig anwendbar. Die Installationshärtung sollte zuerst laufen, weil sie die
Erstinstallation überhaupt erst tragfähig macht.

## Open Questions

- **Wie viel Formular-CSS liefert das Basis-Layout in Nextcloud 30 bis 35
  tatsächlich mit?** Ohne laufenden Server nicht zu beantworten. Die
  Rückfallwerte aus E3 machen die Antwort für dieses Vorhaben entbehrlich — sie
  entscheidet nur darüber, wie viel Arbeit die Stildatei am Ende leistet.
- **Trägt der eingebettete Pfeil auf Safari/iOS in beiden Themen?** Zu prüfen bei
  der Geräteprüfung. Falls nicht, hilft eine zweite Regel unter
  `prefers-color-scheme`.
- **Sollen die beiden Mehrfachauswahlen der Markerverwaltung ersetzt werden?**
  Hier bewusst ausgeklammert. Sie sind auf dem Tablet der schwächste Punkt der
  Oberfläche und verdienen einen eigenen Change — mit Chips statt Listbox.

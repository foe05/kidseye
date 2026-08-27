## Why

Bei der ersten Installation fiel das Layout der Auswahlfelder unangenehm auf. Der
Grund steht im Code: **kidseye liefert keine eigene Stildatei aus.** Es gibt kein
`Util::addStyle`, keine App-CSS — jede Komponente bringt nur ihr `<style scoped>`
mit, und darin ist zu den zwölf `<select>`-Elementen nichts gesetzt außer einer
Mindesthöhe. Kein Rahmen, kein Radius, keine Farbe, kein `appearance`. Was zu
sehen ist, liefert der Browser.

Das fällt an zwei Stellen unterschiedlich aus: `/apps/kidseye/` läuft im vollen
Nextcloud-Layout, `/apps/kidseye/unterricht` über `RENDER_AS_BASE` im minimalen
Basis-Layout. Der Startdialog mit seinen drei Dropdowns sitzt genau dort — auf
dem Gerät, das die Lehrkraft im Unterricht in der Hand hält.

Dazu kommt, dass für dieselbe Art Bedienelement neun verschiedene Höhen vergeben
sind: 28, 32, 34, 36, 40, 44 und 48 px. Auf dem iPad ist alles unter 44 px unter
dem Maß, das der Entwurf selbst als Mindestgröße führt (D10, geprüft in
`tests/js/layout.spec.js` — allerdings nur für die Kacheln des Klassenbilds, nicht
für Formularelemente).

## What Changes

- **Eine App-Stildatei einführen.** `css/kidseye.css`, über `Util::addStyle` an
  beiden Einstiegspunkten eingebunden. Sie ist der Ort, an dem Formularelemente
  einmal beschrieben werden, statt in neun Komponenten gar nicht.
- **Auswahlfelder gestalten.** `select` bekommt Rahmen, Radius, Innenabstand,
  Hintergrund und einen eigenen Pfeil über `appearance: none` — alles über
  Nextcloud-Variablen mit Rückfallwerten, damit es auch im Basis-Layout des
  Erfassungsbildschirms trägt.
- **Textfelder, Textbereiche und Knöpfe mitziehen.** Ein Auswahlfeld, das neben
  einem ungestalteten Textfeld steht, sieht nicht besser aus, sondern nur anders.
  Die drei Elementarten teilen sich Höhe, Rahmen, Radius und Fokusdarstellung.
- **Berührflächen auf ein Maß bringen.** 44 px als Mindesthöhe für alle
  Bedienelemente, 48 px für die bestätigende Handlung. Die neun heutigen Werte
  entfallen.
- **Fokus sichtbar machen.** Ein durchgehender, kontrastreicher Fokusring —
  heute bleibt es beim, was der Browser vorgibt, und im Basis-Layout ist das
  stellenweise gar nichts.
- **Dunkelmodus tragen.** Über `appearance: none` gestaltete Auswahlfelder folgen
  dem Nextcloud-Thema; native folgen dem Betriebssystem. Genau diese Mischung
  erzeugt den unruhigen Eindruck.
- **Feld und Beschriftung einheitlich anordnen.** Die fünf Komponenten mit
  Auswahlfeldern verwenden heute drei verschiedene Anordnungen. Eine gemeinsame
  Klasse ersetzt sie.
- **Nicht enthalten:** Die beiden nativen Mehrfachauswahlen in der
  Markerverwaltung (`multiple size="4"` und `size="2"`) werden gestaltet, aber
  nicht ersetzt. Das wäre ein Eingriff in Markup und Verhalten und gehört in einen
  eigenen Change.

## Capabilities

### New Capabilities

- `formulardarstellung`: Einheitliche Darstellung und Bedienbarkeit der
  Formularelemente in beiden Einstiegspunkten — Maße, Rahmen, Fokus, Thema,
  Dunkelmodus.

### Modified Capabilities

Keine. `openspec/specs/` ist leer; die Capabilities aus
`add-beobachtungs-workflow` sind nie in die Hauptspezifikation übernommen worden.

## Impact

| Bereich | Betroffen |
|---|---|
| Neu | `css/kidseye.css`, `tests/js/formular.spec.js` |
| Geändert | `lib/Controller/PageController.php` (`Util::addStyle` an beiden Einstiegspunkten), `src/components/StundeStart.vue`, `Auswertung.vue`, `Klassenbild.vue`, `MarkerVerwaltung.vue`, `Stammdaten.vue`, `Inbox.vue`, `Einrichtung.vue`, `App.vue` (jeweils nur der `<style scoped>`-Block und Klassennamen im Markup) |
| Tests | `tests/js/layout.spec.js` prüft per Regex gegen den Quelltext von `Unterricht.vue`. Diese Prüfungen müssen weiter greifen — die betroffenen Regeln bleiben, wo sie stehen, oder der Test wandert mit |
| Nicht betroffen | `Unterricht.vue` inhaltlich, PHP-Dienste, Datenmodell, Migrationen, API |
| Abhängigkeiten | Keine neuen. Kein CSS-Präprozessor, keine Komponentenbibliothek |

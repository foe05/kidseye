## 1. Stildatei einführen (E1)

- [x] 1.1 `css/kidseye.css` anlegen, mit Kopfkommentar: warum eine ausgelieferte Datei und nicht `<style scoped>` (scoped Stile wirken nicht auf Kindelemente, und beide Einstiegspunkte brauchen dieselbe Grundlage)
- [x] 1.2 Alle Regeln unter die Wurzelselektoren `#kidseye-main` und `#kidseye-unterricht` stellen, damit nichts an der umgebenden Nextcloud-Oberfläche verändert wird
- [x] 1.3 `Util::addStyle(Application::APP_ID, 'kidseye')` in `PageController::index()` und `PageController::unterricht()` ergänzen
- [x] 1.4 Prüfen, dass `css/` beim Ausliefern mitkommt — `.gitignore` gegenlesen (dort steht `js/`, die Stildatei darf nicht mit erfasst werden)

## 2. Grundlagen der Formularelemente (E3, E4, E5)

- [x] 2.1 Gemeinsame Regel für `select`, `input`, `textarea` und `button`: Rahmen, Radius über `var(--border-radius, 4px)`, Innenabstand, Hintergrund und Schriftfarbe über Nextcloud-Variablen mit Rückfallwerten
- [x] 2.2 Mindesthöhe 44 px für alle vier Elementarten, 48 px für `.primary`
- [x] 2.3 `select` auf `appearance: none` stellen und einen Pfeil als `background-image` mit `data:`-URI einbetten — keine zweite Datei
- [x] 2.4 `color-scheme` setzen, damit das native Auswahlmenü im dunklen Thema dunkel aufklappt
- [x] 2.5 Fokusdarstellung über `:focus-visible` mit sichtbarem Ring, der sich um mehr als einen Farbton vom Ruhezustand unterscheidet — und der bei Bedienung per Berührung nicht stehen bleibt
- [x] 2.6 Zustände: `:disabled` sichtbar gesperrt (nicht allein über Farbe), Platzhaltereintrag zurückhaltender als ein gewählter Eintrag
- [x] 2.7 Die beiden Mehrfachauswahlen (`select[multiple]`) mitgestalten: Rahmen und Radius wie die übrigen Felder, `appearance` unangetastet lassen — sie werden nicht ersetzt
  > **Inzwischen abgelöst.** Der hier bewusst offen gelassene Punkt ist in
  > `erste-deployment-befunde` 5.2 eingelöst: auf dem iPad verlangt eine native
  > Mehrfachauswahl gedrückte Zusatztasten und ist dort nicht bedienbar. Die
  > beiden Listen sind durch Ankreuzfelder ersetzt; die Regel für
  > `select[multiple]` steht weiterhin in `css/kidseye.css` und trägt, falls
  > wieder eine auftaucht.

## 3. Feld und Beschriftung (E6, E7)

- [x] 3.1 Klasse `ke-feld` in der Stildatei: Beschriftung über dem Feld, gemeinsamer Abstand, Beschriftungsgröße wie heute (`.8rem`, `opacity: .8`)
  > **Der Wert `opacity: .8` ist zurückgenommen** (`erste-deployment-befunde` 4.4).
  > Er war hier aus den Komponenten übernommen worden, damit sich am Aussehen
  > nichts ändert — und hat damit einen Fehler mitgenommen: die Beschriftung
  > stand blasser da als der Wert, den sie benennt. Sie trägt jetzt
  > `var(--ke-schrift)`. Der Rest der Aufgabe gilt unverändert.
- [x] 3.2 Klasse `ke-filterzeile` für umbrechende Filterzeilen: Breitenspanne statt `min-width` allein, langer Eintragstext wird abgeschnitten dargestellt statt das Layout zu sprengen
- [x] 3.3 Prüfen, dass die Auszeichnung „Feld liegt im `<label>`" überall erhalten bleibt — sie ordnet Beschriftung und Feld ohne `for`/`id` zu

## 4. Komponenten zurückführen

- [x] 4.1 `StundeStart.vue`: `.feld` durch `ke-feld` ersetzen, `.feld select { min-height: 44px }` und `.primary { min-height: 48px }` aus dem Komponentenstil entfernen — beides kommt jetzt aus der Stildatei
- [x] 4.2 `Auswertung.vue`: `.aw-filter` auf `ke-filterzeile` umstellen, `.aw-filter select { min-height: 40px; min-width: 10rem }` entfernen
- [x] 4.3 `Klassenbild.vue`: `.kb-feld` auf `ke-feld` umstellen, `.kb-feld select` und `.kb-aktionen button { min-height: 40px }` entfernen; `.kb-gruppe` (28 px) bleibt und wird als Anzeige, nicht als Bedienfläche, kommentiert
- [x] 4.4 `MarkerVerwaltung.vue`: `.mv-feld` auf `ke-feld` umstellen; `.mv-text`, `.mv-weg` und `.mv-aktionen button` von 40 px befreien; die Zeile `.mv-zeile` so anpassen, dass die höheren Elemente sie nicht sprengen
  > `.mv-zeile` gibt es nicht mehr: die fünfspaltige Zeile ist in
  > `erste-deployment-befunde` 5.1 durch eine Karte je Marker ersetzt. `.mv-feld`
  > und die 44 px sind dabei geblieben.
- [x] 4.5 `Stammdaten.vue`: `.sd-eingabe` (40 px) und `.sd-zeile button` zurückführen; die 36-px-Regel in Zeile 236 prüfen und entweder anheben oder als Anzeige kennzeichnen
- [x] 4.6 `Inbox.vue`: `.inbox-leiste button` (36 px), die 34-px-Regel und `.inbox-aktionen button` (40 px) zurückführen
- [x] 4.7 `Einrichtung.vue` und `App.vue`: `.ein-aktionen button` und die 40-px-Regel in `App.vue` zurückführen
- [x] 4.8 `Unterricht.vue` unangetastet lassen — der Erfassungsbildschirm steht bereits auf 44 px und ist durchgestaltet; nur prüfen, dass die neue Stildatei ihm nichts überschreibt

## 5. Prüfungen (E2)

- [x] 5.1 `tests/js/formular.spec.js` anlegen, im Muster von `layout.spec.js`: prüft `css/kidseye.css` gegen die Regeln aus Gruppe 2
- [x] 5.2 Prüfung: in keiner Komponente und in keiner Stildatei steht eine Mindesthöhe unter 44 px für ein Bedienelement — mit einer namentlich geführten Ausnahmeliste für Anzeigen wie `.kb-gruppe`
- [x] 5.3 Prüfung: jede Farbangabe in `css/kidseye.css` ist `var(--color-…, <Rückfall>)` mit Rückfallwert
- [x] 5.4 Prüfung: jede Regel steht unter einem der beiden Wurzelselektoren
- [x] 5.5 Prüfung: `PageController` bindet die Stildatei an beiden Einstiegspunkten ein
- [x] 5.6 `tests/js/layout.spec.js` gegenlesen — die Prüfungen auf `.ke-kachel` mit 56 px und den Breakpoint bei 700 px müssen unverändert greifen

## 6. Abnahme

- [x] 6.1 `npm test` — alle Tests grün, die 65 bestehenden eingeschlossen
- [x] 6.2 `npm run build` erzeugt `kidseye-main.js` und `kidseye-unterricht.js`
> **6.3 bis 6.6 brauchen eine laufende Nextcloud, ein iPad und eine gefüllte
> Datenbank.** Ausführbares Protokoll dafür:
> [`tests/protokolle/formulardarstellung.md`](../../../tests/protokolle/formulardarstellung.md).
> Alles, was sich ohne Hardware entscheiden lässt, ist dort in der Spalte
> „vorab" vermerkt und in `tests/js/formular.spec.js` automatisiert.
>
> **Die gefüllte Datenbank aus 6.6 gibt es inzwischen:**
> `occ kidseye:beispieldaten --nutzer <kennung>` legt eine Klasse mit langen
> Kindernamen und einem Beobachtungsverlauf an (`erste-deployment-befunde` 8).
> Damit sind alle vier Punkte durchführbar. Beim Durchgang mitnehmen: die
> Darstellung hat sich seit dem Abschluss dieses Changes an zwei Stellen
> geändert — Nebentext trägt Farbe statt Deckkraft, und die Markerverwaltung
> steht als Karte. Beides gehört zu `erste-deployment-befunde` 9.9 und 9.6.

- [ ] 6.3 Sichtprüfung `/apps/kidseye/` in hellem und dunklem Thema: Auswahlfelder, Textfelder und Knöpfe tragen dieselbe Höhe, denselben Rahmen, denselben Radius
- [ ] 6.4 Sichtprüfung `/apps/kidseye/unterricht`: der Startdialog sieht aus wie die Verwaltungsoberfläche, das Auswahlmenü klappt im dunklen Thema dunkel auf
- [ ] 6.5 Auf dem iPad gegenprüfen: Auswahlfelder mit dem Daumen treffbar, Pfeil sichtbar, kein stehender Fokusring nach einer Berührung
- [ ] 6.6 Mit gefüllter Datenbank gegenprüfen: eine Filterzeile mit langen Kindernamen bricht gleichmäßig um — der Fall, der bei leerer Datenbank nicht auffällt

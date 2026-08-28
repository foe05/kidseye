## 1. Unterrichtsmodus erreichbar machen (D20)

- [x] 1.1 `VerwaltungController::lehrauftragAnlegen()` prüft die Kennung gegen `IUserManager` und weist eine unbekannte mit Klartext ab, bevor geschrieben wird
- [x] 1.2 `VerwaltungController::einrichtung()` gibt die eigene Kennung mit heraus, damit die Oberfläche vorbelegen kann
- [x] 1.3 `StammdatenService::lehrauftraegeSetzen()`: setzt den ganzen Satz an Kontexten einer Lehrkraft in einer Klasse — anlegen, was fehlt, lösen, was nicht mehr genannt ist
- [x] 1.4 Ein Kontext mit gehaltenen Stunden wird beim Lösen behalten und in der Rückmeldung als `behalten` ausgewiesen
- [x] 1.5 Route `verwaltung#lehrauftraegeSetzen` (PUT `/api/v1/lehrauftrag/satz`) und `api.lehrauftraegeSetzen`
- [x] 1.6 `Stammdaten.vue`: Ankreuzraster über alle Kontexte mit „alle" und „keine", Kennung vorbelegt, Zähler „x von y gewählt"
- [x] 1.7 Für eine fremde Kennung wird nicht vorbelegt, sondern der Hinweis gezeigt, dass gesetzt wird, was angekreuzt ist
- [x] 1.8 `StundeService::startAuswahl()` gibt `nutzerId` mit heraus — die Meldung im leeren Startdialog soll sagen, auf welche Kennung vergeblich gesucht wurde
- [x] 1.9 `StundeStart.vue`: die Leermeldung nennt die Kennung, erklärt den Zusammenhang und verweist auf die Stelle in der Verwaltung

## 2. Verwaltungsoberfläche: Arbeitsgrund und Rollbalken

- [x] 2.1 `templates/index.php` in `#app-content > #app-content-wrapper` verpacken, mit Begründung im Markup (heller Grund, `overflow-y: auto`, abgerundete Fläche — `#content` steht auf `overflow: hidden`)
- [x] 2.2 Prüfen, dass die ID für Vue am Wurzelelement von `App.vue` bleibt und nicht in die Vorlage wandert (Vue 2 ersetzt das Mount-Element)

## 3. PDF-Ausgabe

- [x] 3.1 `AuswertungController::berichtDruck()` und `auskunftDruck()` mit `#[NoCSRFRequired]` versehen — beide werden über einen Verweis geöffnet, eine Seitennavigation führt kein `requesttoken` mit
- [x] 3.2 Im Kommentar festhalten, warum das unbedenklich ist: es wird nichts geschrieben, die Berechtigung hängt weiter an `verlangeLehrkraft()` und der Sichtbarkeitsprüfung im `BerichtService`

## 4. Lesbarkeit und Kontrast (D18, D19)

- [x] 4.1 Token `--ke-leise: var(--color-text-maxcontrast, #6b6b6b)` in `css/kidseye.css`, mit der Rechnung im Kommentar, warum `opacity` der falsche Weg ist
- [x] 4.2 Token `--ke-warn-rand`, `--ke-warn-grund`, `--ke-fehler` ergänzen; das fest eingetragene `#4a3a10` am eingeschalteten Zweck-Knopf ablösen
- [x] 4.3 Grundfarbe am Wurzelselektor setzen statt erben — im Basis-Layout gibt sie sonst niemand vor
- [x] 4.4 `.ke-feld > span` auf die volle Schriftfarbe: die Beschriftung stand auf `.8` und war blasser als der Wert, den sie benennt
- [x] 4.5 Alle 34 Deckkraftregeln durchgehen und Textabblendung auf `--ke-leise` umstellen; `.aw-null` von `.3` (praktisch unsichtbar) ablösen
- [x] 4.6 `.ein-offen` von einer abgeblendeten Zeile auf ein eigenes Zeichen umstellen — Deckkraft hätte das `<small>` mit der Begründung ein zweites Mal gedämpft
- [x] 4.7 `.ke-kachel--luecke` von `.6` auf `.85`: das Signal ist der gestrichelte Rahmen, nicht das Verblassen des Namens (D12)
- [x] 4.8 In `Unterricht.vue` jede Themenfarbe über die Token mit Rückfall beziehen (`RENDER_AS_BASE`)
- [x] 4.9 Prüfung: keine Datei blendet Text über `opacity` ab, mit namentlich geführter Ausnahmeliste und Begründung je Eintrag
- [x] 4.10 Prüfung: `--ke-leise` ist mit geprüftem Rückfall definiert
- [x] 4.11 Prüfung: in `Unterricht.vue` steht kein `var(--color-…)` ohne Rückfall
- [x] 4.12 Die bestehende Prüfung in `formular.spec.js`, die `opacity: .8` an der Beschriftung festhielt, auf die neue Regel umstellen — sie hielt genau den Fehler fest

## 5. Schnellmarker (D24)

- [x] 5.1 `MarkerVerwaltung.vue` von der fünfspaltigen Zeile auf eine Karte je Marker umstellen
- [x] 5.2 Die beiden `select[multiple]` durch Ankreuzfelder mit 44 px Zeilenhöhe ersetzen — damit ist der Vorbehalt aus `formularelemente-vereinheitlichen` 2.7 eingelöst
- [x] 5.3 Sichtbare Gruppenbeschriftungen (`legend`) statt `aria-label`, je Gruppe ein Satz, wozu sie dient
- [x] 5.4 Zähler „x von 6 sichtbar" nach oben: die Grenze ist beim Ankreuzen zu beachten, nicht erst beim Speichern
- [x] 5.5 Speichern sperren, solange mehr als sechs sichtbar sind
- [x] 5.6 Unsichtbar geschaltete Karte am gestrichelten Rand erkennbar, nicht am Verblassen — der Text muss bearbeitbar bleiben

## 6. Klassen anlegen und löschen (D21)

- [x] 6.1 `StammdatenService::klasseAnlegen()` weist einen leeren Namen ab; Begründung im Dienst, warum der Klassenname trägt
- [x] 6.2 In `Stammdaten.vue` den Knopf sperren, solange das Feld leer ist — Hinweis, nicht Prüfung
- [x] 6.3 `StammdatenService::klasseLoeschen()`: weist ab, solange Beobachtungen oder Stunden an der Klasse hängen
- [x] 6.4 Beim Löschen gehen Zuordnungen, Klassenbild und Lehraufträge; die Kinder bleiben als eigene Datensätze bestehen
- [x] 6.5 Route `verwaltung#klasseLoeschen` (DELETE) und `api.klasseLoeschen`
- [x] 6.6 In `Stammdaten.vue` mit Rückfrage, bei der gewählten Klasse statt als ✕ am Chip

## 7. Querformat mit Bildschirmtastatur (D23)

- [x] 7.1 `sichtVerfolgen()` führt `--ke-sicht` und `--ke-unten` aus `window.visualViewport` nach, mit Rückfall auf `100dvh`
- [x] 7.2 Beide Spalten rollen in sich, die Seite nie — sonst schöbe die Tastatur „Sichern" unter den Rand
- [x] 7.3 Eingeblendete Meldungen an die sichtbare Unterkante hängen (`--ke-unten`)
- [x] 7.4 Flache Anordnung unter 560 px: Kacheln sechsspaltig, Marker zweispaltig, „Heute" entfällt — umgelegt, nicht verkleinert
- [x] 7.5 Das Notizfeld beim Fokus in Sicht holen
- [x] 7.6 Prüfen, dass die 44 px nirgends unterschritten werden (greift über die bestehende Prüfung aus `formularelemente-vereinheitlichen` 5.2)

## 8. Beispieldaten (D22)

- [x] 8.1 `BeispieldatenService` anlegen: Klasse mit Kennzeichen „(Beispiel)", 22 Kinder, Tischgruppen im Klassenbild, Lehraufträge für alle Kontexte
- [x] 8.2 `BeobachtungService::erfassen()` um einen ausdrücklichen Stundenkontext erweitern, optional und im Normalbetrieb `null`
- [x] 8.3 `StundeService::starten()` um einen rückwirkenden Beginn erweitern, ebenso optional
- [x] 8.4 Verlauf über acht Wochen erzeugen, geschrieben über den echten Erfassungspfad
- [x] 8.5 Verteilung schief halten: zwei Kinder ohne jede Beobachtung, drei mit sehr wenigen, Marker über eine grobe Normalverteilung, rund ein Fünftel mit Freitext
- [x] 8.6 Namen mit Doppelnamen und einem sehr langen Nachnamen — der Fall, an dem Filterzeilen brechen
- [x] 8.7 `mt_srand` mit festem Wert, damit ein zweiter Aufruf dieselbe Klasse ergibt
- [x] 8.8 `entferne()` räumt restlos weg — Beobachtungen samt Nebentabellen, Stunden, Klassenbild, Zuordnungen, Lehraufträge, Kinder, Klasse
- [x] 8.9 `entferne()` weigert sich bei jeder Klasse ohne Kennzeichen
- [x] 8.10 `occ kidseye:beispieldaten` mit `--nutzer`, `--klasse`, `--wochen`, `--entfernen`; Kennung gegen Nextcloud geprüft
- [x] 8.11 Befehl in `appinfo/info.xml` eintragen

## 9. Abnahme

- [x] 9.1 `npm test` — 135 grün, die 132 bestehenden eingeschlossen
- [x] 9.2 PHP-Tests im Docker-Weg — 56 grün, 269 Assertions
- [x] 9.3 `php -l` über alle 38 Dateien in `lib/`
- [x] 9.4 `npm run build` erzeugt beide Bundles ohne Fehler
- [x] 9.5 `appinfo/info.xml` bleibt wohlgeformt

> **9.6 bis 9.11 brauchen die laufende Nextcloud, ein iPad und die erzeugten
> Beispieldaten.** Reihenfolge: erst `occ kidseye:beispieldaten --nutzer …`,
> dann prüfen. Damit sind zugleich 6.3 bis 6.6 aus
> `formularelemente-vereinheitlichen` durchführbar geworden.

- [ ] 9.6 Verwaltungsoberfläche: heller Arbeitsgrund vorhanden, lange Listen rollen
- [ ] 9.7 Lehrauftrags-Satz: „alle" ankreuzen, übernehmen, Unterrichtsmodus öffnen — Klasse und Kontext stehen zur Wahl
- [ ] 9.8 Beide PDFs laden herunter und öffnen sich
- [ ] 9.9 Kontrast in hellem und dunklem Thema an beiden Einstiegspunkten gegenlesen — besonders Hinweisblöcke, Zeitangaben und die Zellen der Kompetenz-Übersicht
- [ ] 9.10 iPad im Querformat mit ausgefahrener Tastatur: Klassenbild und die sechs Marker gleichzeitig sichtbar, „Sichern" erreichbar, „rückgängig" steht über der Tastatur
- [ ] 9.11 Klasse löschen: eine leere geht, eine mit Beobachtungen wird mit Begründung abgewiesen
- [ ] 9.12 Mit den Beispieldaten entscheiden, ob Lücken-Radar und Kompetenz-Übersicht in dieser Form taugen — das ist die offene Frage aus `design.md`, kein Prüfschritt mit einem erwarteten Ergebnis

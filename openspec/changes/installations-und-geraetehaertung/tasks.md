## 1. Symbol für den Home-Bildschirm (E6)

- [x] 1.1 `img/favicon-touch.png` in 512×512 aus `img/app.svg` erzeugen, mit deckendem Hintergrund `#F7F7F4` (iOS unterlegt Transparenz schwarz), und ins Repo legen
- [x] 1.2 Test `tests/js/manifest.spec.js` anlegen: jeder in `img/manifest.json` unter `icons[].src` genannte Pfad existiert im Auslieferstand; `start_url` und `scope` zeigen auf `/apps/kidseye/`; der Erzeugungsbefehl für das PNG steht als Kommentar im Test
- [x] 1.3 Prüfen, dass `start_url` und `scope` als relative Pfade auch unter einer Nextcloud in einem Unterverzeichnis auflösen — sonst auf `generateUrl`-Basis umstellen und das Manifest über einen Controller ausliefern

## 2. Sitzungsablauf erkennen (E4)

- [x] 2.1 In `src/offline.js` die Erkennung „nicht angemeldet" aus einer eigenen Funktion `istAbgemeldet(fehlerOderAntwort)` beziehen, die drei Merkmale prüft: Status 401/403, HTML statt JSON, Anmeldepfad in der Endadresse
- [x] 2.2 `synchronisieren()` so umbauen, dass auch eine erfolgreich zurückgekehrte, aber nicht auswertbare Antwort als `blockiert` gilt statt als leere Übernahme
- [x] 2.3 Fehlt eine Antwort ganz, bleibt `blockiert` falsch — der Zustand ist dann „keine Verbindung"; als eigener Rückgabewert `grund: 'abgemeldet' | 'keine-verbindung'` führen
- [x] 2.4 In `src/api.js` den Header `X-Requested-With: XMLHttpRequest` für den Sync-Aufruf setzen, damit Nextcloud mit 401 statt mit einer Umleitung antwortet
- [x] 2.5 In `src/components/Unterricht.vue` `syncTitel` aus `grund` ableiten: „Nicht angemeldet — die Beobachtungen bleiben auf dem Gerät" gegen „Keine Verbindung — die Beobachtungen bleiben auf dem Gerät"
- [x] 2.6 Tests in `tests/js/offline.spec.js` ergänzen: 401, HTML-Antwort mit Status 200, Anmeldepfad in der Endadresse, Netzfehler ohne Antwort — je einer, und in allen vier Fällen bleibt die Warteschlange vollständig erhalten

## 3. Dublettenschutz belegbar machen (E5)

- [x] 3.1 `BeobachtungService::erfassen()` so erweitern, dass der Rückgabewert bei einem über `clientUuid` gefundenen Eintrag als solcher erkennbar ist (Merkmal im Rückgabe-Array, ohne Schemaänderung)
- [x] 3.2 `ErfassungController::synchronisieren()` übernimmt das Merkmal als `uebernommen[].zustand` mit den Werten `neu` und `bereits_vorhanden`
- [x] 3.3 Einen Eintrag ohne `clientUuid` im Sync als Fehler zurückweisen, ohne die übrigen Einträge derselben Übertragung zu blockieren
- [x] 3.4 PHP-Test: derselbe Eintrag zweimal übertragen ergibt genau eine Beobachtung, die zweite Antwort trägt `bereits_vorhanden`
- [x] 3.5 PHP-Test: eine Übertragung aus drei Einträgen, davon einer ohne `clientUuid` — die beiden anderen werden übernommen
- [x] 3.6 PHP-Test: eine Stunden später übertragene Beobachtung trägt den mitgesendeten `erfasstAm`, nicht den Übertragungszeitpunkt

## 4. Diagnoseregister (E2, E3)

- [x] 4.1 `lib/Service/DiagnoseService.php` anlegen: Liste von Prüfpunkten, je Punkt Kennung, Ermittlung, Pflichtcharakter, Meldung und Abhilfetext
- [x] 4.2 Prüfpunkte umsetzen: Tabellen vollständig, Kompetenzrahmen geladen, aktives Schuljahr, beide Zugriffsgruppen vorhanden, Ablage erreichbar und beschreibbar, `theming.standalone_window.enabled`, Manifest samt Symbol auslieferbar, mindestens ein Lehrauftrag
- [x] 4.3 Nutzerabhängige Punkte ohne angemeldeten Nutzer als „nicht prüfbar" führen — kein Fehler, kein Einfluss auf den Gesamtzustand
- [x] 4.4 Tabellenliste im Dienst pflegen und einen PHP-Test schreiben, der sie gegen die `createTable`-Aufrufe in `lib/Migration/` hält
- [x] 4.5 PHP-Tests für den Dienst: vollständig eingerichtet → alle Punkte erfüllt; fehlender Rahmen → genau dieser Punkt nicht erfüllt und Abhilfetext nennt `kidseye:einrichten`

## 5. occ-Befehl `kidseye:pruefen` (E1)

- [x] 5.1 `lib/Command/Pruefen.php` anlegen, `DiagnoseService` per Konstruktor-Injektion (keine `\OCP\Server::get()`-Aufrufe — siehe die in `add-beobachtungs-workflow` aufgelöste Doppelarbeit)
- [x] 5.2 Option `--nutzer <kennung>` für die nutzerabhängigen Punkte
- [x] 5.3 Option `--output=json` mit einem Eintrag je Prüfpunkt aus Kennung, Zustand und Meldung
- [x] 5.4 Rückgabewert 0, wenn alle Pflichtpunkte erfüllt sind, sonst 1 — kein Punkt bricht den Durchlauf ab
- [x] 5.5 Befehl in `appinfo/info.xml` unter `<commands>` registrieren
- [x] 5.6 PHP-Test über `CommandTester`: Rückgabewerte und Ausgabe in beiden Formaten

## 6. Einrichtungsstand in der Oberfläche (E2)

- [x] 6.1 `VerwaltungController::einrichtung()` aus dem `DiagnoseService` füllen, die heutige Struktur der Antwort dabei erhalten
- [x] 6.2 Punkte „Lehraufträge" und „Symbol für den Home-Bildschirm" in die Antwort aufnehmen
- [x] 6.3 `src/components/Einrichtung.vue` um beide Punkte ergänzen, im Stil der bestehenden Zeilen mit `ok()` und Hinweistext
- [x] 6.4 JS-Test: bei fehlendem Lehrauftrag ist der Punkt als nicht erfüllt dargestellt und der Hinweis nennt, dass ohne Lehrauftrag keine Stunde startbar ist

## 7. Dokumentation und Protokolle (E7)

- [x] 7.1 `docs/INSTALLATION.md`, Schritt 2: Tabellenzahl auf 25 berichtigen und den Testaufruf `occ db:convert-type --help` durch `occ kidseye:pruefen` ersetzen
- [x] 7.2 `docs/INSTALLATION.md`, Schritt 1: den Hinweis auf `--legacy-peer-deps` streichen — die `package-lock.json` liegt im Repo und ist abgeglichen
- [x] 7.3 `docs/INSTALLATION.md`: `occ kidseye:pruefen` als eigenen Schritt zwischen Aktivieren und Einrichten sowie als Abschlusskontrolle vor Schritt 9 aufnehmen
- [x] 7.4 `docs/INSTALLATION.md`, Fehlertabelle: Zeile ergänzen für „Symbol zeigt einen Bildschirmabzug statt des kidseye-Zeichens"
- [x] 7.5 `tests/protokolle/geraetepruefung.md`: je Prüfschritt kennzeichnen, ob er vorab automatisiert geprüft ist; bei C4 die tatsächlich beobachtete Serverantwort als Notizfeld einfordern
- [x] 7.6 `WEITERMACHEN.md` auf den neuen Stand bringen: die drei behobenen Befunde, `kidseye:pruefen` als erster Schritt nach dem Aktivieren

## 8. Abnahme

- [x] 8.1 `npm test` — alle Tests grün, einschließlich der neuen aus 1.2, 2.6 und 6.4
- [x] 8.2 `npm run build` erzeugt `kidseye-main.js` und `kidseye-unterricht.js`
- [x] 8.3 PHP-Tests über Docker (`php:8.3-cli` mit PHPUnit-Phar, siehe `WEITERMACHEN.md`) — alle grün, einschließlich der neuen aus 3.4–3.6, 4.4, 4.5 und 5.6
- [x] 8.4 `appinfo/info.xml` gegen das Schema von apps.nextcloud.com validieren (XML-Kommentare enthalten kein `--`)
- [ ] 8.5 Durchgang der Reihenfolge aus dem Migrationsplan auf einer echten Nextcloud; `occ kidseye:pruefen` muss am Ende mit 0 enden
- [ ] 8.6 Geräteprüfung auf dem iPad nach `tests/protokolle/geraetepruefung.md`, Ergebnis eintragen; damit sind die Altaufgaben 0.2, 0.2b und 9.5 aus `add-beobachtungs-workflow` abgedeckt

## 9. Nachträge aus der Umsetzung

Nicht geplant, bei der Arbeit gefunden.

- [x] 9.1 `.gitignore`: Muster `js/` auf `/js/` verankern. Es galt für **jedes** Verzeichnis dieses Namens, also auch für `tests/js/` — `git ls-files tests/js` war leer, die gesamte JS-Testsuite lag außerhalb des Repositoriums. Ohne diese Berichtigung wären auch die in 1.2, 2.6 und 6.4 angelegten Prüfungen nie mitgekommen
- [x] 9.2 Manifest über einen Controller ausliefern (Zweig aus 1.3, der eingetreten ist): `start_url` und `scope` lösten als relative Pfade zu `/apps/kidseye/apps/kidseye/unterricht` auf. Neu: Route `page#manifest` unter `/manifest.webmanifest`, Werte aus `linkToRoute`, Symbolpfade aus `imagePath`
- [x] 9.3 Testgerüst erweitert: AppFramework- und Symfony-Console-Stubs (`tests/php/stubs/`), damit Controller und occ-Befehle ohne Composer prüfbar sind — im Muster der vorhandenen OCP-Stubs

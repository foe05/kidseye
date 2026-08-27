## Why

kidseye ist vollständig gebaut und getestet, aber **noch nie auf einer laufenden
Nextcloud installiert worden**. Die erste Installation und die Geräteprüfung auf
dem iPad sind der Lackmustest — und beide würden heute an Punkten scheitern, die
im Repo nachweisbar sind: das im `img/manifest.json` verlangte Symbol
`favicon-touch.png` existiert nicht, eine abgelaufene Sitzung wird im Frontend
nur an einem HTTP-Status erkannt, den Nextcloud bei Seitenanfragen gar nicht
liefert, und die Installationsanleitung nennt 21 Tabellen, während die
Migrationen 25 anlegen.

Diese Punkte lassen sich vor der Installation beheben. Wer sie stehen lässt,
verbringt die Stunde vor Ort mit der Suche nach Fehlern, die schon bekannt sind —
und riskiert im Punkt Sitzungsablauf, dass ein echter Datenverlust erst im
Unterricht auffällt.

## What Changes

- **Symbol für den Home-Bildschirm mitliefern.** `img/favicon-touch.png` (512×512)
  wird ergänzt, damit das Manifest ein auflösbares Symbol hat. Ohne die Datei
  vergibt iOS einen Bildschirmabzug der Seite als Symbol — Prüfschritt A3 der
  Geräteprüfung fällt durch.
- **Sitzungsablauf zuverlässig erkennen.** Die Warteschlange stuft eine Antwort
  künftig auch dann als „nicht angemeldet" ein, wenn Nextcloud statt eines 401
  auf die Anmeldeseite umleitet oder HTML statt JSON zurückgibt. Der Hinweis
  „Nicht angemeldet — die Beobachtungen bleiben auf dem Gerät" erscheint dann
  verlässlich (Prüfschritt C4).
- **Dublettenschutz beim Nachliefern absichern.** Der Sync-Endpunkt meldet
  zurück, ob ein Eintrag neu angelegt oder als Dublette erkannt wurde, damit
  Prüfschritt C6 nicht nur „keine Doppelten sichtbar" feststellt, sondern belegt,
  dass der Schutz gegriffen hat.
- **Selbstprüfung per occ.** Neuer Befehl `occ kidseye:pruefen`, der den
  Einrichtungsstand ohne Browser ausgibt: Tabellen vollständig, Rahmen geladen,
  Gruppen vorhanden, Ablage beschreibbar, `theming.standalone_window.enabled`,
  Symbol- und Manifest-Auflösung. Ein Befehl statt zehn Handgriffen — und die
  Meldung, die bei einer Fernunterstützung durchgegeben werden kann.
- **Einrichtungsstand ergänzen.** Der Reiter „Einrichtung" nennt zusätzlich, ob
  Lehraufträge vorliegen (der Schritt, den man laut Anleitung „leicht vergisst")
  und ob das Manifest-Symbol ausgeliefert wird.
- **Installationsanleitung an den Code angleichen.** Tabellenzahl korrigieren
  (25 statt 21), den irreführenden Testaufruf `occ db:convert-type --help`
  durch `occ kidseye:pruefen` ersetzen, den überholten Hinweis auf
  `--legacy-peer-deps` streichen (die `package-lock.json` liegt im Repo).
- **Kein Umbau der Erfassung.** Der tragende Mechanismus — Marker trägt
  Kompetenzzuordnung, Stundenkontext ergänzt die zweite Achse — bleibt
  unangetastet.

## Capabilities

### New Capabilities

- `inbetriebnahme-diagnose`: Prüfbarer Einrichtungsstand — welche Voraussetzungen
  für den Betrieb erfüllt sind, über occ und über die Oberfläche, mit einer
  Meldung je Befund, die sagt, was zu tun ist.
- `geraete-installation`: Installation auf dem Endgerät — Manifest, Symbol,
  Vollbildstart und einmalige Anmeldung im eigenen Speicherbereich einer über den
  Home-Bildschirm installierten Anwendung.
- `sitzungsausfall-erfassung`: Verhalten der Erfassung bei abgelaufener Sitzung
  oder fehlendem Netz — Annahme ohne Wartezeit, erkennbarer Zustand,
  vollständiges und dublettenfreies Nachliefern.

### Modified Capabilities

Keine. `openspec/specs/` ist leer — die Capabilities des Changes
`add-beobachtungs-workflow` sind nie in die Hauptspezifikation übernommen worden.
Die drei oben genannten Fähigkeiten werden deshalb als neu geführt, auch wo sie
Verhalten schärfen, das dort bereits angelegt war (D9, D14, D15).

## Impact

| Bereich | Betroffen |
|---|---|
| Neu | `img/favicon-touch.png`, `lib/Command/Pruefen.php`, `openspec/changes/installations-und-geraetehaertung/specs/**` |
| Geändert | `src/offline.js` (Erkennung „nicht angemeldet"), `src/components/Unterricht.vue` (Hinweistext), `lib/Controller/ErfassungController.php` und `lib/Service/BeobachtungService.php` (Rückmeldung „neu / Dublette"), `lib/Controller/VerwaltungController.php` (Einrichtungsstand), `src/components/Einrichtung.vue`, `appinfo/info.xml` (Befehl registrieren), `docs/INSTALLATION.md`, `tests/protokolle/geraetepruefung.md` |
| Tests | Neue JS-Tests für die Sitzungsablauf-Erkennung, neue PHP-Tests für `kidseye:pruefen` und die Dubletten-Rückmeldung |
| Abhängigkeiten | Keine neuen. PHP-Tests laufen weiterhin über Docker (`php:8.3-cli`), da lokal kein PHP vorhanden ist |
| Nicht betroffen | Datenmodell und Migrationen (keine neue Tabelle, keine Spaltenänderung), Kompetenzrahmen, Auswertung, PDF |

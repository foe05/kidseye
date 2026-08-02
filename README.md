# kidseye

**Schülerbeobachtung für die Grundschule — als Nextcloud-App.**

Beobachtungen zu einzelnen Kindern in Sekunden erfassen, automatisch an das
hessische Kerncurriculum binden und daraus Belege für Elterngespräch, Zeugnis
und Förderplan gewinnen. Vollständig auf der selbst gehosteten Nextcloud der
Schule — die Daten verlassen das Haus nicht.

> **Stand: Vorabfassung.** Der Code ist vollständig und getestet, aber noch nie
> auf einer laufenden Nextcloud installiert worden. Siehe [Projektstand](#projektstand).

---

## Die Idee in einem Absatz

Grundschullehrkräfte beobachten ständig — nur bleibt davon nichts übrig, was
im Februar noch belegbar wäre. kidseye trennt deshalb **Erfassen und
Einschätzen**: im Unterricht wird nur festgehalten, in Sekunden. Bewertet wird
nie vom Programm und nie nebenbei.

```
  ERFASSEN                 NACHARBEITEN              AUSWERTEN
  mehrmals täglich         1× pro Woche              vor Gespräch/Zeugnis
  5–15 Sek · iPad          10 Min · Desktop          punktuell

  Kontext einmal setzen    nur was noch etwas        Zeitleiste pro Kind
  Kind antippen            braucht — nicht alles     Kompetenz-Übersicht
  Wort antippen, fertig    Lücken-Radar              PDF-Bericht
```

Der Kniff: ein Schnellmarker trägt seine Kompetenzzuordnung in der Definition.
Zusammen mit dem Stundenkontext ergibt **ein einziger Fingertipp** eine auf
beiden Achsen zugeordnete, fertige Beobachtung:

```
  Du tippst: „hilft anderen"
    └─ Kompetenz:  Sozialkompetenz › Rücksichtnahme und Solidarität
    └─ Kontext:    Mathematik › Zahl und Operation
    └─ vorgemerkt: Sozial- und Arbeitsverhalten   (regelbasiert)
```

Rund 70 Prozent aller Beobachtungen sind damit sofort vollständig und
erscheinen nie in der Nacharbeit.

## Was drin ist

- **Erfassungsbildschirm im Vollbild**, als Symbol auf dem Home-Bildschirm
  installierbar. Offline-fähig — Speichern wartet nie auf das Netz, auch nicht
  bei abgelaufener Anmeldung.
- **Klassenbild** statt Sitzplan oder Liste: frei anordenbare Kacheln, optional
  in Tischgruppen. Kinder, die länger nicht beobachtet wurden, fallen auf.
- **Hessisches Kerncurriculum Primarstufe** als versionierte Daten: 231 Knoten,
  darunter 15 überfachliche Dimensionen und 155 Bildungsstandards für Deutsch,
  Mathematik, Sachunterricht, Kunst und Ethik.
- **Fachneutrale Kontexte** für Freiarbeit und Sozial- und Arbeitsverhalten —
  ohne Fachbezug, dafür mit eigenen Markersätzen.
- **Fotos von Arbeitsproben**, vor dem Hochladen verkleinert und von EXIF- und
  Ortsdaten befreit, abgelegt im Gruppenordner der Schule.
- **Datenschutz im Datenmodell**: drei Sichtbarkeitsstufen, einbahnig ab
  „Akte", Protokollierung, Aufbewahrungsfristen, DSGVO-Auskunft auf Knopfdruck.
- **PDF-Berichte** ohne zusätzliche Abhängigkeit — für Elterngespräch,
  Zeugniskonferenz, Förderplan-Mappe und Auskunft.

### Was kidseye ausdrücklich nicht tut

Keine Noten, keine automatische Einstufung, keine algorithmische Bewertung von
Kindern. Das Kerncurriculum stellt selbst fest, dass sich überfachliche
Kompetenzen „weitgehend einer Normierung und empirischen Überprüfung"
entziehen — kidseye sammelt deshalb Belege und zählt sie. Die pädagogische
Einschätzung trifft die Lehrkraft.

## Installation

Ausführlich in **[docs/INSTALLATION.md](docs/INSTALLATION.md)**. Kurzfassung:

```bash
cd /pfad/zu/nextcloud/apps
git clone git@github.com:foe05/kidseye.git
cd kidseye && npm ci && npm run build

cd /pfad/zu/nextcloud
sudo -u www-data php occ app:enable kidseye
sudo -u www-data php occ kidseye:einrichten --schuljahr 2026/27
```

**Voraussetzungen:** Nextcloud 30–34, PHP 8.1+, Node 20+ für den Bau des
Frontends.

## Entwicklung

```bash
npm ci                  # Abhängigkeiten
npm run watch           # Frontend im Entwicklungsmodus
npm test                # 65 JS-Tests
```

PHP-Tests der Geschäftsregeln, ohne Nextcloud-Installation:

```bash
docker run --rm -v "$PWD":/app -w /app php:8.3-cli sh -c '
  php -r "copy(\"https://phar.phpunit.de/phpunit-10.5.phar\",\"/tmp/p.phar\");"
  php /tmp/p.phar --configuration phpunit-regeln.xml'
```

Lasttest des Datenmodells:

```bash
python3 tests/last/lasttest.py
```

### Aufbau

```
appinfo/          info.xml, Routen
lib/
  Service/        Geschäftslogik — hier liegen die Regeln
  Controller/     REST unter /apps/kidseye/api/v1/
  Migration/      zwei Schritte, 21 Tabellen
  Command/        occ kidseye:einrichten, :rahmen:import, :rahmen:list
src/
  components/     Vue: Unterricht, Inbox, Auswertung, Verwaltung
  offline.js      Warteschlange (IndexedDB)
  bild.js         Verkleinern und EXIF entfernen
data/rahmen/      Kompetenzrahmen als JSON
tests/
  js/             65 Tests (vitest)
  php/            28 Tests (PHPUnit)
  last/           Lasttest des Datenmodells
  protokolle/     Prüfprotokolle für Gerät, Praxis und Datenschutz
openspec/         Entwurf, Spezifikation, Aufgaben
```

### Ein anderes Bundesland

Kompetenzrahmen sind Daten, nicht Code:

```bash
occ kidseye:rahmen:import mein-rahmen.json --pruefen
occ kidseye:rahmen:import mein-rahmen.json
```

Format siehe `data/rahmen/hessen-primarstufe-2011.json`. Der Import ist
alles-oder-nichts und prüft Pflichtfelder, Fachbezug und Zyklenfreiheit.

## Projektstand

93 von 101 Aufgaben umgesetzt. Verifiziert:

| | |
|---|---|
| JS-Tests | 65 grün |
| PHP-Tests | 28 grün, 172 Assertions |
| PHP-Syntax | 39 von 39 Dateien fehlerfrei |
| Lasttest | 8 Abfrageformen, alle unter 8 ms beim Jahresbestand |

**Noch offen** — alles davon braucht etwas, das außerhalb der Entwicklung
liegt. Protokolle dafür liegen unter `tests/protokolle/`:

- Prüfung auf einem echten iPad: Symbol, Vollbild, Sitzungsablauf, Zeitbudget
- Zweiwöchiger Praxistest im Unterricht
- Marker-Wortlisten und weitere Verwendungszwecke aus dem Kollegium
- Abstimmung der Aufbewahrungsfristen mit der Datenschutzbeauftragung

Der Entwurf wurde im Juli 2026 einer Grundschullehrkraft vorgelegt; vier von
zehn Annahmen wurden korrigiert und eingearbeitet. Nachzulesen in
`openspec/changes/add-beobachtungs-workflow/design.md`.

## Entstehung

Der Workflow ist gegen die amtlichen Kerncurricula des Hessischen
Kultusministeriums (2011) und die hessische Schul-Datenschutzverordnung
entwickelt. Zwei Sätze aus dem Kerncurriculum haben das Datenmodell bestimmt:
dass eine ausschließliche Zuordnung zu genau einem Kompetenzbereich nicht
möglich ist (daher n:m), und dass sich überfachliche Kompetenzen einer
Normierung entziehen (daher keine Skala).

## Lizenz

AGPL-3.0-or-later

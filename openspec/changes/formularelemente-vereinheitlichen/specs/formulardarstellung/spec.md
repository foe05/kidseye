## ADDED Requirements

### Requirement: Formularelemente sind in beiden Einstiegspunkten gestaltet

Das System SHALL eine eigene Stildatei an beiden Einstiegspunkten einbinden — an
der Verwaltungsoberfläche im vollen Nextcloud-Layout und am Erfassungsbildschirm
im Basis-Layout — und SHALL darin Auswahlfelder, Textfelder, Textbereiche und
Knöpfe gestalten.

#### Scenario: Erfassungsbildschirm im Basis-Layout

- **WHEN** `/apps/kidseye/unterricht` ausgeliefert wird
- **THEN** ist die App-Stildatei eingebunden
- **AND** die Auswahlfelder des Startdialogs tragen Rahmen, Radius, Innenabstand
  und Hintergrund

#### Scenario: Verwaltungsoberfläche

- **WHEN** `/apps/kidseye/` ausgeliefert wird
- **THEN** ist dieselbe Stildatei eingebunden
- **AND** die Auswahlfelder sehen aus wie die des Erfassungsbildschirms

#### Scenario: Darstellung hängt nicht vom Nextcloud-Layout ab

- **WHEN** dieselbe Art Auswahlfeld an beiden Einstiegspunkten dargestellt wird
- **THEN** stimmen Höhe, Rahmen, Radius und Innenabstand überein

### Requirement: Auswahlfelder folgen dem Thema, nicht dem Betriebssystem

Das System SHALL Auswahlfelder mit `appearance: none` und einer eigenen
Pfeildarstellung versehen und SHALL Farben ausschließlich über Nextcloud-Variablen
mit Rückfallwerten beziehen.

#### Scenario: Dunkelmodus

- **WHEN** das Nextcloud-Thema auf dunkel steht
- **THEN** tragen Auswahlfelder Hintergrund und Schriftfarbe des Themas
- **AND** nicht die hellen Vorgabewerte des Betriebssystems

#### Scenario: Variable nicht gesetzt

- **WHEN** eine verwendete Nextcloud-Variable im Basis-Layout nicht definiert ist
- **THEN** greift der im Stil hinterlegte Rückfallwert
- **AND** das Element bleibt lesbar und als Bedienelement erkennbar

#### Scenario: Eigener Pfeil

- **WHEN** ein Auswahlfeld dargestellt wird
- **THEN** trägt es eine eigene Pfeildarstellung
- **AND** diese ist ohne externe Datei eingebettet

### Requirement: Ein Mindestmaß für alle Bedienelemente

Das System SHALL für Auswahlfelder, Textfelder, Textbereiche und Knöpfe eine
Mindesthöhe von 44 CSS-Pixeln einhalten und SHALL für die bestätigende Handlung
eines Bildschirms 48 CSS-Pixel einhalten.

#### Scenario: Kein Bedienelement unter dem Mindestmaß

- **WHEN** die Stildatei und die Komponentenstile ausgewertet werden
- **THEN** setzt kein Bedienelement eine Mindesthöhe unter 44 px
- **AND** die zuvor vergebenen Werte 28, 32, 34, 36 und 40 px kommen nicht mehr vor

#### Scenario: Bestätigende Handlung

- **WHEN** ein Bildschirm eine bestätigende Handlung anbietet
- **THEN** ist deren Bedienelement mindestens 48 px hoch

#### Scenario: Kacheln des Klassenbilds bleiben unberührt

- **WHEN** die Stildatei eingeführt wird
- **THEN** behalten die Kacheln des Erfassungsbildschirms ihre Mindesthöhe von 56 px
- **AND** die bestehenden Maßprüfungen greifen weiterhin

### Requirement: Fokus ist sichtbar

Das System SHALL jedem Bedienelement eine sichtbare Fokusdarstellung geben, die
sich vom Ruhezustand deutlich unterscheidet und nicht allein auf Farbe beruht.

#### Scenario: Bedienung mit der Tastatur

- **WHEN** ein Formular mit der Tabulatortaste durchlaufen wird
- **THEN** ist bei jedem Schritt erkennbar, welches Element den Fokus hat
- **AND** die Darstellung unterscheidet sich vom Ruhezustand um mehr als einen Farbton

#### Scenario: Fokus bei Bedienung per Berührung

- **WHEN** ein Element per Berührung bedient wird
- **THEN** erzeugt das keine dauerhaft stehende Fokusdarstellung

### Requirement: Feld und Beschriftung sind einheitlich angeordnet

Das System SHALL Beschriftung und zugehöriges Bedienelement über eine gemeinsame
Klasse anordnen und SHALL jede Beschriftung ihrem Bedienelement zuordnen.

#### Scenario: Einheitliche Anordnung

- **WHEN** Bildschirme mit Auswahlfeldern dargestellt werden
- **THEN** stehen Beschriftung und Feld überall im selben Verhältnis zueinander
- **AND** mit demselben Abstand

#### Scenario: Zuordnung für Vorlesewerkzeuge

- **WHEN** ein Vorlesewerkzeug ein Bedienelement erreicht
- **THEN** wird die zugehörige Beschriftung mitgelesen

#### Scenario: Langer Eintragstext

- **WHEN** ein Auswahlfeld Einträge enthält, die breiter sind als das Feld
- **THEN** bricht die Anordnung nicht um
- **AND** der Text wird abgeschnitten dargestellt statt das Layout zu sprengen

### Requirement: Zustände sind unterscheidbar

Das System SHALL gesperrte Bedienelemente als solche erkennbar machen und SHALL
einen nicht gewählten Pflichteintrag von einem gewählten unterscheidbar
darstellen.

#### Scenario: Gesperrtes Auswahlfeld

- **WHEN** ein Auswahlfeld gesperrt ist, weil eine vorhergehende Auswahl fehlt
- **THEN** ist es sichtbar als gesperrt dargestellt
- **AND** der Unterschied beruht nicht allein auf Farbe

#### Scenario: Noch keine Wahl getroffen

- **WHEN** ein Auswahlfeld den Platzhaltereintrag zeigt
- **THEN** ist dieser zurückhaltender dargestellt als ein gewählter Eintrag

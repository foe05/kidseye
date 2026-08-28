## ADDED Requirements

### Requirement: Nebentext wird eingefärbt, nicht abgeblendet

Das System SHALL zurückgenommenen Text über eine eigene Farbe auszeichnen und
SHALL `opacity` nicht verwenden, um Text abzublenden.

Deckkraft mischt die Textfarbe gegen den Elternhintergrund: der erzeugte
Kontrast ist am geschriebenen Wert nicht abzulesen, und geschachtelte Werte
multiplizieren sich.

#### Scenario: Hinweistext auf hellem Grund

- **WHEN** ein Hinweis- oder Nebentext dargestellt wird
- **THEN** trägt er eine eigene Farbe mit mindestens 4,5:1 gegen den Arbeitsgrund
- **AND** keine verminderte Deckkraft

#### Scenario: Geschachtelter Nebentext

- **WHEN** ein zurückgenommener Text einen weiteren zurückgenommenen Text enthält
- **THEN** bleibt der Kontrast des inneren Textes derselbe wie der des äußeren

#### Scenario: Deckkraft an einem ganzen Bedienelement

- **WHEN** Deckkraft ein vollständiges Bedienelement zurücknimmt
- **THEN** trägt ein zweites Merkmal dieselbe Aussage — etwa ein gestrichelter
  Rahmen
- **AND** der Fall ist in der Prüfung namentlich mit Begründung geführt

### Requirement: Feldbeschriftungen sind nicht schwächer als ihr Wert

Das System SHALL die Beschriftung eines Formularfeldes in der vollen
Schriftfarbe darstellen.

#### Scenario: Beschriftung und Feldinhalt

- **WHEN** ein Feld mit Beschriftung dargestellt wird
- **THEN** ist die Beschriftung nicht blasser als der Wert, den sie benennt

### Requirement: Der Erfassungsbildschirm bezieht Farben mit Rückfallwert

Das System SHALL im Erfassungsbildschirm jede Themenfarbe über einen Wert mit
hinterlegtem Rückfall beziehen.

Der Bildschirm wird über `RENDER_AS_BASE` ausgeliefert; dort ist nicht
verlässlich, dass die Nextcloud-Variablen definiert sind. Eine Farbangabe ohne
Rückfall ist dann ungültig zum Berechnungszeitpunkt und fällt auf die geerbte
Farbe zurück.

#### Scenario: Variable fehlt im Basis-Layout

- **WHEN** eine Nextcloud-Farbvariable im Basis-Layout nicht definiert ist
- **THEN** greift der hinterlegte Rückfallwert
- **AND** Schrift und Hintergrund bleiben unterscheidbar

#### Scenario: Grundfarbe wird gesetzt, nicht geerbt

- **WHEN** der Erfassungsbildschirm eingehängt wird
- **THEN** setzt sein Wurzelelement Schriftfarbe und Hintergrund ausdrücklich

### Requirement: Der Erfassungsbildschirm bleibt bei eingeblendeter Tastatur bedienbar

Das System SHALL sich an der sichtbaren Höhe ausrichten und SHALL bei knapper
Höhe eine flache Anordnung wählen, ohne Antippflächen zu verkleinern.

#### Scenario: Tablet im Querformat mit ausgefahrener Tastatur

- **WHEN** die Bildschirmtastatur einen Teil der Anzeige verdeckt
- **THEN** richtet sich der Bildschirm an der verbleibenden Höhe aus
- **AND** der Erfassungsbereich mit den Markern und der bestätigenden Handlung
  bleibt sichtbar

#### Scenario: Eingeblendete Meldung

- **WHEN** eine Meldung eingeblendet wird, während die Tastatur ausgefahren ist
- **THEN** steht sie über der Tastatur, nicht dahinter

#### Scenario: Flache Anordnung

- **WHEN** die sichtbare Höhe unter das Maß für die gewohnte Anordnung fällt
- **THEN** werden Klassenbild und Marker auf mehr Spalten verteilt
- **AND** die Mindesthöhe der Antippflächen bleibt bei 44 px

#### Scenario: Schnittstelle nicht verfügbar

- **WHEN** der Browser die sichtbare Höhe nicht meldet
- **THEN** bleibt die bisherige Anordnung gültig

### Requirement: Zuordnungen werden angekreuzt, nicht in einer Liste mehrfach gewählt

Das System SHALL Mehrfachzuordnungen in der Markerverwaltung als einzeln
ankreuzbare Einträge anbieten.

Eine native Mehrfachauswahl verlangt auf einem Tablet gedrückte Zusatztasten und
ist dort nicht bedienbar.

#### Scenario: Zuordnung auf dem Tablet

- **WHEN** mehrere Kompetenzen einem Marker zugeordnet werden
- **THEN** ist jeder Eintrag einzeln antippbar
- **AND** die Antippfläche ist mindestens 44 px hoch

#### Scenario: Gewählte Zuordnungen sind sichtbar

- **WHEN** ein Marker Zuordnungen trägt
- **THEN** sind sie ohne Aufklappen oder Rollen erkennbar

#### Scenario: Gruppen sind benannt

- **WHEN** eine Gruppe von Zuordnungen dargestellt wird
- **THEN** trägt sie eine sichtbare Beschriftung, nicht nur eine für
  Vorlesewerkzeuge

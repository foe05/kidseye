## ADDED Requirements

### Requirement: Unterrichtskontexte umfassen Schulfächer und fachneutrale Kontexte

Das System SHALL Unterrichtskontexte als eigene Stammdaten führen. Jeder Unterrichtskontext SHALL eine Art tragen: `schulfach` oder `fachneutral`. Ein Kontext der Art `schulfach` SHALL auf ein Fach des aktiven Kompetenzrahmens verweisen. Ein Kontext der Art `fachneutral` SHALL keinen Fachbezug tragen.

#### Scenario: Freiarbeit als fachneutraler Kontext

- **WHEN** eine Lehrkraft den Unterrichtskontext „Freiarbeit" mit der Art `fachneutral` anlegt
- **THEN** speichert das System ihn ohne Fachbezug
- **AND** er steht beim Starten einer Stunde gleichberechtigt neben den Schulfächern zur Auswahl

#### Scenario: Fachneutrale Kontexte bieten keine fachliche Achse an

- **WHEN** eine Lehrkraft eine Stunde im Kontext „Freiarbeit" startet
- **THEN** bietet das System kein Inhaltsfeld zur Auswahl an
- **AND** in der Kuratierung dieser Beobachtungen werden keine fachlichen Bildungsstandards vorgeschlagen
- **AND** die überfachlichen Kompetenzdimensionen bleiben vollständig verfügbar

#### Scenario: Sozial- und Arbeitsverhalten als fachneutraler Kontext

- **WHEN** eine Lehrkraft außerhalb jeder Fachstunde beobachtet, etwa in der Pause
- **THEN** kann sie eine Stunde im Kontext „Sozial- und Arbeitsverhalten" starten
- **AND** die dort erfassten Beobachtungen tragen keinen Fachbezug

#### Scenario: Schulfach bietet die fachliche Achse an

- **WHEN** eine Lehrkraft eine Stunde im Kontext „Mathematik" der Art `schulfach` startet
- **THEN** bietet das System die Inhaltsfelder des Fachs Mathematik zur Auswahl an

### Requirement: Unterrichtsstunde als Kontextsitzung

Das System SHALL einer Lehrkraft erlauben, eine Unterrichtsstunde zu starten, die Klasse, Unterrichtskontext und — bei Kontexten der Art `schulfach` — optional ein Inhaltsfeld festlegt. Jede während der Stunde erfasste Beobachtung SHALL diese Angaben erben, ohne dass die Lehrkraft sie erneut auswählt.

#### Scenario: Kontext wird vererbt

- **WHEN** eine Lehrkraft eine Stunde mit Klasse 3a, Unterrichtskontext Mathematik und Inhaltsfeld „Zahl und Operation" startet und danach eine Beobachtung erfasst
- **THEN** trägt die Beobachtung Klasse 3a, Unterrichtskontext Mathematik und das Inhaltsfeld „Zahl und Operation"
- **AND** die Lehrkraft hat keines dieser Felder einzeln ausgewählt

#### Scenario: Auswahl nur aus eigenen Lehraufträgen

- **WHEN** eine Lehrkraft eine Stunde starten will
- **THEN** bietet das System nur Kombinationen aus Klasse und Unterrichtskontext an, für die sie im laufenden Schuljahr einen Lehrauftrag hat

#### Scenario: Zuletzt genutzte Kombination ist vorbelegt

- **WHEN** eine Lehrkraft den Startdialog öffnet
- **THEN** sind Klasse und Unterrichtskontext mit der zuletzt genutzten Kombination vorbelegt

### Requirement: Stunden enden automatisch

Das System SHALL eine laufende Stunde spätestens 90 Minuten nach ihrem Start automatisch beenden. Das System SHALL eine laufende Stunde beenden, sobald dieselbe Lehrkraft eine neue Stunde startet.

#### Scenario: Automatisches Ende nach 90 Minuten

- **WHEN** seit dem Start einer Stunde 90 Minuten vergangen sind
- **THEN** gilt die Stunde als beendet
- **AND** neue Beobachtungen erben ihren Kontext nicht mehr

#### Scenario: Neue Stunde beendet die alte

- **WHEN** eine Lehrkraft mit laufender Stunde in der 3a eine neue Stunde in der 4b startet
- **THEN** ist die Stunde in der 3a beendet
- **AND** genau eine Stunde dieser Lehrkraft ist aktiv

### Requirement: Veralteter Kontext wird nicht stillschweigend weiterverwendet

Wird die Anwendung geöffnet und der zuletzt gestartete Kontext ist älter als zwei Stunden, SHALL das System nachfragen, statt weiter in diesen Kontext zu schreiben.

#### Scenario: Nachfrage bei veraltetem Kontext

- **WHEN** eine Lehrkraft die Anwendung öffnet und ihr letzter Stundenkontext vor mehr als zwei Stunden gestartet wurde
- **THEN** fragt das System, ob eine neue Stunde gestartet werden soll
- **AND** erfasst keine Beobachtung im alten Kontext, bevor die Frage beantwortet ist

### Requirement: Beobachtungen tragen den echten Zeitstempel

Das System SHALL jeder Beobachtung den Zeitpunkt ihrer tatsächlichen Erfassung zuweisen, nicht den Startzeitpunkt der Stunde.

#### Scenario: Zeitstempel weicht vom Stundenstart ab

- **WHEN** eine Stunde um 09:00 startet und um 09:37 eine Beobachtung erfasst wird
- **THEN** trägt die Beobachtung den Zeitstempel 09:37
- **AND** verweist zusätzlich auf die Stunde, aus der sie stammt

### Requirement: Anwendung startet im laufenden Kontext

Läuft eine Stunde, SHALL die Anwendung beim Öffnen unmittelbar den Erfassungsbildschirm dieser Stunde anzeigen, ohne zwischengeschaltete Übersicht oder Navigation.

#### Scenario: Direkteinstieg bei laufender Stunde

- **WHEN** eine Lehrkraft die Anwendung bei laufender Stunde öffnet
- **THEN** ist der erste sichtbare Bildschirm die Kindauswahl dieser Stunde

#### Scenario: Startdialog ohne laufende Stunde

- **WHEN** eine Lehrkraft die Anwendung ohne laufende Stunde öffnet
- **THEN** zeigt das System den Startdialog für eine neue Stunde

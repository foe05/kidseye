## ADDED Requirements

### Requirement: Die Inbox enthält nur kuratierungsbedürftige Beobachtungen

Das System SHALL in der Inbox ausschließlich Beobachtungen anzeigen, die als kuratierungsbedürftig gekennzeichnet sind. Beobachtungen, die ausschließlich aus Schnellmarkern in einem Stundenkontext entstanden sind, SHALL NOT in der Inbox erscheinen.

#### Scenario: Markerbeobachtungen bleiben außen vor

- **WHEN** eine Lehrkraft in einer Woche 100 Beobachtungen erfasst hat, davon 70 reine Schnellmarker
- **THEN** enthält die Inbox 30 Einträge
- **AND** die 70 Markerbeobachtungen sind vollständig und in Auswertungen enthalten

#### Scenario: Nachträgliches Markieren holt eine Beobachtung in die Inbox

- **WHEN** eine Lehrkraft eine bereits vollständige Markerbeobachtung ausdrücklich markiert
- **THEN** erscheint sie in der Inbox

### Requirement: Zuordnung zu Bildungsstandards in der Kuratierung

Das System SHALL in der Inbox das Zuordnen einer Beobachtung zu einem oder mehreren Knoten des aktiven Kompetenzrahmens ermöglichen und dabei die Knoten des Fachs der Beobachtung bevorzugt anbieten.

#### Scenario: Vorschläge aus dem Fachkontext

- **WHEN** eine Lehrkraft eine Beobachtung aus einer Mathematikstunde kuratiert
- **THEN** bietet das System zuerst Kompetenzbereiche, Bildungsstandards und Inhaltsfelder des Fachs Mathematik an
- **AND** die überfachlichen Dimensionen bleiben ebenfalls erreichbar

#### Scenario: Fachneutraler Kontext bietet nur die überfachliche Achse

- **WHEN** eine Lehrkraft eine Beobachtung aus einer Freiarbeitsstunde kuratiert
- **THEN** bietet das System ausschließlich überfachliche Kompetenzdimensionen an
- **AND** keine fachlichen Bildungsstandards oder Inhaltsfelder

#### Scenario: Mehrfachzuordnung bleibt möglich

- **WHEN** eine Lehrkraft einer Beobachtung zwei Bildungsstandards und eine überfachliche Dimension zuordnet
- **THEN** speichert das System alle drei Zuordnungen

### Requirement: Sammelbearbeitung in der Inbox

Das System SHALL das Auswählen mehrerer Inbox-Einträge und das gemeinsame Zuordnen, Freigeben oder Erledigen dieser Einträge ermöglichen.

#### Scenario: Gemeinsame Zuordnung

- **WHEN** eine Lehrkraft acht Einträge auswählt und ihnen denselben Bildungsstandard zuordnet
- **THEN** tragen alle acht Beobachtungen diese Zuordnung
- **AND** sie verschwinden gemeinsam aus der Inbox

### Requirement: Erledigen ohne Zuordnung

Das System SHALL das Erledigen eines Inbox-Eintrags auch ohne Kompetenzzuordnung erlauben.

#### Scenario: Beobachtung bleibt bewusst unzugeordnet

- **WHEN** eine Lehrkraft einen Eintrag ohne Zuordnung als erledigt markiert
- **THEN** verschwindet er aus der Inbox
- **AND** die Beobachtung bleibt in der Zeitleiste des Kindes sichtbar

### Requirement: Lücken-Radar

Das System SHALL je Klasse ausweisen, welche Kinder seit einem konfigurierbaren Zeitraum keine Beobachtung erhalten haben, aufgeschlüsselt nach Fach.

#### Scenario: Kinder ohne Beobachtung werden benannt

- **WHEN** eine Lehrkraft das Lücken-Radar ihrer Klasse öffnet
- **THEN** listet das System die Kinder ohne Beobachtung im eingestellten Zeitraum
- **AND** nennt je Kind das Fach mit der längsten Lücke

#### Scenario: Fachbezogene Lücke trotz Beobachtungen insgesamt

- **WHEN** ein Kind in Deutsch regelmäßig, in Sachunterricht aber seit sechs Wochen nicht beobachtet wurde
- **THEN** weist das Lücken-Radar die Lücke in Sachunterricht aus

### Requirement: Korrektur der Kindzuordnung

Das System SHALL das Umhängen einer Beobachtung auf ein anderes Kind erlauben, solange sie die Sichtbarkeitsstufe `akte` nicht erreicht hat.

#### Scenario: Umhängen einer Beobachtung

- **WHEN** eine Lehrkraft eine privat gespeicherte Beobachtung auf ein anderes Kind umhängt
- **THEN** ist sie ausschließlich dem neuen Kind zugeordnet
- **AND** verschwindet aus der Zeitleiste des vorherigen Kindes

#### Scenario: Umhängen in der Akte ist ausgeschlossen

- **WHEN** eine Beobachtung die Stufe `akte` erreicht hat
- **THEN** verweigert das System das Umhängen
- **AND** verweist auf den Nachtrag als Korrekturweg

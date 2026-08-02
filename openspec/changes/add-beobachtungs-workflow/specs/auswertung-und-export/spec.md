## ADDED Requirements

### Requirement: Zeitleiste pro Kind

Das System SHALL alle für die abrufende Lehrkraft sichtbaren Beobachtungen eines Kindes chronologisch darstellen, filterbar nach Unterrichtskontext, Zeitraum, Kompetenzknoten, Verwendungszweck und Beobachtungsart.

#### Scenario: Zeitleiste zeigt nur Sichtbares

- **WHEN** eine Lehrkraft die Zeitleiste eines Kindes öffnet
- **THEN** enthält sie ihre eigenen privaten Beobachtungen sowie alle Beobachtungen der Stufen `klassenteam` und `akte`
- **AND** keine privaten Beobachtungen anderer Lehrkräfte

#### Scenario: Filterung bei großem Bestand

- **WHEN** ein Kind über 190 Beobachtungen in einem Schuljahr hat
- **THEN** ist die Zeitleiste nach Unterrichtskontext, Zeitraum und Kompetenzknoten einschränkbar
- **AND** zeigt in der Voreinstellung nur die letzten acht Wochen

### Requirement: Kompetenz-Heatmap über die Klasse

Das System SHALL je Klasse eine Matrix aus Kindern und Kompetenzknoten darstellen, deren Zellen die Anzahl vorliegender Belege ausweisen.

#### Scenario: Belegdichte auf einen Blick

- **WHEN** eine Lehrkraft die Heatmap ihrer Klasse für die überfachlichen Dimensionen öffnet
- **THEN** zeigt jede Zelle die Anzahl der Belege für dieses Kind und diese Dimension
- **AND** Zellen ohne Belege sind als solche erkennbar

#### Scenario: Häufigkeit ist keine Bewertung

- **WHEN** die Heatmap überfachliche Dimensionen darstellt
- **THEN** enthält sie keine Note, keine Einstufung und keine Rangfolge der Kinder
- **AND** weist aus, dass die Werte Belegzahlen und keine Bewertungen sind

### Requirement: Auswertungen bleiben innerhalb einer Rahmenversion

Das System SHALL Auswertungen auf Kompetenzknoten je Rahmenversion berechnen und SHALL versionsübergreifende Darstellungen als solche kennzeichnen.

#### Scenario: Kennzeichnung bei Versionswechsel

- **WHEN** eine Auswertung einen Zeitraum umfasst, in dem die Rahmenversion gewechselt hat
- **THEN** weist das System auf den Wechsel hin
- **AND** trennt die Werte nach Rahmenversion

### Requirement: Bericht für Elterngespräch und Zeugniskonferenz

Das System SHALL zu einem Kind einen PDF-Bericht mit wählbarem Zeitraum, wählbaren Unterrichtskontexten, wählbarem Verwendungszweck und wählbarer Sichtbarkeitsstufe erzeugen, der Beobachtungen und Belegübersicht enthält.

#### Scenario: Bericht für ein Elterngespräch

- **WHEN** eine Lehrkraft einen Bericht über das laufende Halbjahr für alle Unterrichtskontexte erzeugt
- **THEN** enthält das PDF die ausgewählten Beobachtungen, eine Belegübersicht je Kompetenzbereich und die verwendete Rahmenversion

### Requirement: Berichte sind auf Handybreite erzeugbar

Das System SHALL die Auswahl von Zeitraum, Unterrichtskontexten, Verwendungszweck und Sichtbarkeitsstufe sowie das Auslösen des PDF-Exports auf Bildschirmbreiten ab 375 px bedienbar machen.

#### Scenario: Bericht vom Handy erzeugen

- **WHEN** eine Lehrkraft bei 375 px Breite einen Bericht erzeugt
- **THEN** sind alle Auswahlfelder und der Auslöser ohne waagerechtes Scrollen der Seite erreichbar

#### Scenario: Heatmap scrollt in ihrem eigenen Bereich

- **WHEN** die Kompetenz-Heatmap auf einem schmalen Bildschirm dargestellt wird
- **THEN** scrollt sie innerhalb ihres eigenen Bereichs waagerecht
- **AND** die Seite selbst scrollt nicht waagerecht

#### Scenario: Bericht formuliert keine Einschätzung

- **WHEN** ein Bericht erzeugt wird
- **THEN** enthält er ausschließlich erfasste Beobachtungen und daraus abgeleitete Zählwerte
- **AND** keine vom System formulierte pädagogische Einschätzung oder Empfehlung

### Requirement: Arbeitsproben im Bericht

Das System SHALL zugeordnete Fotos von Arbeitsproben in den Bericht aufnehmen können, wenn dies beim Erzeugen gewählt wird.

#### Scenario: Bericht mit Arbeitsproben

- **WHEN** eine Lehrkraft beim Erzeugen des Berichts Arbeitsproben einbezieht
- **THEN** enthält das PDF die Fotos mit Datum und zugehöriger Beobachtung

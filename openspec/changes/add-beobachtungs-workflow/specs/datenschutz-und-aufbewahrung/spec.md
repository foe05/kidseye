## ADDED Requirements

### Requirement: Dreistufige Sichtbarkeit je Beobachtung

Jede Beobachtung SHALL genau eine Sichtbarkeitsstufe tragen: `privat`, `klassenteam` oder `akte`. Neue Beobachtungen SHALL die Stufe `privat` erhalten.

#### Scenario: Voreinstellung privat

- **WHEN** eine Beobachtung erfasst wird
- **THEN** trägt sie die Stufe `privat`
- **AND** ist ausschließlich für die erfassende Lehrkraft sichtbar

#### Scenario: Klassenteam sieht freigegebene Beobachtungen

- **WHEN** eine Beobachtung auf `klassenteam` gesetzt wird
- **THEN** sehen alle Lehrkräfte mit einem Lehrauftrag in der Klasse des Kindes diese Beobachtung
- **AND** Lehrkräfte ohne Lehrauftrag in dieser Klasse sehen sie nicht

### Requirement: Einzelbetrieb in der ersten Ausbaustufe

In der ersten Ausbaustufe SHALL ausschließlich die erfassende Lehrkraft ihre Beobachtungen sehen. Die Stufe `klassenteam` SHALL im Datenmodell vorhanden, in der Oberfläche jedoch nicht erreichbar sein. Das System SHALL NOT in dieser Ausbaustufe Beobachtungen zwischen Lehrkräften sichtbar machen.

#### Scenario: Keine Freigabe an Kolleginnen und Kollegen

- **WHEN** eine Lehrkraft eine Beobachtung öffnet
- **THEN** bietet das System als Sichtbarkeitsstufen nur `privat` und `akte` an
- **AND** `klassenteam` erscheint nicht zur Auswahl

#### Scenario: Fremde Beobachtungen bleiben unsichtbar

- **WHEN** eine Lehrkraft die Zeitleiste eines Kindes öffnet, zu dem eine andere Lehrkraft Beobachtungen erfasst hat
- **THEN** enthält die Zeitleiste ausschließlich ihre eigenen Beobachtungen

#### Scenario: Modell bleibt erweiterbar

- **WHEN** eine Beobachtung gespeichert wird
- **THEN** trägt sie ein Sichtbarkeitsfeld, das alle drei Stufen aufnehmen kann
- **AND** eine spätere Freischaltung von `klassenteam` erfordert keine Änderung des Datenbestands

### Requirement: Der Übergang in die Akte ist einbahnig und unveränderlich

Das System SHALL Übergänge zwischen `privat` und `klassenteam` in beide Richtungen erlauben. Das System SHALL den Übergang nach `akte` nur als ausdrückliche Handlung zulassen und SHALL NOT ein Zurücksetzen aus `akte` erlauben. Beobachtungen in `akte` SHALL unveränderlich sein.

#### Scenario: Zurücknahme einer Freigabe

- **WHEN** eine Lehrkraft eine auf `klassenteam` gesetzte Beobachtung wieder auf `privat` setzt
- **THEN** ist sie für das Klassenteam nicht mehr sichtbar

#### Scenario: Akte ist endgültig

- **WHEN** eine Beobachtung die Stufe `akte` erreicht hat und zurückgestuft werden soll
- **THEN** verweigert das System die Änderung

#### Scenario: Korrektur als Nachtrag

- **WHEN** eine Lehrkraft eine inhaltlich falsche Beobachtung in `akte` korrigieren will
- **THEN** legt das System einen verknüpften Nachtrag an
- **AND** der ursprüngliche Eintrag bleibt unverändert erhalten

### Requirement: Protokollierung sichtbarkeitsrelevanter Handlungen

Das System SHALL jede Änderung der Sichtbarkeitsstufe, jede Löschung und jede Auskunftserteilung mit Zeitpunkt, handelnder Lehrkraft und betroffener Beobachtung protokollieren. Das Protokoll SHALL nicht durch Anwendende veränderbar sein.

#### Scenario: Freigabe wird protokolliert

- **WHEN** eine Lehrkraft eine Beobachtung auf `klassenteam` setzt
- **THEN** enthält das Protokoll einen Eintrag mit Zeitpunkt, Lehrkraft, Beobachtung und beiden Stufen

### Requirement: Konfigurierbare Aufbewahrungsfristen

Das System SHALL Aufbewahrungsfristen je Sichtbarkeitsstufe konfigurierbar machen und SHALL keine Frist als rechtlich verbindlich ausweisen. Die Konfiguration SHALL den Hinweis tragen, dass die Fristen mit der schulischen Datenschutzbeauftragung abzustimmen sind.

#### Scenario: Fristen sind einstellbar

- **WHEN** eine Schulleitung die Aufbewahrungskonfiguration öffnet
- **THEN** kann sie je Sichtbarkeitsstufe eine Frist setzen
- **AND** das System weist auf die erforderliche Abstimmung mit der Datenschutzbeauftragung hin

#### Scenario: Ablauf der Frist löscht nicht automatisch

- **WHEN** die Aufbewahrungsfrist einer Beobachtung abgelaufen ist
- **THEN** kennzeichnet das System sie als zur Löschung vorgesehen
- **AND** löscht sie erst nach ausdrücklicher Bestätigung

### Requirement: Löschritual zum Schuljahresende

Das System SHALL beim Schuljahreswechsel private Rohbeobachtungen des abgelaufenen Schuljahres zur Löschung vorschlagen und die Auswahl je Beobachtung veränderbar machen.

#### Scenario: Vorschlag zum Schuljahresende

- **WHEN** eine Lehrkraft das Schuljahresende-Ritual startet
- **THEN** listet das System ihre privaten Beobachtungen des abgelaufenen Schuljahres
- **AND** Beobachtungen der Stufen `klassenteam` und `akte` sind nicht vorausgewählt

### Requirement: Auskunft nach Artikel 15 DSGVO

Das System SHALL zu einem Kind einen vollständigen Auskunftsbericht als PDF erzeugen, der alle Beobachtungen der Stufen `klassenteam` und `akte` mit Zeitpunkt, Unterrichtskontext, Inhalt und Zuordnungen enthält. Beobachtungen der Stufe `privat` SHALL NOT enthalten sein.

#### Scenario: Auskunftsbericht auf Knopfdruck

- **WHEN** eine berechtigte Person eine Auskunft zu einem Kind anfordert
- **THEN** erzeugt das System ein PDF mit allen Beobachtungen der Stufen `klassenteam` und `akte`
- **AND** ohne Beobachtungen der Stufe `privat`
- **AND** der Vorgang wird protokolliert

#### Scenario: Auskunft umfasst Arbeitsproben

- **WHEN** zu einem Kind Fotos von Arbeitsproben in nicht-privaten Beobachtungen vorliegen
- **THEN** weist der Auskunftsbericht diese mit Zeitpunkt und Fundort aus

### Requirement: Löschung entfernt auch Dateien

Das System SHALL beim Löschen einer Beobachtung die zugehörigen Dateien im Ablageordner entfernen, sofern sie von keiner anderen Beobachtung referenziert werden.

#### Scenario: Foto wird mitgelöscht

- **WHEN** eine Beobachtung mit einem Foto gelöscht wird und keine weitere Beobachtung dieses Foto referenziert
- **THEN** wird die Datei im Ablageordner entfernt

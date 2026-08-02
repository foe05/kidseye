## ADDED Requirements

### Requirement: Schüler:innen sind app-eigene Entitäten

Das System SHALL Schüler:innen als eigene Datensätze führen und SHALL NOT für sie Nextcloud-Benutzerkonten anlegen oder voraussetzen. Ein Schülerdatensatz SHALL mindestens Vorname, Nachname und Geburtsjahr führen und über die Schuljahre hinweg stabil bleiben.

#### Scenario: Schülerdatensatz ohne Nextcloud-Konto

- **WHEN** eine Klasse mit 20 Schüler:innen angelegt wird
- **THEN** existieren 20 Schülerdatensätze
- **AND** es wird kein Nextcloud-Benutzerkonto erzeugt

### Requirement: Lehrauftrag verbindet Lehrkraft, Klasse und Unterrichtskontext

Das System SHALL Lehraufträge führen, die genau eine Lehrkraft, eine Klasse, einen Unterrichtskontext und ein Schuljahr verbinden und kennzeichnen, ob die Lehrkraft Klassenlehrkraft ist. Das System SHALL NOT Nextcloud-Gruppen für diese Zuordnung verwenden.

#### Scenario: Fachlehrkraft in mehreren Klassen

- **WHEN** eine Lehrkraft Mathematik in 3a und in 4b unterrichtet und Klassenlehrkraft der 3a ist
- **THEN** existieren zwei Lehraufträge
- **AND** nur der Lehrauftrag für die 3a ist als Klassenlehrauftrag gekennzeichnet

#### Scenario: Lehrauftrag für einen fachneutralen Kontext

- **WHEN** eine Lehrkraft in der 3a Freiarbeit durchführt
- **THEN** kann dafür ein Lehrauftrag mit dem Unterrichtskontext „Freiarbeit" angelegt werden
- **AND** dieser verhält sich bei Auswahl und Zugriffsprüfung wie ein Lehrauftrag für ein Schulfach

#### Scenario: Zugriff ohne Lehrauftrag wird verweigert

- **WHEN** eine Lehrkraft ohne Lehrauftrag in einer Klasse deren Schüler:innen abrufen will
- **THEN** verweigert das System den Zugriff

### Requirement: Schuljahre rahmen alle Zuordnungen

Das System SHALL Klassen, Lehraufträge und Klassenzugehörigkeiten einem Schuljahr zuordnen. Beobachtungen SHALL dem Schüler:innen-Datensatz zugeordnet bleiben, unabhängig davon, welcher Klasse dieser in einem späteren Schuljahr angehört.

#### Scenario: Beobachtungen überleben den Klassenwechsel

- **WHEN** ein Kind zum neuen Schuljahr von der 3a in die 4a wechselt
- **THEN** bleiben alle bisherigen Beobachtungen dem Kind zugeordnet
- **AND** sind in seiner Zeitleiste weiterhin sichtbar

#### Scenario: Rollover legt Folgeklassen an

- **WHEN** eine Schulleitung den Schuljahreswechsel durchführt
- **THEN** schlägt das System Folgeklassen mit übernommenen Schüler:innen vor
- **AND** die Übernahme ist je Kind bestätigbar oder abwählbar

### Requirement: Schülerimport per CSV

Das System SHALL Schüler:innen und Klassenzugehörigkeiten aus einer CSV-Datei importieren. Der Import SHALL vor dem Schreiben eine Vorschau mit erkannten Datensätzen, Dubletten und Fehlern anzeigen.

#### Scenario: Import mit Vorschau

- **WHEN** eine Schulleitung eine CSV-Datei hochlädt
- **THEN** zeigt das System die zu erzeugenden und zu aktualisierenden Datensätze an
- **AND** schreibt erst nach ausdrücklicher Bestätigung

#### Scenario: Dublettenerkennung

- **WHEN** die CSV-Datei ein Kind enthält, das in derselben Klasse und im selben Schuljahr bereits existiert
- **THEN** kennzeichnet das System den Datensatz als Dublette
- **AND** legt keinen zweiten Datensatz an

### Requirement: Klassenbild als frei anordenbares Kachelraster

Das System SHALL je Klasse ein Klassenbild führen: ein Kachelraster, dessen Reihenfolge die Lehrkraft festlegt und das optional in benannte Gruppen geteilt werden kann. Das Klassenbild SHALL NOT eine Raumgeometrie abbilden — keine Tischformen, keine Koordinaten, keine Position von Tafel oder Möbeln.

#### Scenario: Sofort nutzbar ohne Pflege

- **WHEN** eine Klasse neu angelegt wird
- **THEN** erzeugt das System ein alphabetisch geordnetes Klassenbild
- **AND** die Erfassung ist ohne weitere Einrichtung uneingeschränkt möglich

#### Scenario: Anordnung ändern

- **WHEN** eine Lehrkraft Kacheln im Klassenbild umsortiert
- **THEN** gilt die neue Reihenfolge in allen Ansichten dieser Klasse
- **AND** die Zuordnung bestehender Beobachtungen bleibt unverändert

#### Scenario: Gruppen im Klassenbild

- **WHEN** eine Lehrkraft vier Kinder zu einer benannten Gruppe zusammenfasst
- **THEN** stellt das System diese Kacheln als zusammengehörig dar
- **AND** die Gruppe ist als Ganzes für eine Sammelbeobachtung auswählbar

#### Scenario: Eine Ansicht, keine Umschaltung

- **WHEN** der Erfassungsbildschirm die Kindauswahl darstellt
- **THEN** zeigt er ausschließlich das Klassenbild
- **AND** bietet keine alternative Listenansicht als gleichrangige Ansicht an

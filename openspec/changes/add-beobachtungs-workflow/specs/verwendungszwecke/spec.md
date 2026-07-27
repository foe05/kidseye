## ADDED Requirements

### Requirement: Verwendungszwecke sind konfigurierbare Daten

Das System SHALL Verwendungszwecke als konfigurierbare Liste führen, nicht als fest verdrahtete Aufzählung. Ein Verwendungszweck SHALL mindestens einen Namen und einen Aktivitätszustand tragen. Neue Verwendungszwecke SHALL ohne Codeänderung anlegbar sein.

#### Scenario: Neuer Verwendungszweck ohne Entwicklung

- **WHEN** eine Lehrkraft den Verwendungszweck „Übergangsempfehlung" anlegt
- **THEN** steht er unmittelbar bei der Erfassung, in der Kuratierung und als Berichtsfilter zur Verfügung

#### Scenario: Auslieferungszustand

- **WHEN** kidseye erstmals eingerichtet wird
- **THEN** ist mindestens der Verwendungszweck „Förderplan" vorhanden
- **AND** alle mitgelieferten Verwendungszwecke sind umbenennbar und deaktivierbar

#### Scenario: Deaktivieren erhält bestehende Vormerkungen

- **WHEN** ein Verwendungszweck deaktiviert wird, für den bereits Beobachtungen vorgemerkt sind
- **THEN** wird er bei der Erfassung nicht mehr angeboten
- **AND** bestehende Vormerkungen bleiben erhalten und weiterhin auswertbar

### Requirement: Regelbasierte Verwendungszwecke füllen sich selbst

Ein Verwendungszweck MAY eine Regel tragen, die ihn über eine Menge von Kompetenzknoten definiert. Die Mappe eines regelbasierten Zwecks SHALL bei Abruf aus den Kompetenzzuordnungen der Beobachtungen berechnet werden. Das System SHALL NOT für regelbasierte Zwecke eine Handlung der Lehrkraft verlangen.

#### Scenario: Sozial- und Arbeitsverhalten sammelt sich automatisch

- **WHEN** der Verwendungszweck „Sozial- und Arbeitsverhalten" über die Bereiche Sozialkompetenz, Lernkompetenz und Personale Kompetenz definiert ist
- **AND** eine Lehrkraft in einer Mathematikstunde den Marker „hilft anderen" antippt
- **THEN** erscheint diese Beobachtung in der Mappe „Sozial- und Arbeitsverhalten"
- **AND** die Lehrkraft hat dafür nichts zusätzlich getan

#### Scenario: Regel greift über alle Kontexte hinweg

- **WHEN** die Mappe „Sozial- und Arbeitsverhalten" eines Kindes abgerufen wird
- **THEN** enthält sie passende Beobachtungen aus allen Unterrichtskontexten
- **AND** nicht nur aus dem Kontext „Sozial- und Arbeitsverhalten"

#### Scenario: Manuelle Vormerkung ergänzt die Regel

- **WHEN** eine Beobachtung ohne passende Kompetenzzuordnung von Hand für einen regelbasierten Zweck vorgemerkt wird
- **THEN** erscheint sie zusätzlich in dessen Mappe

### Requirement: Vormerkung ist eine n:m-Beziehung

Das System SHALL einer Beobachtung mehrere Verwendungszwecke zuordnen können und einen Verwendungszweck beliebig vielen Beobachtungen.

#### Scenario: Beobachtung für zwei Zwecke vorgemerkt

- **WHEN** eine Beobachtung für „Förderplan" und für „Elterngespräch" vorgemerkt wird
- **THEN** speichert das System beide Vormerkungen
- **AND** die Beobachtung erscheint in beiden Mappen

### Requirement: Vormerkung bei der Erfassung ohne Zeitverlust

Das System SHALL das Vormerken im Erfassungsdialog ermöglichen. Die Vormerkung SHALL NOT ein Pflichtfeld sein und SHALL NOT vorausgewählt sein. Der Ein-Tap-Pfad über einen Schnellmarker SHALL durch die Anwesenheit von Verwendungszwecken nicht um einen zusätzlichen Schritt verlängert werden.

#### Scenario: Ein-Tap-Pfad bleibt ein Tap

- **WHEN** eine Lehrkraft einen Schnellmarker antippt, dem kein Verwendungszweck hinterlegt ist
- **THEN** ist die Beobachtung gespeichert und der Dialog geschlossen
- **AND** es wurde kein Verwendungszweck abgefragt

#### Scenario: Vormerken von Hand

- **WHEN** eine Lehrkraft im Erfassungsdialog einen Verwendungszweck antippt und danach speichert
- **THEN** trägt die Beobachtung diese Vormerkung

### Requirement: Schnellmarker können einen Verwendungszweck mitführen

Das System SHALL in der Definition eines Schnellmarkers einen oder mehrere Verwendungszwecke hinterlegen können. Wird ein solcher Marker angetippt, SHALL die entstehende Beobachtung ohne weiteren Schritt entsprechend vorgemerkt werden.

#### Scenario: Marker merkt automatisch vor

- **WHEN** dem Marker „Hürde" der Verwendungszweck „Förderplan" hinterlegt ist und eine Lehrkraft ihn antippt
- **THEN** ist die entstehende Beobachtung für den Förderplan vorgemerkt
- **AND** die Lehrkraft hat genau einmal getippt

#### Scenario: Automatische Vormerkung ist korrigierbar

- **WHEN** eine automatisch vorgemerkte Beobachtung in der Kuratierung geöffnet wird
- **THEN** kann die Vormerkung entfernt werden

### Requirement: Vormerkung ist nachträglich änderbar

Das System SHALL das Setzen und Entfernen von Vormerkungen in der Kuratierung und in der Zeitleiste ermöglichen, solange die Beobachtung die Sichtbarkeitsstufe `akte` nicht erreicht hat.

#### Scenario: Nachträgliches Vormerken

- **WHEN** eine Lehrkraft eine zwei Wochen alte Beobachtung öffnet und für „Förderplan" vormerkt
- **THEN** erscheint sie in der Mappe „Förderplan" dieses Kindes

#### Scenario: Keine Änderung in der Akte

- **WHEN** eine Beobachtung die Stufe `akte` erreicht hat
- **THEN** verweigert das System das Ändern ihrer Vormerkungen

### Requirement: Mappe je Verwendungszweck und Kind

Das System SHALL je Kombination aus Kind und Verwendungszweck eine Mappe darstellen, die alle vorgemerkten Beobachtungen chronologisch enthält und nach Zeitraum sowie Unterrichtskontext filterbar ist.

#### Scenario: Mappe vor dem Förderplangespräch

- **WHEN** eine Lehrkraft die Mappe „Förderplan" für ein Kind öffnet
- **THEN** enthält sie alle dafür vorgemerkten Beobachtungen dieses Kindes in zeitlicher Reihenfolge
- **AND** ist auf einen Zeitraum und einzelne Unterrichtskontexte einschränkbar

#### Scenario: Mappe als PDF

- **WHEN** eine Lehrkraft eine Mappe als PDF ausgibt
- **THEN** enthält das Dokument die vorgemerkten Beobachtungen, ihre Kompetenzzuordnungen und auf Wunsch die zugehörigen Arbeitsproben

#### Scenario: Mappe formuliert nicht

- **WHEN** eine Mappe ausgegeben wird
- **THEN** enthält das Dokument ausschließlich erfasste Beobachtungen und Zählwerte
- **AND** keinen vom System formulierten Förderplan, Text oder Vorschlag

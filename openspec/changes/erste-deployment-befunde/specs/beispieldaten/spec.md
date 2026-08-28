## ADDED Requirements

### Requirement: Ein erzeugter Datenbestand macht die Auswertungen beurteilbar

Das System SHALL einen vollständigen Beispielbestand erzeugen können — Klasse,
Kinder, Unterrichtsstunden und Beobachtungsverlauf über mehrere Wochen.

Auf leerer Datenbank steht im Lücken-Radar bei jedem Kind „noch nie beobachtet"
und in jeder Zelle der Kompetenz-Übersicht ein Platzhalter. Ob richtig gerechnet
wird, ist daran nicht zu erkennen.

#### Scenario: Bestand erzeugen

- **WHEN** der Beispielbestand für eine Lehrkraft erzeugt wird
- **THEN** entstehen eine Klasse mit Kindern, Lehraufträge für alle
  Unterrichtskontexte und ein Beobachtungsverlauf über den angegebenen Zeitraum

#### Scenario: Voraussetzungen fehlen

- **WHEN** kein Schuljahr aktiv ist oder keine Unterrichtskontexte angelegt sind
- **THEN** wird abgewiesen
- **AND** die Meldung nennt den Befehl, der die Voraussetzung herstellt

#### Scenario: Unbekannte Kennung

- **WHEN** eine Kennung angegeben wird, die es in Nextcloud nicht gibt
- **THEN** wird abgewiesen, bevor etwas geschrieben wird

### Requirement: Erzeugte Beobachtungen entstehen auf dem echten Erfassungspfad

Das System SHALL erzeugte Beobachtungen über dieselbe Erfassung schreiben wie
eine Eingabe am Gerät.

Ein Erzeuger mit eigenem SQL hätte seine eigene Wahrheit; ein Fehler in der
Zuordnung auf den beiden Achsen fiele dort nicht auf.

#### Scenario: Zuordnung auf beiden Achsen

- **WHEN** eine erzeugte Beobachtung über einen Schnellmarker entsteht
- **THEN** trägt sie dieselbe Kompetenz- und Zweckzuordnung wie eine am Gerät
  erfasste

#### Scenario: Rückwirkender Zeitpunkt

- **WHEN** eine Beobachtung mit einem Zeitpunkt in der Vergangenheit erzeugt wird
- **THEN** trägt sie Klasse und Unterrichtskontext der zugehörigen Stunde
- **AND** nicht die einer laufenden Stunde

### Requirement: Der erzeugte Bestand ist ungleich verteilt

Das System SHALL die erzeugten Beobachtungen ungleich über Kinder und
Kompetenzdimensionen verteilen.

Eine Gleichverteilung erzeugt einen Bestand, an dem sich so wenig beurteilen
lässt wie an einem leeren: der Lücken-Radar zeigte entweder alle oder keinen,
die Kompetenz-Übersicht eine Fläche statt eines Musters.

#### Scenario: Lücken sind vorhanden

- **WHEN** der Bestand erzeugt ist
- **THEN** gibt es Kinder ohne jede Beobachtung und Kinder mit sehr wenigen

#### Scenario: Muster in der Übersicht

- **WHEN** die Kompetenz-Übersicht auf dem erzeugten Bestand geöffnet wird
- **THEN** unterscheiden sich die Zählwerte zwischen den Dimensionen erkennbar

#### Scenario: Einträge für die Nacharbeit

- **WHEN** der Bestand erzeugt ist
- **THEN** trägt ein Teil der Beobachtungen Freitext und ist damit
  kuratierungsbedürftig

#### Scenario: Darstellung mit echtem Inhalt

- **WHEN** der Bestand erzeugt ist
- **THEN** enthalten die Namen Doppelnamen und mindestens einen sehr langen
  Nachnamen

#### Scenario: Wiederholbarkeit

- **WHEN** der Bestand ein zweites Mal erzeugt wird
- **THEN** ergibt sich dieselbe Verteilung

### Requirement: Der erzeugte Bestand ist als solcher gekennzeichnet und restlos entfernbar

Das System SHALL erzeugte Klassen kennzeichnen und SHALL beim Entfernen nur
gekennzeichnete Klassen anfassen.

#### Scenario: Entfernen

- **WHEN** der Beispielbestand entfernt wird
- **THEN** gehen Beobachtungen samt ihrer Zuordnungen, Stunden, Klassenbild,
  Lehraufträge, Kinder und die Klasse

Hier wird bewusst tiefer geräumt als beim Löschen einer echten Klasse: die Daten
sind erzeugt, und die Kinder gab es vorher nicht.

#### Scenario: Echte Klasse

- **WHEN** das Entfernen auf eine Klasse ohne Kennzeichen angewendet wird
- **THEN** wird abgewiesen
- **AND** es wird nichts gelöscht

#### Scenario: Nichts vorhanden

- **WHEN** entfernt werden soll und kein erzeugter Bestand existiert
- **THEN** endet der Vorgang erfolgreich, ohne etwas zu ändern

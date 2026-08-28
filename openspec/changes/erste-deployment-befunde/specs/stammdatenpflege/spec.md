## ADDED Requirements

### Requirement: Lehraufträge werden je Klasse als Satz gepflegt

Das System SHALL die Unterrichtskontexte, die eine Lehrkraft in einer Klasse
betreut, in einem Vorgang setzen können und SHALL dabei anlegen, was fehlt, und
lösen, was nicht mehr genannt ist.

#### Scenario: Alle Kontexte einer Klasse

- **WHEN** für eine Lehrkraft alle Kontexte einer Klasse gewählt werden
- **THEN** entstehen die fehlenden Aufträge in einem Vorgang
- **AND** die Kennung muss dabei nur einmal angegeben werden

#### Scenario: Kontext mit gehaltenen Stunden

- **WHEN** ein Kontext aus dem Satz genommen wird, in dem bereits Stunden
  gehalten wurden
- **THEN** bleibt der Auftrag bestehen
- **AND** die Rückmeldung weist ihn als behalten aus

Ohne Lehrauftrag verweigert die Zugriffsprüfung den Zugang zu den eigenen
Beobachtungen; ein versehentlich entfernter Haken darf keine Daten unerreichbar
machen.

#### Scenario: Fremde Kennung

- **WHEN** ein Satz für eine andere Kennung als die eigene bearbeitet wird
- **THEN** wird der bestehende Satz nicht vorbelegt
- **AND** es wird darauf hingewiesen, dass gesetzt wird, was angekreuzt ist

### Requirement: Lehraufträge entstehen nur auf vorhandenen Kennungen

Das System SHALL eine angegebene Nextcloud-Kennung prüfen, bevor ein Lehrauftrag
geschrieben wird.

Ein Auftrag auf einer nicht vorhandenen Kennung wirkt sich nirgends aus, weil
jede Abfrage nach der eigenen Kennung filtert — sichtbar wird er erst als leerer
Startdialog.

#### Scenario: Unbekannte Kennung

- **WHEN** eine Kennung angegeben wird, die es in Nextcloud nicht gibt
- **THEN** wird der Vorgang abgewiesen
- **AND** die Meldung sagt, dass der Anmeldename gemeint ist, nicht der
  angezeigte Name

#### Scenario: Eigene Kennung vorbelegt

- **WHEN** die Lehrauftragspflege geöffnet wird
- **THEN** ist die eigene Kennung eingetragen

### Requirement: Ein leerer Startdialog erklärt sich

Das System SHALL im Startdialog ohne wählbare Klasse benennen, auf welche
Kennung gesucht wurde, und SHALL auf die Stelle verweisen, an der ein
Lehrauftrag entsteht.

#### Scenario: Kein Lehrauftrag vorhanden

- **WHEN** für die angemeldete Kennung kein Lehrauftrag vorliegt
- **THEN** nennt die Meldung diese Kennung
- **AND** sie erklärt, dass Klasse und Kontext ausschließlich daraus kommen
- **AND** sie verweist auf die Lehrauftragspflege

### Requirement: Klassen tragen einen Namen

Das System SHALL eine Klasse ohne Namen abweisen.

Der Klassenname steht in der Kopfzeile der laufenden Stunde, im Startdialog, im
Ablagepfad der Arbeitsproben und auf jedem Bericht.

#### Scenario: Leerer Name

- **WHEN** eine Klasse ohne Namen angelegt werden soll
- **THEN** wird der Vorgang im Dienst abgewiesen
- **AND** die Oberfläche bietet die Handlung gar nicht erst an

### Requirement: Klassen lassen sich löschen, solange nichts daran hängt

Das System SHALL das Löschen einer Klasse anbieten und SHALL es abweisen,
solange Beobachtungen oder Unterrichtsstunden auf sie verweisen.

Verschwände die Klasse unter einer bestehenden Beobachtung, fiele diese aus jeder
Auswertung heraus, ohne gelöscht zu sein.

#### Scenario: Klasse ohne Beobachtungen

- **WHEN** eine Klasse ohne Beobachtungen und ohne Stunden gelöscht wird
- **THEN** entfallen Zuordnungen, Klassenbild und Lehraufträge dieser Klasse
- **AND** die Kinder bleiben als eigene Datensätze bestehen

#### Scenario: Klasse mit Beobachtungen

- **WHEN** eine Klasse gelöscht werden soll, an der Beobachtungen hängen
- **THEN** wird der Vorgang abgewiesen
- **AND** die Meldung nennt den Grund

#### Scenario: Rückfrage

- **WHEN** das Löschen angestoßen wird
- **THEN** wird zurückgefragt, bevor gelöscht wird

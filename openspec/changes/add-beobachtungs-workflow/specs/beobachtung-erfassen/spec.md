## ADDED Requirements

### Requirement: Erfassung in einem Schritt

Der Erfassungsbildschirm SHALL genau vier Dinge ermöglichen: Kind wählen, Schnellmarker oder Freitext erfassen, Foto anfügen, abschließen. Er SHALL NOT die Auswahl eines Bildungsstandards, einer Einschätzung, einer Sichtbarkeit oder einer Kategorie verlangen. Er MAY das Vormerken für einen Verwendungszweck anbieten, sofern dies optional bleibt.

#### Scenario: Erfassung ohne Zusatzfelder

- **WHEN** eine Lehrkraft ein Kind auswählt
- **THEN** zeigt das System Schnellmarker, ein Freitextfeld und Schaltflächen für Foto und Sprachaufnahme
- **AND** kein Feld für Bildungsstandard, Einschätzung oder Sichtbarkeit

#### Scenario: Vormerken bleibt optional

- **WHEN** der Erfassungsdialog Verwendungszwecke anbietet
- **THEN** ist keiner davon vorausgewählt
- **AND** das Speichern ist ohne Auswahl eines Verwendungszwecks möglich

### Requirement: Ein Tap auf einen Schnellmarker erzeugt eine vollständige Beobachtung

Das System SHALL einen Schnellmarker mit einer oder mehreren überfachlichen Kompetenzdimensionen des aktiven Kompetenzrahmens verknüpfen. Ein Tap auf einen Schnellmarker SHALL die Beobachtung unmittelbar speichern und den Erfassungsdialog schließen, ohne weiteren Bestätigungsschritt.

#### Scenario: Beobachtung ist auf beiden Achsen zugeordnet

- **WHEN** eine Lehrkraft in einer Mathematikstunde mit Inhaltsfeld „Zahl und Operation" den Marker „hilft anderen" antippt
- **THEN** ist die Beobachtung der Dimension „Rücksichtnahme und Solidarität" im Bereich Sozialkompetenz zugeordnet
- **AND** zusätzlich dem Unterrichtskontext Mathematik und dem Inhaltsfeld „Zahl und Operation"
- **AND** sie gilt als vollständig und erscheint nicht in der Inbox

#### Scenario: Vollständig auch ohne fachliche Achse

- **WHEN** eine Lehrkraft in einer Freiarbeitsstunde den Marker „plant seine Arbeit" antippt
- **THEN** ist die Beobachtung der Dimension „Problemlösekompetenz" im Bereich Lernkompetenz zugeordnet
- **AND** sie trägt keinen Fachbezug
- **AND** sie gilt dennoch als vollständig und erscheint nicht in der Inbox

#### Scenario: Kein zweiter Bestätigungsschritt

- **WHEN** eine Lehrkraft einen Schnellmarker antippt
- **THEN** ist die Beobachtung gespeichert und der Dialog geschlossen
- **AND** es wurde keine weitere Schaltfläche betätigt

### Requirement: Schnellmarker sind je Unterrichtskontext frei konfigurierbar

Das System SHALL Schnellmarker je Unterrichtskontext konfigurierbar machen und SHALL höchstens sechs Marker gleichzeitig anzeigen. Die Verwaltung der Marker SHALL Bestandteil der Anwendung sein. Mitgelieferte Marker SHALL beschreibend statt bewertend formuliert und vollständig änderbar sein.

#### Scenario: Markersätze je Kontext

- **WHEN** eine Lehrkraft eine Stunde im Kontext „Freiarbeit" startet
- **THEN** zeigt das System die für Freiarbeit konfigurierten Marker
- **AND** nicht die für Mathematik konfigurierten

#### Scenario: Marker vollständig ersetzbar

- **WHEN** eine Lehrkraft alle mitgelieferten Marker eines Kontexts durch eigene ersetzt
- **THEN** speichert das System die neuen Marker
- **AND** bereits erfasste Beobachtungen behalten ihren ursprünglichen Markertext und ihre Kompetenzzuordnung

#### Scenario: Höchstens sechs sichtbare Marker

- **WHEN** für einen Unterrichtskontext mehr als sechs Marker als sichtbar konfiguriert sind
- **THEN** verhindert das System das Speichern der Konfiguration mit einem Hinweis

### Requirement: Freitext und Foto erzeugen kuratierungspflichtige Beobachtungen

Beobachtungen mit Freitext, Foto oder ausdrücklicher Markierung SHALL als kuratierungsbedürftig gekennzeichnet werden und in der Inbox erscheinen.

#### Scenario: Freitextbeobachtung landet in der Inbox

- **WHEN** eine Lehrkraft eine Beobachtung mit Freitext speichert
- **THEN** ist sie als kuratierungsbedürftig gekennzeichnet
- **AND** erscheint in der Inbox der Lehrkraft

### Requirement: Foto einer Arbeitsprobe

Das System SHALL das Anfügen von Fotos sowohl aus dem Erfassungsdialog eines Kindes als auch als eigenständigen Einstieg mit anschließender Zuordnung ermöglichen. Fotos SHALL vor dem Hochladen clientseitig auf höchstens 2000 px Kantenlänge verkleinert und von EXIF- und Ortsdaten befreit werden.

#### Scenario: Foto zuerst, Zuordnung danach

- **WHEN** eine Lehrkraft die Kamera aus der Fußzeile öffnet und ein Foto aufnimmt
- **THEN** fordert das System die Zuordnung zu einem Kind der laufenden Stunde an
- **AND** bietet an, die Zuordnung auf später zu verschieben

#### Scenario: Verkleinerung und Metadatenbereinigung

- **WHEN** ein Foto mit 4032 px Kantenlänge und Ortsdaten aufgenommen wird
- **THEN** wird höchstens ein Bild mit 2000 px Kantenlänge übertragen
- **AND** die übertragene Datei enthält keine EXIF- oder Ortsdaten

#### Scenario: Hinweis auf unbeteiligte Kinder

- **WHEN** eine Lehrkraft zum ersten Mal ein Foto aufnimmt
- **THEN** weist das System darauf hin, ausschließlich Arbeitsproben und keine unbeteiligten Kinder zu fotografieren

### Requirement: Sammelbeobachtung für mehrere Kinder

Das System SHALL die Auswahl mehrerer Kinder ermöglichen und daraus je Kind eine eigene Beobachtung mit identischem Inhalt erzeugen.

#### Scenario: Eine Notiz, mehrere Beobachtungen

- **WHEN** eine Lehrkraft vier Kinder auswählt und eine Notiz speichert
- **THEN** entstehen vier Beobachtungen mit identischem Inhalt
- **AND** jede ist genau einem Kind zugeordnet und einzeln bearbeitbar

### Requirement: Speichern wartet nie auf das Netz

Das System SHALL jede Beobachtung zuerst lokal auf dem Gerät speichern und asynchron übertragen. Das System SHALL NOT beim Speichern eine Wartezustandsanzeige zeigen oder die Eingabe blockieren.

#### Scenario: Erfassung ohne Netzverbindung

- **WHEN** eine Lehrkraft ohne Netzverbindung eine Beobachtung speichert
- **THEN** ist die Beobachtung sofort lokal gespeichert und der Dialog geschlossen
- **AND** die Kopfleiste zeigt die Anzahl noch nicht übertragener Beobachtungen

#### Scenario: Nachlieferung bei abgelaufener Sitzung

- **WHEN** die Nextcloud-Sitzung während der Stunde abläuft und danach Beobachtungen erfasst werden
- **THEN** nimmt das System die Beobachtungen weiterhin lokal an
- **AND** überträgt sie nach erneuter Anmeldung vollständig und ohne Dubletten

#### Scenario: Entwurf überlebt das Schließen der Anwendung

- **WHEN** das Gerät während einer laufenden Texteingabe gesperrt wird
- **THEN** ist der Entwurf beim nächsten Öffnen unverändert vorhanden

### Requirement: Rückgängig statt Bestätigung

Nach dem Speichern SHALL das System für mindestens fünf Sekunden eine Möglichkeit zum Rückgängigmachen anbieten. Das System SHALL NOT vor dem Speichern eine Bestätigung verlangen.

#### Scenario: Falsches Kind erwischt

- **WHEN** eine Lehrkraft versehentlich beim falschen Kind einen Marker antippt und innerhalb von fünf Sekunden „rückgängig" wählt
- **THEN** ist die Beobachtung gelöscht und erscheint in keiner Auswertung

### Requirement: Kinder außerhalb der laufenden Klasse sind erreichbar

Das System SHALL eine Suche über alle Kinder anbieten, für deren Klassen die Lehrkraft im laufenden Schuljahr einen Lehrauftrag hat.

#### Scenario: Gastkind aus anderer Klasse

- **WHEN** eine Lehrkraft während einer Stunde in der 3a ein Kind der 4b sucht und auswählt, für das sie einen Lehrauftrag hat
- **THEN** kann sie eine Beobachtung zu diesem Kind erfassen
- **AND** die Beobachtung ist dem Kind und dessen eigener Klasse zugeordnet

### Requirement: Erfassungsbildschirm ist auf iPad und Handy bedienbar

Der Erfassungsbildschirm SHALL ab 700 px Breite Kindauswahl und Erfassung nebeneinander in zwei Spalten darstellen und unterhalb davon einspaltig mit einem von unten einfahrenden Bereich. Auswahlflächen für Kinder SHALL auf allen unterstützten Breiten mindestens 44 px in beiden Richtungen messen.

#### Scenario: Zweispaltige Darstellung auf dem iPad

- **WHEN** der Erfassungsbildschirm bei 1180 px Breite angezeigt wird
- **THEN** sind Kindauswahl und Erfassungsbereich gleichzeitig sichtbar
- **AND** der Erfassungsbereich überdeckt die Kindauswahl nicht

#### Scenario: Einspaltige Darstellung auf dem Handy

- **WHEN** der Erfassungsbildschirm bei 375 px Breite angezeigt wird und ein Kind ausgewählt ist
- **THEN** fährt der Erfassungsbereich von unten ein
- **AND** ein Teil der Kindauswahl bleibt sichtbar

#### Scenario: 20 Kinder ohne Scrollen

- **WHEN** eine Klasse mit 20 Kindern auf einem Gerät ab 375 px Breite angezeigt wird
- **THEN** sind alle 20 Auswahlflächen ohne Scrollen erreichbar

### Requirement: Kindauswahl über das Klassenbild

Der Erfassungsbildschirm SHALL die Kinder als Klassenbild darstellen — ein Kachelraster in der für die Klasse festgelegten Reihenfolge, optional nach Gruppen geteilt. Er SHALL NOT eine alternative Listenansicht als gleichrangige Ansicht anbieten.

#### Scenario: Reihenfolge folgt dem Klassenbild

- **WHEN** eine Lehrkraft eine Stunde in einer Klasse mit angepasster Anordnung startet
- **THEN** erscheinen die Kacheln in der für diese Klasse festgelegten Reihenfolge

#### Scenario: Auswahl bleibt überall möglich

- **WHEN** eine Lehrkraft in der Turnhalle unterrichtet
- **THEN** ist das Klassenbild unverändert nutzbar
- **AND** es wird keine Einrichtung eines Raumplans verlangt

### Requirement: Beobachtungsstand ist während der Stunde sichtbar

Das System SHALL an jeder Auswahlfläche kenntlich machen, wann das Kind zuletzt beobachtet wurde, und SHALL Kinder ohne Beobachtung seit mehr als zwei Wochen visuell hervorheben.

#### Scenario: Lücke fällt im Erfassungsbildschirm auf

- **WHEN** ein Kind seit mehr als zwei Wochen keine Beobachtung erhalten hat
- **THEN** ist seine Auswahlfläche von der übrigen Klasse unterscheidbar dargestellt

#### Scenario: Heutige Beobachtungen sind erkennbar

- **WHEN** ein Kind am laufenden Tag bereits beobachtet wurde
- **THEN** weist seine Auswahlfläche dies aus
- **AND** der Erfassungsbereich listet die heutigen Beobachtungen dieses Kindes

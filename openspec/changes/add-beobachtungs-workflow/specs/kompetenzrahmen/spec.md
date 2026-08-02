## ADDED Requirements

### Requirement: Kompetenzrahmen sind versionierte Referenzdaten

Das System SHALL Kompetenzrahmen als Daten verwalten, nicht als Code. Jeder Rahmen SHALL mindestens eine Rahmenversion mit Gültigkeitsbeginn besitzen. Jede Beobachtungszuordnung SHALL das Paar aus Rahmenversion und Knoten referenzieren, niemals den Knoten allein.

#### Scenario: Neue Rahmenversion lässt Altbestand unberührt

- **WHEN** eine Schule eine neue Version des Kompetenzrahmens importiert
- **THEN** verweisen bestehende Beobachtungen weiterhin auf die Knoten ihrer ursprünglichen Rahmenversion
- **AND** ihre Zuordnungen bleiben inhaltlich unverändert und auswertbar

#### Scenario: Zuordnung ohne Version wird abgelehnt

- **WHEN** eine Zuordnung ohne Angabe einer Rahmenversion gespeichert werden soll
- **THEN** lehnt das System die Zuordnung mit einem Validierungsfehler ab

### Requirement: Struktur des hessischen Kerncurriculums Primarstufe

Das System SHALL den Kompetenzrahmen „Hessen Primarstufe" als Baum aus Knoten abbilden. Jeder Knoten SHALL eine Art (`bereich`, `dimension`, `kompetenzbereich`, `bildungsstandard`, `inhaltsfeld`) und eine Ebene (`ueberfachlich`, `fachlich`) tragen. Fachliche Knoten SHALL einem Fach zugeordnet sein, überfachliche Knoten SHALL fachneutral sein.

#### Scenario: Überfachliche Kompetenzen sind vollständig hinterlegt

- **WHEN** der Rahmen „Hessen Primarstufe" importiert ist
- **THEN** enthält er genau vier überfachliche Bereiche: Personale Kompetenz, Sozialkompetenz, Lernkompetenz, Sprachkompetenz
- **AND** darunter genau 15 Dimensionen: Selbstwahrnehmung, Selbstkonzept, Selbstregulierung, Soziale Wahrnehmungsfähigkeit, Rücksichtnahme und Solidarität, Kooperation und Teamfähigkeit, Umgang mit Konflikten, Gesellschaftliche Verantwortung, Interkulturelle Verständigung, Problemlösekompetenz, Arbeitskompetenz, Medienkompetenz, Lesekompetenz, Schreibkompetenz, Kommunikationskompetenz
- **AND** alle 15 Dimensionen sind fachneutral, also ohne Fachzuordnung

#### Scenario: Fachliche Kompetenzbereiche sind je Fach hinterlegt

- **WHEN** ein Fach des Rahmens abgefragt wird
- **THEN** liefert das System dessen Kompetenzbereiche gemäß Kapitel 4 des jeweiligen Kerncurriculums
- **AND** für Mathematik sind dies: Darstellen, Kommunizieren, Argumentieren, Umgehen mit symbolischen/formalen/technischen Elementen, Problemlösen, Modellieren
- **AND** für Deutsch: Sprechen und Zuhören, Schreiben, Lesen und Rezipieren – mit literarischen und nichtliterarischen Texten/Medien umgehen, Sprache und Sprachgebrauch untersuchen und reflektieren
- **AND** für Sachunterricht: Erkenntnisgewinnung, Kommunikation, Bewertung

#### Scenario: Inhaltsfelder sind je Fach hinterlegt

- **WHEN** die Inhaltsfelder eines Fachs abgefragt werden
- **THEN** liefert das System für Mathematik: Muster und Strukturen, Zahl und Operation, Raum und Form, Größen und Messen, Daten und Zufall
- **AND** für Sachunterricht: Gesellschaft und Politik, Natur, Raum, Technik, Geschichte und Zeit

### Requirement: Bildungsstandards und Inhaltsfelder sind zwei gekreuzte Achsen

Das Kerncurriculum beschreibt das Verhältnis von Bildungsstandards und Inhaltsfeldern als „korrespondierend". Das System SHALL beide als gleichrangige Knotenarten führen und ihre Beziehung als n:m-Zuordnung abbilden. Das System SHALL NOT Inhaltsfelder als Unterknoten von Bildungsstandards oder umgekehrt modellieren.

#### Scenario: Ein Inhaltsfeld korrespondiert mit mehreren Kompetenzbereichen

- **WHEN** das Inhaltsfeld „Zahl und Operation" abgefragt wird
- **THEN** liefert das System alle korrespondierenden Kompetenzbereiche des Fachs Mathematik
- **AND** keiner davon ist Elternknoten des Inhaltsfelds

### Requirement: Bezugsstufen statt Bewertungsskala

Bildungsstandards des hessischen Kerncurriculums beschreiben Könnenserwartungen zu zwei Zeitpunkten: Ende der Jahrgangsstufe 2 und Ende der Jahrgangsstufe 4. Das System SHALL diese Bezugsstufe je Standard führen. Das System SHALL NOT eine Bewertungsskala als Bestandteil des Kerncurriculums ausgeben, da das Kerncurriculum keine solche definiert.

#### Scenario: Standard trägt seine Bezugsstufe

- **WHEN** ein Bildungsstandard abgefragt wird
- **THEN** ist seine Bezugsstufe entweder `jgst_2` oder `jgst_4`

#### Scenario: Einschätzungsskala ist als schuleigen gekennzeichnet

- **WHEN** eine Schule eine Einschätzungsskala für fachliche Standards konfiguriert
- **THEN** kennzeichnet das System diese Skala in Oberfläche und Export als schuleigene Festlegung
- **AND** nicht als Bestandteil des hessischen Kerncurriculums

### Requirement: Überfachliche Kompetenzen werden nicht bewertet

Das Kerncurriculum stellt fest, dass sich überfachliche Kompetenzen „weitgehend einer Normierung und empirischen Überprüfung" entziehen. Das System SHALL für überfachliche Kompetenzdimensionen keine Skala, keine Note und keine automatisch abgeleitete Einstufung anbieten. Es SHALL ausschließlich Belege und deren Häufigkeit ausweisen.

#### Scenario: Keine Einstufung auf überfachlichen Dimensionen

- **WHEN** eine Lehrkraft eine Beobachtung einer überfachlichen Dimension zuordnet
- **THEN** bietet das System kein Feld für eine Bewertung oder Niveaustufe an

#### Scenario: Häufigkeiten werden nicht als Bewertung dargestellt

- **WHEN** eine Auswertung überfachliche Dimensionen zeigt
- **THEN** stellt sie ausschließlich Anzahl und Zeitpunkt von Belegen dar
- **AND** leitet daraus keine Einstufung, Note oder Rangfolge ab

### Requirement: Mehrfachzuordnung ist zulässig

Das Kerncurriculum stellt fest, dass eine „ausschließliche Zuordnung zu nur einem dieser Bereiche oder nur einer der Dimensionen" nicht immer möglich ist. Das System SHALL einer Beobachtung mehrere Kompetenzknoten zuordnen können, auch über Ebenen und Bereiche hinweg.

#### Scenario: Beobachtung mit überfachlicher und fachlicher Zuordnung

- **WHEN** eine Beobachtung einer überfachlichen Dimension und zusätzlich einem fachlichen Inhaltsfeld zugeordnet wird
- **THEN** speichert das System beide Zuordnungen
- **AND** die Beobachtung erscheint in Auswertungen zu beiden Knoten

### Requirement: Kompetenzrahmen sind importier- und austauschbar

Das System SHALL Kompetenzrahmen über ein dokumentiertes Dateiformat importieren und exportieren. Ein Rahmen für ein anderes Bundesland oder ein schuleigenes Raster SHALL ohne Codeänderung nutzbar sein.

#### Scenario: Import eines schuleigenen Rahmens

- **WHEN** eine Schulleitung eine gültige Rahmendatei importiert
- **THEN** legt das System eine neue Rahmenversion mit allen Knoten und Korrespondenzen an
- **AND** der Rahmen steht bei der Konfiguration von Fächern und Schnellmarkern zur Auswahl

#### Scenario: Ungültige Rahmendatei wird abgelehnt

- **WHEN** eine Rahmendatei fehlende Pflichtfelder oder Zyklen im Knotenbaum enthält
- **THEN** bricht das System den Import vollständig ab
- **AND** meldet die betroffenen Knoten, ohne Teildaten zu schreiben

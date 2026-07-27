## ADDED Requirements

### Requirement: Lehrkräfte sind Nextcloud-Nutzer

Das System SHALL Lehrkräfte ausschließlich über bestehende Nextcloud-Benutzerkonten identifizieren und SHALL keine eigene Benutzerverwaltung oder Anmeldung führen.

#### Scenario: Anmeldung über Nextcloud

- **WHEN** eine Lehrkraft kidseye aufruft
- **THEN** nutzt das System ihre bestehende Nextcloud-Anmeldung
- **AND** fordert keine zusätzliche Anmeldung

#### Scenario: Deaktiviertes Konto verliert den Zugriff

- **WHEN** ein Nextcloud-Konto deaktiviert wird
- **THEN** ist für dieses Konto kein Zugriff auf kidseye mehr möglich
- **AND** die von ihm erfassten Beobachtungen bleiben gemäß ihrer Sichtbarkeitsstufe erhalten

### Requirement: Rollen über Nextcloud-Gruppen

Das System SHALL Zugriff auf die Anwendung und auf Verwaltungsfunktionen über Nextcloud-Gruppen steuern. Es SHALL mindestens die Rollen Lehrkraft und Schulleitung unterscheiden und SHALL NOT Nextcloud-Gruppen für die Zuordnung von Klassen oder Fächern verwenden.

#### Scenario: Zugriff nur für Mitglieder der Lehrkraftgruppe

- **WHEN** ein Nextcloud-Nutzer ohne Mitgliedschaft in der konfigurierten Lehrkraftgruppe kidseye aufruft
- **THEN** verweigert das System den Zugriff

#### Scenario: Verwaltungsfunktionen nur für die Leitungsgruppe

- **WHEN** eine Lehrkraft ohne Mitgliedschaft in der Leitungsgruppe den Schülerimport aufruft
- **THEN** verweigert das System den Zugriff

### Requirement: Ablage im schuleigenen Gruppenordner

Das System SHALL Fotos von Arbeitsproben in einem Gruppenordner der Schule ablegen und SHALL NOT den persönlichen Ordner der Lehrkraft verwenden. Der Ablagepfad SHALL nach Schuljahr, Klasse und Kind gegliedert sein.

#### Scenario: Ablage außerhalb des Nutzerordners

- **WHEN** eine Lehrkraft ein Foto zu einem Kind der Klasse 3a im Schuljahr 2026/27 speichert
- **THEN** liegt die Datei unterhalb des konfigurierten Gruppenordners im Pfad aus Schuljahr, Klasse und Kind
- **AND** nicht im persönlichen Ordner der Lehrkraft

#### Scenario: Daten bleiben beim Ausscheiden einer Lehrkraft

- **WHEN** eine Lehrkraft die Schule verlässt und ihr Nextcloud-Konto gelöscht wird
- **THEN** bleiben die von ihr abgelegten Arbeitsproben im Gruppenordner vorhanden

### Requirement: Dateireferenzen über Nextcloud-Datei-IDs

Das System SHALL Dateien ausschließlich über ihre Nextcloud-Datei-ID referenzieren und SHALL NOT Pfade als Referenz speichern.

#### Scenario: Referenz überlebt das Verschieben

- **WHEN** eine Datei innerhalb von Nextcloud umbenannt oder verschoben wird
- **THEN** bleibt die zugehörige Beobachtung mit der Datei verknüpft

### Requirement: Fehlender Gruppenordner wird erkannt

Das System SHALL beim Einrichten prüfen, ob der konfigurierte Gruppenordner vorhanden und beschreibbar ist, und SHALL die Fotofunktion andernfalls mit einem verständlichen Hinweis deaktivieren.

#### Scenario: Hinweis statt stiller Fehler

- **WHEN** der konfigurierte Gruppenordner nicht existiert
- **THEN** meldet das System dies in der Verwaltung
- **AND** die Fotofunktion ist deaktiviert, während Marker- und Freitexterfassung weiterhin funktionieren

### Requirement: Erfassung im Vollbild ohne Nextcloud-Rahmen

Das System SHALL den Erfassungsbildschirm in einem Vollbildmodus ohne Nextcloud-Kopfleiste und Navigation anbieten und SHALL die übrigen Bereiche in der gewohnten Nextcloud-Oberfläche darstellen.

#### Scenario: Umschalten in den Unterrichtsmodus

- **WHEN** eine Lehrkraft den Unterrichtsmodus aufruft
- **THEN** nutzt der Erfassungsbildschirm die gesamte Bildschirmfläche
- **AND** Inbox, Verwaltung und Auswertung bleiben in der Nextcloud-Oberfläche erreichbar

### Requirement: Installation auf dem Home-Bildschirm

Das System SHALL den Erfassungsbildschirm als installierbare Web-Anwendung mit eigenem Symbol und eigenem Startziel bereitstellen, sodass er ohne Browserleiste und ohne Navigation durch Nextcloud startet.

#### Scenario: Start ohne Umweg

- **WHEN** eine Lehrkraft das auf dem Home-Bildschirm abgelegte Symbol antippt
- **THEN** startet unmittelbar der Erfassungsbildschirm
- **AND** ohne Browserleiste und ohne Zwischenschritt über die Nextcloud-Startseite

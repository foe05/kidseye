## ADDED Requirements

### Requirement: Eigenes Symbol auf dem Home-Bildschirm

Das System SHALL ein Symbol mitliefern, das über das Manifest auflösbar ist, und
SHALL es unter jedem im Manifest genannten Pfad tatsächlich ausliefern.

#### Scenario: Symbol ist auslieferbar

- **WHEN** ein im `img/manifest.json` als `icons[].src` genannter Pfad abgerufen wird
- **THEN** antwortet der Server mit Status 200
- **AND** dem im Manifest angegebenen Inhaltstyp

#### Scenario: Symbol beim Ablegen auf dem Home-Bildschirm

- **WHEN** der Erfassungsbildschirm auf einem iPad über „Zum Home-Bildschirm" abgelegt wird
- **THEN** trägt das entstandene Symbol das kidseye-Zeichen
- **AND** nicht das Nextcloud-Logo und keinen Bildschirmabzug der Seite

#### Scenario: Vorgeschlagener Name

- **WHEN** „Zum Home-Bildschirm" den Namen vorschlägt
- **THEN** lautet der Vorschlag `kidseye`
- **AND** nicht der Name der Nextcloud-Instanz

### Requirement: Start im Vollbild und im Erfassungsbildschirm

Das System SHALL das Manifest auf dem Erfassungsbildschirm so einbinden, dass ein
Start über das Symbol ohne Adressleiste und unmittelbar im Erfassungsbildschirm
erfolgt.

#### Scenario: Manifest ist verlinkt

- **WHEN** die Seite `/apps/kidseye/unterricht` ausgeliefert wird
- **THEN** enthält der Kopfbereich ein `link`-Element mit `rel="manifest"`
- **AND** dessen Ziel ist `/apps/kidseye/img/manifest.json`, nicht die Theming-Route

#### Scenario: Startziel des Symbols

- **WHEN** das Symbol angetippt wird
- **THEN** erscheint der Erfassungsbildschirm
- **AND** nicht die Nextcloud-Startseite

#### Scenario: Vollbild ohne Serveroption

- **WHEN** `theming.standalone_window.enabled` auf `false` steht
- **THEN** weist der Einrichtungsstand diesen Punkt als nicht erfüllt aus
- **AND** nennt den occ-Befehl, der ihn setzt

### Requirement: Anmeldung im eigenen Speicherbereich bleibt bestehen

Das System SHALL eine über den Home-Bildschirm installierte Anwendung nach
einmaliger Anmeldung angemeldet halten, auch über Programmende und Geräteneustart
hinweg.

#### Scenario: Erste Anmeldung nach dem Ablegen

- **WHEN** das Symbol zum ersten Mal angetippt wird, während Safari angemeldet ist
- **THEN** erscheint die Nextcloud-Anmeldung
- **AND** dieses Verhalten ist im Protokoll als erwartet ausgewiesen, nicht als Fehler

#### Scenario: Bestehen der Sitzung

- **WHEN** nach der Anmeldung mit „angemeldet bleiben" die Anwendung geschlossen und
  das Gerät neu gestartet wird
- **THEN** erscheint beim erneuten Antippen keine Anmeldung
- **AND** der Erfassungsbildschirm ist unmittelbar bedienbar

### Requirement: Prüfschritte der Geräteprüfung sind belegbar

Das System SHALL zu jedem Prüfschritt der Geräteprüfung, der ohne Hardware
entscheidbar ist, eine automatisierte Prüfung mitführen, damit auf dem Gerät nur
noch geprüft wird, was Hardware verlangt.

#### Scenario: Manifest und Symbol vorab geprüft

- **WHEN** die Testsuite läuft
- **THEN** ist geprüft, dass jeder im Manifest genannte Symbolpfad im Auslieferstand existiert
- **AND** dass `start_url` und `scope` auf den Erfassungsbildschirm der App zeigen

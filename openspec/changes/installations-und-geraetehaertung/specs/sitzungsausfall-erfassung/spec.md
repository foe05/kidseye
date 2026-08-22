## ADDED Requirements

### Requirement: Erfassung nimmt ohne Netz und ohne Sitzung an

Das System SHALL jede Beobachtung lokal auf dem Gerät annehmen und die Annahme
bestätigen, ohne auf eine Serverantwort zu warten — auch bei fehlender Verbindung
und bei abgelaufener Sitzung.

#### Scenario: Erfassung bei abgelaufener Sitzung

- **WHEN** die Nextcloud-Sitzung abgelaufen ist und eine Beobachtung per Marker erfasst wird
- **THEN** ist die Beobachtung sofort angenommen
- **AND** es erscheint kein Wartekreis und keine Fehlermeldung
- **AND** die Bedienung bleibt für weitere Erfassungen offen

#### Scenario: Erfassung ohne Verbindung

- **WHEN** das Gerät keine Netzverbindung hat und drei Beobachtungen erfasst werden
- **THEN** sind alle drei lokal gespeichert
- **AND** keine geht beim Schließen der Anwendung verloren

### Requirement: Zustand der Warteschlange ist erkennbar

Das System SHALL die Anzahl noch nicht übertragener Beobachtungen anzeigen und
SHALL unterscheiden, ob die Übertragung an fehlender Verbindung oder an fehlender
Anmeldung scheitert.

#### Scenario: Hinweis bei fehlender Anmeldung

- **WHEN** die Übertragung an einer abgelaufenen Sitzung scheitert
- **THEN** zeigt die Kopfleiste die Anzahl offener Beobachtungen
- **AND** der Hinweis nennt, dass die Anmeldung fehlt und die Beobachtungen auf dem
  Gerät bleiben

#### Scenario: Umleitung auf die Anmeldeseite

- **WHEN** der Server die Übertragung nicht mit Status 401 oder 403 beantwortet,
  sondern auf die Anmeldeseite umleitet oder HTML statt JSON zurückgibt
- **THEN** wertet das System dies ebenfalls als fehlende Anmeldung
- **AND** zeigt denselben Hinweis wie bei Status 401

#### Scenario: Unterscheidung zur fehlenden Verbindung

- **WHEN** die Übertragung ohne Serverantwort scheitert
- **THEN** nennt der Hinweis die fehlende Verbindung
- **AND** nicht die fehlende Anmeldung

#### Scenario: Rückkehr auf null

- **WHEN** nach erneuter Anmeldung die Übertragung gelingt
- **THEN** geht die Anzeige binnen 20 Sekunden auf null
- **AND** ohne Zutun der Lehrkraft

### Requirement: Nachliefern legt keine Dubletten an

Das System SHALL beim Übertragen der Warteschlange jeden Eintrag anhand seiner
vom Gerät vergebenen Kennung eindeutig halten und SHALL je Eintrag zurückmelden,
ob er neu angelegt oder als bereits vorhanden erkannt wurde.

#### Scenario: Zweifach gesendeter Eintrag

- **WHEN** derselbe Eintrag mit derselben Kennung zweimal übertragen wird
- **THEN** existiert danach genau eine Beobachtung dazu
- **AND** die zweite Übertragung wird als „bereits vorhanden" zurückgemeldet
- **AND** die zweite Übertragung gilt als erfolgreich, nicht als Fehler

#### Scenario: Übertragung ohne Kennung

- **WHEN** ein Eintrag ohne Kennung übertragen wird
- **THEN** weist das System ihn als fehlerhaft zurück
- **AND** die übrigen Einträge derselben Übertragung werden dennoch übernommen

#### Scenario: Erfassungszeitpunkt bleibt der des Geräts

- **WHEN** eine offline erfasste Beobachtung erst Stunden später übertragen wird
- **THEN** trägt sie den Zeitpunkt der Erfassung
- **AND** nicht den der Übertragung

#### Scenario: Ein kaputter Eintrag blockiert die Warteschlange nicht

- **WHEN** ein Eintrag der Warteschlange nicht übernehmbar ist
- **THEN** werden die übrigen Einträge übernommen
- **AND** der nicht übernehmbare bleibt mit seiner Meldung erkennbar bestehen

### Requirement: Rückgängig bleibt auch offline verlässlich

Das System SHALL das Zurücknehmen einer Beobachtung innerhalb des Undo-Fensters
auch dann ermöglichen, wenn sie noch nicht übertragen wurde, und SHALL bereits
übertragene Beobachtungen serverseitig entfernen.

#### Scenario: Zurücknehmen vor der Übertragung

- **WHEN** eine noch nicht übertragene Beobachtung innerhalb des Undo-Fensters
  zurückgenommen wird
- **THEN** verlässt sie die Warteschlange
- **AND** wird nie an den Server gesendet

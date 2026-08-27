## ADDED Requirements

### Requirement: Einrichtungsstand ohne Browser abrufbar

Das System SHALL einen occ-Befehl `kidseye:pruefen` bereitstellen, der jede
Betriebsvoraussetzung einzeln als erfüllt oder nicht erfüllt ausweist und mit
Rückgabewert 0 endet, wenn alle Pflichtvoraussetzungen erfüllt sind, sonst mit 1.

Geprüft werden: Vollständigkeit der Tabellen, geladener Kompetenzrahmen, aktives
Schuljahr, Vorhandensein der beiden Zugriffsgruppen, Erreichbarkeit und
Beschreibbarkeit der Ablage, `theming.standalone_window.enabled`, Auslieferbarkeit
des Manifests samt Symbol sowie das Vorliegen mindestens eines Lehrauftrags.

#### Scenario: Frische Installation vor dem Einrichten

- **WHEN** `occ kidseye:pruefen` unmittelbar nach `occ app:enable kidseye` läuft
- **THEN** meldet die Ausgabe die Tabellen als vollständig
- **AND** Kompetenzrahmen, Schuljahr, Gruppen und Lehrauftrag als nicht erfüllt
- **AND** nennt zu jedem nicht erfüllten Punkt den Befehl oder Schritt, der ihn erfüllt
- **AND** der Rückgabewert ist 1

#### Scenario: Vollständig eingerichtete Installation

- **WHEN** `occ kidseye:pruefen` nach `occ kidseye:einrichten`, angelegten Gruppen,
  eingerichteter Ablage und mindestens einem Lehrauftrag läuft
- **THEN** ist jeder Punkt als erfüllt ausgewiesen
- **AND** der Rückgabewert ist 0

#### Scenario: Fehlende Tabelle wird benannt

- **WHEN** eine der von den Migrationen angelegten Tabellen fehlt
- **THEN** nennt die Ausgabe die fehlende Tabelle beim Namen
- **AND** verweist auf `occ app:disable kidseye && occ app:enable kidseye`

#### Scenario: Maschinenlesbare Ausgabe

- **WHEN** `occ kidseye:pruefen --output=json` läuft
- **THEN** ist die Ausgabe gültiges JSON mit einem Eintrag je Prüfpunkt
- **AND** jeder Eintrag trägt Kennung, Zustand und Meldung

### Requirement: Prüfung ohne Nutzerkontext lauffähig

Das System SHALL die Prüfung auch dann durchführen, wenn kein Nutzer angemeldet
ist, und SHALL nutzerabhängige Punkte in diesem Fall als „nicht prüfbar"
ausweisen statt einen Fehler zu werfen.

#### Scenario: Ablageprüfung ohne Nutzer

- **WHEN** `occ kidseye:pruefen` ohne `--nutzer` läuft
- **THEN** ist die Beschreibbarkeit der Ablage als „nicht prüfbar" ausgewiesen
- **AND** der Hinweis nennt `--nutzer <kennung>` als Weg, sie zu prüfen
- **AND** der Punkt verhindert den Rückgabewert 0 nicht

#### Scenario: Ablageprüfung mit Nutzer

- **WHEN** `occ kidseye:pruefen --nutzer anna` läuft
- **THEN** wird die Ablage im Namen dieses Nutzers geprüft
- **AND** die Ausgabe nennt den gefundenen Pfad und ob er beschreibbar ist

### Requirement: Einrichtungsstand in der Oberfläche vollständig

Das System SHALL im Reiter „Einrichtung" zusätzlich ausweisen, ob mindestens ein
Lehrauftrag vorliegt und ob das Manifest-Symbol ausgeliefert werden kann.

#### Scenario: Kein Lehrauftrag angelegt

- **WHEN** eine Person mit Leitungsrecht den Reiter „Einrichtung" öffnet und kein
  Lehrauftrag existiert
- **THEN** ist der Punkt „Lehraufträge" als nicht erfüllt dargestellt
- **AND** der Hinweis nennt, dass ohne Lehrauftrag keine Stunde startbar ist

#### Scenario: Symbol fehlt im Auslieferstand

- **WHEN** die im Manifest verlangte Symboldatei nicht auslieferbar ist
- **THEN** ist der Punkt „Symbol für den Home-Bildschirm" als nicht erfüllt dargestellt
- **AND** der Hinweis nennt, dass das Symbol auf dem Gerät sonst durch einen
  Bildschirmabzug ersetzt wird

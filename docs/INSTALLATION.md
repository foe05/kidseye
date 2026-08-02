# kidseye in Betrieb nehmen

Von der leeren Nextcloud bis zur ersten Beobachtung. Rechne mit rund einer
Stunde, davon 20 Minuten Wartezeit beim Bauen des Frontends.

> **Wichtig vorweg:** Diese App wurde noch nie auf einer laufenden Nextcloud
> installiert. Der Code ist getestet, die Installation nicht. Wenn etwas
> hakt, liegt es mit einiger Wahrscheinlichkeit an Schritt 2 oder 3 — dort
> stehen die häufigsten Ursachen dabei.
>
> **Mach vorher ein Backup der Datenbank.** Die App legt 21 Tabellen an.

---

## Voraussetzungen

| | |
|---|---|
| Nextcloud | 30 bis 34 |
| PHP | 8.1 oder neuer |
| Node.js | 20 oder neuer, **nur zum Bauen** — auf dem Server nicht nötig |
| Datenbank | MariaDB, MySQL oder PostgreSQL (SQLite geht, ist aber für den Dauerbetrieb nicht empfohlen) |
| App `groupfolders` | für die Ablage der Arbeitsproben |

Version prüfen:

```bash
sudo -u www-data php occ status
```

---

## Schritt 1 · App holen und bauen

Das Frontend wird **nicht** mitgeliefert, es muss einmal gebaut werden. Das
kannst du auf deinem Rechner tun und nur das Ergebnis hochladen — Node muss
nicht auf den Server.

```bash
cd /var/www/nextcloud/apps          # Pfad ggf. anpassen
sudo -u www-data git clone git@github.com:foe05/kidseye.git
cd kidseye

npm ci                              # dauert ein paar Minuten
npm run build                       # erzeugt js/
```

Danach muss ein Verzeichnis `js/` mit `kidseye-main.js` und
`kidseye-unterricht.js` existieren:

```bash
ls -la js/
```

**Wenn `npm ci` scheitert:** meist wegen `--legacy-peer-deps`. Dann:

```bash
npm install --legacy-peer-deps && npm run build
```

**Wenn du auf deinem Rechner baust:** danach den gesamten Ordner auf den
Server kopieren, inklusive `js/`, aber **ohne** `node_modules/`:

```bash
rsync -av --exclude node_modules kidseye/ server:/var/www/nextcloud/apps/kidseye/
```

Rechte prüfen:

```bash
sudo chown -R www-data:www-data /var/www/nextcloud/apps/kidseye
```

---

## Schritt 2 · App aktivieren

```bash
cd /var/www/nextcloud
sudo -u www-data php occ app:enable kidseye
```

Dabei laufen die beiden Migrationen und legen 21 Tabellen mit dem Präfix
`oc_kidseye_` an. Kontrolle:

```bash
sudo -u www-data php occ db:convert-type --help >/dev/null   # nur ein Testaufruf
mysql -u nextcloud -p nextcloud -e "SHOW TABLES LIKE 'oc_kidseye%';" | wc -l
# erwartet: 22 Zeilen (21 Tabellen + Kopfzeile)
```

### Wenn es hier hakt

| Fehler | Ursache | Abhilfe |
|---|---|---|
| `App is not compatible` | Nextcloud-Version außerhalb 30–34 | In `appinfo/info.xml` die `max-version` anpassen und erneut versuchen |
| `Class ... not found` | Autoloader kennt die App noch nicht | `occ app:disable kidseye && occ app:enable kidseye` |
| Fehler beim Anlegen einer Tabelle | Namenskonflikt oder zu langer Index | Meldung notieren — das wäre ein Fehler in der Migration, bitte melden |
| Weiße Seite beim Aufruf | `js/` fehlt | Schritt 1 wiederholen, `npm run build` |

---

## Schritt 3 · Grunddaten einspielen

Ein Befehl legt Kompetenzrahmen, Unterrichtskontexte, Verwendungszwecke und
Markervorschläge an:

```bash
sudo -u www-data php occ kidseye:einrichten --schuljahr 2026/27
```

Erwartete Ausgabe:

```
Kompetenzrahmen: Version 1 mit 231 Knoten und 57 Korrespondenzen.
Unterrichtskontexte: 7 neu angelegt.
Verwendungszwecke: 2 neu angelegt.
Schnellmarker: 42 Vorschläge angelegt.
  Die Marker sind ausdrücklich nur ein Vorschlag. Welche sechs Wörter auf dem
  Bildschirm stehen, entscheidet die Lehrkraft.
Schuljahr: 2026/27 angelegt und aktiviert.
```

Der Befehl ist mehrfach aufrufbar — er legt nur an, was fehlt.

Kontrolle:

```bash
sudo -u www-data php occ kidseye:rahmen:list
```

**Wenn die Zahlen abweichen:** vorher prüfen, ob die Datei gültig ist:

```bash
sudo -u www-data php occ kidseye:rahmen:import --pruefen
```

---

## Schritt 4 · Gruppen anlegen

kidseye steuert den Zugriff über zwei Nextcloud-Gruppen:

```bash
sudo -u www-data php occ group:add kidseye-lehrkraft
sudo -u www-data php occ group:add kidseye-leitung

sudo -u www-data php occ group:adduser kidseye-lehrkraft anna
sudo -u www-data php occ group:adduser kidseye-leitung   schulleitung
```

- **kidseye-lehrkraft** — darf die App benutzen und eigene Beobachtungen erfassen
- **kidseye-leitung** — darf zusätzlich Klassen, Kinder, Lehraufträge und
  Einstellungen verwalten

Serveradministratoren dürfen immer, sonst käme niemand an die Einrichtung.

Die Gruppennamen lassen sich später ändern (Reiter „Einrichtung").

---

## Schritt 5 · Ablage für die Arbeitsproben

Fotos landen bewusst in einem Ordner der **Schule**, nicht im persönlichen
Ordner der Lehrkraft. So bleiben sie da, wenn jemand die Schule wechselt.

1. App **Gruppenordner** (`groupfolders`) aktivieren, falls noch nicht geschehen:
   ```bash
   sudo -u www-data php occ app:enable groupfolders
   ```
2. In der Nextcloud-Verwaltung unter *Verwaltung → Gruppenordner* einen Ordner
   **`Beobachtung`** anlegen
3. Der Gruppe `kidseye-lehrkraft` Schreibrechte darauf geben

Prüfen lässt sich das im Reiter „Einrichtung" der App — dort steht, ob der
Ordner gefunden wurde und beschreibbar ist.

Ein anderer Pfad geht auch; er wird in den Einstellungen hinterlegt.

---

## Schritt 6 · Vollbild sicherstellen

Damit das Symbol auf dem Home-Bildschirm ohne Browserleiste startet:

```bash
sudo -u www-data php occ config:system:get theming.standalone_window.enabled
```

Kommt `false` zurück:

```bash
sudo -u www-data php occ config:system:set theming.standalone_window.enabled --value=true --type=boolean
```

Die App meldet das auch selbst im Reiter „Einrichtung".

---

## Schritt 7 · Klassen und Kinder

Zwei Wege. Für eine ganze Klasse ist der Import schneller.

### Per CSV (empfohlen)

Datei mit Kopfzeile, Semikolon als Trennzeichen:

```csv
vorname;nachname;klasse;geburtsjahr
Mia;Müller;3a;2017
Tim;Schneider;3a;2017
Lea;Wagner;3a;2018
```

In der App: **Einrichtung → Schüler importieren**. Erst „Vorschau" — dort
stehen Dubletten und Fehler. Erst danach „übernehmen". Vorher wird nichts
geschrieben.

### Von Hand

**Klassen & Kinder** → Klasse anlegen, dann Kinder einzeln.

### Lehraufträge — der Schritt, den man leicht vergisst

Ohne Lehrauftrag lässt sich **keine Stunde starten**. Ein Auftrag verbindet
Lehrkraft × Klasse × Unterrichtskontext:

**Klassen & Kinder → Lehraufträge** → Nextcloud-Kennung, Kontext wählen,
anlegen. Für jedes Fach, das die Lehrkraft in dieser Klasse unterrichtet, ein
eigener Auftrag. Auch für Freiarbeit und Sozial- und Arbeitsverhalten.

---

## Schritt 8 · Marker anpassen

Die 42 mitgelieferten Marker sind **Vorschläge**. Welche sechs Wörter im
Unterricht auf dem Bildschirm stehen, entscheidet die Lehrkraft.

**Marker** → Kontext wählen → Texte ändern, Kompetenzen zuordnen, speichern.

Beim Formulieren hilft eine Faustregel: beschreibend statt bewertend. „sucht
Kontakt" trägt weiter als „stört".

Ein Marker kann einen Verwendungszweck mitführen — dann merkt ein Fingertipp
die Beobachtung ohne Zusatzschritt für den Förderplan vor.

---

## Schritt 9 · Aufs iPad

1. In Safari `https://deine-cloud/apps/kidseye/unterricht` öffnen
2. Teilen-Menü → **Zum Home-Bildschirm**
3. Symbol antippen

**Beim ersten Start meldet sich Nextcloud erneut an. Das ist kein Fehler.**
Eine über den Home-Bildschirm installierte Anwendung hat unter iOS einen
eigenen Speicherbereich, getrennt von Safari. Einmal anmelden, „angemeldet
bleiben" wählen — danach bleibt die Sitzung dort bestehen.

Zum Durchprüfen liegt ein Protokoll bereit:
[`tests/protokolle/geraetepruefung.md`](../tests/protokolle/geraetepruefung.md).
Rund 30 Minuten, und danach weißt du, ob der Erfassungs-Workflow trägt.

---

## Schritt 10 · Datenschutz klären

**Vor dem Produktivbetrieb.** kidseye legt keine Aufbewahrungsfrist normativ
fest — die Voreinstellung von 12 Monaten für private Beobachtungen orientiert
sich an § 10 Abs. 3 der hessischen Schul-Datenschutzverordnung, aber die
spricht von *privaten Geräten der Lehrkraft*, und kidseye läuft auf dem
Schulserver.

Eine ausfüllfertige Vorlage mit fünf konkreten Entscheidungsfragen liegt bei:
[`tests/protokolle/datenschutz-vorlage.md`](../tests/protokolle/datenschutz-vorlage.md).

Die Antworten werden dann unter **Einrichtung → Aufbewahrungsfristen**
eingetragen.

---

## Die erste Beobachtung

1. Symbol antippen
2. Klasse und Kontext wählen, optional einen Schwerpunkt → **Loslegen**
3. Ein Kind antippen
4. Ein Wort antippen

Fertig. Die Beobachtung ist gespeichert, dem Kerncurriculum zugeordnet und
erscheint nicht in der Nacharbeit.

Fünf Sekunden lang steht unten **rückgängig** — falls es das falsche Kind war.

---

## Fehlersuche

| Was du siehst | Woran es liegt |
|---|---|
| Weiße Seite | `js/` fehlt → `npm run build` |
| „Für kidseye ist eine Mitgliedschaft … erforderlich" | Nutzer nicht in `kidseye-lehrkraft` |
| „Für diese Klasse liegt kein Lehrauftrag vor" | Schritt 7, letzter Abschnitt |
| Startdialog zeigt keine Klassen | Ebenfalls fehlender Lehrauftrag |
| Kein Foto-Knopf | Gruppenordner fehlt oder nicht beschreibbar → Reiter „Einrichtung" |
| Symbol startet mit Browserleiste | Schritt 6 |
| Zähler `⟳` geht nicht auf 0 | Keine Verbindung oder Sitzung abgelaufen. Die Beobachtungen sind **nicht verloren** — sie liegen auf dem Gerät und gehen nach der nächsten Anmeldung raus |

Ausführliches Protokoll im Nextcloud-Log:

```bash
sudo -u www-data php occ log:tail -f | grep kidseye
```

---

## Wieder entfernen

```bash
sudo -u www-data php occ app:remove kidseye
```

**Achtung:** Das entfernt die App, aber **nicht** die Tabellen und **nicht**
die Fotos im Gruppenordner. Beobachtungsdaten bleiben erhalten. Sollen sie
weg, müssen die Tabellen `oc_kidseye_*` und der Ordner `Beobachtung` von Hand
gelöscht werden.

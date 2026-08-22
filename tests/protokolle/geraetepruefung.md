# Geräteprüfung auf dem iPad

Deckt die Aufgaben **0.2**, **0.2b** und **9.5** ab. Diese drei lassen sich nur
auf echter Hardware gegen eine laufende Nextcloud durchführen — hier steht,
was genau zu tun ist und woran das Ergebnis zu erkennen ist.

**Dauer:** rund 30 Minuten
**Voraussetzung:** `occ kidseye:pruefen --nutzer <lehrkraft>` endet mit 0.
Ist das nicht der Fall, sagt der Befehl, was fehlt — auf dem Gerät wäre
dieselbe Ursache deutlich schwerer zu finden.

**Was hier steht, braucht Hardware.** Alles, was sich ohne Gerät entscheiden
lässt, ist vorab automatisiert geprüft und in der Spalte „vorab" vermerkt:

| Zeichen | Bedeutung |
|---|---|
| ✓ vorab | Automatisiert geprüft (`npm test`, PHP-Tests). Schlägt der Schritt hier trotzdem fehl, liegt es am Gerät oder an der Instanz, nicht am Auslieferstand |
| — | Braucht das Gerät. Das ist der eigentliche Zweck dieses Protokolls |

---

## A · Symbol auf dem Home-Bildschirm (0.2b)

| # | Schritt | Erwartet | vorab | ✓ |
|---|---|---|---|---|
| A1 | In Safari `…/apps/kidseye/unterricht` öffnen | Erfassungsbildschirm, **ohne** Nextcloud-Kopfleiste und ohne App-Navigation | — | ☐ |
| A2 | Teilen-Menü → „Zum Home-Bildschirm" | Vorgeschlagener Name lautet **kidseye**, nicht der Name der Nextcloud-Instanz | ✓ vorab | ☐ |
| A3 | Symbol prüfen | Eigenes kidseye-Symbol, nicht das Nextcloud-Logo | ✓ vorab | ☐ |
| A4 | Symbol antippen | Startet **direkt** im Erfassungsbildschirm, nicht auf der Nextcloud-Startseite | ✓ vorab | ☐ |
| A5 | Auf Browserleiste achten | **Keine** Adressleiste sichtbar (Vollbild) | — | ☐ |

**Wenn A5 fehlschlägt:** Die Serveroption `theming.standalone_window.enabled`
steht vermutlich auf `false`. Der Reiter „Einrichtung" meldet das; prüfen mit
`occ config:system:get theming.standalone_window.enabled`.

**Wenn A3 oder A4 fehlschlägt:** Das Manifest greift nicht. Es kommt seit der
Installationshärtung aus dem `PageController` und nicht mehr als Datei — prüfen,
ob die Seite es verlinkt:

```bash
curl -s '…/apps/kidseye/unterricht' | grep 'rel="manifest"'
curl -s '…/apps/kidseye/manifest.webmanifest'
```

Im Manifest müssen `start_url` und `scope` auf **diese** Instanz zeigen, und
`icons[].src` muss abrufbar sein. Bis zur Härtung stand dort
`../apps/kidseye/unterricht`, was gegen den Ort des Manifests zu
`/apps/kidseye/apps/kidseye/unterricht` auflöste — genau dieser Fehler ließ A4
fehlschlagen.

---

## B · Anmeldung im installierten Modus (0.2, D15)

Erwartung aus dem Entwurf: Der **erste** Start aus dem Symbol verlangt eine
eigene Anmeldung, weil eine über den Home-Bildschirm installierte Anwendung
unter iOS einen eigenen Speicherbereich hat. Das ist einmalig und kein Fehler.

| # | Schritt | Erwartet | vorab | ✓ |
|---|---|---|---|---|
| B1 | Symbol zum ersten Mal antippen | Nextcloud-Anmeldung erscheint, obwohl Safari angemeldet ist | — | ☐ |
| B2 | Anmelden, „angemeldet bleiben" wählen | Erfassungsbildschirm erscheint | — | ☐ |
| B3 | App schließen, Symbol erneut antippen | **Keine** erneute Anmeldung | — | ☐ |
| B4 | Gerät neu starten, Symbol antippen | **Keine** erneute Anmeldung | — | ☐ |

**Wenn B3 oder B4 fehlschlägt**, ist die Sitzung zu kurzlebig. Zu prüfen:
`session_lifetime` und `remember_login_cookie_lifetime` in der `config.php`.

---

## C · Sitzungsablauf mitten im Unterricht (0.2)

Der kritischste Fall im ganzen Entwurf. Erwartung: Eine abgelaufene Sitzung
darf **keine** Beobachtung kosten.

| # | Schritt | Erwartet | vorab | ✓ |
|---|---|---|---|---|
| C1 | Stunde starten, zwei Beobachtungen per Marker erfassen | Zähler `⟳` in der Kopfleiste geht auf 0 zurück | — | ☐ |
| C2 | Am Server alle Sitzungen beenden: `occ user:delete-tokens <nutzer>` — hilfsweise das WLAN abschalten | — | — | ☐ |
| C3 | Drei weitere Beobachtungen erfassen | Jede wird **sofort** angenommen, kein Wartekreis, kein Fehler | ✓ vorab | ☐ |
| C4 | Kopfleiste ansehen | `⟳ 3`, beim Antippen der Hinweis „Nicht angemeldet — die Beobachtungen bleiben auf dem Gerät" | ✓ vorab | ☐ |
| C5 | Neu anmelden | Zähler geht binnen 20 Sekunden auf 0 | — | ☐ |
| C6 | In der Auswertung nachsehen | **Alle fünf** Beobachtungen vorhanden, keine doppelt | ✓ vorab | ☐ |

**C4 — die Antwort des Servers bitte notieren.** Die Erkennung prüft drei
Merkmale: Status 401/403, HTML statt JSON, Anmeldepfad in der Endadresse
(`src/offline.js`, `istAbgemeldet`). Welche Form diese Instanz tatsächlich
liefert, ist offen und lässt sich ohne laufenden Server nicht beantworten.

In den Entwicklerwerkzeugen von Safari nachsehen, was
`POST …/api/v1/beobachtung/sync` beantwortet:

Status: ________  Inhaltstyp: ________  Endadresse: ____________________

Steht dort ein sauberes 401, greift das Kopfzeilenfeld `X-Requested-With` wie
gedacht. Steht dort HTML mit 200, greifen die beiden anderen Merkmale — und
der Hinweis muss trotzdem „Nicht angemeldet" lauten. Sagt er stattdessen
„Keine Verbindung", ist die Erkennung an dieser Instanz zu schwach.

C6 ist der eigentliche Prüfstein. Ein doppelter Eintrag bedeutet, dass der
Dublettenschutz über `client_uuid` nicht greift. Belegen lässt sich das jetzt
auch direkt: die Antwort auf `…/api/v1/beobachtung/sync` führt je Eintrag
`zustand` mit `neu` oder `bereits_vorhanden`.

---

## D · Zeitbudget (9.5)

Erwartung aus D1: Erfassung per Schnellmarker in **unter 10 Sekunden**, ab
gesperrtem Gerät gemessen.

**Vorgehen:** Zweite Person mit Stoppuhr. Fünf Durchläufe, jeweils Gerät
sperren, dann messen bis die Beobachtung gesichert ist.

| Durchlauf | Sekunden | ✓ |
|---|---|---|
| 1 | ________ | ☐ |
| 2 | ________ | ☐ |
| 3 | ________ | ☐ |
| 4 | ________ | ☐ |
| 5 | ________ | ☐ |
| **Mittel** | ________ | |

Zerlegung zur Fehlersuche, falls das Mittel über 10 Sekunden liegt:

| Abschnitt | Erwartet | Gemessen |
|---|---|---|
| Entsperren bis Home-Bildschirm | ~2 s | ______ |
| Symbol antippen bis Klassenbild sichtbar | ~2 s | ______ |
| Kind antippen bis Erfassungsbereich | <1 s | ______ |
| Marker antippen bis geschlossen | <1 s | ______ |

**Häufigste Ursache für zu lange Zeiten:** Die Anwendung startet nicht im
laufenden Kontext, sondern zeigt erst den Startdialog. Dann greift die
90-Minuten-Regel nicht wie gedacht — Stundenbeginn und Uhrzeit vergleichen.

---

## E · Bedienbarkeit (9.7, ergänzend)

Die Maße sind rechnerisch geprüft (`tests/js/layout.spec.js`), die Handhabung
nicht.

| # | Prüfung | Erwartet | vorab | ✓ |
|---|---|---|---|---|
| E1 | 20 Kinder im Klassenbild | Alle ohne Scrollen sichtbar | ✓ vorab | ☐ |
| E2 | Kacheln mit dem Daumen treffen | Kein Fehlgriff auf Nachbarkacheln | — | ☐ |
| E3 | Gerät quer drehen | Zweispaltig, Erfassungsbereich verdeckt das Klassenbild nicht | — | ☐ |
| E4 | Auf dem Handy prüfen (375 px) | Einspaltig, Bereich fährt von unten ein | — | ☐ |
| E5 | Kind ohne Beobachtung seit >2 Wochen | Kachel deutlich unterscheidbar (gestrichelt, blasser) | — | ☐ |

---

## Ergebnis

Datum: ____________  Gerät: ____________  Nextcloud-Version: ____________

Bestanden: ☐ vollständig  ☐ mit Einschränkungen  ☐ nicht bestanden

Offene Punkte:

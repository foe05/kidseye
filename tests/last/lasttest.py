#!/usr/bin/env python3
"""Lasttest des Datenmodells (Kapitel 9.6).

Prüft die Abfrageformen aus AuswertungService, InboxService und
BeobachtungService gegen den Jahresbestand einer Lehrkraft: rund 3.800
Beobachtungen, hier auf 5.000 aufgerundet.

Das Schema entspricht den beiden Doctrine-Migrationen. Getestet wird SQLite
statt MariaDB oder PostgreSQL — die absoluten Zeiten sind deshalb nicht
übertragbar, wohl aber die Frage, die hier zählt: greifen die angelegten
Indizes, oder läuft eine Abfrage über die gesamte Tabelle?

Aufruf:  python3 tests/last/lasttest.py
"""
import random
import sqlite3
import sys
import time

BEOBACHTUNGEN = 5000
KINDER = 20
KONTEXTE = 7
KNOTEN = 231           # entspricht dem Seed hessen-primarstufe-2011
GRENZE_MS = 150.0      # ab hier wird es auf einem Schulserver spürbar

SCHEMA = """
CREATE TABLE kidseye_schueler (
    id INTEGER PRIMARY KEY, vorname TEXT, nachname TEXT,
    kuerzel TEXT, aktiv INTEGER DEFAULT 1, geloescht_am TEXT);
CREATE TABLE kidseye_kontext (
    id INTEGER PRIMARY KEY, kennung TEXT, name TEXT, art TEXT,
    fach_kennung TEXT, aktiv INTEGER DEFAULT 1, sortierung INTEGER DEFAULT 0);
CREATE TABLE kidseye_knoten (
    id INTEGER PRIMARY KEY, version_id INTEGER, eltern_id INTEGER,
    kennung TEXT, art TEXT, ebene TEXT, fach_kennung TEXT,
    bezeichnung TEXT, beschreibung TEXT, struktur_name TEXT,
    bezugsstufe TEXT, sortierung INTEGER, waehlbar INTEGER DEFAULT 1);
CREATE TABLE kidseye_beobachtung (
    id INTEGER PRIMARY KEY, client_uuid TEXT, schueler_id INTEGER,
    nutzer_id TEXT, stunde_id INTEGER, klasse_id INTEGER, kontext_id INTEGER,
    version_id INTEGER, erfasst_am TEXT, text TEXT, marker_id INTEGER,
    marker_text TEXT, sichtbarkeit TEXT DEFAULT 'privat', akte_am TEXT,
    kuratierung INTEGER DEFAULT 0, erledigt_am TEXT, gemerkt INTEGER DEFAULT 0,
    loeschen_ab TEXT, geloescht_am TEXT);
CREATE TABLE kidseye_beob_knoten (
    id INTEGER PRIMARY KEY, beobachtung_id INTEGER, version_id INTEGER,
    knoten_id INTEGER, herkunft TEXT, stufe_id INTEGER);
CREATE TABLE kidseye_beob_datei (
    id INTEGER PRIMARY KEY, beobachtung_id INTEGER, file_id INTEGER, dateiname TEXT);
CREATE TABLE kidseye_beob_zweck (
    id INTEGER PRIMARY KEY, beobachtung_id INTEGER, zweck_id INTEGER, herkunft TEXT);
"""

# Wortgleich zu den Indizes aus Version001001Date20260727130000
INDIZES = """
CREATE UNIQUE INDEX kidseye_beob_client ON kidseye_beobachtung (nutzer_id, client_uuid);
CREATE INDEX kidseye_beob_kind   ON kidseye_beobachtung (schueler_id, erfasst_am);
CREATE INDEX kidseye_beob_inbox  ON kidseye_beobachtung (nutzer_id, kuratierung, erledigt_am);
CREATE INDEX kidseye_beob_klasse ON kidseye_beobachtung (klasse_id, erfasst_am);
CREATE UNIQUE INDEX kidseye_bk_paar   ON kidseye_beob_knoten (beobachtung_id, knoten_id);
CREATE INDEX kidseye_bk_knoten ON kidseye_beob_knoten (knoten_id);
CREATE INDEX kidseye_bd_beob   ON kidseye_beob_datei (beobachtung_id);
CREATE INDEX kidseye_bz_zweck  ON kidseye_beob_zweck (zweck_id);
"""


def befuellen(db):
    zufall = random.Random(20260727)
    db.executescript(SCHEMA)

    db.executemany(
        'INSERT INTO kidseye_schueler (id, vorname, nachname, kuerzel) VALUES (?,?,?,?)',
        [(i, f'Kind{i}', f'N{i}', f'kind-{i}') for i in range(1, KINDER + 1)])
    db.executemany(
        'INSERT INTO kidseye_kontext (id, kennung, name, art) VALUES (?,?,?,?)',
        [(i, f'k{i}', f'Kontext {i}',
          'schulfach' if i <= 5 else 'fachneutral') for i in range(1, KONTEXTE + 1)])
    db.executemany(
        'INSERT INTO kidseye_knoten (id, version_id, kennung, art, ebene, bezeichnung, '
        'sortierung, waehlbar) VALUES (?,1,?,?,?,?,?,1)',
        [(i, f'n{i}', 'dimension' if i <= 15 else 'bildungsstandard',
          'ueberfachlich' if i <= 15 else 'fachlich', f'Knoten {i}', i)
         for i in range(1, KNOTEN + 1)])

    beobachtungen, zuordnungen = [], []
    zuordnungId = 1
    jetzt = time.time()
    for i in range(1, BEOBACHTUNGEN + 1):
        # gleichmäßig über ein Schuljahr verteilt
        zeit = jetzt - zufall.uniform(0, 190 * 86400)
        beobachtungen.append((
            i, f'uuid-{i}', zufall.randint(1, KINDER), 'lehrerin',
            zufall.randint(1, KONTEXTE), 1,
            time.strftime('%Y-%m-%d %H:%M:%S', time.localtime(zeit)),
            'Text' if i % 10 < 3 else None,
            zufall.choice(['privat', 'privat', 'privat', 'akte']),
            1 if i % 10 < 3 else 0,
        ))
        # 70 Prozent Markerbeobachtungen tragen eine überfachliche Zuordnung
        for knoten in zufall.sample(range(1, 16), zufall.choice([1, 1, 2])):
            zuordnungen.append((zuordnungId, i, 1, knoten, 'marker'))
            zuordnungId += 1

    db.executemany(
        'INSERT INTO kidseye_beobachtung (id, client_uuid, schueler_id, nutzer_id, '
        'kontext_id, klasse_id, erfasst_am, text, sichtbarkeit, kuratierung) '
        'VALUES (?,?,?,?,?,?,?,?,?,?)', beobachtungen)
    db.executemany(
        'INSERT INTO kidseye_beob_knoten (id, beobachtung_id, version_id, knoten_id, herkunft) '
        'VALUES (?,?,?,?,?)', zuordnungen)
    db.commit()
    return len(beobachtungen), len(zuordnungen)


ABFRAGEN = {
    'Zeitleiste, Voreinstellung acht Wochen': """
        SELECT b.* FROM kidseye_beobachtung b
        WHERE b.schueler_id = 7 AND b.nutzer_id = 'lehrerin'
          AND b.geloescht_am IS NULL AND b.erfasst_am >= date('now','-56 days')
        ORDER BY b.erfasst_am DESC""",
    'Zeitleiste, ganzes Schuljahr': """
        SELECT b.* FROM kidseye_beobachtung b
        WHERE b.schueler_id = 7 AND b.nutzer_id = 'lehrerin' AND b.geloescht_am IS NULL
        ORDER BY b.erfasst_am DESC""",
    'Inbox, offene Einträge': """
        SELECT b.* FROM kidseye_beobachtung b
        WHERE b.nutzer_id = 'lehrerin' AND b.kuratierung = 1
          AND b.erledigt_am IS NULL AND b.geloescht_am IS NULL
        ORDER BY b.erfasst_am DESC LIMIT 200""",
    'Heatmap, Klasse mal Kompetenzknoten': """
        SELECT b.schueler_id, z.knoten_id, COUNT(b.id) FROM kidseye_beobachtung b
        JOIN kidseye_beob_knoten z ON z.beobachtung_id = b.id
        WHERE b.nutzer_id = 'lehrerin' AND b.geloescht_am IS NULL
          AND z.knoten_id BETWEEN 1 AND 15
        GROUP BY b.schueler_id, z.knoten_id""",
    'Lücken-Radar, letzte Beobachtung je Kind und Kontext': """
        SELECT b.schueler_id, b.kontext_id, MAX(b.erfasst_am) FROM kidseye_beobachtung b
        WHERE b.nutzer_id = 'lehrerin' AND b.geloescht_am IS NULL
        GROUP BY b.schueler_id, b.kontext_id""",
    'Klassenbild, Stand je Kind': """
        SELECT b.schueler_id, MAX(b.erfasst_am), COUNT(b.id) FROM kidseye_beobachtung b
        WHERE b.nutzer_id = 'lehrerin' AND b.geloescht_am IS NULL
        GROUP BY b.schueler_id""",
    'Dublettenschutz der Warteschlange': """
        SELECT id FROM kidseye_beobachtung
        WHERE nutzer_id = 'lehrerin' AND client_uuid = 'uuid-4711'""",
    'Mappe, regelbasiert über Knotenmenge': """
        SELECT DISTINCT b.id FROM kidseye_beobachtung b
        JOIN kidseye_beob_knoten z ON z.beobachtung_id = b.id
        WHERE b.schueler_id = 7 AND b.nutzer_id = 'lehrerin'
          AND z.knoten_id IN (1,2,3,4,5,6,7,8,9,10,11,12,13,14,15)""",
}


def messen(db, sql, laeufe=5):
    zeiten = []
    for _ in range(laeufe):
        start = time.perf_counter()
        zeilen = db.execute(sql).fetchall()
        zeiten.append((time.perf_counter() - start) * 1000)
    return min(zeiten), len(zeilen)


def main():
    db = sqlite3.connect(':memory:')
    anzahl, zuordnungen = befuellen(db)
    print(f'Bestand: {anzahl} Beobachtungen, {zuordnungen} Kompetenzzuordnungen, '
          f'{KINDER} Kinder, {KONTEXTE} Kontexte\n')

    print('--- ohne Indizes ---')
    ohne = {name: messen(db, sql)[0] for name, sql in ABFRAGEN.items()}

    db.executescript(INDIZES)
    db.execute('ANALYZE')

    print('--- mit den Indizes aus der Migration ---\n')
    print(f'{"Abfrage":52} {"ohne":>9} {"mit":>9} {"Zeilen":>7}  Scan?')
    fehler = []
    for name, sql in ABFRAGEN.items():
        dauer, zeilen = messen(db, sql)
        plan = db.execute('EXPLAIN QUERY PLAN ' + sql).fetchall()
        scan = any('SCAN' in str(p[3]) and 'USING' not in str(p[3]) for p in plan)
        print(f'{name:52} {ohne[name]:7.2f}ms {dauer:7.2f}ms {zeilen:7d}  '
              f'{"VOLLSCAN" if scan else "Index"}')
        if dauer > GRENZE_MS:
            fehler.append(f'{name}: {dauer:.1f} ms über der Grenze von {GRENZE_MS} ms')

    print()
    if fehler:
        for f in fehler:
            print('FEHLER:', f)
        return 1
    print(f'Alle Abfragen unter {GRENZE_MS:.0f} ms beim Jahresbestand einer Lehrkraft.')
    return 0


if __name__ == '__main__':
    sys.exit(main())

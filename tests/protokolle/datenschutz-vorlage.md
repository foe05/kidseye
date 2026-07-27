# Vorlage für die schulische Datenschutzbeauftragung

Deckt Aufgabe **0.4** ab, soweit sie ohne Unterschrift abzudecken ist. Die
Rechtsgrundlagen sind recherchiert und im Code hinterlegt; die Bewertung und
Freigabe obliegt der oder dem Datenschutzbeauftragten nach § 11 SchDSV.

**An:** schulische Datenschutzbeauftragung
**Betrifft:** kidseye — Schülerbeobachtung auf der Nextcloud der Schule

---

## 1 · Was verarbeitet wird

| | |
|---|---|
| **Zweck** | Dokumentation von Unterrichtsbeobachtungen als Beleggrundlage für Elterngespräche, Zeugnisse und Förderplanung |
| **Betroffene** | Schüler:innen der Primarstufe |
| **Datenarten** | Vor- und Nachname, Geburtsjahr, Klassenzugehörigkeit; Beobachtungstexte; Fotos von Arbeitsproben; Zuordnungen zu Kompetenzen des hessischen Kerncurriculums |
| **Herkunft** | Erhebung durch die Lehrkraft im Unterricht |
| **Ort der Verarbeitung** | Ausschließlich auf der selbst gehosteten Nextcloud der Schule. Keine Übermittlung an Dritte, keine Auftragsverarbeitung, kein Cloud-Dienst |
| **Zugriff** | In der ersten Ausbaustufe **ausschließlich die erfassende Lehrkraft** |
| **Fotos** | In einem Gruppenordner der Schule, nicht im persönlichen Ordner der Lehrkraft. Vor dem Hochladen auf 2000 px verkleinert und von EXIF- und Ortsdaten befreit |

**Ausdrücklich nicht verarbeitet:** keine Noten, keine automatische Einstufung,
keine algorithmische Bewertung. Das System erzeugt Belege und Zählwerte; jede
pädagogische Einschätzung trifft die Lehrkraft.

---

## 2 · Herangezogene Rechtsgrundlagen

Verordnung über die Verarbeitung personenbezogener Daten in Schulen und
statistische Erhebungen an Schulen (**SchDSV**) vom 4. Februar 2009, sowie
Hessisches Schulgesetz (**HSchG**).

| Regelung | Wortlaut / Inhalt | Umsetzung in kidseye |
|---|---|---|
| **§ 10 Abs. 1 SchDSV** | „In Schulen sind personenbezogene Daten nur so lange aufzubewahren, wie sie für die Erfüllung des Bildungs- und Erziehungsauftrags […] erforderlich sind. Die Aufbewahrungsfristen richten sich nach Anlage 3." | Fristen sind je Sichtbarkeitsstufe konfigurierbar |
| **§ 10 Abs. 3 SchDSV** | „[…] sind zu löschen, wenn ihre Kenntnis für die Aufgabenerfüllung nicht mehr erforderlich ist, spätestens jedoch **ein Jahr nach dem Ende des jeweiligen Schuljahres**." | Voreinstellung der Stufe `privat`: **12 Monate** |
| **§ 10 Abs. 4 SchDSV** | Vernichtung erst nach Abstimmung mit dem zuständigen Staatsarchiv | Stufe `akte` hat **keine** automatische Frist |
| **§ 3 Abs. 2 Satz 2 SchDSV** | „Nach Ende des Datenverarbeitungsvorgangs sind alle für die Schüler- oder die Schulaktenführung relevanten Daten unverzüglich zu diesen Akten zu nehmen." | Einbahniger Übergang `privat` → `akte`, danach unveränderlich |
| **§ 3 Abs. 5 SchDSV** | Die Schule bleibt datenverarbeitende Stelle | Fotos im Gruppenordner der Schule, nicht bei der Lehrkraft |
| **§ 72 Abs. 5 HSchG, § 1 Abs. 7 SchDSV** | Einsichtsrecht in die Schülerakte | PDF-Auskunft auf Knopfdruck, ohne private Aufzeichnungen |
| **Art. 15 DSGVO** | Auskunftsrecht | eigener Bericht, jede Erteilung wird protokolliert |

---

## 3 · Die drei Sichtbarkeitsstufen

```
   privat  ────────▶  klassenteam  ────────▶  akte
   nur die                (in v1 nicht        einbahnig,
   erfassende             erreichbar)         unveränderlich
   Lehrkraft                                  Korrektur nur
                                              als Nachtrag
```

| Stufe | Einordnung | Frist (Voreinstellung) | In der Auskunft? |
|---|---|---|---|
| `privat` | persönliche Aufzeichnung der Lehrkraft, nicht Teil der Schülerakte | 12 Monate | **nein** |
| `klassenteam` | intern geteilt — in v1 gesperrt | 24 Monate | ja |
| `akte` | schulische Unterlage | keine automatische | ja |

---

## 4 · Fragen zur Entscheidung

**4.1** § 10 Abs. 3 SchDSV spricht von *privaten Datenverarbeitungs­einrichtungen
der Lehrkräfte*. kidseye läuft auf dem Server der Schule. Ist die Frist von
einem Jahr nach Schuljahresende dennoch der richtige Maßstab für die Stufe
`privat` — oder gilt hier etwas anderes?

☐ übertragbar, 12 Monate  ☐ andere Frist: ______  ☐ anders zu bewerten

**4.2** Welche Frist nach Anlage 3 SchDSV gilt für Beobachtungen, die in die
Schülerakte übernommen wurden?

_____________________________________________

**4.3** Ist die Stufe `privat` als persönliche Aufzeichnung außerhalb der
Schülerakte zulässig, wenn sie auf dem Schulserver liegt?

☐ ja  ☐ nein  ☐ mit Auflagen: ______________________

**4.4** Fotos von Arbeitsproben: reicht die Ablage im Gruppenordner mit
EXIF-Bereinigung, oder ist eine gesonderte Einwilligung der Eltern nötig?

_____________________________________________

**4.5** Ist ein Verzeichnis von Verarbeitungstätigkeiten nach Art. 30 DSGVO
zu ergänzen? Falls ja, wird dieses Dokument als Grundlage genügen?

☐ ja  ☐ nein, zusätzlich nötig: ______________________

---

## 5 · Was das System bereits leistet

- Jede Änderung der Sichtbarkeit, jede Löschung und jede Auskunft wird
  protokolliert; das Protokoll ist über die Oberfläche nicht veränderbar
- Abgelaufene Fristen werden nur **gekennzeichnet** — gelöscht wird erst nach
  ausdrücklicher Bestätigung
- Zum Schuljahresende schlägt das System private Rohbeobachtungen zur Löschung
  vor; Einträge der Stufen `klassenteam` und `akte` sind dabei ausdrücklich
  **nicht** vorausgewählt
- Beim Löschen einer Beobachtung wird die zugehörige Datei mit entfernt, sofern
  keine andere Beobachtung sie referenziert
- Die Auskunft nach Art. 15 DSGVO enthält alle Einträge der Stufen
  `klassenteam` und `akte`, **keine** der Stufe `privat`

---

Vorgelegt am: ____________  durch: ____________

Stellungnahme: ☐ keine Bedenken  ☐ Bedenken, siehe Anlage  ☐ Rückfragen

Datum, Unterschrift: ____________________________

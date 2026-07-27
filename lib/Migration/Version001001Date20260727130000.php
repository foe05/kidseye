<?php

declare(strict_types=1);

namespace OCA\KidsEye\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Schüler:innen, Klassen, Lehraufträge, Stunden, Beobachtungen,
 * Schnellmarker, Verwendungszwecke, Protokoll.
 *
 * Leitplanken aus design.md, die hier im Schema landen:
 *  - D7  Sichtbarkeit ist eine Eigenschaft der einzelnen Beobachtung.
 *        Alle drei Stufen sind vorhanden; `klassenteam` ist in v1 nur
 *        nicht erreichbar, damit ein späterer Mehrbenutzerbetrieb keine
 *        Datenmigration braucht.
 *  - D13 Schüler:innen sind app-eigene Entitäten, keine Nextcloud-Nutzer.
 *  - D16 Verwendungszwecke sind konfigurierbar; regelbasierte Zwecke
 *        speichern keine Vormerkung, sondern eine Knotenmenge.
 */
class Version001001Date20260727130000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->schuljahr($schema);
		$this->klasse($schema);
		$this->schueler($schema);
		$this->klassenzugehoerigkeit($schema);
		$this->lehrauftrag($schema);
		$this->klassenbild($schema);
		$this->stunde($schema);
		$this->verwendungszweck($schema);
		$this->schnellmarker($schema);
		$this->beobachtung($schema);
		$this->beobachtungKnoten($schema);
		$this->beobachtungDatei($schema);
		$this->beobachtungZweck($schema);
		$this->nachtrag($schema);
		$this->protokoll($schema);
		$this->skala($schema);

		return $schema;
	}

	private function schuljahr(ISchemaWrapper $schema): void {
		if ($schema->hasTable('kidseye_schuljahr')) {
			return;
		}
		$t = $schema->createTable('kidseye_schuljahr');
		$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		// z. B. "2026/27"
		$t->addColumn('kennung', Types::STRING, ['notnull' => true, 'length' => 16]);
		$t->addColumn('beginn', Types::DATE, ['notnull' => true]);
		$t->addColumn('ende', Types::DATE, ['notnull' => true]);
		$t->addColumn('aktiv', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
		$t->setPrimaryKey(['id']);
		$t->addUniqueIndex(['kennung'], 'kidseye_sj_kennung');
	}

	private function klasse(ISchemaWrapper $schema): void {
		if ($schema->hasTable('kidseye_klasse')) {
			return;
		}
		$t = $schema->createTable('kidseye_klasse');
		$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$t->addColumn('schuljahr_id', Types::BIGINT, ['notnull' => true]);
		$t->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 64]);
		// Verkettung über Schuljahre hinweg, damit der Rollover die Vorgängerklasse kennt
		$t->addColumn('vorgaenger_id', Types::BIGINT, ['notnull' => false]);
		$t->addColumn('sortierung', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$t->setPrimaryKey(['id']);
		$t->addUniqueIndex(['schuljahr_id', 'name'], 'kidseye_klasse_name');
	}

	private function schueler(ISchemaWrapper $schema): void {
		if ($schema->hasTable('kidseye_schueler')) {
			return;
		}
		$t = $schema->createTable('kidseye_schueler');
		$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$t->addColumn('vorname', Types::STRING, ['notnull' => true, 'length' => 128]);
		$t->addColumn('nachname', Types::STRING, ['notnull' => true, 'length' => 128]);
		$t->addColumn('geburtsjahr', Types::INTEGER, ['notnull' => false]);
		// Für Ablagepfade im Gruppenordner (D8), z. B. "mia-m"
		$t->addColumn('kuerzel', Types::STRING, ['notnull' => true, 'length' => 64]);
		$t->addColumn('aktiv', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
		$t->addColumn('geloescht_am', Types::DATETIME, ['notnull' => false]);
		$t->setPrimaryKey(['id']);
		$t->addIndex(['nachname', 'vorname'], 'kidseye_schueler_name');
		$t->addUniqueIndex(['kuerzel'], 'kidseye_schueler_kuerzel');
	}

	private function klassenzugehoerigkeit(ISchemaWrapper $schema): void {
		if ($schema->hasTable('kidseye_klassen_zug')) {
			return;
		}
		$t = $schema->createTable('kidseye_klassen_zug');
		$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$t->addColumn('klasse_id', Types::BIGINT, ['notnull' => true]);
		$t->addColumn('schueler_id', Types::BIGINT, ['notnull' => true]);
		$t->setPrimaryKey(['id']);
		$t->addUniqueIndex(['klasse_id', 'schueler_id'], 'kidseye_zug_paar');
		$t->addIndex(['schueler_id'], 'kidseye_zug_schueler');
	}

	private function lehrauftrag(ISchemaWrapper $schema): void {
		if ($schema->hasTable('kidseye_lehrauftrag')) {
			return;
		}
		$t = $schema->createTable('kidseye_lehrauftrag');
		$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$t->addColumn('nutzer_id', Types::STRING, ['notnull' => true, 'length' => 64]);
		$t->addColumn('klasse_id', Types::BIGINT, ['notnull' => true]);
		$t->addColumn('kontext_id', Types::BIGINT, ['notnull' => true]);
		$t->addColumn('klassenlehrkraft', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
		$t->setPrimaryKey(['id']);
		$t->addUniqueIndex(['nutzer_id', 'klasse_id', 'kontext_id'], 'kidseye_la_tripel');
		$t->addIndex(['nutzer_id'], 'kidseye_la_nutzer');
	}

	private function klassenbild(ISchemaWrapper $schema): void {
		if ($schema->hasTable('kidseye_klassenbild')) {
			return;
		}
		$t = $schema->createTable('kidseye_klassenbild');
		$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$t->addColumn('klasse_id', Types::BIGINT, ['notnull' => true]);
		$t->addColumn('schueler_id', Types::BIGINT, ['notnull' => true]);
		$t->addColumn('position', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		// Optionale benannte Gruppe (Tischgruppe). Keine Raumgeometrie (D11).
		$t->addColumn('gruppe', Types::STRING, ['notnull' => false, 'length' => 64]);
		$t->setPrimaryKey(['id']);
		$t->addUniqueIndex(['klasse_id', 'schueler_id'], 'kidseye_kb_paar');
	}

	private function stunde(ISchemaWrapper $schema): void {
		if ($schema->hasTable('kidseye_stunde')) {
			return;
		}
		$t = $schema->createTable('kidseye_stunde');
		$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$t->addColumn('nutzer_id', Types::STRING, ['notnull' => true, 'length' => 64]);
		$t->addColumn('klasse_id', Types::BIGINT, ['notnull' => true]);
		$t->addColumn('kontext_id', Types::BIGINT, ['notnull' => true]);
		// Nur bei Kontexten der Art schulfach gesetzt (D2)
		$t->addColumn('inhaltsfeld_id', Types::BIGINT, ['notnull' => false]);
		$t->addColumn('version_id', Types::BIGINT, ['notnull' => false]);
		$t->addColumn('begonnen_am', Types::DATETIME, ['notnull' => true]);
		$t->addColumn('beendet_am', Types::DATETIME, ['notnull' => false]);
		$t->setPrimaryKey(['id']);
		$t->addIndex(['nutzer_id', 'beendet_am'], 'kidseye_stunde_offen');
	}

	private function verwendungszweck(ISchemaWrapper $schema): void {
		if (!$schema->hasTable('kidseye_zweck')) {
			$t = $schema->createTable('kidseye_zweck');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$t->addColumn('kennung', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 128]);
			$t->addColumn('aktiv', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
			// Regelbasiert: Mappe wird aus den Kompetenzzuordnungen berechnet (D16)
			$t->addColumn('regelbasiert', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$t->addColumn('sortierung', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['kennung'], 'kidseye_zweck_kennung');
		}

		if (!$schema->hasTable('kidseye_zweck_knoten')) {
			// Knotenmenge eines regelbasierten Zwecks. Referenziert die
			// Knotenkennung, nicht die ID — die Regel gilt versionsübergreifend.
			$t = $schema->createTable('kidseye_zweck_knoten');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$t->addColumn('zweck_id', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('knoten_kennung', Types::STRING, ['notnull' => true, 'length' => 128]);
			// true = auch alle Nachfahren dieses Knotens
			$t->addColumn('mit_nachfahren', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['zweck_id', 'knoten_kennung'], 'kidseye_zk_paar');
		}
	}

	private function schnellmarker(ISchemaWrapper $schema): void {
		if (!$schema->hasTable('kidseye_marker')) {
			$t = $schema->createTable('kidseye_marker');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$t->addColumn('kontext_id', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('text', Types::STRING, ['notnull' => true, 'length' => 128]);
			$t->addColumn('sichtbar', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
			$t->addColumn('sortierung', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$t->setPrimaryKey(['id']);
			$t->addIndex(['kontext_id', 'sichtbar'], 'kidseye_marker_kontext');
		}

		if (!$schema->hasTable('kidseye_marker_knoten')) {
			// Kompetenzzuordnung in der Markerdefinition (D3). Über die
			// Kennung, damit ein Rahmenwechsel die Marker nicht entwertet.
			$t = $schema->createTable('kidseye_marker_knoten');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$t->addColumn('marker_id', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('knoten_kennung', Types::STRING, ['notnull' => true, 'length' => 128]);
			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['marker_id', 'knoten_kennung'], 'kidseye_mk_paar');
		}

		if (!$schema->hasTable('kidseye_marker_zweck')) {
			// Marker kann einen Verwendungszweck mitführen (D16, Weg 2)
			$t = $schema->createTable('kidseye_marker_zweck');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$t->addColumn('marker_id', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('zweck_id', Types::BIGINT, ['notnull' => true]);
			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['marker_id', 'zweck_id'], 'kidseye_mz_paar');
		}
	}

	private function beobachtung(ISchemaWrapper $schema): void {
		if ($schema->hasTable('kidseye_beobachtung')) {
			return;
		}
		$t = $schema->createTable('kidseye_beobachtung');
		$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);

		// Vom Client vergebene Kennung — Dublettenschutz der Offline-
		// Warteschlange (D9). Ein doppelt gesendeter Eintrag legt nichts neu an.
		$t->addColumn('client_uuid', Types::STRING, ['notnull' => false, 'length' => 64]);

		$t->addColumn('schueler_id', Types::BIGINT, ['notnull' => true]);
		$t->addColumn('nutzer_id', Types::STRING, ['notnull' => true, 'length' => 64]);
		$t->addColumn('stunde_id', Types::BIGINT, ['notnull' => false]);
		$t->addColumn('klasse_id', Types::BIGINT, ['notnull' => false]);
		$t->addColumn('kontext_id', Types::BIGINT, ['notnull' => false]);
		$t->addColumn('version_id', Types::BIGINT, ['notnull' => false]);

		// Echter Erfassungszeitpunkt, nie der Stundenbeginn
		$t->addColumn('erfasst_am', Types::DATETIME, ['notnull' => true]);

		$t->addColumn('text', Types::TEXT, ['notnull' => false]);
		$t->addColumn('marker_id', Types::BIGINT, ['notnull' => false]);
		// Wortlaut zum Zeitpunkt der Erfassung — überlebt spätere Änderungen
		// an der Markerdefinition
		$t->addColumn('marker_text', Types::STRING, ['notnull' => false, 'length' => 128]);

		// privat | klassenteam | akte   (D7)
		$t->addColumn('sichtbarkeit', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'privat']);
		$t->addColumn('akte_am', Types::DATETIME, ['notnull' => false]);

		// Kuratierungsbedürftig (D4): Freitext, Foto oder ausdrücklich markiert
		$t->addColumn('kuratierung', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
		$t->addColumn('erledigt_am', Types::DATETIME, ['notnull' => false]);
		$t->addColumn('gemerkt', Types::BOOLEAN, ['notnull' => true, 'default' => false]);

		$t->addColumn('loeschen_ab', Types::DATE, ['notnull' => false]);
		$t->addColumn('geloescht_am', Types::DATETIME, ['notnull' => false]);

		$t->setPrimaryKey(['id']);
		$t->addUniqueIndex(['nutzer_id', 'client_uuid'], 'kidseye_beob_client');
		$t->addIndex(['schueler_id', 'erfasst_am'], 'kidseye_beob_kind');
		$t->addIndex(['nutzer_id', 'kuratierung', 'erledigt_am'], 'kidseye_beob_inbox');
		$t->addIndex(['klasse_id', 'erfasst_am'], 'kidseye_beob_klasse');
	}

	private function beobachtungKnoten(ISchemaWrapper $schema): void {
		if ($schema->hasTable('kidseye_beob_knoten')) {
			return;
		}
		// n:m — das Kerncurriculum schließt eine ausschließliche Zuordnung
		// zu genau einem Bereich ausdrücklich aus (D6).
		$t = $schema->createTable('kidseye_beob_knoten');
		$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$t->addColumn('beobachtung_id', Types::BIGINT, ['notnull' => true]);
		// Immer das Paar aus Version und Knoten (D5)
		$t->addColumn('version_id', Types::BIGINT, ['notnull' => true]);
		$t->addColumn('knoten_id', Types::BIGINT, ['notnull' => true]);
		// Woher die Zuordnung stammt: marker | kontext | kuratierung
		$t->addColumn('herkunft', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'kuratierung']);
		$t->setPrimaryKey(['id']);
		$t->addUniqueIndex(['beobachtung_id', 'knoten_id'], 'kidseye_bk_paar');
		$t->addIndex(['knoten_id'], 'kidseye_bk_knoten');
	}

	private function beobachtungDatei(ISchemaWrapper $schema): void {
		if ($schema->hasTable('kidseye_beob_datei')) {
			return;
		}
		$t = $schema->createTable('kidseye_beob_datei');
		$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$t->addColumn('beobachtung_id', Types::BIGINT, ['notnull' => true]);
		// Nextcloud-Datei-ID, nie ein Pfad — überlebt Umbenennen (D8)
		$t->addColumn('file_id', Types::BIGINT, ['notnull' => true]);
		$t->addColumn('dateiname', Types::STRING, ['notnull' => false, 'length' => 255]);
		$t->setPrimaryKey(['id']);
		$t->addIndex(['beobachtung_id'], 'kidseye_bd_beob');
		$t->addIndex(['file_id'], 'kidseye_bd_file');
	}

	private function beobachtungZweck(ISchemaWrapper $schema): void {
		if ($schema->hasTable('kidseye_beob_zweck')) {
			return;
		}
		$t = $schema->createTable('kidseye_beob_zweck');
		$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$t->addColumn('beobachtung_id', Types::BIGINT, ['notnull' => true]);
		$t->addColumn('zweck_id', Types::BIGINT, ['notnull' => true]);
		// marker | hand
		$t->addColumn('herkunft', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'hand']);
		$t->setPrimaryKey(['id']);
		$t->addUniqueIndex(['beobachtung_id', 'zweck_id'], 'kidseye_bz_paar');
		$t->addIndex(['zweck_id'], 'kidseye_bz_zweck');
	}

	private function nachtrag(ISchemaWrapper $schema): void {
		if ($schema->hasTable('kidseye_nachtrag')) {
			return;
		}
		// Korrekturweg für unveränderliche Beobachtungen in der Akte (D7)
		$t = $schema->createTable('kidseye_nachtrag');
		$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$t->addColumn('beobachtung_id', Types::BIGINT, ['notnull' => true]);
		$t->addColumn('nutzer_id', Types::STRING, ['notnull' => true, 'length' => 64]);
		$t->addColumn('text', Types::TEXT, ['notnull' => true]);
		$t->addColumn('erstellt_am', Types::DATETIME, ['notnull' => true]);
		$t->setPrimaryKey(['id']);
		$t->addIndex(['beobachtung_id'], 'kidseye_nachtrag_beob');
	}

	private function protokoll(ISchemaWrapper $schema): void {
		if ($schema->hasTable('kidseye_protokoll')) {
			return;
		}
		// Nicht durch Anwendende veränderbar: es gibt keinen Schreibweg
		// außer dem Anfügen im ProtokollService.
		$t = $schema->createTable('kidseye_protokoll');
		$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$t->addColumn('zeitpunkt', Types::DATETIME, ['notnull' => true]);
		$t->addColumn('nutzer_id', Types::STRING, ['notnull' => true, 'length' => 64]);
		// sichtbarkeit | loeschung | auskunft
		$t->addColumn('aktion', Types::STRING, ['notnull' => true, 'length' => 32]);
		$t->addColumn('beobachtung_id', Types::BIGINT, ['notnull' => false]);
		$t->addColumn('schueler_id', Types::BIGINT, ['notnull' => false]);
		$t->addColumn('von_wert', Types::STRING, ['notnull' => false, 'length' => 64]);
		$t->addColumn('nach_wert', Types::STRING, ['notnull' => false, 'length' => 64]);
		$t->addColumn('details', Types::TEXT, ['notnull' => false]);
		$t->setPrimaryKey(['id']);
		$t->addIndex(['zeitpunkt'], 'kidseye_prot_zeit');
		$t->addIndex(['beobachtung_id'], 'kidseye_prot_beob');
	}

	private function skala(ISchemaWrapper $schema): void {
		if (!$schema->hasTable('kidseye_skala')) {
			// Schuleigene Einschätzungsskala für FACHLICHE Standards.
			// Für überfachliche Dimensionen gibt es bewusst keine Skala —
			// das Kerncurriculum sieht dort keine Normierung vor (2.9).
			$t = $schema->createTable('kidseye_skala');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$t->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 128]);
			$t->addColumn('aktiv', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
			$t->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('kidseye_skala_stufe')) {
			$t = $schema->createTable('kidseye_skala_stufe');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$t->addColumn('skala_id', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('bezeichnung', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('wert', Types::INTEGER, ['notnull' => true]);
			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['skala_id', 'wert'], 'kidseye_stufe_wert');
		}

		if ($schema->hasTable('kidseye_beob_knoten')) {
			$t = $schema->getTable('kidseye_beob_knoten');
			if (!$t->hasColumn('stufe_id')) {
				// Einschätzung hängt an der Zuordnung, nicht an der Beobachtung:
				// dieselbe Beobachtung kann zu zwei Standards verschieden stehen.
				$t->addColumn('stufe_id', Types::BIGINT, ['notnull' => false]);
			}
		}
	}
}

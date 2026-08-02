<?php

declare(strict_types=1);

namespace OCA\KidsEye\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Fundament: Kompetenzrahmen (versioniert) und Unterrichtskontexte.
 *
 * Die Rahmenstruktur folgt D5/D6 aus design.md:
 *  - Beobachtungen referenzieren immer (rahmen_version, knoten), nie den Knoten allein.
 *  - Bildungsstandards und Inhaltsfelder sind zwei gekreuzte Achsen, keine Hierarchie;
 *    ihre Beziehung liegt in kidseye_knoten_korr.
 */
class Version001000Date20260727120000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->rahmen($schema);
		$this->rahmenVersion($schema);
		$this->knoten($schema);
		$this->knotenKorrespondenz($schema);
		$this->unterrichtskontext($schema);

		return $schema;
	}

	private function rahmen(ISchemaWrapper $schema): void {
		if ($schema->hasTable('kidseye_rahmen')) {
			return;
		}
		$t = $schema->createTable('kidseye_rahmen');
		$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		// Stabile technische Kennung, z. B. "hessen-primarstufe"
		$t->addColumn('kennung', Types::STRING, ['notnull' => true, 'length' => 64]);
		$t->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
		$t->addColumn('herausgeber', Types::STRING, ['notnull' => false, 'length' => 255]);
		$t->setPrimaryKey(['id']);
		$t->addUniqueIndex(['kennung'], 'kidseye_rahmen_kennung');
	}

	private function rahmenVersion(ISchemaWrapper $schema): void {
		if ($schema->hasTable('kidseye_rahmen_vers')) {
			return;
		}
		$t = $schema->createTable('kidseye_rahmen_vers');
		$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$t->addColumn('rahmen_id', Types::BIGINT, ['notnull' => true]);
		// z. B. "2011"
		$t->addColumn('kennung', Types::STRING, ['notnull' => true, 'length' => 64]);
		$t->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
		$t->addColumn('gueltig_ab', Types::DATE, ['notnull' => false]);
		$t->addColumn('quelle', Types::STRING, ['notnull' => false, 'length' => 512]);
		$t->addColumn('importiert_am', Types::DATETIME, ['notnull' => true]);
		$t->setPrimaryKey(['id']);
		$t->addUniqueIndex(['rahmen_id', 'kennung'], 'kidseye_vers_rahmen_kenn');
	}

	private function knoten(ISchemaWrapper $schema): void {
		if ($schema->hasTable('kidseye_knoten')) {
			return;
		}
		$t = $schema->createTable('kidseye_knoten');
		$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$t->addColumn('version_id', Types::BIGINT, ['notnull' => true]);
		$t->addColumn('eltern_id', Types::BIGINT, ['notnull' => false]);

		// Innerhalb einer Rahmenversion stabile Kennung, damit Seeds
		// wiederholbar sind und Beobachtungen nachvollziehbar bleiben.
		$t->addColumn('kennung', Types::STRING, ['notnull' => true, 'length' => 128]);

		// bereich | dimension | kompetenzbereich | bildungsstandard
		// | inhaltsfeld | leitstruktur
		$t->addColumn('art', Types::STRING, ['notnull' => true, 'length' => 32]);

		// ueberfachlich | fachlich
		$t->addColumn('ebene', Types::STRING, ['notnull' => true, 'length' => 16]);

		// null bei überfachlichen Knoten (D2: Ebene A gilt fächerübergreifend)
		$t->addColumn('fach_kennung', Types::STRING, ['notnull' => false, 'length' => 64]);

		$t->addColumn('bezeichnung', Types::TEXT, ['notnull' => true]);

		// Wortlaut aus dem Kerncurriculum, hilft bei der Zuordnung
		$t->addColumn('beschreibung', Types::TEXT, ['notnull' => false]);

		// Fachspezifischer Name der Querstruktur (D2b): Leitideen,
		// Basiskonzepte, Kernbereiche, Leitperspektiven
		$t->addColumn('struktur_name', Types::STRING, ['notnull' => false, 'length' => 128]);

		// null | jgst_2 | jgst_4
		$t->addColumn('bezugsstufe', Types::STRING, ['notnull' => false, 'length' => 16]);

		$t->addColumn('sortierung', Types::INTEGER, ['notnull' => true, 'default' => 0]);

		// Ob der Knoten zur Zuordnung angeboten wird. leitstruktur wird
		// in v1 geseedet, aber nicht angeboten (D2b).
		$t->addColumn('waehlbar', Types::BOOLEAN, ['notnull' => true, 'default' => true]);

		$t->setPrimaryKey(['id']);
		$t->addUniqueIndex(['version_id', 'kennung'], 'kidseye_knoten_kennung');
		$t->addIndex(['version_id', 'ebene', 'fach_kennung'], 'kidseye_knoten_ebene');
		$t->addIndex(['eltern_id'], 'kidseye_knoten_eltern');
	}

	private function knotenKorrespondenz(ISchemaWrapper $schema): void {
		if ($schema->hasTable('kidseye_knoten_korr')) {
			return;
		}
		$t = $schema->createTable('kidseye_knoten_korr');
		$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$t->addColumn('von_knoten_id', Types::BIGINT, ['notnull' => true]);
		$t->addColumn('nach_knoten_id', Types::BIGINT, ['notnull' => true]);
		$t->setPrimaryKey(['id']);
		$t->addUniqueIndex(['von_knoten_id', 'nach_knoten_id'], 'kidseye_korr_paar');
		$t->addIndex(['nach_knoten_id'], 'kidseye_korr_nach');
	}

	private function unterrichtskontext(ISchemaWrapper $schema): void {
		if ($schema->hasTable('kidseye_kontext')) {
			return;
		}
		$t = $schema->createTable('kidseye_kontext');
		$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$t->addColumn('kennung', Types::STRING, ['notnull' => true, 'length' => 64]);
		$t->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);

		// schulfach | fachneutral  (D2)
		$t->addColumn('art', Types::STRING, ['notnull' => true, 'length' => 16]);

		// Nur bei art = schulfach gesetzt.
		$t->addColumn('fach_kennung', Types::STRING, ['notnull' => false, 'length' => 64]);

		$t->addColumn('aktiv', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
		$t->addColumn('sortierung', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$t->setPrimaryKey(['id']);
		$t->addUniqueIndex(['kennung'], 'kidseye_kontext_kennung');
	}
}

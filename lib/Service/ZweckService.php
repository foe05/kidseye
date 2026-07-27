<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Verwendungszwecke (Kapitel 5b, D16).
 *
 * Zwei Sorten, und der Unterschied ist der entscheidende Teil:
 *
 *  manuell      Förderplan — braucht das Urteil der Lehrkraft, wird
 *               ausdrücklich vorgemerkt.
 *
 *  regelbasiert Sozial- und Arbeitsverhalten — ergibt sich aus der
 *               Kompetenzzuordnung, die dank D3 ohnehin schon vorliegt.
 *               Beobachtungen dazu entstehen mitten in Mathematik oder
 *               Deutsch; über den Unterrichtskontext wären sie nie
 *               einzusammeln, von Hand wäre es lästig und unvollständig.
 */
class ZweckService {

	public const VORGABE = [
		[
			'kennung' => 'foerderplan',
			'name' => 'Förderplan',
			'regelbasiert' => false,
			'knoten' => [],
		],
		[
			'kennung' => 'sozial_arbeitsverhalten',
			'name' => 'Sozial- und Arbeitsverhalten',
			'regelbasiert' => true,
			// Der Zeugnisabschnitt deckt sich mit drei der vier überfachlichen
			// Bereiche des Kerncurriculums.
			'knoten' => ['uk.sozial', 'uk.lern', 'uk.personal'],
		],
	];

	public function __construct(
		private IDBConnection $db,
		private RahmenService $rahmen,
	) {
	}

	public function alle(bool $nurAktive = true): array {
		$q = $this->db->getQueryBuilder();
		$q->select('id', 'kennung', 'name', 'aktiv', 'regelbasiert', 'sortierung')
			->from('kidseye_zweck')->orderBy('sortierung')->addOrderBy('name');
		if ($nurAktive) {
			$q->where($q->expr()->eq('aktiv', $q->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));
		}
		$treffer = $q->executeQuery();
		$zwecke = [];
		while ($z = $treffer->fetch()) {
			$zwecke[] = [
				'id' => (int)$z['id'],
				'kennung' => $z['kennung'],
				'name' => $z['name'],
				'aktiv' => (bool)$z['aktiv'],
				'regelbasiert' => (bool)$z['regelbasiert'],
				'sortierung' => (int)$z['sortierung'],
			];
		}
		$treffer->closeCursor();
		return $zwecke;
	}

	public function nachKennung(string $kennung): ?array {
		foreach ($this->alle(false) as $z) {
			if ($z['kennung'] === $kennung) {
				return $z;
			}
		}
		return null;
	}

	public function anlegen(string $kennung, string $name, bool $regelbasiert = false, array $knoten = [], int $sortierung = 0): int {
		$q = $this->db->getQueryBuilder();
		$q->insert('kidseye_zweck')->values([
			'kennung' => $q->createNamedParameter($kennung),
			'name' => $q->createNamedParameter($name),
			'aktiv' => $q->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
			'regelbasiert' => $q->createNamedParameter($regelbasiert, IQueryBuilder::PARAM_BOOL),
			'sortierung' => $q->createNamedParameter($sortierung, IQueryBuilder::PARAM_INT),
		])->executeStatement();
		$zweckId = $q->getLastInsertId();

		foreach ($knoten as $kennungKnoten) {
			$q = $this->db->getQueryBuilder();
			$q->insert('kidseye_zweck_knoten')->values([
				'zweck_id' => $q->createNamedParameter($zweckId, IQueryBuilder::PARAM_INT),
				'knoten_kennung' => $q->createNamedParameter($kennungKnoten),
				'mit_nachfahren' => $q->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
			])->executeStatement();
		}
		return $zweckId;
	}

	public function umbenennen(int $zweckId, string $name): void {
		$q = $this->db->getQueryBuilder();
		$q->update('kidseye_zweck')->set('name', $q->createNamedParameter($name))
			->where($q->expr()->eq('id', $q->createNamedParameter($zweckId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/**
	 * Deaktivieren blendet den Zweck bei der Erfassung aus, lässt bestehende
	 * Vormerkungen aber unangetastet und weiterhin auswertbar.
	 */
	public function aktivSetzen(int $zweckId, bool $aktiv): void {
		$q = $this->db->getQueryBuilder();
		$q->update('kidseye_zweck')
			->set('aktiv', $q->createNamedParameter($aktiv, IQueryBuilder::PARAM_BOOL))
			->where($q->expr()->eq('id', $q->createNamedParameter($zweckId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function vorgabeAnlegen(): int {
		$vorhanden = [];
		foreach ($this->alle(false) as $z) {
			$vorhanden[$z['kennung']] = true;
		}
		$zahl = 0;
		foreach (self::VORGABE as $i => $v) {
			if (isset($vorhanden[$v['kennung']])) {
				continue;
			}
			$this->anlegen($v['kennung'], $v['name'], $v['regelbasiert'], $v['knoten'], $i * 10);
			$zahl++;
		}
		return $zahl;
	}

	// ------------------------------------------------------------- Vormerkung

	public function vormerken(int $beobachtungId, int $zweckId, string $herkunft = 'hand'): void {
		$q = $this->db->getQueryBuilder();
		$q->select('id')->from('kidseye_beob_zweck')
			->where($q->expr()->eq('beobachtung_id', $q->createNamedParameter($beobachtungId, IQueryBuilder::PARAM_INT)))
			->andWhere($q->expr()->eq('zweck_id', $q->createNamedParameter($zweckId, IQueryBuilder::PARAM_INT)));
		$treffer = $q->executeQuery();
		$da = $treffer->fetchOne();
		$treffer->closeCursor();
		if ($da !== false) {
			return;
		}

		$q = $this->db->getQueryBuilder();
		$q->insert('kidseye_beob_zweck')->values([
			'beobachtung_id' => $q->createNamedParameter($beobachtungId, IQueryBuilder::PARAM_INT),
			'zweck_id' => $q->createNamedParameter($zweckId, IQueryBuilder::PARAM_INT),
			'herkunft' => $q->createNamedParameter($herkunft),
		])->executeStatement();
	}

	public function vormerkungEntfernen(int $beobachtungId, int $zweckId): void {
		$q = $this->db->getQueryBuilder();
		$q->delete('kidseye_beob_zweck')
			->where($q->expr()->eq('beobachtung_id', $q->createNamedParameter($beobachtungId, IQueryBuilder::PARAM_INT)))
			->andWhere($q->expr()->eq('zweck_id', $q->createNamedParameter($zweckId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/** Vormerkungen einer Beobachtung — nur die ausdrücklich gesetzten. */
	public function vormerkungen(int $beobachtungId): array {
		$q = $this->db->getQueryBuilder();
		$q->select('z.id', 'z.kennung', 'z.name', 'v.herkunft')
			->from('kidseye_beob_zweck', 'v')
			->innerJoin('v', 'kidseye_zweck', 'z', 'z.id = v.zweck_id')
			->where($q->expr()->eq('v.beobachtung_id', $q->createNamedParameter($beobachtungId, IQueryBuilder::PARAM_INT)));
		$treffer = $q->executeQuery();
		$zeilen = [];
		while ($z = $treffer->fetch()) {
			$zeilen[] = [
				'id' => (int)$z['id'], 'kennung' => $z['kennung'],
				'name' => $z['name'], 'herkunft' => $z['herkunft'],
			];
		}
		$treffer->closeCursor();
		return $zeilen;
	}

	/**
	 * Knoten-IDs, die einen regelbasierten Zweck auslösen — inklusive aller
	 * Nachfahren, damit die Regel auf Bereichsebene formuliert werden kann.
	 */
	public function regelKnoten(int $zweckId, int $versionId): array {
		$q = $this->db->getQueryBuilder();
		$q->select('knoten_kennung', 'mit_nachfahren')->from('kidseye_zweck_knoten')
			->where($q->expr()->eq('zweck_id', $q->createNamedParameter($zweckId, IQueryBuilder::PARAM_INT)));
		$treffer = $q->executeQuery();
		$regeln = [];
		while ($z = $treffer->fetch()) {
			$regeln[] = ['kennung' => $z['knoten_kennung'], 'nachfahren' => (bool)$z['mit_nachfahren']];
		}
		$treffer->closeCursor();

		if ($regeln === []) {
			return [];
		}

		$kennungen = array_column($regeln, 'kennung');
		$knoten = $this->rahmen->knotenNachKennungen($versionId, $kennungen);
		$nachKennung = [];
		foreach ($knoten as $k) {
			$nachKennung[$k['kennung']] = $k['id'];
		}

		$ids = [];
		foreach ($regeln as $regel) {
			$wurzel = $nachKennung[$regel['kennung']] ?? null;
			if ($wurzel === null) {
				continue;
			}
			$ids = array_merge(
				$ids,
				$regel['nachfahren'] ? $this->rahmen->mitNachfahren($versionId, $wurzel) : [$wurzel]
			);
		}
		return array_values(array_unique($ids));
	}

	/**
	 * Trägt eine Beobachtung mit diesen Knoten einen regelbasierten Zweck?
	 *
	 * @param int[] $knotenIds Zuordnungen der Beobachtung
	 * @return array Liste der ausgelösten Zwecke
	 */
	public function ausgeloesteZwecke(array $knotenIds, int $versionId): array {
		if ($knotenIds === []) {
			return [];
		}
		$ausgeloest = [];
		foreach ($this->alle() as $zweck) {
			if (!$zweck['regelbasiert']) {
				continue;
			}
			$regel = $this->regelKnoten($zweck['id'], $versionId);
			if (array_intersect($knotenIds, $regel) !== []) {
				$ausgeloest[] = $zweck;
			}
		}
		return $ausgeloest;
	}
}

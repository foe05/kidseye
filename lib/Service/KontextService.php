<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Unterrichtskontexte: Schulfächer und fachneutrale Kontexte (D2).
 *
 * Was die Lehrkraft am Stundenanfang wählt, ist nicht zwingend ein Schulfach.
 * Freiarbeit ist eine Unterrichtsform, Sozial- und Arbeitsverhalten gar keine
 * Unterrichtssituation — beobachtet wird in beiden. Kontexte der Art
 * `fachneutral` tragen keinen Fachbezug und bieten deshalb ausschließlich die
 * überfachliche Achse an.
 */
class KontextService {

	public const ART_SCHULFACH = 'schulfach';
	public const ART_FACHNEUTRAL = 'fachneutral';

	/** Auslieferungszustand, abgestimmt mit der Grundschullehrkraft (0.3). */
	public const VORGABE = [
		['kennung' => 'deutsch', 'name' => 'Deutsch', 'art' => self::ART_SCHULFACH, 'fach' => 'deutsch'],
		['kennung' => 'mathematik', 'name' => 'Mathematik', 'art' => self::ART_SCHULFACH, 'fach' => 'mathematik'],
		['kennung' => 'sachunterricht', 'name' => 'Sachunterricht', 'art' => self::ART_SCHULFACH, 'fach' => 'sachunterricht'],
		['kennung' => 'kunst', 'name' => 'Kunst', 'art' => self::ART_SCHULFACH, 'fach' => 'kunst'],
		['kennung' => 'ethik', 'name' => 'Ethik', 'art' => self::ART_SCHULFACH, 'fach' => 'ethik'],
		['kennung' => 'freiarbeit', 'name' => 'Freiarbeit', 'art' => self::ART_FACHNEUTRAL, 'fach' => null],
		['kennung' => 'sozial_arbeitsverhalten', 'name' => 'Sozial- und Arbeitsverhalten', 'art' => self::ART_FACHNEUTRAL, 'fach' => null],
	];

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function alle(bool $nurAktive = true): array {
		$q = $this->db->getQueryBuilder();
		$q->select('id', 'kennung', 'name', 'art', 'fach_kennung', 'aktiv', 'sortierung')
			->from('kidseye_kontext')->orderBy('sortierung')->addOrderBy('name');
		if ($nurAktive) {
			$q->where($q->expr()->eq('aktiv', $q->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));
		}
		return $this->hole($q);
	}

	public function nachId(int $id): ?array {
		$q = $this->db->getQueryBuilder();
		$q->select('id', 'kennung', 'name', 'art', 'fach_kennung', 'aktiv', 'sortierung')
			->from('kidseye_kontext')
			->where($q->expr()->eq('id', $q->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->hole($q)[0] ?? null;
	}

	public function anlegen(string $kennung, string $name, string $art, ?string $fach, int $sortierung = 0): int {
		if (!in_array($art, [self::ART_SCHULFACH, self::ART_FACHNEUTRAL], true)) {
			throw new \InvalidArgumentException('Unbekannte Kontextart: ' . $art);
		}
		// Ein fachneutraler Kontext darf keinen Fachbezug tragen — sonst
		// bekäme er in Auswertungen eine Fachspalte ohne Inhalt (D2).
		if ($art === self::ART_FACHNEUTRAL && $fach !== null) {
			throw new \InvalidArgumentException(
				'Fachneutrale Kontexte dürfen keinen Fachbezug tragen.'
			);
		}
		if ($art === self::ART_SCHULFACH && ($fach === null || $fach === '')) {
			throw new \InvalidArgumentException(
				'Ein Schulfach muss auf ein Fach des Kompetenzrahmens verweisen.'
			);
		}

		$q = $this->db->getQueryBuilder();
		$q->insert('kidseye_kontext')->values([
			'kennung' => $q->createNamedParameter($kennung),
			'name' => $q->createNamedParameter($name),
			'art' => $q->createNamedParameter($art),
			'fach_kennung' => $q->createNamedParameter($fach),
			'aktiv' => $q->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
			'sortierung' => $q->createNamedParameter($sortierung, IQueryBuilder::PARAM_INT),
		])->executeStatement();
		return $q->getLastInsertId();
	}

	/** Legt fehlende Vorgabekontexte an. Mehrfach aufrufbar. */
	public function vorgabeAnlegen(): int {
		$vorhanden = [];
		foreach ($this->alle(false) as $k) {
			$vorhanden[$k['kennung']] = true;
		}
		$zahl = 0;
		foreach (self::VORGABE as $i => $v) {
			if (isset($vorhanden[$v['kennung']])) {
				continue;
			}
			$this->anlegen($v['kennung'], $v['name'], $v['art'], $v['fach'], $i * 10);
			$zahl++;
		}
		return $zahl;
	}

	/** Trägt dieser Kontext eine fachliche Achse? */
	public function hatFachbezug(array $kontext): bool {
		return $kontext['art'] === self::ART_SCHULFACH && $kontext['fach'] !== null;
	}

	private function hole(IQueryBuilder $q): array {
		$treffer = $q->executeQuery();
		$zeilen = [];
		while ($z = $treffer->fetch()) {
			$zeilen[] = [
				'id' => (int)$z['id'],
				'kennung' => $z['kennung'],
				'name' => $z['name'],
				'art' => $z['art'],
				'fach' => $z['fach_kennung'],
				'aktiv' => (bool)$z['aktiv'],
				'sortierung' => (int)$z['sortierung'],
			];
		}
		$treffer->closeCursor();
		return $zeilen;
	}
}

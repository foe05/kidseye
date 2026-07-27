<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Schuleigene Einschätzungsskala für fachliche Bildungsstandards (Kapitel 2.10).
 *
 * Wichtig für die Darstellung: das hessische Kerncurriculum definiert KEINE
 * Bewertungsskala. Es beschreibt Könnenserwartungen zu zwei Zeitpunkten
 * (Ende Jahrgangsstufe 2 und 4). Jede Skala hier ist deshalb eine Festlegung
 * der Schule und muss in Oberfläche und Export als solche gekennzeichnet
 * werden — dafür liefert diese Klasse den Hinweistext mit.
 */
class SkalaService {

	public const HINWEIS = 'Schuleigene Festlegung — nicht Bestandteil des '
		. 'hessischen Kerncurriculums.';

	public function __construct(
		private IDBConnection $db,
		private RahmenService $rahmen,
	) {
	}

	public function aktiveSkala(): ?array {
		$q = $this->db->getQueryBuilder();
		$q->select('id', 'name')->from('kidseye_skala')
			->where($q->expr()->eq('aktiv', $q->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->setMaxResults(1);
		$treffer = $q->executeQuery();
		$zeile = $treffer->fetch();
		$treffer->closeCursor();
		if ($zeile === false) {
			return null;
		}
		return [
			'id' => (int)$zeile['id'],
			'name' => $zeile['name'],
			'schuleigen' => true,
			'hinweis' => self::HINWEIS,
			'stufen' => $this->stufen((int)$zeile['id']),
		];
	}

	public function stufen(int $skalaId): array {
		$q = $this->db->getQueryBuilder();
		$q->select('id', 'bezeichnung', 'wert')->from('kidseye_skala_stufe')
			->where($q->expr()->eq('skala_id', $q->createNamedParameter($skalaId, IQueryBuilder::PARAM_INT)))
			->orderBy('wert');
		$treffer = $q->executeQuery();
		$stufen = [];
		while ($zeile = $treffer->fetch()) {
			$stufen[] = [
				'id' => (int)$zeile['id'],
				'bezeichnung' => $zeile['bezeichnung'],
				'wert' => (int)$zeile['wert'],
			];
		}
		$treffer->closeCursor();
		return $stufen;
	}

	/** @param array<int,string> $stufen wert => bezeichnung */
	public function anlegen(string $name, array $stufen): int {
		$q = $this->db->getQueryBuilder();
		$q->insert('kidseye_skala')->values([
			'name' => $q->createNamedParameter($name),
			'aktiv' => $q->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
		])->executeStatement();
		$skalaId = $q->getLastInsertId();

		foreach ($stufen as $wert => $bezeichnung) {
			$q = $this->db->getQueryBuilder();
			$q->insert('kidseye_skala_stufe')->values([
				'skala_id' => $q->createNamedParameter($skalaId, IQueryBuilder::PARAM_INT),
				'bezeichnung' => $q->createNamedParameter($bezeichnung),
				'wert' => $q->createNamedParameter((int)$wert, IQueryBuilder::PARAM_INT),
			])->executeStatement();
		}
		return $skalaId;
	}

	/**
	 * Setzt eine Einschätzung an einer Zuordnung.
	 *
	 * @throws BewertungNichtZulaessig wenn der Knoten überfachlich ist (2.9)
	 */
	public function setzeEinschaetzung(int $zuordnungId, ?int $stufeId): void {
		$q = $this->db->getQueryBuilder();
		$q->select('knoten_id')->from('kidseye_beob_knoten')
			->where($q->expr()->eq('id', $q->createNamedParameter($zuordnungId, IQueryBuilder::PARAM_INT)));
		$treffer = $q->executeQuery();
		$knotenId = $treffer->fetchOne();
		$treffer->closeCursor();

		if ($knotenId === false) {
			throw new BewertungNichtZulaessig('Unbekannte Zuordnung.');
		}
		if ($stufeId !== null) {
			$this->rahmen->verlangeBewertbar((int)$knotenId);
		}

		$q = $this->db->getQueryBuilder();
		$q->update('kidseye_beob_knoten')
			->set('stufe_id', $q->createNamedParameter($stufeId, IQueryBuilder::PARAM_INT))
			->where($q->expr()->eq('id', $q->createNamedParameter($zuordnungId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}

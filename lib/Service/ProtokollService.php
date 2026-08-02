<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Protokoll sichtbarkeitsrelevanter Handlungen (Kapitel 7.5).
 *
 * Nicht durch Anwendende veränderbar: es gibt hier bewusst kein `aendern()`
 * und kein `loeschen()`. Der einzige Schreibweg ist das Anfügen.
 */
class ProtokollService {

	public const AKTION_SICHTBARKEIT = 'sichtbarkeit';
	public const AKTION_LOESCHUNG = 'loeschung';
	public const AKTION_AUSKUNFT = 'auskunft';

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function anfuegen(
		string $nutzerId,
		string $aktion,
		?int $beobachtungId = null,
		?int $schuelerId = null,
		?string $vonWert = null,
		?string $nachWert = null,
		?string $details = null,
	): void {
		$q = $this->db->getQueryBuilder();
		$q->insert('kidseye_protokoll')->values([
			'zeitpunkt' => $q->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')),
			'nutzer_id' => $q->createNamedParameter($nutzerId),
			'aktion' => $q->createNamedParameter($aktion),
			'beobachtung_id' => $q->createNamedParameter($beobachtungId, IQueryBuilder::PARAM_INT),
			'schueler_id' => $q->createNamedParameter($schuelerId, IQueryBuilder::PARAM_INT),
			'von_wert' => $q->createNamedParameter($vonWert),
			'nach_wert' => $q->createNamedParameter($nachWert),
			'details' => $q->createNamedParameter($details),
		])->executeStatement();
	}

	public function eintraege(?int $schuelerId = null, int $grenze = 500): array {
		$q = $this->db->getQueryBuilder();
		$q->select('id', 'zeitpunkt', 'nutzer_id', 'aktion', 'beobachtung_id',
			'schueler_id', 'von_wert', 'nach_wert', 'details')
			->from('kidseye_protokoll')
			->orderBy('zeitpunkt', 'DESC')->setMaxResults($grenze);
		if ($schuelerId !== null) {
			$q->where($q->expr()->eq('schueler_id', $q->createNamedParameter($schuelerId, IQueryBuilder::PARAM_INT)));
		}
		$treffer = $q->executeQuery();
		$zeilen = [];
		while ($z = $treffer->fetch()) {
			$zeilen[] = [
				'id' => (int)$z['id'],
				'zeitpunkt' => (new \DateTime($z['zeitpunkt']))->format(\DateTimeInterface::ATOM),
				'nutzerId' => $z['nutzer_id'],
				'aktion' => $z['aktion'],
				'beobachtungId' => $z['beobachtung_id'] !== null ? (int)$z['beobachtung_id'] : null,
				'schuelerId' => $z['schueler_id'] !== null ? (int)$z['schueler_id'] : null,
				'vonWert' => $z['von_wert'],
				'nachWert' => $z['nach_wert'],
				'details' => $z['details'],
			];
		}
		$treffer->closeCursor();
		return $zeilen;
	}
}

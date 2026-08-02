<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Sichtbarkeitsstufen und ihre Übergänge (Kapitel 7.1–7.3, D7).
 *
 *   privat  ←→  klassenteam  ──→  akte
 *                                  └── nie zurück, ab hier unveränderlich
 *
 * Die Unterscheidung zwischen persönlicher Aufzeichnung und schulischer
 * Unterlage hat rechtliche Folgen für Auskunftsrecht, Aufbewahrung und
 * Zugriff. Sie liegt deshalb im Datenmodell, nicht in einer Konvention.
 */
class SichtbarkeitService {

	public function __construct(
		private IDBConnection $db,
		private BeobachtungService $beobachtungen,
		private ZugriffService $zugriff,
		private ProtokollService $protokoll,
	) {
	}

	/** Welche Stufen darf die Oberfläche anbieten? In v1 ohne `klassenteam`. */
	public function waehlbareStufen(): array {
		return $this->zugriff->waehlbareStufen();
	}

	/**
	 * Setzt die Sichtbarkeit einer Beobachtung.
	 *
	 * @throws ZugriffVerweigert bei fremder Beobachtung oder Rückstufung aus der Akte
	 */
	public function setzen(string $nutzerId, int $beobachtungId, string $ziel): array {
		$beobachtung = $this->beobachtungen->nachId($beobachtungId);
		if ($beobachtung === null) {
			throw new \InvalidArgumentException('Unbekannte Beobachtung.');
		}
		if ($beobachtung['nutzerId'] !== $nutzerId) {
			throw new ZugriffVerweigert('Nur die erfassende Lehrkraft kann die Sichtbarkeit ändern.');
		}
		if (!in_array($ziel, ZugriffService::STUFEN_ALLE, true)) {
			throw new \InvalidArgumentException('Unbekannte Sichtbarkeitsstufe: ' . $ziel);
		}
		if (!in_array($ziel, $this->waehlbareStufen(), true)) {
			throw new ZugriffVerweigert(
				'Die Stufe „' . $ziel . '" ist in dieser Ausbaustufe nicht verfügbar. '
				. 'kidseye läuft derzeit im Einzelbetrieb.'
			);
		}

		$aktuell = $beobachtung['sichtbarkeit'];
		if ($aktuell === $ziel) {
			return $beobachtung;
		}

		// Einbahnstraße: aus der Akte führt kein Weg zurück.
		if ($aktuell === ZugriffService::SICHT_AKTE) {
			throw new ZugriffVerweigert(
				'Beobachtungen in der Akte sind unveränderlich. '
				. 'Eine Korrektur erfolgt als Nachtrag, nicht als Rückstufung.'
			);
		}

		$q = $this->db->getQueryBuilder();
		$q->update('kidseye_beobachtung')
			->set('sichtbarkeit', $q->createNamedParameter($ziel))
			->where($q->expr()->eq('id', $q->createNamedParameter($beobachtungId, IQueryBuilder::PARAM_INT)));
		if ($ziel === ZugriffService::SICHT_AKTE) {
			$q->set('akte_am', $q->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')));
		}
		$q->executeStatement();

		$this->protokoll->anfuegen(
			$nutzerId, ProtokollService::AKTION_SICHTBARKEIT,
			$beobachtungId, $beobachtung['schuelerId'], $aktuell, $ziel
		);

		return $this->beobachtungen->nachId($beobachtungId) ?? [];
	}

	/**
	 * Korrektur einer unveränderlichen Beobachtung als Nachtrag (7.3).
	 * Der ursprüngliche Eintrag bleibt unangetastet.
	 */
	public function nachtragen(string $nutzerId, int $beobachtungId, string $text): int {
		$beobachtung = $this->beobachtungen->nachId($beobachtungId);
		if ($beobachtung === null) {
			throw new \InvalidArgumentException('Unbekannte Beobachtung.');
		}
		$this->zugriff->verlangeSehen($nutzerId, $beobachtung);
		if (trim($text) === '') {
			throw new \InvalidArgumentException('Ein Nachtrag braucht einen Text.');
		}

		$q = $this->db->getQueryBuilder();
		$q->insert('kidseye_nachtrag')->values([
			'beobachtung_id' => $q->createNamedParameter($beobachtungId, IQueryBuilder::PARAM_INT),
			'nutzer_id' => $q->createNamedParameter($nutzerId),
			'text' => $q->createNamedParameter(trim($text)),
			'erstellt_am' => $q->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')),
		])->executeStatement();
		return $q->getLastInsertId();
	}

	public function nachtraege(int $beobachtungId): array {
		$q = $this->db->getQueryBuilder();
		$q->select('id', 'nutzer_id', 'text', 'erstellt_am')->from('kidseye_nachtrag')
			->where($q->expr()->eq('beobachtung_id', $q->createNamedParameter($beobachtungId, IQueryBuilder::PARAM_INT)))
			->orderBy('erstellt_am');
		$treffer = $q->executeQuery();
		$zeilen = [];
		while ($z = $treffer->fetch()) {
			$zeilen[] = [
				'id' => (int)$z['id'], 'nutzerId' => $z['nutzer_id'], 'text' => $z['text'],
				'erstelltAm' => (new \DateTime($z['erstellt_am']))->format(\DateTimeInterface::ATOM),
			];
		}
		$treffer->closeCursor();
		return $zeilen;
	}
}

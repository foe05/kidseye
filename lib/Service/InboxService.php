<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Wochendurchgang: Inbox, Sammelbearbeitung, Lücken-Radar (Kapitel 6).
 *
 * Der springende Punkt (D4): die Inbox zeigt **nicht** alles. Bei rund 100
 * Beobachtungen pro Woche wäre „alles durchsehen" ein Pflichtprogramm ohne
 * Ertrag. Beobachtungen, die nur aus einem Schnellmarker im Stundenkontext
 * entstanden sind, sind bereits vollständig und erscheinen hier nie.
 * Übrig bleiben rund 30 Einträge — etwa zehn Minuten mit Nutzen.
 */
class InboxService {

	/** Ab dieser Zeit ohne Beobachtung fällt ein Kind im Lücken-Radar auf. */
	public const LUECKE_TAGE_STANDARD = 14;

	public function __construct(
		private IDBConnection $db,
		private BeobachtungService $beobachtungen,
		private StammdatenService $stammdaten,
		private ZugriffService $zugriff,
		private RahmenService $rahmen,
		private KontextService $kontexte,
	) {
	}

	/**
	 * Offene Inbox-Einträge, nach Woche gruppiert.
	 *
	 * @return array{eintraege:array, gesamt:int, nachWoche:array}
	 */
	public function offen(string $nutzerId, int $grenze = 200): array {
		$q = $this->db->getQueryBuilder();
		$this->beobachtungen->grundAbfrage($q);
		$q->andWhere($q->expr()->eq('b.kuratierung', $q->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($q->expr()->isNull('b.erledigt_am'))
			->orderBy('b.erfasst_am', 'DESC')
			->setMaxResults($grenze);
		$this->zugriff->sichtbarkeitsFilter($q, $nutzerId);

		$eintraege = $this->beobachtungen->hole($q);

		$nachWoche = [];
		foreach ($eintraege as $e) {
			$woche = (new \DateTime($e['erfasstAmRoh']))->format('o-\KW');
			$nachWoche[$woche][] = $e['id'];
		}

		return [
			'eintraege' => $eintraege,
			'gesamt' => count($eintraege),
			'nachWoche' => $nachWoche,
		];
	}

	/**
	 * Zuordnungsvorschläge für einen Eintrag.
	 *
	 * Bei einer Beobachtung aus einem fachneutralen Kontext gibt es keine
	 * fachliche Achse — dann kommen ausschließlich überfachliche Dimensionen
	 * zurück (D2).
	 */
	public function vorschlaege(int $beobachtungId): array {
		$beobachtung = $this->beobachtungen->nachId($beobachtungId);
		if ($beobachtung === null) {
			return ['fachlich' => [], 'ueberfachlich' => []];
		}
		$versionId = $beobachtung['versionId'] ?? $this->rahmen->aktiveVersionId();
		if ($versionId === null) {
			return ['fachlich' => [], 'ueberfachlich' => []];
		}
		return $this->rahmen->vorschlaege($versionId, $beobachtung['fach']);
	}

	/** Erledigen — auch ohne Kompetenzzuordnung ausdrücklich erlaubt (6.4). */
	public function erledigen(string $nutzerId, array $beobachtungIds): int {
		$erlaubt = $this->eigene($nutzerId, $beobachtungIds);
		if ($erlaubt === []) {
			return 0;
		}
		$q = $this->db->getQueryBuilder();
		$q->update('kidseye_beobachtung')
			->set('erledigt_am', $q->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')))
			->where($q->expr()->in('id', $q->createNamedParameter($erlaubt, IQueryBuilder::PARAM_INT_ARRAY)));
		return $q->executeStatement();
	}

	/** Holt eine bereits fertige Beobachtung ausdrücklich in die Inbox zurück. */
	public function merken(string $nutzerId, int $beobachtungId, bool $gemerkt = true): void {
		$erlaubt = $this->eigene($nutzerId, [$beobachtungId]);
		if ($erlaubt === []) {
			throw new ZugriffVerweigert('Diese Beobachtung gehört dir nicht.');
		}
		$q = $this->db->getQueryBuilder();
		$q->update('kidseye_beobachtung')
			->set('gemerkt', $q->createNamedParameter($gemerkt, IQueryBuilder::PARAM_BOOL))
			->set('kuratierung', $q->createNamedParameter($gemerkt, IQueryBuilder::PARAM_BOOL))
			->set('erledigt_am', $q->createNamedParameter(null))
			->where($q->expr()->eq('id', $q->createNamedParameter($beobachtungId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/**
	 * Sammelbearbeitung: mehreren Einträgen dieselbe Zuordnung geben und sie
	 * gemeinsam erledigen (6.3).
	 *
	 * @param int[] $beobachtungIds
	 * @param int[] $knotenIds
	 */
	public function sammelZuordnung(string $nutzerId, array $beobachtungIds, array $knotenIds, bool $erledigen = true): int {
		$erlaubt = $this->eigene($nutzerId, $beobachtungIds);
		if ($erlaubt === [] || $knotenIds === []) {
			return 0;
		}

		$this->db->beginTransaction();
		try {
			foreach ($erlaubt as $beobachtungId) {
				$beobachtung = $this->beobachtungen->nachId($beobachtungId);
				$versionId = $beobachtung['versionId'] ?? $this->rahmen->aktiveVersionId();
				if ($versionId === null) {
					continue;
				}
				foreach ($knotenIds as $knotenId) {
					$this->beobachtungen->zuordnen($beobachtungId, $versionId, (int)$knotenId);
				}
			}
			if ($erledigen) {
				$this->erledigen($nutzerId, $erlaubt);
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
		return count($erlaubt);
	}

	/**
	 * Lücken-Radar (6.6): welche Kinder wurden seit einem Zeitraum nicht
	 * beobachtet — aufgeschlüsselt nach Unterrichtskontext.
	 *
	 * Beobachtung konzentriert sich systematisch auf auffällige Kinder; die
	 * stillen fallen hinten runter. Deshalb wird auch die fachbezogene Lücke
	 * ausgewiesen: ein Kind kann in Deutsch regelmäßig vorkommen und in
	 * Sachunterricht seit Wochen nicht.
	 */
	public function luecken(string $nutzerId, int $klasseId, int $tage = self::LUECKE_TAGE_STANDARD): array {
		$this->zugriff->verlangeLehrauftrag($nutzerId, $klasseId);

		$kinder = $this->stammdaten->kinderDerKlasse($klasseId);
		if ($kinder === []) {
			return ['tage' => $tage, 'kinder' => []];
		}
		$schuelerIds = array_column($kinder, 'id');

		// Kontexte, die diese Lehrkraft in dieser Klasse unterrichtet
		$kontexte = [];
		foreach ($this->stammdaten->lehrauftraege($nutzerId) as $l) {
			if ($l['klasseId'] === $klasseId) {
				$kontexte[$l['kontextId']] = $l['kontext'];
			}
		}

		$q = $this->db->getQueryBuilder();
		$q->select('b.schueler_id', 'b.kontext_id')
			->selectAlias($q->func()->max('b.erfasst_am'), 'letzte')
			->from('kidseye_beobachtung', 'b')
			->where($q->expr()->in('b.schueler_id',
				$q->createNamedParameter($schuelerIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->groupBy('b.schueler_id')->addGroupBy('b.kontext_id');
		$this->zugriff->sichtbarkeitsFilter($q, $nutzerId);

		$letzte = [];
		$treffer = $q->executeQuery();
		while ($z = $treffer->fetch()) {
			$letzte[(int)$z['schueler_id']][(int)$z['kontext_id']] = $z['letzte'];
		}
		$treffer->closeCursor();

		$jetzt = new \DateTime();
		$ergebnis = [];
		foreach ($kinder as $kind) {
			$proKontext = [];
			$laengste = null;

			foreach ($kontexte as $kontextId => $name) {
				$roh = $letzte[$kind['id']][$kontextId] ?? null;
				$tageHer = $roh === null
					? null
					: (int)(new \DateTime($roh))->diff($jetzt)->days;
				$proKontext[] = ['kontextId' => $kontextId, 'kontext' => $name,
					'letzte' => $roh, 'tageHer' => $tageHer];
				if ($tageHer === null || $laengste === null
					|| ($laengste['tageHer'] !== null && $tageHer > $laengste['tageHer'])) {
					if ($laengste === null || $laengste['tageHer'] !== null) {
						$laengste = end($proKontext);
					}
				}
			}

			$alle = array_filter(
				array_map(static fn ($k) => $k['tageHer'], $proKontext),
				static fn ($t) => $t !== null
			);
			$gesamtTage = $alle === [] ? null : min($alle);

			if ($gesamtTage === null || $gesamtTage >= $tage
				|| ($laengste !== null && $laengste['tageHer'] === null)) {
				$ergebnis[] = [
					'schuelerId' => $kind['id'],
					'anzeige' => $kind['anzeige'],
					'tageHer' => $gesamtTage,
					'nie' => $gesamtTage === null,
					'laengsteLuecke' => $laengste,
					'proKontext' => $proKontext,
				];
			}
		}

		usort($ergebnis, static function ($a, $b) {
			if ($a['nie'] !== $b['nie']) {
				return $a['nie'] ? -1 : 1;
			}
			return ($b['tageHer'] ?? 0) <=> ($a['tageHer'] ?? 0);
		});

		return ['tage' => $tage, 'kinder' => $ergebnis];
	}

	/** @param int[] $ids @return int[] */
	private function eigene(string $nutzerId, array $ids): array {
		$ids = array_values(array_filter(array_map('intval', $ids)));
		if ($ids === []) {
			return [];
		}
		$q = $this->db->getQueryBuilder();
		$q->select('id')->from('kidseye_beobachtung')
			->where($q->expr()->in('id', $q->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($q->expr()->eq('nutzer_id', $q->createNamedParameter($nutzerId)))
			// Einträge in der Akte sind unveränderlich
			->andWhere($q->expr()->neq('sichtbarkeit', $q->createNamedParameter(ZugriffService::SICHT_AKTE)))
			->andWhere($q->expr()->isNull('geloescht_am'));
		$treffer = $q->executeQuery();
		$erlaubt = array_map('intval', $treffer->fetchAll(\PDO::FETCH_COLUMN) ?: []);
		$treffer->closeCursor();
		return $erlaubt;
	}
}

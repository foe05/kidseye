<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Zeitleiste, Heatmap und Mappen (Kapitel 8).
 *
 * Zwei Regeln, die hier durchgehalten werden:
 *
 *  - Auf überfachlichen Dimensionen gibt es ausschließlich Belegzahlen,
 *    niemals eine Einstufung oder Rangfolge (2.9). Jede Auswertung führt
 *    deshalb einen Hinweis mit, der das in der Oberfläche klarstellt.
 *
 *  - Auswertungen laufen innerhalb einer Rahmenversion. Umfasst ein Zeitraum
 *    einen Versionswechsel, wird das gekennzeichnet und getrennt (D5).
 */
class AuswertungService {

	public const HINWEIS_BELEGE = 'Die Zahlen sind Belegzahlen, keine Bewertung. '
		. 'Aus ihnen wird keine Einstufung abgeleitet.';

	/** Voreinstellung der Zeitleiste — bei ~190 Beobachtungen im Jahr nötig. */
	public const ZEITLEISTE_WOCHEN = 8;

	public function __construct(
		private IDBConnection $db,
		private BeobachtungService $beobachtungen,
		private StammdatenService $stammdaten,
		private ZugriffService $zugriff,
		private ZweckService $zwecke,
		private RahmenService $rahmen,
		private SichtbarkeitService $sichtbarkeit,
	) {
	}

	/**
	 * Zeitleiste eines Kindes (8.1).
	 *
	 * @param array $filter {
	 *   von, bis:     ?string  ISO-Datum
	 *   kontextIds:   int[]
	 *   knotenIds:    int[]
	 *   zweck:        ?string  Kennung
	 *   art:          ?string  marker | text | foto
	 *   stufen:       string[] Sichtbarkeitsstufen
	 * }
	 */
	public function zeitleiste(string $nutzerId, int $schuelerId, array $filter = []): array {
		$von = $filter['von'] ?? (new \DateTime('-' . self::ZEITLEISTE_WOCHEN . ' weeks'))->format('Y-m-d');
		$bis = $filter['bis'] ?? null;

		$q = $this->db->getQueryBuilder();
		$this->beobachtungen->grundAbfrage($q);
		$q->andWhere($q->expr()->eq('b.schueler_id', $q->createNamedParameter($schuelerId, IQueryBuilder::PARAM_INT)))
			->orderBy('b.erfasst_am', 'DESC');
		$this->zugriff->sichtbarkeitsFilter($q, $nutzerId);

		if ($von !== null) {
			$q->andWhere($q->expr()->gte('b.erfasst_am', $q->createNamedParameter($von . ' 00:00:00')));
		}
		if ($bis !== null) {
			$q->andWhere($q->expr()->lte('b.erfasst_am', $q->createNamedParameter($bis . ' 23:59:59')));
		}
		if (!empty($filter['kontextIds'])) {
			$q->andWhere($q->expr()->in('b.kontext_id',
				$q->createNamedParameter(array_map('intval', $filter['kontextIds']), IQueryBuilder::PARAM_INT_ARRAY)));
		}
		if (!empty($filter['stufen'])) {
			$q->andWhere($q->expr()->in('b.sichtbarkeit',
				$q->createNamedParameter($filter['stufen'], IQueryBuilder::PARAM_STR_ARRAY)));
		}
		if (!empty($filter['art'])) {
			match ($filter['art']) {
				'marker' => $q->andWhere($q->expr()->isNotNull('b.marker_id')),
				'text' => $q->andWhere($q->expr()->isNotNull('b.text')),
				'foto' => $q->andWhere($q->expr()->in('b.id',
					$q->createFunction('SELECT beobachtung_id FROM `*PREFIX*kidseye_beob_datei`'))),
				default => null,
			};
		}
		if (!empty($filter['knotenIds'])) {
			$q->andWhere($q->expr()->in('b.id', $q->createFunction(
				'SELECT beobachtung_id FROM `*PREFIX*kidseye_beob_knoten` WHERE knoten_id IN ('
				. implode(',', array_map('intval', $filter['knotenIds'])) . ')'
			)));
		}

		$eintraege = $this->beobachtungen->hole($q);

		// Mappenfilter wirkt nach dem Laden, weil regelbasierte Zwecke sich
		// aus den Kompetenzzuordnungen ergeben und nicht gespeichert sind.
		if (!empty($filter['zweck'])) {
			$eintraege = $this->nurZweck($eintraege, (string)$filter['zweck']);
		}

		foreach ($eintraege as &$eintrag) {
			$eintrag['nachtraege'] = $this->sichtbarkeit->nachtraege($eintrag['id']);
		}
		unset($eintrag);

		return [
			'schuelerId' => $schuelerId,
			'von' => $von,
			'bis' => $bis,
			'gesamt' => count($eintraege),
			'eintraege' => $eintraege,
			'versionen' => $this->versionsHinweis($eintraege),
		];
	}

	/**
	 * Kompetenz-Heatmap einer Klasse (8.2): Kinder × Kompetenzknoten,
	 * Zellen sind Belegzahlen.
	 */
	public function heatmap(string $nutzerId, int $klasseId, string $ebene = RahmenService::EBENE_UEBERFACHLICH, ?string $fach = null, array $filter = []): array {
		$this->zugriff->verlangeLehrauftrag($nutzerId, $klasseId);

		$versionId = $this->rahmen->aktiveVersionId();
		if ($versionId === null) {
			return ['spalten' => [], 'zeilen' => [], 'hinweis' => self::HINWEIS_BELEGE];
		}

		$arten = $ebene === RahmenService::EBENE_UEBERFACHLICH
			? ['dimension']
			: ['kompetenzbereich', 'inhaltsfeld'];
		$spalten = $this->rahmen->knoten($versionId, $ebene, $fach, $arten);
		$kinder = $this->stammdaten->kinderDerKlasse($klasseId);

		if ($spalten === [] || $kinder === []) {
			return ['spalten' => $spalten, 'zeilen' => [], 'hinweis' => self::HINWEIS_BELEGE];
		}

		$q = $this->db->getQueryBuilder();
		$q->select('b.schueler_id', 'z.knoten_id')
			->selectAlias($q->func()->count('b.id'), 'anzahl')
			->from('kidseye_beobachtung', 'b')
			->innerJoin('b', 'kidseye_beob_knoten', 'z', 'z.beobachtung_id = b.id')
			->where($q->expr()->in('b.schueler_id',
				$q->createNamedParameter(array_column($kinder, 'id'), IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($q->expr()->in('z.knoten_id',
				$q->createNamedParameter(array_column($spalten, 'id'), IQueryBuilder::PARAM_INT_ARRAY)))
			->groupBy('b.schueler_id')->addGroupBy('z.knoten_id');
		$this->zugriff->sichtbarkeitsFilter($q, $nutzerId);

		if (!empty($filter['von'])) {
			$q->andWhere($q->expr()->gte('b.erfasst_am', $q->createNamedParameter($filter['von'] . ' 00:00:00')));
		}
		if (!empty($filter['bis'])) {
			$q->andWhere($q->expr()->lte('b.erfasst_am', $q->createNamedParameter($filter['bis'] . ' 23:59:59')));
		}

		$zellen = [];
		$treffer = $q->executeQuery();
		while ($z = $treffer->fetch()) {
			$zellen[(int)$z['schueler_id']][(int)$z['knoten_id']] = (int)$z['anzahl'];
		}
		$treffer->closeCursor();

		$zeilen = [];
		foreach ($kinder as $kind) {
			$werte = [];
			foreach ($spalten as $spalte) {
				$werte[] = [
					'knotenId' => $spalte['id'],
					'anzahl' => $zellen[$kind['id']][$spalte['id']] ?? 0,
				];
			}
			$zeilen[] = [
				'schuelerId' => $kind['id'],
				'anzeige' => $kind['anzeige'],
				'werte' => $werte,
				'summe' => array_sum(array_column($werte, 'anzahl')),
			];
		}

		return [
			'ebene' => $ebene,
			'fach' => $fach,
			'spalten' => array_map(
				static fn ($s) => ['id' => $s['id'], 'bezeichnung' => $s['bezeichnung'], 'art' => $s['art']],
				$spalten
			),
			'zeilen' => $zeilen,
			// Auf überfachlicher Ebene gibt es ausdrücklich keine Einstufung
			'bewertbar' => $ebene === RahmenService::EBENE_FACHLICH,
			'hinweis' => self::HINWEIS_BELEGE,
		];
	}

	/**
	 * Mappe je Kind und Verwendungszweck (5b.5).
	 *
	 * Bei einem regelbasierten Zweck wird die Mappe aus den
	 * Kompetenzzuordnungen berechnet; manuelle Vormerkungen kommen hinzu.
	 */
	public function mappe(string $nutzerId, int $schuelerId, string $zweckKennung, array $filter = []): array {
		$zweck = $this->zwecke->nachKennung($zweckKennung);
		if ($zweck === null) {
			throw new \InvalidArgumentException('Unbekannter Verwendungszweck: ' . $zweckKennung);
		}

		$zeitleiste = $this->zeitleiste($nutzerId, $schuelerId, [
			'von' => $filter['von'] ?? '1970-01-01',
			'bis' => $filter['bis'] ?? null,
			'kontextIds' => $filter['kontextIds'] ?? [],
			'stufen' => $filter['stufen'] ?? [],
		]);

		$eintraege = $this->nurZweck($zeitleiste['eintraege'], $zweckKennung);

		return [
			'zweck' => $zweck,
			'schuelerId' => $schuelerId,
			'gesamt' => count($eintraege),
			'eintraege' => $eintraege,
			'hinweis' => $zweck['regelbasiert']
				? 'Diese Mappe füllt sich automatisch aus den Kompetenzzuordnungen.'
				: null,
		];
	}

	/**
	 * Filtert auf einen Verwendungszweck — manuelle Vormerkung ODER Regel.
	 *
	 * @param array[] $eintraege
	 */
	private function nurZweck(array $eintraege, string $zweckKennung): array {
		$zweck = $this->zwecke->nachKennung($zweckKennung);
		if ($zweck === null) {
			return [];
		}

		$regelNachVersion = [];
		return array_values(array_filter($eintraege, function ($e) use ($zweck, &$regelNachVersion) {
			// Von Hand oder über den Marker vorgemerkt
			foreach ($e['zwecke'] as $v) {
				if ($v['kennung'] === $zweck['kennung']) {
					return true;
				}
			}
			if (!$zweck['regelbasiert'] || $e['versionId'] === null) {
				return false;
			}
			$versionId = $e['versionId'];
			if (!isset($regelNachVersion[$versionId])) {
				$regelNachVersion[$versionId] = $this->zwecke->regelKnoten($zweck['id'], $versionId);
			}
			$knotenIds = array_column($e['knoten'], 'id');
			return array_intersect($knotenIds, $regelNachVersion[$versionId]) !== [];
		}));
	}

	/** Kennzeichnet, wenn ein Zeitraum mehrere Rahmenversionen umfasst (D5). */
	private function versionsHinweis(array $eintraege): array {
		$versionen = array_values(array_unique(array_filter(
			array_column($eintraege, 'versionId'),
			static fn ($v) => $v !== null
		)));
		return [
			'ids' => $versionen,
			'wechsel' => count($versionen) > 1,
			'hinweis' => count($versionen) > 1
				? 'Der Zeitraum umfasst mehrere Fassungen des Kompetenzrahmens. '
					. 'Werte werden nach Fassung getrennt ausgewiesen.'
				: null,
		];
	}

	/**
	 * Belegübersicht je Kompetenzbereich — der verdichtete Teil eines
	 * Berichts. Bei ~190 Beobachtungen pro Kind und Jahr ist die
	 * Vollliste unlesbar, die Verdichtung trägt.
	 */
	public function belegUebersicht(string $nutzerId, int $schuelerId, array $filter = []): array {
		$zeitleiste = $this->zeitleiste($nutzerId, $schuelerId, $filter);

		$nachBereich = [];
		foreach ($zeitleiste['eintraege'] as $e) {
			foreach ($e['knoten'] as $k) {
				$schluessel = $k['ebene'] . '|' . ($k['fach'] ?? '-');
				$nachBereich[$schluessel]['ebene'] = $k['ebene'];
				$nachBereich[$schluessel]['fach'] = $k['fach'];
				$nachBereich[$schluessel]['knoten'][$k['kennung']]['bezeichnung'] = $k['bezeichnung'];
				$nachBereich[$schluessel]['knoten'][$k['kennung']]['anzahl'] =
					($nachBereich[$schluessel]['knoten'][$k['kennung']]['anzahl'] ?? 0) + 1;
			}
		}

		$ausgabe = [];
		foreach ($nachBereich as $bereich) {
			$knoten = array_values($bereich['knoten']);
			usort($knoten, static fn ($a, $b) => $b['anzahl'] <=> $a['anzahl']);
			$ausgabe[] = [
				'ebene' => $bereich['ebene'],
				'fach' => $bereich['fach'],
				'knoten' => $knoten,
				'summe' => array_sum(array_column($knoten, 'anzahl')),
			];
		}

		return [
			'bereiche' => $ausgabe,
			'gesamt' => $zeitleiste['gesamt'],
			'hinweis' => self::HINWEIS_BELEGE,
		];
	}
}

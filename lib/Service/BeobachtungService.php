<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Erfassung und Abruf von Beobachtungen (Kapitel 5).
 *
 * Der Ein-Tap-Pfad (D3 + D2) ist der Grund, warum das Ganze funktioniert:
 * ein Tap auf einen Schnellmarker erzeugt eine Beobachtung, die auf beiden
 * Achsen zugeordnet und damit **fertig** ist — sie geht nie in die Inbox.
 * Rund 70 Prozent aller Beobachtungen entstehen so.
 *
 * Freitext, Foto und ausdrücklich markierte Einträge sind
 * kuratierungsbedürftig und landen in der Inbox (D4).
 */
class BeobachtungService {

	/** Fenster, in dem eine Beobachtung ohne Spuren zurückgenommen werden kann. */
	public const UNDO_SEKUNDEN = 5;

	public function __construct(
		private IDBConnection $db,
		private StundeService $stunden,
		private MarkerService $marker,
		private ZweckService $zwecke,
		private RahmenService $rahmen,
		private KontextService $kontexte,
		private ZugriffService $zugriff,
		private StammdatenService $stammdaten,
	) {
	}

	/**
	 * Erfasst eine Beobachtung.
	 *
	 * @param array $eingabe {
	 *   schuelerId:  int,
	 *   markerId:    ?int,
	 *   text:        ?string,
	 *   zwecke:      string[]  Kennungen, von Hand gesetzt
	 *   gemerkt:     bool      ausdrücklich für die Inbox markiert
	 *   clientUuid:  ?string   Dublettenschutz der Offline-Warteschlange
	 *   erfasstAm:   ?string   echter Erfassungszeitpunkt vom Gerät
	 * }
	 */
	public function erfassen(string $nutzerId, array $eingabe): array {
		$schuelerId = (int)$eingabe['schuelerId'];
		$clientUuid = $eingabe['clientUuid'] ?? null;

		// Ein doppelt gesendeter Eintrag legt nichts neu an (D9).
		if ($clientUuid !== null) {
			$vorhanden = $this->nachClientUuid($nutzerId, $clientUuid);
			if ($vorhanden !== null) {
				return $vorhanden;
			}
		}

		$stunde = $this->stunden->laufende($nutzerId);
		$markerId = isset($eingabe['markerId']) ? (int)$eingabe['markerId'] : null;
		$marker = $markerId !== null ? $this->marker->nachId($markerId) : null;
		$text = trim((string)($eingabe['text'] ?? ''));
		$gemerkt = (bool)($eingabe['gemerkt'] ?? false);

		if ($marker === null && $text === '') {
			throw new \InvalidArgumentException(
				'Eine Beobachtung braucht mindestens einen Marker oder einen Text.'
			);
		}

		// Der Zeitstempel kommt vom Gerät, damit offline erfasste Einträge
		// nicht auf den Zeitpunkt der Übertragung rutschen.
		$erfasstAm = $this->zeitpunkt($eingabe['erfasstAm'] ?? null);

		// Freitext und ausdrückliches Merken machen kuratierungsbedürftig.
		// Fotos ebenfalls — das setzt der Aufrufer nach dem Anhängen (5.7).
		$kuratierung = $text !== '' || $gemerkt;

		$versionId = $stunde['versionId'] ?? $this->rahmen->aktiveVersionId();

		$this->db->beginTransaction();
		try {
			$q = $this->db->getQueryBuilder();
			$q->insert('kidseye_beobachtung')->values([
				'client_uuid' => $q->createNamedParameter($clientUuid),
				'schueler_id' => $q->createNamedParameter($schuelerId, IQueryBuilder::PARAM_INT),
				'nutzer_id' => $q->createNamedParameter($nutzerId),
				'stunde_id' => $q->createNamedParameter($stunde['id'] ?? null, IQueryBuilder::PARAM_INT),
				'klasse_id' => $q->createNamedParameter($stunde['klasseId'] ?? null, IQueryBuilder::PARAM_INT),
				'kontext_id' => $q->createNamedParameter($stunde['kontextId'] ?? null, IQueryBuilder::PARAM_INT),
				'version_id' => $q->createNamedParameter($versionId, IQueryBuilder::PARAM_INT),
				'erfasst_am' => $q->createNamedParameter($erfasstAm->format('Y-m-d H:i:s')),
				'text' => $q->createNamedParameter($text !== '' ? $text : null),
				'marker_id' => $q->createNamedParameter($markerId, IQueryBuilder::PARAM_INT),
				// Wortlaut festhalten: eine spätere Änderung der Markerdefinition
				// darf alte Beobachtungen nicht umschreiben.
				'marker_text' => $q->createNamedParameter($marker['text'] ?? null),
				'sichtbarkeit' => $q->createNamedParameter(ZugriffService::SICHT_PRIVAT),
				'kuratierung' => $q->createNamedParameter($kuratierung, IQueryBuilder::PARAM_BOOL),
				'gemerkt' => $q->createNamedParameter($gemerkt, IQueryBuilder::PARAM_BOOL),
			])->executeStatement();
			$beobachtungId = $q->getLastInsertId();

			$knotenIds = [];

			// Achse 1: überfachlich, aus der Markerdefinition (D3)
			if ($marker !== null && $marker['knoten'] !== [] && $versionId !== null) {
				foreach ($this->rahmen->knotenNachKennungen($versionId, $marker['knoten']) as $k) {
					$this->zuordnen($beobachtungId, $versionId, $k['id'], 'marker');
					$knotenIds[] = $k['id'];
				}
			}

			// Achse 2: fachlich, aus dem Stundenkontext (D2).
			// Bei fachneutralen Kontexten entfällt sie ersatzlos.
			if (!empty($stunde['inhaltsfeld']['id']) && $versionId !== null) {
				$this->zuordnen($beobachtungId, $versionId, (int)$stunde['inhaltsfeld']['id'], 'kontext');
				$knotenIds[] = (int)$stunde['inhaltsfeld']['id'];
			}

			// Verwendungszwecke aus der Markerdefinition — ohne zusätzlichen Tap
			foreach ($marker['zwecke'] ?? [] as $zweck) {
				$treffer = $this->zwecke->nachKennung($zweck['kennung']);
				if ($treffer !== null) {
					$this->zwecke->vormerken($beobachtungId, $treffer['id'], 'marker');
				}
			}

			// Von Hand gesetzte Zwecke
			foreach ($eingabe['zwecke'] ?? [] as $kennung) {
				$treffer = $this->zwecke->nachKennung((string)$kennung);
				if ($treffer !== null) {
					$this->zwecke->vormerken($beobachtungId, $treffer['id'], 'hand');
				}
			}

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		return $this->nachId($beobachtungId) ?? [];
	}

	/**
	 * Sammelbeobachtung: eine Notiz, je Kind eine eigene Beobachtung (5.10).
	 *
	 * @param int[] $schuelerIds
	 */
	public function erfassenFuerMehrere(string $nutzerId, array $schuelerIds, array $eingabe): array {
		$ergebnis = [];
		foreach ($schuelerIds as $i => $schuelerId) {
			$einzeln = $eingabe;
			$einzeln['schuelerId'] = (int)$schuelerId;
			// Je Kind eine eigene Kennung, sonst greift der Dublettenschutz
			if (!empty($eingabe['clientUuid'])) {
				$einzeln['clientUuid'] = $eingabe['clientUuid'] . '-' . $i;
			}
			$ergebnis[] = $this->erfassen($nutzerId, $einzeln);
		}
		return $ergebnis;
	}

	/** Anhängen einer Arbeitsprobe. Macht die Beobachtung kuratierungsbedürftig. */
	public function dateiAnhaengen(int $beobachtungId, int $fileId, ?string $dateiname = null): void {
		$q = $this->db->getQueryBuilder();
		$q->insert('kidseye_beob_datei')->values([
			'beobachtung_id' => $q->createNamedParameter($beobachtungId, IQueryBuilder::PARAM_INT),
			'file_id' => $q->createNamedParameter($fileId, IQueryBuilder::PARAM_INT),
			'dateiname' => $q->createNamedParameter($dateiname),
		])->executeStatement();

		$q = $this->db->getQueryBuilder();
		$q->update('kidseye_beobachtung')
			->set('kuratierung', $q->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			->where($q->expr()->eq('id', $q->createNamedParameter($beobachtungId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/**
	 * Rückgängig innerhalb des Undo-Fensters — löscht endgültig statt zu
	 * markieren, damit ein Fehlgriff keine Spur hinterlässt (5.15).
	 */
	public function zuruecknehmen(string $nutzerId, int $beobachtungId): bool {
		$beobachtung = $this->nachId($beobachtungId);
		if ($beobachtung === null || $beobachtung['nutzerId'] !== $nutzerId) {
			return false;
		}
		$alter = time() - (new \DateTime($beobachtung['erfasstAmRoh']))->getTimestamp();
		if ($alter > self::UNDO_SEKUNDEN + 30) {
			return false;
		}

		$this->db->beginTransaction();
		try {
			foreach (['kidseye_beob_knoten', 'kidseye_beob_zweck', 'kidseye_beob_datei'] as $tabelle) {
				$q = $this->db->getQueryBuilder();
				$q->delete($tabelle)
					->where($q->expr()->eq('beobachtung_id', $q->createNamedParameter($beobachtungId, IQueryBuilder::PARAM_INT)))
					->executeStatement();
			}
			$q = $this->db->getQueryBuilder();
			$q->delete('kidseye_beobachtung')
				->where($q->expr()->eq('id', $q->createNamedParameter($beobachtungId, IQueryBuilder::PARAM_INT)))
				->executeStatement();
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
		return true;
	}

	/** Umhängen auf ein anderes Kind — gesperrt ab der Akte (6.5). */
	public function umhaengen(string $nutzerId, int $beobachtungId, int $neuerSchuelerId): void {
		$beobachtung = $this->nachId($beobachtungId);
		if ($beobachtung === null) {
			throw new \InvalidArgumentException('Unbekannte Beobachtung.');
		}
		$this->zugriff->verlangeAendern($nutzerId, $beobachtung);

		$q = $this->db->getQueryBuilder();
		$q->update('kidseye_beobachtung')
			->set('schueler_id', $q->createNamedParameter($neuerSchuelerId, IQueryBuilder::PARAM_INT))
			->where($q->expr()->eq('id', $q->createNamedParameter($beobachtungId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function zuordnen(int $beobachtungId, int $versionId, int $knotenId, string $herkunft = 'kuratierung'): void {
		$q = $this->db->getQueryBuilder();
		$q->select('id')->from('kidseye_beob_knoten')
			->where($q->expr()->eq('beobachtung_id', $q->createNamedParameter($beobachtungId, IQueryBuilder::PARAM_INT)))
			->andWhere($q->expr()->eq('knoten_id', $q->createNamedParameter($knotenId, IQueryBuilder::PARAM_INT)));
		$treffer = $q->executeQuery();
		$da = $treffer->fetchOne();
		$treffer->closeCursor();
		if ($da !== false) {
			return;
		}

		$q = $this->db->getQueryBuilder();
		$q->insert('kidseye_beob_knoten')->values([
			'beobachtung_id' => $q->createNamedParameter($beobachtungId, IQueryBuilder::PARAM_INT),
			'version_id' => $q->createNamedParameter($versionId, IQueryBuilder::PARAM_INT),
			'knoten_id' => $q->createNamedParameter($knotenId, IQueryBuilder::PARAM_INT),
			'herkunft' => $q->createNamedParameter($herkunft),
		])->executeStatement();
	}

	public function zuordnungEntfernen(int $beobachtungId, int $knotenId): void {
		$q = $this->db->getQueryBuilder();
		$q->delete('kidseye_beob_knoten')
			->where($q->expr()->eq('beobachtung_id', $q->createNamedParameter($beobachtungId, IQueryBuilder::PARAM_INT)))
			->andWhere($q->expr()->eq('knoten_id', $q->createNamedParameter($knotenId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	// ------------------------------------------------------------------- Abruf

	public function nachId(int $id): ?array {
		$q = $this->db->getQueryBuilder();
		$this->grundAbfrage($q);
		$q->where($q->expr()->eq('b.id', $q->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->hole($q)[0] ?? null;
	}

	public function nachClientUuid(string $nutzerId, string $uuid): ?array {
		$q = $this->db->getQueryBuilder();
		$this->grundAbfrage($q);
		$q->where($q->expr()->eq('b.nutzer_id', $q->createNamedParameter($nutzerId)))
			->andWhere($q->expr()->eq('b.client_uuid', $q->createNamedParameter($uuid)));
		return $this->hole($q)[0] ?? null;
	}

	/** Heutige Beobachtungen eines Kindes — für den Erfassungsbereich (5.18). */
	public function heute(string $nutzerId, int $schuelerId): array {
		$q = $this->db->getQueryBuilder();
		$this->grundAbfrage($q);
		$q->where($q->expr()->eq('b.schueler_id', $q->createNamedParameter($schuelerId, IQueryBuilder::PARAM_INT)))
			->andWhere($q->expr()->gte('b.erfasst_am',
				$q->createNamedParameter((new \DateTime('today'))->format('Y-m-d H:i:s'))))
			->orderBy('b.erfasst_am', 'DESC');
		$this->zugriff->sichtbarkeitsFilter($q, $nutzerId);
		return $this->hole($q);
	}

	/**
	 * Kachelzustand je Kind (5.17): wann zuletzt beobachtet, wie oft heute.
	 * Grundlage für das Verblassen im Klassenbild und das Lücken-Radar.
	 *
	 * @return array<int,array{heute:int,letzte:?string,tageHer:?int}>
	 */
	public function klassenStand(string $nutzerId, int $klasseId): array {
		$kinder = $this->stammdaten->kinderDerKlasse($klasseId);
		$stand = [];
		foreach ($kinder as $kind) {
			$stand[$kind['id']] = ['heute' => 0, 'letzte' => null, 'tageHer' => null];
		}
		if ($stand === []) {
			return [];
		}

		$q = $this->db->getQueryBuilder();
		$q->select('b.schueler_id')
			->selectAlias($q->func()->max('b.erfasst_am'), 'letzte')
			->selectAlias($q->func()->count('b.id'), 'gesamt')
			->from('kidseye_beobachtung', 'b')
			->where($q->expr()->in('b.schueler_id',
				$q->createNamedParameter(array_keys($stand), IQueryBuilder::PARAM_INT_ARRAY)))
			->groupBy('b.schueler_id');
		$this->zugriff->sichtbarkeitsFilter($q, $nutzerId);

		$treffer = $q->executeQuery();
		while ($z = $treffer->fetch()) {
			$letzte = new \DateTime($z['letzte']);
			$stand[(int)$z['schueler_id']]['letzte'] = $letzte->format(\DateTimeInterface::ATOM);
			$stand[(int)$z['schueler_id']]['tageHer'] =
				(int)$letzte->diff(new \DateTime())->days;
		}
		$treffer->closeCursor();

		$q = $this->db->getQueryBuilder();
		$q->select('b.schueler_id')->selectAlias($q->func()->count('b.id'), 'heute')
			->from('kidseye_beobachtung', 'b')
			->where($q->expr()->in('b.schueler_id',
				$q->createNamedParameter(array_keys($stand), IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($q->expr()->gte('b.erfasst_am',
				$q->createNamedParameter((new \DateTime('today'))->format('Y-m-d H:i:s'))))
			->groupBy('b.schueler_id');
		$this->zugriff->sichtbarkeitsFilter($q, $nutzerId);

		$treffer = $q->executeQuery();
		while ($z = $treffer->fetch()) {
			$stand[(int)$z['schueler_id']]['heute'] = (int)$z['heute'];
		}
		$treffer->closeCursor();

		return $stand;
	}

	public function grundAbfrage(IQueryBuilder $q): void {
		$q->select('b.id', 'b.client_uuid', 'b.schueler_id', 'b.nutzer_id', 'b.stunde_id',
			'b.klasse_id', 'b.kontext_id', 'b.version_id', 'b.erfasst_am', 'b.text',
			'b.marker_id', 'b.marker_text', 'b.sichtbarkeit', 'b.akte_am',
			'b.kuratierung', 'b.erledigt_am', 'b.gemerkt', 'b.loeschen_ab', 'b.geloescht_am')
			->selectAlias('s.vorname', 'vorname')
			->selectAlias('s.nachname', 'nachname')
			->selectAlias('s.kuerzel', 'kuerzel')
			->selectAlias('c.name', 'kontext')
			->selectAlias('c.art', 'kontext_art')
			->selectAlias('c.fach_kennung', 'fach')
			->from('kidseye_beobachtung', 'b')
			->innerJoin('b', 'kidseye_schueler', 's', 's.id = b.schueler_id')
			->leftJoin('b', 'kidseye_kontext', 'c', 'c.id = b.kontext_id');
	}

	/** @return array[] */
	public function hole(IQueryBuilder $q, bool $mitDetails = true): array {
		$treffer = $q->executeQuery();
		$zeilen = [];
		while ($z = $treffer->fetch()) {
			$erfasst = new \DateTime($z['erfasst_am']);
			$zeilen[(int)$z['id']] = [
				'id' => (int)$z['id'],
				'clientUuid' => $z['client_uuid'],
				'schuelerId' => (int)$z['schueler_id'],
				'kind' => $z['vorname'] . ' ' . mb_substr($z['nachname'], 0, 1) . '.',
				'kuerzel' => $z['kuerzel'],
				'nutzerId' => $z['nutzer_id'],
				'stundeId' => $z['stunde_id'] !== null ? (int)$z['stunde_id'] : null,
				'klasseId' => $z['klasse_id'] !== null ? (int)$z['klasse_id'] : null,
				'kontextId' => $z['kontext_id'] !== null ? (int)$z['kontext_id'] : null,
				'kontext' => $z['kontext'],
				'kontextArt' => $z['kontext_art'],
				'fach' => $z['fach'],
				'versionId' => $z['version_id'] !== null ? (int)$z['version_id'] : null,
				'erfasstAm' => $erfasst->format(\DateTimeInterface::ATOM),
				'erfasstAmRoh' => $z['erfasst_am'],
				'text' => $z['text'],
				'markerId' => $z['marker_id'] !== null ? (int)$z['marker_id'] : null,
				'markerText' => $z['marker_text'],
				'sichtbarkeit' => $z['sichtbarkeit'],
				'akteAm' => $z['akte_am'],
				'kuratierung' => (bool)$z['kuratierung'],
				'erledigtAm' => $z['erledigt_am'],
				'gemerkt' => (bool)$z['gemerkt'],
				'loeschenAb' => $z['loeschen_ab'],
				'geloeschtAm' => $z['geloescht_am'],
				'knoten' => [],
				'zwecke' => [],
				'dateien' => [],
			];
		}
		$treffer->closeCursor();

		if ($zeilen === [] || !$mitDetails) {
			return array_values($zeilen);
		}
		$ids = array_keys($zeilen);

		$q2 = $this->db->getQueryBuilder();
		$q2->select('z.beobachtung_id', 'z.knoten_id', 'z.herkunft', 'z.stufe_id',
			'k.kennung', 'k.bezeichnung', 'k.art', 'k.ebene', 'k.fach_kennung')
			->from('kidseye_beob_knoten', 'z')
			->innerJoin('z', 'kidseye_knoten', 'k', 'k.id = z.knoten_id')
			->where($q2->expr()->in('z.beobachtung_id', $q2->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
		$treffer = $q2->executeQuery();
		while ($z = $treffer->fetch()) {
			$zeilen[(int)$z['beobachtung_id']]['knoten'][] = [
				'id' => (int)$z['knoten_id'],
				'kennung' => $z['kennung'],
				'bezeichnung' => $z['bezeichnung'],
				'art' => $z['art'],
				'ebene' => $z['ebene'],
				'fach' => $z['fach_kennung'],
				'herkunft' => $z['herkunft'],
				'stufeId' => $z['stufe_id'] !== null ? (int)$z['stufe_id'] : null,
				// Kapitel 2.9 — überfachliche Knoten tragen nie eine Einschätzung
				'bewertbar' => $z['ebene'] === RahmenService::EBENE_FACHLICH
					&& $z['art'] === 'bildungsstandard',
			];
		}
		$treffer->closeCursor();

		$q3 = $this->db->getQueryBuilder();
		$q3->select('v.beobachtung_id', 'v.herkunft', 'z.id', 'z.kennung', 'z.name')
			->from('kidseye_beob_zweck', 'v')
			->innerJoin('v', 'kidseye_zweck', 'z', 'z.id = v.zweck_id')
			->where($q3->expr()->in('v.beobachtung_id', $q3->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
		$treffer = $q3->executeQuery();
		while ($z = $treffer->fetch()) {
			$zeilen[(int)$z['beobachtung_id']]['zwecke'][] = [
				'id' => (int)$z['id'], 'kennung' => $z['kennung'],
				'name' => $z['name'], 'herkunft' => $z['herkunft'],
			];
		}
		$treffer->closeCursor();

		$q4 = $this->db->getQueryBuilder();
		$q4->select('beobachtung_id', 'file_id', 'dateiname')->from('kidseye_beob_datei')
			->where($q4->expr()->in('beobachtung_id', $q4->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
		$treffer = $q4->executeQuery();
		while ($z = $treffer->fetch()) {
			$zeilen[(int)$z['beobachtung_id']]['dateien'][] = [
				'fileId' => (int)$z['file_id'], 'dateiname' => $z['dateiname'],
			];
		}
		$treffer->closeCursor();

		return array_values($zeilen);
	}

	private function zeitpunkt(?string $roh): \DateTime {
		if ($roh === null || $roh === '') {
			return new \DateTime();
		}
		try {
			$zeit = new \DateTime($roh);
		} catch (\Throwable) {
			return new \DateTime();
		}
		// Keine Zeitstempel aus der Zukunft übernehmen
		return $zeit > new \DateTime() ? new \DateTime() : $zeit;
	}
}

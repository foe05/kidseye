<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Die Unterrichtsstunde als Kontextsitzung (Kapitel 4, D2).
 *
 * Der grösste UX-Hebel im ganzen Entwurf: die Lehrkraft wählt einmal pro
 * Stunde Klasse und Kontext, danach erbt jede Beobachtung diese Angaben.
 * Das senkt die Erfassung von rund 19 auf rund 8 Sekunden.
 *
 * Damit das trägt, muss die Stunde von selbst enden — sonst landen
 * Beobachtungen am Donnerstag noch im Dienstagsfach.
 */
class StundeService {

	/** Nach dieser Zeit gilt eine Stunde als beendet. */
	public const LAUFZEIT_MINUTEN = 90;

	/** Ab hier wird beim Öffnen nachgefragt, statt stillschweigend weiterzuschreiben. */
	public const NACHFRAGE_MINUTEN = 120;

	public function __construct(
		private IDBConnection $db,
		private KontextService $kontexte,
		private StammdatenService $stammdaten,
		private ZugriffService $zugriff,
		private RahmenService $rahmen,
	) {
	}

	/**
	 * Startet eine Stunde. Eine bereits laufende Stunde derselben Lehrkraft
	 * wird dabei beendet — es ist immer höchstens eine aktiv.
	 *
	 * @throws ZugriffVerweigert          ohne Lehrauftrag
	 * @throws \InvalidArgumentException  bei Inhaltsfeld an fachneutralem Kontext
	 *
	 * @param \DateTimeInterface|null $begonnenAm Rückwirkender Beginn. Nur für
	 *        erzeugte Beispieldaten; im Normalbetrieb null.
	 */
	public function starten(
		string $nutzerId,
		int $klasseId,
		int $kontextId,
		?int $inhaltsfeldId = null,
		?\DateTimeInterface $begonnenAm = null,
	): array {
		$this->zugriff->verlangeLehrauftrag($nutzerId, $klasseId, $kontextId);

		$kontext = $this->kontexte->nachId($kontextId);
		if ($kontext === null) {
			throw new \InvalidArgumentException('Unbekannter Unterrichtskontext.');
		}

		// Fachneutrale Kontexte haben keine fachliche Achse (D2)
		if ($inhaltsfeldId !== null && !$this->kontexte->hatFachbezug($kontext)) {
			throw new \InvalidArgumentException(
				'Der Kontext „' . $kontext['name'] . '" ist fachneutral und kennt keine Inhaltsfelder.'
			);
		}
		if ($inhaltsfeldId !== null) {
			$knoten = $this->rahmen->knotenNachId($inhaltsfeldId);
			if ($knoten === null || $knoten['fach'] !== $kontext['fach']) {
				throw new \InvalidArgumentException(
					'Das Inhaltsfeld gehört nicht zum Fach dieses Kontexts.'
				);
			}
		}

		$this->laufendeBeenden($nutzerId);

		// $begonnenAm setzt nur, wer rückwirkend erzeugt (Beispieldaten).
		$jetzt = $begonnenAm !== null
			? \DateTime::createFromInterface($begonnenAm)
			: new \DateTime();
		$q = $this->db->getQueryBuilder();
		$q->insert('kidseye_stunde')->values([
			'nutzer_id' => $q->createNamedParameter($nutzerId),
			'klasse_id' => $q->createNamedParameter($klasseId, IQueryBuilder::PARAM_INT),
			'kontext_id' => $q->createNamedParameter($kontextId, IQueryBuilder::PARAM_INT),
			'inhaltsfeld_id' => $q->createNamedParameter($inhaltsfeldId, IQueryBuilder::PARAM_INT),
			'version_id' => $q->createNamedParameter($this->rahmen->aktiveVersionId(), IQueryBuilder::PARAM_INT),
			'begonnen_am' => $q->createNamedParameter($jetzt->format('Y-m-d H:i:s')),
		])->executeStatement();

		return $this->nachId($q->getLastInsertId()) ?? [];
	}

	/**
	 * Die laufende Stunde einer Lehrkraft — oder null.
	 *
	 * Eine Stunde, die älter als LAUFZEIT_MINUTEN ist, gilt als beendet und
	 * wird dabei auch in der Datenbank geschlossen.
	 */
	public function laufende(string $nutzerId): ?array {
		$q = $this->db->getQueryBuilder();
		$q->select('id')->from('kidseye_stunde')
			->where($q->expr()->eq('nutzer_id', $q->createNamedParameter($nutzerId)))
			->andWhere($q->expr()->isNull('beendet_am'))
			->orderBy('begonnen_am', 'DESC')->setMaxResults(1);
		$treffer = $q->executeQuery();
		$id = $treffer->fetchOne();
		$treffer->closeCursor();

		if ($id === false) {
			return null;
		}

		$stunde = $this->nachId((int)$id);
		if ($stunde === null) {
			return null;
		}

		if ($stunde['alterMinuten'] >= self::LAUFZEIT_MINUTEN) {
			$this->beenden((int)$id);
			return null;
		}
		return $stunde;
	}

	/**
	 * Zustand für den Einstieg in die Anwendung (4.4, 4.5).
	 *
	 * @return array{status:string, stunde:?array}
	 *         laeuft    — direkt in den Erfassungsbildschirm
	 *         nachfrage — Kontext ist alt, es wird gefragt statt geschrieben
	 *         keine     — Startdialog
	 */
	public function einstieg(string $nutzerId): array {
		$stunde = $this->laufende($nutzerId);
		if ($stunde === null) {
			return ['status' => 'keine', 'stunde' => null, 'letzte' => $this->letzteKombination($nutzerId)];
		}
		if ($stunde['alterMinuten'] >= self::NACHFRAGE_MINUTEN) {
			return ['status' => 'nachfrage', 'stunde' => $stunde, 'letzte' => null];
		}
		return ['status' => 'laeuft', 'stunde' => $stunde, 'letzte' => null];
	}

	public function beenden(int $stundeId): void {
		$q = $this->db->getQueryBuilder();
		$q->update('kidseye_stunde')
			->set('beendet_am', $q->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')))
			->where($q->expr()->eq('id', $q->createNamedParameter($stundeId, IQueryBuilder::PARAM_INT)))
			->andWhere($q->expr()->isNull('beendet_am'))
			->executeStatement();
	}

	public function laufendeBeenden(string $nutzerId): void {
		$q = $this->db->getQueryBuilder();
		$q->update('kidseye_stunde')
			->set('beendet_am', $q->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')))
			->where($q->expr()->eq('nutzer_id', $q->createNamedParameter($nutzerId)))
			->andWhere($q->expr()->isNull('beendet_am'))
			->executeStatement();
	}

	public function nachId(int $id): ?array {
		$q = $this->db->getQueryBuilder();
		$q->select('s.id', 's.nutzer_id', 's.klasse_id', 's.kontext_id',
			's.inhaltsfeld_id', 's.version_id', 's.begonnen_am', 's.beendet_am')
			->selectAlias('k.name', 'klasse')
			->selectAlias('c.name', 'kontext')
			->selectAlias('c.art', 'kontext_art')
			->selectAlias('c.fach_kennung', 'fach')
			->from('kidseye_stunde', 's')
			->innerJoin('s', 'kidseye_klasse', 'k', 'k.id = s.klasse_id')
			->innerJoin('s', 'kidseye_kontext', 'c', 'c.id = s.kontext_id')
			->where($q->expr()->eq('s.id', $q->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		$treffer = $q->executeQuery();
		$z = $treffer->fetch();
		$treffer->closeCursor();
		if ($z === false) {
			return null;
		}

		$begonnen = new \DateTime($z['begonnen_am']);
		$inhaltsfeld = null;
		if ($z['inhaltsfeld_id'] !== null) {
			$knoten = $this->rahmen->knotenNachId((int)$z['inhaltsfeld_id']);
			$inhaltsfeld = $knoten === null ? null
				: ['id' => $knoten['id'], 'bezeichnung' => $knoten['bezeichnung']];
		}

		return [
			'id' => (int)$z['id'],
			'nutzerId' => $z['nutzer_id'],
			'klasseId' => (int)$z['klasse_id'],
			'klasse' => $z['klasse'],
			'kontextId' => (int)$z['kontext_id'],
			'kontext' => $z['kontext'],
			'kontextArt' => $z['kontext_art'],
			'fach' => $z['fach'],
			'inhaltsfeld' => $inhaltsfeld,
			'versionId' => $z['version_id'] !== null ? (int)$z['version_id'] : null,
			'begonnenAm' => $begonnen->format(\DateTimeInterface::ATOM),
			'beendetAm' => $z['beendet_am'],
			'alterMinuten' => (int)floor((time() - $begonnen->getTimestamp()) / 60),
		];
	}

	/** Zuletzt genutzte Kombination — Vorbelegung des Startdialogs (4.2). */
	public function letzteKombination(string $nutzerId): ?array {
		$q = $this->db->getQueryBuilder();
		$q->select('klasse_id', 'kontext_id', 'inhaltsfeld_id')->from('kidseye_stunde')
			->where($q->expr()->eq('nutzer_id', $q->createNamedParameter($nutzerId)))
			->orderBy('begonnen_am', 'DESC')->setMaxResults(1);
		$treffer = $q->executeQuery();
		$z = $treffer->fetch();
		$treffer->closeCursor();
		return $z === false ? null : [
			'klasseId' => (int)$z['klasse_id'],
			'kontextId' => (int)$z['kontext_id'],
			'inhaltsfeldId' => $z['inhaltsfeld_id'] !== null ? (int)$z['inhaltsfeld_id'] : null,
		];
	}

	/**
	 * Auswahlmöglichkeiten für den Startdialog: nur Kombinationen, für die
	 * ein Lehrauftrag vorliegt.
	 */
	public function startAuswahl(string $nutzerId): array {
		$schuljahr = $this->stammdaten->aktivesSchuljahr();
		$auftraege = $this->stammdaten->lehrauftraege($nutzerId, $schuljahr['id'] ?? null);
		$versionId = $this->rahmen->aktiveVersionId();

		$klassen = [];
		foreach ($auftraege as $a) {
			$klassen[$a['klasseId']]['id'] = $a['klasseId'];
			$klassen[$a['klasseId']]['name'] = $a['klasse'];
			$klassen[$a['klasseId']]['kontexte'][] = [
				'id' => $a['kontextId'],
				'name' => $a['kontext'],
				'art' => $a['kontextArt'],
				'fach' => $a['fach'],
				// Fachneutrale Kontexte bieten keine Inhaltsfelder an (D2)
				'inhaltsfelder' => ($a['fach'] !== null && $versionId !== null)
					? array_map(
						static fn ($k) => ['id' => $k['id'], 'bezeichnung' => $k['bezeichnung']],
						$this->rahmen->knoten($versionId, RahmenService::EBENE_FACHLICH, $a['fach'], ['inhaltsfeld'])
					)
					: [],
			];
		}

		return [
			// Auf welche Kennung wurde gesucht? Bleibt die Liste leer, ist genau
			// das die Auskunft, die weiterhilft: ein Lehrauftrag auf einer anderen
			// Kennung sieht von hier aus wie gar kein Lehrauftrag.
			'nutzerId' => $nutzerId,
			'klassen' => array_values($klassen),
			'letzte' => $this->letzteKombination($nutzerId),
		];
	}
}

<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Schnellmarker (D3) — der Mechanismus, der die Ein-Tap-Erfassung trägt.
 *
 * Ein Marker ist kein freies Etikett, sondern eine benannte Verknüpfung auf
 * überfachliche Kompetenzdimensionen, optional mit einem Verwendungszweck.
 * Zusammen mit dem Stundenkontext (D2) ergibt ein einziger Tap damit eine auf
 * beiden Achsen zugeordnete, vollständige Beobachtung.
 *
 * Die mitgelieferten Sätze sind ausdrücklich nur Vorschlag: welche sechs
 * Wörter auf dem Bildschirm stehen, entscheidet die Lehrkraft.
 */
class MarkerService {

	public const HOECHSTZAHL_SICHTBAR = 6;

	/**
	 * Vorschlagssätze je Kontext. Beschreibend statt bewertend formuliert —
	 * „sucht Kontakt" statt „stört".
	 */
	public const VORSCHLAG = [
		'deutsch' => [
			['liest flüssig vor', ['uk.sprach.lesen']],
			['hört aufmerksam zu', ['uk.sprach.kommunikation']],
			['erzählt zusammenhängend', ['uk.sprach.kommunikation']],
			['schreibt selbstständig', ['uk.sprach.schreiben']],
			['sucht Unterstützung', ['uk.lern.arbeit']],
			['Hürde', ['uk.lern.problemloesen'], 'foerderplan'],
		],
		'mathematik' => [
			['erklärt seinen Rechenweg', ['uk.sprach.kommunikation']],
			['probiert eigene Wege', ['uk.lern.problemloesen']],
			['arbeitet allein weiter', ['uk.personal.selbstregulierung', 'uk.lern.arbeit']],
			['hilft anderen', ['uk.sozial.ruecksicht']],
			['braucht mehr Zeit', ['uk.lern.arbeit']],
			['Hürde', ['uk.lern.problemloesen'], 'foerderplan'],
		],
		'sachunterricht' => [
			['beobachtet genau', ['uk.lern.problemloesen']],
			['stellt eigene Fragen', ['uk.lern.problemloesen']],
			['bringt Vorwissen ein', ['uk.sprach.kommunikation']],
			['arbeitet in der Gruppe mit', ['uk.sozial.kooperation']],
			['dokumentiert Ergebnisse', ['uk.lern.medien']],
			['Hürde', ['uk.lern.problemloesen'], 'foerderplan'],
		],
		'kunst' => [
			['probiert Material aus', ['uk.lern.problemloesen']],
			['setzt eigene Ideen um', ['uk.personal.selbstkonzept']],
			['bleibt lange dran', ['uk.personal.selbstregulierung']],
			['spricht über Bilder', ['uk.sprach.kommunikation']],
			['geht sorgsam mit Material um', ['uk.sozial.verantwortung']],
			['Hürde', ['uk.lern.problemloesen'], 'foerderplan'],
		],
		'ethik' => [
			['hört anderen zu', ['uk.sozial.wahrnehmung']],
			['begründet seine Meinung', ['uk.sprach.kommunikation']],
			['nimmt andere Sichtweisen auf', ['uk.sozial.wahrnehmung']],
			['sucht faire Lösungen', ['uk.sozial.konflikte']],
			['übernimmt Verantwortung', ['uk.sozial.verantwortung']],
			['Hürde', ['uk.lern.problemloesen'], 'foerderplan'],
		],
		'freiarbeit' => [
			['plant seine Arbeit', ['uk.lern.problemloesen']],
			['arbeitet ausdauernd', ['uk.personal.selbstregulierung']],
			['holt sich Hilfe', ['uk.sozial.wahrnehmung', 'uk.lern.arbeit']],
			['wechselt oft die Aufgabe', ['uk.personal.selbstregulierung']],
			['hilft anderen', ['uk.sozial.ruecksicht']],
			['Hürde', ['uk.lern.problemloesen'], 'foerderplan'],
		],
		'sozial_arbeitsverhalten' => [
			['hält Absprachen ein', ['uk.sozial.kooperation']],
			['geht rücksichtsvoll um', ['uk.sozial.ruecksicht']],
			['löst Streit selbst', ['uk.sozial.konflikte']],
			['bringt sich ein', ['uk.sozial.verantwortung']],
			['schätzt sich realistisch ein', ['uk.personal.selbstwahrnehmung']],
			['Hürde', ['uk.sozial.konflikte'], 'foerderplan'],
		],
	];

	public function __construct(
		private IDBConnection $db,
		private KontextService $kontexte,
		private RahmenService $rahmen,
	) {
	}

	/** Sichtbare Marker eines Kontexts, in Anzeigereihenfolge. */
	public function fuerKontext(int $kontextId, bool $nurSichtbare = true): array {
		$q = $this->db->getQueryBuilder();
		$q->select('id', 'kontext_id', 'text', 'sichtbar', 'sortierung')
			->from('kidseye_marker')
			->where($q->expr()->eq('kontext_id', $q->createNamedParameter($kontextId, IQueryBuilder::PARAM_INT)))
			->orderBy('sortierung')->addOrderBy('id');
		if ($nurSichtbare) {
			$q->andWhere($q->expr()->eq('sichtbar', $q->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));
		}

		$treffer = $q->executeQuery();
		$marker = [];
		while ($z = $treffer->fetch()) {
			$marker[(int)$z['id']] = [
				'id' => (int)$z['id'],
				'kontextId' => (int)$z['kontext_id'],
				'text' => $z['text'],
				'sichtbar' => (bool)$z['sichtbar'],
				'sortierung' => (int)$z['sortierung'],
				'knoten' => [],
				'zwecke' => [],
			];
		}
		$treffer->closeCursor();

		if ($marker === []) {
			return [];
		}
		$ids = array_keys($marker);

		$q = $this->db->getQueryBuilder();
		$q->select('marker_id', 'knoten_kennung')->from('kidseye_marker_knoten')
			->where($q->expr()->in('marker_id', $q->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
		$treffer = $q->executeQuery();
		while ($z = $treffer->fetch()) {
			$marker[(int)$z['marker_id']]['knoten'][] = $z['knoten_kennung'];
		}
		$treffer->closeCursor();

		$q = $this->db->getQueryBuilder();
		$q->select('m.marker_id', 'z.kennung', 'z.name')
			->from('kidseye_marker_zweck', 'm')
			->innerJoin('m', 'kidseye_zweck', 'z', 'z.id = m.zweck_id')
			->where($q->expr()->in('m.marker_id', $q->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($q->expr()->eq('z.aktiv', $q->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));
		$treffer = $q->executeQuery();
		while ($z = $treffer->fetch()) {
			$marker[(int)$z['marker_id']]['zwecke'][] = ['kennung' => $z['kennung'], 'name' => $z['name']];
		}
		$treffer->closeCursor();

		return array_values($marker);
	}

	public function nachId(int $markerId): ?array {
		$q = $this->db->getQueryBuilder();
		$q->select('kontext_id')->from('kidseye_marker')
			->where($q->expr()->eq('id', $q->createNamedParameter($markerId, IQueryBuilder::PARAM_INT)));
		$treffer = $q->executeQuery();
		$kontextId = $treffer->fetchOne();
		$treffer->closeCursor();
		if ($kontextId === false) {
			return null;
		}
		foreach ($this->fuerKontext((int)$kontextId, false) as $m) {
			if ($m['id'] === $markerId) {
				return $m;
			}
		}
		return null;
	}

	/**
	 * Markersatz eines Kontexts ersetzen.
	 *
	 * Bereits erfasste Beobachtungen behalten ihren ursprünglichen Markertext
	 * und ihre Kompetenzzuordnung — beides steht an der Beobachtung selbst.
	 *
	 * @param array $marker Liste aus
	 *        ['text'=>string,'knoten'=>string[],'zwecke'=>string[],'sichtbar'=>bool]
	 * @throws \InvalidArgumentException bei mehr als sechs sichtbaren Markern
	 */
	public function satzSpeichern(int $kontextId, array $marker): void {
		$sichtbare = array_filter($marker, static fn ($m) => ($m['sichtbar'] ?? true) === true);
		if (count($sichtbare) > self::HOECHSTZAHL_SICHTBAR) {
			throw new \InvalidArgumentException(sprintf(
				'Es können höchstens %d Marker gleichzeitig sichtbar sein; angegeben sind %d. '
				. 'Der Erfassungsbildschirm ist auf Tempo ausgelegt, nicht auf Vollständigkeit.',
				self::HOECHSTZAHL_SICHTBAR,
				count($sichtbare)
			));
		}

		$this->db->beginTransaction();
		try {
			$q = $this->db->getQueryBuilder();
			$q->select('id')->from('kidseye_marker')
				->where($q->expr()->eq('kontext_id', $q->createNamedParameter($kontextId, IQueryBuilder::PARAM_INT)));
			$treffer = $q->executeQuery();
			$alt = array_map('intval', $treffer->fetchAll(\PDO::FETCH_COLUMN) ?: []);
			$treffer->closeCursor();

			if ($alt !== []) {
				foreach (['kidseye_marker_knoten', 'kidseye_marker_zweck'] as $tabelle) {
					$q = $this->db->getQueryBuilder();
					$q->delete($tabelle)
						->where($q->expr()->in('marker_id', $q->createNamedParameter($alt, IQueryBuilder::PARAM_INT_ARRAY)))
						->executeStatement();
				}
				$q = $this->db->getQueryBuilder();
				$q->delete('kidseye_marker')
					->where($q->expr()->eq('kontext_id', $q->createNamedParameter($kontextId, IQueryBuilder::PARAM_INT)))
					->executeStatement();
			}

			foreach (array_values($marker) as $i => $m) {
				$this->anlegen(
					$kontextId,
					$m['text'],
					$m['knoten'] ?? [],
					$m['zwecke'] ?? [],
					($m['sichtbar'] ?? true) === true,
					$i * 10
				);
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	public function anlegen(
		int $kontextId,
		string $text,
		array $knotenKennungen = [],
		array $zweckKennungen = [],
		bool $sichtbar = true,
		int $sortierung = 0,
	): int {
		$q = $this->db->getQueryBuilder();
		$q->insert('kidseye_marker')->values([
			'kontext_id' => $q->createNamedParameter($kontextId, IQueryBuilder::PARAM_INT),
			'text' => $q->createNamedParameter($text),
			'sichtbar' => $q->createNamedParameter($sichtbar, IQueryBuilder::PARAM_BOOL),
			'sortierung' => $q->createNamedParameter($sortierung, IQueryBuilder::PARAM_INT),
		])->executeStatement();
		$markerId = $q->getLastInsertId();

		foreach (array_unique($knotenKennungen) as $kennung) {
			$q = $this->db->getQueryBuilder();
			$q->insert('kidseye_marker_knoten')->values([
				'marker_id' => $q->createNamedParameter($markerId, IQueryBuilder::PARAM_INT),
				'knoten_kennung' => $q->createNamedParameter($kennung),
			])->executeStatement();
		}

		foreach (array_unique($zweckKennungen) as $kennung) {
			$zweckId = $this->zweckId($kennung);
			if ($zweckId === null) {
				continue;
			}
			$q = $this->db->getQueryBuilder();
			$q->insert('kidseye_marker_zweck')->values([
				'marker_id' => $q->createNamedParameter($markerId, IQueryBuilder::PARAM_INT),
				'zweck_id' => $q->createNamedParameter($zweckId, IQueryBuilder::PARAM_INT),
			])->executeStatement();
		}

		return $markerId;
	}

	/** Legt die Vorschlagssätze an, wo noch keine Marker existieren. */
	public function vorschlaegeAnlegen(): int {
		$zahl = 0;
		foreach ($this->kontexte->alle(false) as $kontext) {
			if ($this->fuerKontext($kontext['id'], false) !== []) {
				continue;
			}
			$satz = self::VORSCHLAG[$kontext['kennung']] ?? [];
			foreach ($satz as $i => $eintrag) {
				$this->anlegen(
					$kontext['id'],
					$eintrag[0],
					$eintrag[1],
					isset($eintrag[2]) ? [$eintrag[2]] : [],
					true,
					$i * 10
				);
				$zahl++;
			}
		}
		return $zahl;
	}

	private function zweckId(string $kennung): ?int {
		$q = $this->db->getQueryBuilder();
		$q->select('id')->from('kidseye_zweck')
			->where($q->expr()->eq('kennung', $q->createNamedParameter($kennung)));
		$treffer = $q->executeQuery();
		$id = $treffer->fetchOne();
		$treffer->closeCursor();
		return $id === false ? null : (int)$id;
	}
}

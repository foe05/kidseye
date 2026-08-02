<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Lesezugriff auf den Kompetenzrahmen.
 *
 * Hier lebt die Sperre aus Kapitel 2.9: auf überfachliche Kompetenzen gibt es
 * keine Skala und keine Einstufung. Das Kerncurriculum stellt fest, dass sie
 * sich „weitgehend einer Normierung und empirischen Überprüfung" entziehen —
 * kidseye weist deshalb nur Belege und Häufigkeiten aus.
 */
class RahmenService {

	public const EBENE_UEBERFACHLICH = 'ueberfachlich';
	public const EBENE_FACHLICH = 'fachlich';

	private const SPALTEN = [
		'id', 'version_id', 'eltern_id', 'kennung', 'art', 'ebene',
		'fach_kennung', 'bezeichnung', 'beschreibung', 'struktur_name',
		'bezugsstufe', 'sortierung', 'waehlbar',
	];

	public function __construct(
		private IDBConnection $db,
	) {
	}

	/** Die zuletzt importierte Rahmenversion — die aktive. */
	public function aktiveVersionId(): ?int {
		$q = $this->db->getQueryBuilder();
		$q->select('id')->from('kidseye_rahmen_vers')
			->orderBy('gueltig_ab', 'DESC')->addOrderBy('id', 'DESC')
			->setMaxResults(1);
		$treffer = $q->executeQuery();
		$id = $treffer->fetchOne();
		$treffer->closeCursor();
		return $id === false ? null : (int)$id;
	}

	/**
	 * Knoten einer Version, optional gefiltert.
	 *
	 * @param string|null $ebene       ueberfachlich | fachlich
	 * @param string|null $fach        nur bei fachlicher Ebene sinnvoll
	 * @param bool        $nurWaehlbar Leitstrukturen sind geseedet, aber in
	 *                                 v1 nicht zur Zuordnung angeboten (D2b)
	 */
	public function knoten(
		int $versionId,
		?string $ebene = null,
		?string $fach = null,
		?array $arten = null,
		bool $nurWaehlbar = true,
	): array {
		$q = $this->db->getQueryBuilder();
		$q->select(...self::SPALTEN)->from('kidseye_knoten')
			->where($q->expr()->eq('version_id', $q->createNamedParameter($versionId, IQueryBuilder::PARAM_INT)));

		if ($ebene !== null) {
			$q->andWhere($q->expr()->eq('ebene', $q->createNamedParameter($ebene)));
		}
		if ($fach !== null) {
			$q->andWhere($q->expr()->eq('fach_kennung', $q->createNamedParameter($fach)));
		}
		if ($arten !== null && $arten !== []) {
			$q->andWhere($q->expr()->in('art', $q->createNamedParameter($arten, IQueryBuilder::PARAM_STR_ARRAY)));
		}
		if ($nurWaehlbar) {
			$q->andWhere($q->expr()->eq('waehlbar', $q->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));
		}
		$q->orderBy('fach_kennung')->addOrderBy('art')->addOrderBy('sortierung');

		return $this->hole($q);
	}

	/**
	 * Zuordnungsvorschläge für eine Beobachtung.
	 *
	 * Bei einem fachneutralen Kontext (Freiarbeit, Sozial- und
	 * Arbeitsverhalten) gibt es keine fachliche Achse — dann werden
	 * ausschließlich überfachliche Dimensionen angeboten (D2).
	 */
	public function vorschlaege(int $versionId, ?string $fach): array {
		$ueberfachlich = $this->knoten(
			$versionId, self::EBENE_UEBERFACHLICH, null, ['bereich', 'dimension']
		);

		if ($fach === null) {
			return ['fachlich' => [], 'ueberfachlich' => $ueberfachlich];
		}

		return [
			'fachlich' => $this->knoten(
				$versionId, self::EBENE_FACHLICH, $fach,
				['kompetenzbereich', 'bildungsstandard', 'inhaltsfeld']
			),
			'ueberfachlich' => $ueberfachlich,
		];
	}

	public function knotenNachId(int $id): ?array {
		$q = $this->db->getQueryBuilder();
		$q->select(...self::SPALTEN)->from('kidseye_knoten')
			->where($q->expr()->eq('id', $q->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$alle = $this->hole($q);
		return $alle[0] ?? null;
	}

	/** @param string[] $kennungen */
	public function knotenNachKennungen(int $versionId, array $kennungen): array {
		if ($kennungen === []) {
			return [];
		}
		$q = $this->db->getQueryBuilder();
		$q->select(...self::SPALTEN)->from('kidseye_knoten')
			->where($q->expr()->eq('version_id', $q->createNamedParameter($versionId, IQueryBuilder::PARAM_INT)))
			->andWhere($q->expr()->in('kennung', $q->createNamedParameter($kennungen, IQueryBuilder::PARAM_STR_ARRAY)));
		return $this->hole($q);
	}

	/**
	 * Alle Nachfahren-IDs eines Knotens, den Knoten selbst eingeschlossen.
	 * Wird für regelbasierte Verwendungszwecke gebraucht (D16).
	 */
	public function mitNachfahren(int $versionId, int $knotenId): array {
		$alle = $this->knoten($versionId, null, null, null, false);
		$kinder = [];
		foreach ($alle as $k) {
			if ($k['elternId'] !== null) {
				$kinder[$k['elternId']][] = $k['id'];
			}
		}
		$ergebnis = [];
		$stapel = [$knotenId];
		while ($stapel !== []) {
			$aktuell = array_pop($stapel);
			if (isset($ergebnis[$aktuell])) {
				continue;
			}
			$ergebnis[$aktuell] = true;
			foreach ($kinder[$aktuell] ?? [] as $kind) {
				$stapel[] = $kind;
			}
		}
		return array_keys($ergebnis);
	}

	/**
	 * Darf dieser Knoten eine Einschätzung tragen? (Kapitel 2.9)
	 *
	 * Nur fachliche Bildungsstandards. Für überfachliche Dimensionen gibt es
	 * keine Skala — das ist keine Einschränkung der Oberfläche, sondern eine
	 * Eigenschaft des Kerncurriculums.
	 */
	public function istBewertbar(array $knoten): bool {
		return $knoten['ebene'] === self::EBENE_FACHLICH
			&& $knoten['art'] === 'bildungsstandard';
	}

	/** @throws BewertungNichtZulaessig */
	public function verlangeBewertbar(int $knotenId): void {
		$knoten = $this->knotenNachId($knotenId);
		if ($knoten === null) {
			throw new BewertungNichtZulaessig('Unbekannter Kompetenzknoten.');
		}
		if (!$this->istBewertbar($knoten)) {
			throw new BewertungNichtZulaessig(
				'Für „' . $knoten['bezeichnung'] . '" ist keine Einschätzung vorgesehen. '
				. 'Überfachliche Kompetenzen werden im hessischen Kerncurriculum nicht normiert; '
				. 'kidseye weist dort ausschließlich Belege aus.'
			);
		}
	}

	/** Korrespondierende Knoten (Bildungsstandard ↔ Inhaltsfeld, D6). */
	public function korrespondenzen(int $knotenId): array {
		$q = $this->db->getQueryBuilder();
		$q->select('k.' . implode(', k.', self::SPALTEN))
			->from('kidseye_knoten_korr', 'c')
			->innerJoin('c', 'kidseye_knoten', 'k',
				'k.id = CASE WHEN c.von_knoten_id = :knoten THEN c.nach_knoten_id ELSE c.von_knoten_id END')
			->where($q->expr()->orX(
				$q->expr()->eq('c.von_knoten_id', $q->createNamedParameter($knotenId, IQueryBuilder::PARAM_INT)),
				$q->expr()->eq('c.nach_knoten_id', $q->createNamedParameter($knotenId, IQueryBuilder::PARAM_INT))
			))
			->setParameter('knoten', $knotenId);
		return $this->hole($q);
	}

	/** Rahmenversion als Datei zurückschreiben (Kapitel 2.2, Export). */
	public function exportiere(int $versionId): array {
		$q = $this->db->getQueryBuilder();
		$q->select('v.kennung', 'v.name', 'v.gueltig_ab', 'v.quelle')
			->selectAlias('r.kennung', 'r_kennung')
			->selectAlias('r.name', 'r_name')
			->selectAlias('r.herausgeber', 'r_herausgeber')
			->from('kidseye_rahmen_vers', 'v')
			->innerJoin('v', 'kidseye_rahmen', 'r', 'r.id = v.rahmen_id')
			->where($q->expr()->eq('v.id', $q->createNamedParameter($versionId, IQueryBuilder::PARAM_INT)));
		$treffer = $q->executeQuery();
		$kopf = $treffer->fetch();
		$treffer->closeCursor();
		if ($kopf === false) {
			throw new \RuntimeException('Unbekannte Rahmenversion: ' . $versionId);
		}

		$knoten = $this->knoten($versionId, null, null, null, false);
		$nachId = [];
		foreach ($knoten as $k) {
			$nachId[$k['id']] = $k['kennung'];
		}

		$faecher = [];
		foreach ($knoten as $k) {
			if ($k['fach'] !== null) {
				$faecher[$k['fach']] = true;
			}
		}

		$ausgabe = [];
		foreach ($knoten as $k) {
			$eintrag = [
				'kennung' => $k['kennung'],
				'art' => $k['art'],
				'ebene' => $k['ebene'],
				'sortierung' => $k['sortierung'],
				'bezeichnung' => $k['bezeichnung'],
			];
			foreach ([
				'eltern' => $k['elternId'] !== null ? ($nachId[$k['elternId']] ?? null) : null,
				'fach' => $k['fach'],
				'beschreibung' => $k['beschreibung'],
				'strukturName' => $k['strukturName'],
				'bezugsstufe' => $k['bezugsstufe'],
			] as $schluessel => $wert) {
				if ($wert !== null) {
					$eintrag[$schluessel] = $wert;
				}
			}
			if (!$k['waehlbar']) {
				$eintrag['waehlbar'] = false;
			}
			$ausgabe[] = $eintrag;
		}

		return [
			'$schema' => 'kidseye-rahmen/1',
			'rahmen' => [
				'kennung' => $kopf['r_kennung'],
				'name' => $kopf['r_name'],
				'herausgeber' => $kopf['r_herausgeber'],
			],
			'version' => [
				'kennung' => $kopf['kennung'],
				'name' => $kopf['name'],
				'gueltigAb' => $kopf['gueltig_ab'],
				'quelle' => $kopf['quelle'],
			],
			'faecher' => array_map(
				static fn ($f) => ['kennung' => $f, 'name' => ucfirst($f)],
				array_keys($faecher)
			),
			'knoten' => $ausgabe,
			'korrespondenzen' => $this->alleKorrespondenzen($versionId, $nachId),
		];
	}

	private function alleKorrespondenzen(int $versionId, array $nachId): array {
		$q = $this->db->getQueryBuilder();
		$q->select('c.von_knoten_id', 'c.nach_knoten_id')
			->from('kidseye_knoten_korr', 'c')
			->innerJoin('c', 'kidseye_knoten', 'k', 'k.id = c.von_knoten_id')
			->where($q->expr()->eq('k.version_id', $q->createNamedParameter($versionId, IQueryBuilder::PARAM_INT)));
		$treffer = $q->executeQuery();
		$paare = [];
		while ($zeile = $treffer->fetch()) {
			$von = $nachId[(int)$zeile['von_knoten_id']] ?? null;
			$nach = $nachId[(int)$zeile['nach_knoten_id']] ?? null;
			if ($von !== null && $nach !== null) {
				$paare[] = ['von' => $von, 'nach' => $nach];
			}
		}
		$treffer->closeCursor();
		return $paare;
	}

	private function hole(IQueryBuilder $q): array {
		$treffer = $q->executeQuery();
		$zeilen = [];
		while ($zeile = $treffer->fetch()) {
			$zeilen[] = [
				'id' => (int)$zeile['id'],
				'versionId' => (int)$zeile['version_id'],
				'elternId' => $zeile['eltern_id'] !== null ? (int)$zeile['eltern_id'] : null,
				'kennung' => $zeile['kennung'],
				'art' => $zeile['art'],
				'ebene' => $zeile['ebene'],
				'fach' => $zeile['fach_kennung'],
				'bezeichnung' => $zeile['bezeichnung'],
				'beschreibung' => $zeile['beschreibung'],
				'strukturName' => $zeile['struktur_name'],
				'bezugsstufe' => $zeile['bezugsstufe'],
				'sortierung' => (int)$zeile['sortierung'],
				'waehlbar' => (bool)$zeile['waehlbar'],
				// Kapitel 2.9 — die Oberfläche darf hier keine Skala anbieten
				'bewertbar' => $zeile['ebene'] === self::EBENE_FACHLICH
					&& $zeile['art'] === 'bildungsstandard',
			];
		}
		$treffer->closeCursor();
		return $zeilen;
	}
}

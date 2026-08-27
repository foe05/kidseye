<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Schuljahre, Klassen, Schüler:innen, Lehraufträge, Klassenbild.
 *
 * Zwei Leitplanken aus design.md:
 *  - D13 Schüler:innen sind app-eigene Datensätze, keine Nextcloud-Nutzer.
 *  - D11 Das Klassenbild ist ein frei anordenbares Kachelraster, ausdrücklich
 *        keine Raumgeometrie. Neue Klassen bekommen automatisch eine
 *        alphabetische Anordnung, damit ohne Pflege gearbeitet werden kann.
 */
class StammdatenService {

	public function __construct(
		private IDBConnection $db,
	) {
	}

	// ---------------------------------------------------------------- Schuljahr

	public function aktivesSchuljahr(): ?array {
		$q = $this->db->getQueryBuilder();
		$q->select('id', 'kennung', 'beginn', 'ende', 'aktiv')->from('kidseye_schuljahr')
			->where($q->expr()->eq('aktiv', $q->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->setMaxResults(1);
		$treffer = $q->executeQuery();
		$z = $treffer->fetch();
		$treffer->closeCursor();
		return $z === false ? null : [
			'id' => (int)$z['id'], 'kennung' => $z['kennung'],
			'beginn' => $z['beginn'], 'ende' => $z['ende'], 'aktiv' => true,
		];
	}

	public function schuljahrAnlegen(string $kennung, string $beginn, string $ende, bool $aktiv = false): int {
		if ($aktiv) {
			$q = $this->db->getQueryBuilder();
			$q->update('kidseye_schuljahr')
				->set('aktiv', $q->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
				->executeStatement();
		}
		$q = $this->db->getQueryBuilder();
		$q->insert('kidseye_schuljahr')->values([
			'kennung' => $q->createNamedParameter($kennung),
			'beginn' => $q->createNamedParameter($beginn),
			'ende' => $q->createNamedParameter($ende),
			'aktiv' => $q->createNamedParameter($aktiv, IQueryBuilder::PARAM_BOOL),
		])->executeStatement();
		return $q->getLastInsertId();
	}

	// ------------------------------------------------------------------- Klasse

	public function klasseAnlegen(int $schuljahrId, string $name, ?int $vorgaengerId = null): int {
		$q = $this->db->getQueryBuilder();
		$q->insert('kidseye_klasse')->values([
			'schuljahr_id' => $q->createNamedParameter($schuljahrId, IQueryBuilder::PARAM_INT),
			'name' => $q->createNamedParameter($name),
			'vorgaenger_id' => $q->createNamedParameter($vorgaengerId, IQueryBuilder::PARAM_INT),
		])->executeStatement();
		return $q->getLastInsertId();
	}

	public function klassen(int $schuljahrId): array {
		$q = $this->db->getQueryBuilder();
		$q->select('id', 'schuljahr_id', 'name', 'vorgaenger_id', 'sortierung')
			->from('kidseye_klasse')
			->where($q->expr()->eq('schuljahr_id', $q->createNamedParameter($schuljahrId, IQueryBuilder::PARAM_INT)))
			->orderBy('sortierung')->addOrderBy('name');
		$treffer = $q->executeQuery();
		$zeilen = [];
		while ($z = $treffer->fetch()) {
			$zeilen[] = [
				'id' => (int)$z['id'], 'schuljahrId' => (int)$z['schuljahr_id'],
				'name' => $z['name'],
				'vorgaengerId' => $z['vorgaenger_id'] !== null ? (int)$z['vorgaenger_id'] : null,
			];
		}
		$treffer->closeCursor();
		return $zeilen;
	}

	public function klasseNachId(int $id): ?array {
		$q = $this->db->getQueryBuilder();
		$q->select('k.id', 'k.name', 'k.schuljahr_id')->selectAlias('s.kennung', 'schuljahr')
			->from('kidseye_klasse', 'k')
			->innerJoin('k', 'kidseye_schuljahr', 's', 's.id = k.schuljahr_id')
			->where($q->expr()->eq('k.id', $q->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$treffer = $q->executeQuery();
		$z = $treffer->fetch();
		$treffer->closeCursor();
		return $z === false ? null : [
			'id' => (int)$z['id'], 'name' => $z['name'],
			'schuljahrId' => (int)$z['schuljahr_id'], 'schuljahr' => $z['schuljahr'],
		];
	}

	// ------------------------------------------------------------------ Schüler

	public function schuelerAnlegen(string $vorname, string $nachname, ?int $geburtsjahr = null): int {
		$q = $this->db->getQueryBuilder();
		$q->insert('kidseye_schueler')->values([
			'vorname' => $q->createNamedParameter($vorname),
			'nachname' => $q->createNamedParameter($nachname),
			'geburtsjahr' => $q->createNamedParameter($geburtsjahr, IQueryBuilder::PARAM_INT),
			'kuerzel' => $q->createNamedParameter($this->freiesKuerzel($vorname, $nachname)),
			'aktiv' => $q->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
		])->executeStatement();
		return $q->getLastInsertId();
	}

	public function inKlasse(int $klasseId, int $schuelerId): void {
		$q = $this->db->getQueryBuilder();
		$q->select('id')->from('kidseye_klassen_zug')
			->where($q->expr()->eq('klasse_id', $q->createNamedParameter($klasseId, IQueryBuilder::PARAM_INT)))
			->andWhere($q->expr()->eq('schueler_id', $q->createNamedParameter($schuelerId, IQueryBuilder::PARAM_INT)));
		$treffer = $q->executeQuery();
		$da = $treffer->fetchOne();
		$treffer->closeCursor();
		if ($da !== false) {
			return;
		}

		$q = $this->db->getQueryBuilder();
		$q->insert('kidseye_klassen_zug')->values([
			'klasse_id' => $q->createNamedParameter($klasseId, IQueryBuilder::PARAM_INT),
			'schueler_id' => $q->createNamedParameter($schuelerId, IQueryBuilder::PARAM_INT),
		])->executeStatement();

		$this->klassenbildErgaenzen($klasseId, $schuelerId);
	}

	/** Kinder einer Klasse in der Reihenfolge des Klassenbilds. */
	public function kinderDerKlasse(int $klasseId): array {
		$q = $this->db->getQueryBuilder();
		$q->select('s.id', 's.vorname', 's.nachname', 's.kuerzel')
			->selectAlias('b.position', 'position')
			->selectAlias('b.gruppe', 'gruppe')
			->from('kidseye_klassen_zug', 'z')
			->innerJoin('z', 'kidseye_schueler', 's', 's.id = z.schueler_id')
			->leftJoin('z', 'kidseye_klassenbild', 'b',
				'b.klasse_id = z.klasse_id AND b.schueler_id = z.schueler_id')
			->where($q->expr()->eq('z.klasse_id', $q->createNamedParameter($klasseId, IQueryBuilder::PARAM_INT)))
			->andWhere($q->expr()->isNull('s.geloescht_am'))
			->orderBy('b.position')->addOrderBy('s.nachname')->addOrderBy('s.vorname');

		$treffer = $q->executeQuery();
		$kinder = [];
		while ($z = $treffer->fetch()) {
			$kinder[] = [
				'id' => (int)$z['id'],
				'vorname' => $z['vorname'],
				'nachname' => $z['nachname'],
				'kuerzel' => $z['kuerzel'],
				'anzeige' => $z['vorname'] . ' ' . mb_substr($z['nachname'], 0, 1) . '.',
				'position' => $z['position'] !== null ? (int)$z['position'] : null,
				'gruppe' => $z['gruppe'],
			];
		}
		$treffer->closeCursor();
		return $kinder;
	}

	// -------------------------------------------------------------- Klassenbild

	/**
	 * Neue Kinder ans Ende des Klassenbilds. Damit ist ohne jede Pflege
	 * sofort arbeitsfähig — die Anordnung ist Komfort, keine Voraussetzung.
	 */
	public function klassenbildErgaenzen(int $klasseId, int $schuelerId): void {
		$q = $this->db->getQueryBuilder();
		$q->select($q->func()->max('position'))->from('kidseye_klassenbild')
			->where($q->expr()->eq('klasse_id', $q->createNamedParameter($klasseId, IQueryBuilder::PARAM_INT)));
		$treffer = $q->executeQuery();
		$max = $treffer->fetchOne();
		$treffer->closeCursor();

		$q = $this->db->getQueryBuilder();
		$q->insert('kidseye_klassenbild')->values([
			'klasse_id' => $q->createNamedParameter($klasseId, IQueryBuilder::PARAM_INT),
			'schueler_id' => $q->createNamedParameter($schuelerId, IQueryBuilder::PARAM_INT),
			'position' => $q->createNamedParameter(($max === false ? 0 : (int)$max) + 1, IQueryBuilder::PARAM_INT),
		])->executeStatement();
	}

	/** Alphabetische Voreinstellung erzeugen oder wiederherstellen. */
	public function klassenbildAlphabetisch(int $klasseId): void {
		$q = $this->db->getQueryBuilder();
		$q->select('s.id')->from('kidseye_klassen_zug', 'z')
			->innerJoin('z', 'kidseye_schueler', 's', 's.id = z.schueler_id')
			->where($q->expr()->eq('z.klasse_id', $q->createNamedParameter($klasseId, IQueryBuilder::PARAM_INT)))
			->orderBy('s.nachname')->addOrderBy('s.vorname');
		$treffer = $q->executeQuery();
		$position = 0;
		while ($id = $treffer->fetchOne()) {
			$this->klassenbildSetzen($klasseId, (int)$id, ++$position, null);
		}
		$treffer->closeCursor();
	}

	/**
	 * Anordnung ändern. Bestehende Beobachtungen bleiben unberührt — das
	 * Klassenbild ist reine Darstellung.
	 *
	 * @param array $eintraege Liste aus ['schuelerId'=>int,'position'=>int,'gruppe'=>?string]
	 */
	public function klassenbildSpeichern(int $klasseId, array $eintraege): void {
		$this->db->beginTransaction();
		try {
			foreach ($eintraege as $e) {
				$this->klassenbildSetzen(
					$klasseId, (int)$e['schuelerId'], (int)$e['position'], $e['gruppe'] ?? null
				);
			}
			$this->db->commit();
		} catch (\Throwable $ex) {
			$this->db->rollBack();
			throw $ex;
		}
	}

	private function klassenbildSetzen(int $klasseId, int $schuelerId, int $position, ?string $gruppe): void {
		$q = $this->db->getQueryBuilder();
		$q->update('kidseye_klassenbild')
			->set('position', $q->createNamedParameter($position, IQueryBuilder::PARAM_INT))
			->set('gruppe', $q->createNamedParameter($gruppe))
			->where($q->expr()->eq('klasse_id', $q->createNamedParameter($klasseId, IQueryBuilder::PARAM_INT)))
			->andWhere($q->expr()->eq('schueler_id', $q->createNamedParameter($schuelerId, IQueryBuilder::PARAM_INT)));
		if ($q->executeStatement() === 0) {
			$q = $this->db->getQueryBuilder();
			$q->insert('kidseye_klassenbild')->values([
				'klasse_id' => $q->createNamedParameter($klasseId, IQueryBuilder::PARAM_INT),
				'schueler_id' => $q->createNamedParameter($schuelerId, IQueryBuilder::PARAM_INT),
				'position' => $q->createNamedParameter($position, IQueryBuilder::PARAM_INT),
				'gruppe' => $q->createNamedParameter($gruppe),
			])->executeStatement();
		}
	}

	// ------------------------------------------------------------- Lehrauftrag

	public function lehrauftragAnlegen(string $nutzerId, int $klasseId, int $kontextId, bool $klassenlehrkraft = false): int {
		$q = $this->db->getQueryBuilder();
		$q->insert('kidseye_lehrauftrag')->values([
			'nutzer_id' => $q->createNamedParameter($nutzerId),
			'klasse_id' => $q->createNamedParameter($klasseId, IQueryBuilder::PARAM_INT),
			'kontext_id' => $q->createNamedParameter($kontextId, IQueryBuilder::PARAM_INT),
			'klassenlehrkraft' => $q->createNamedParameter($klassenlehrkraft, IQueryBuilder::PARAM_BOOL),
		])->executeStatement();
		return $q->getLastInsertId();
	}

	/** Alle Kombinationen aus Klasse und Kontext, die diese Lehrkraft hat. */
	public function lehrauftraege(string $nutzerId, ?int $schuljahrId = null): array {
		$q = $this->db->getQueryBuilder();
		$q->select('l.id', 'l.klasse_id', 'l.kontext_id', 'l.klassenlehrkraft')
			->selectAlias('k.name', 'klasse')
			->selectAlias('c.name', 'kontext')
			->selectAlias('c.art', 'kontext_art')
			->selectAlias('c.fach_kennung', 'fach')
			->from('kidseye_lehrauftrag', 'l')
			->innerJoin('l', 'kidseye_klasse', 'k', 'k.id = l.klasse_id')
			->innerJoin('l', 'kidseye_kontext', 'c', 'c.id = l.kontext_id')
			->where($q->expr()->eq('l.nutzer_id', $q->createNamedParameter($nutzerId)))
			->andWhere($q->expr()->eq('c.aktiv', $q->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->orderBy('k.name')->addOrderBy('c.sortierung');

		if ($schuljahrId !== null) {
			$q->andWhere($q->expr()->eq('k.schuljahr_id', $q->createNamedParameter($schuljahrId, IQueryBuilder::PARAM_INT)));
		}

		$treffer = $q->executeQuery();
		$zeilen = [];
		while ($z = $treffer->fetch()) {
			$zeilen[] = [
				'id' => (int)$z['id'],
				'klasseId' => (int)$z['klasse_id'],
				'klasse' => $z['klasse'],
				'kontextId' => (int)$z['kontext_id'],
				'kontext' => $z['kontext'],
				'kontextArt' => $z['kontext_art'],
				'fach' => $z['fach'],
				'klassenlehrkraft' => (bool)$z['klassenlehrkraft'],
			];
		}
		$treffer->closeCursor();
		return $zeilen;
	}

	/**
	 * Liegt überhaupt ein Lehrauftrag vor?
	 *
	 * Der Schritt, den man bei der Einrichtung am leichtesten vergisst: ohne
	 * Lehrauftrag lässt sich keine Stunde starten, und der Startdialog zeigt
	 * keine Klassen. Für den Einrichtungsstand (DiagnoseService).
	 */
	public function lehrauftraegeVorhanden(): bool {
		$q = $this->db->getQueryBuilder();
		$q->select('id')->from('kidseye_lehrauftrag')->setMaxResults(1);
		$treffer = $q->executeQuery();
		$da = $treffer->fetchOne();
		$treffer->closeCursor();
		return $da !== false;
	}

	public function hatLehrauftrag(string $nutzerId, int $klasseId, ?int $kontextId = null): bool {
		$q = $this->db->getQueryBuilder();
		$q->select('id')->from('kidseye_lehrauftrag')
			->where($q->expr()->eq('nutzer_id', $q->createNamedParameter($nutzerId)))
			->andWhere($q->expr()->eq('klasse_id', $q->createNamedParameter($klasseId, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		if ($kontextId !== null) {
			$q->andWhere($q->expr()->eq('kontext_id', $q->createNamedParameter($kontextId, IQueryBuilder::PARAM_INT)));
		}
		$treffer = $q->executeQuery();
		$da = $treffer->fetchOne();
		$treffer->closeCursor();
		return $da !== false;
	}

	/**
	 * Alle Kinder, an die eine Lehrkraft über einen Lehrauftrag herankommt.
	 * Grundlage für die Gastkind-Suche im Erfassungsbildschirm (5.16).
	 */
	public function erreichbareKinder(string $nutzerId, ?string $suche = null): array {
		$q = $this->db->getQueryBuilder();
		$q->selectDistinct(['s.id', 's.vorname', 's.nachname', 's.kuerzel', 'k.name'])
			->from('kidseye_lehrauftrag', 'l')
			->innerJoin('l', 'kidseye_klasse', 'k', 'k.id = l.klasse_id')
			->innerJoin('l', 'kidseye_klassen_zug', 'z', 'z.klasse_id = l.klasse_id')
			->innerJoin('z', 'kidseye_schueler', 's', 's.id = z.schueler_id')
			->where($q->expr()->eq('l.nutzer_id', $q->createNamedParameter($nutzerId)))
			->andWhere($q->expr()->isNull('s.geloescht_am'))
			->orderBy('s.nachname')->addOrderBy('s.vorname');

		if ($suche !== null && trim($suche) !== '') {
			$muster = '%' . $this->db->escapeLikeParameter(trim($suche)) . '%';
			$q->andWhere($q->expr()->orX(
				$q->expr()->iLike('s.vorname', $q->createNamedParameter($muster)),
				$q->expr()->iLike('s.nachname', $q->createNamedParameter($muster))
			));
		}

		$treffer = $q->executeQuery();
		$zeilen = [];
		while ($z = $treffer->fetch()) {
			$zeilen[] = [
				'id' => (int)$z['id'],
				'vorname' => $z['vorname'],
				'nachname' => $z['nachname'],
				'kuerzel' => $z['kuerzel'],
				'anzeige' => $z['vorname'] . ' ' . mb_substr($z['nachname'], 0, 1) . '.',
				'klasse' => $z['name'],
			];
		}
		$treffer->closeCursor();
		return $zeilen;
	}

	// ---------------------------------------------------------------- Rollover

	/**
	 * Vorschlag für den Schuljahreswechsel: je Klasse eine Folgeklasse mit
	 * den übernommenen Kindern. Schreibt nichts — die Übernahme ist je Kind
	 * bestätigbar oder abwählbar.
	 */
	public function rolloverVorschlag(int $vonSchuljahrId): array {
		$vorschlag = [];
		foreach ($this->klassen($vonSchuljahrId) as $klasse) {
			$vorschlag[] = [
				'vonKlasseId' => $klasse['id'],
				'vonName' => $klasse['name'],
				'nachName' => $this->naechsterKlassenname($klasse['name']),
				'kinder' => array_map(
					static fn ($k) => ['id' => $k['id'], 'anzeige' => $k['anzeige'], 'uebernehmen' => true],
					$this->kinderDerKlasse($klasse['id'])
				),
			];
		}
		return $vorschlag;
	}

	/** "3a" → "4a". Bleibt der Name unerkennbar, wird er unverändert übernommen. */
	public function naechsterKlassenname(string $name): string {
		if (preg_match('/^(\d+)(.*)$/u', $name, $t) === 1) {
			return ((int)$t[1] + 1) . $t[2];
		}
		return $name;
	}

	/**
	 * Führt den Rollover aus. Beobachtungen bleiben am Kind hängen und
	 * überleben den Klassenwechsel — sie hängen nie an der Klasse.
	 *
	 * @param array $bestaetigt Liste aus
	 *        ['vonKlasseId'=>int,'nachName'=>string,'schuelerIds'=>int[]]
	 */
	public function rolloverAusfuehren(int $nachSchuljahrId, array $bestaetigt): array {
		$angelegt = [];
		$this->db->beginTransaction();
		try {
			foreach ($bestaetigt as $eintrag) {
				$neueKlasseId = $this->klasseAnlegen(
					$nachSchuljahrId, $eintrag['nachName'], (int)$eintrag['vonKlasseId']
				);
				foreach ($eintrag['schuelerIds'] as $schuelerId) {
					$this->inKlasse($neueKlasseId, (int)$schuelerId);
				}
				$this->klassenbildAlphabetisch($neueKlasseId);
				$angelegt[] = ['klasseId' => $neueKlasseId, 'name' => $eintrag['nachName'],
					'kinder' => count($eintrag['schuelerIds'])];
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
		return $angelegt;
	}

	// ---------------------------------------------------------------- Intern

	/** Kürzel für die Ablagepfade im Gruppenordner (D8), eindeutig gehalten. */
	private function freiesKuerzel(string $vorname, string $nachname): string {
		$basis = $this->slug($vorname . '-' . mb_substr($nachname, 0, 1));
		$kandidat = $basis;
		$n = 1;
		while ($this->kuerzelVergeben($kandidat)) {
			$kandidat = $basis . '-' . (++$n);
		}
		return $kandidat;
	}

	private function kuerzelVergeben(string $kuerzel): bool {
		$q = $this->db->getQueryBuilder();
		$q->select('id')->from('kidseye_schueler')
			->where($q->expr()->eq('kuerzel', $q->createNamedParameter($kuerzel)))
			->setMaxResults(1);
		$treffer = $q->executeQuery();
		$da = $treffer->fetchOne();
		$treffer->closeCursor();
		return $da !== false;
	}

	private function slug(string $text): string {
		$text = mb_strtolower($text);
		$text = strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
		$text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
		return trim($text, '-') ?: 'kind';
	}
}

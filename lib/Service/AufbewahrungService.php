<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCA\KidsEye\AppInfo\Application;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;

/**
 * Aufbewahrung, Schuljahresende-Ritual und Löschung (Kapitel 7.6–7.10).
 *
 * Rechtlicher Rahmen in Hessen — Verordnung über die Verarbeitung
 * personenbezogener Daten in Schulen (SchDSV) vom 4. Februar 2009:
 *
 *   § 10 Abs. 1  „In Schulen sind personenbezogene Daten nur so lange
 *                aufzubewahren, wie sie für die Erfüllung des Bildungs- und
 *                Erziehungsauftrags […] erforderlich sind. Die Aufbewahrungs-
 *                fristen richten sich nach Anlage 3."
 *
 *   § 10 Abs. 3  „[…] sind zu löschen, wenn ihre Kenntnis für die
 *                Aufgabenerfüllung nicht mehr erforderlich ist, spätestens
 *                jedoch ein Jahr nach dem Ende des jeweiligen Schuljahres."
 *                → Grundlage der Voreinstellung für die Stufe `privat`.
 *
 *   § 3 Abs. 2   „Nach Ende des Datenverarbeitungsvorgangs sind alle für die
 *                Schüler- oder die Schulaktenführung relevanten Daten
 *                unverzüglich zu diesen Akten zu nehmen."
 *                → entspricht dem Übergang privat → akte aus D7.
 *
 *   § 10 Abs. 4  Abgelaufene Unterlagen werden erst nach Abstimmung mit dem
 *                Staatsarchiv vernichtet. Deshalb hat `akte` bewusst KEINE
 *                automatische Frist.
 *
 * Trotz dieser Grundlage legt kidseye nichts normativ fest: § 10 Abs. 3
 * spricht von privaten Geräten der Lehrkraft, kidseye läuft auf dem Server
 * der Schule. Die Übertragbarkeit und die Fristen nach Anlage 3 sind mit der
 * schulischen Datenschutzbeauftragung (§ 11 SchDSV) zu klären.
 *
 * Nichts wird automatisch gelöscht: eine abgelaufene Frist kennzeichnet nur,
 * gelöscht wird erst nach ausdrücklicher Bestätigung.
 */
class AufbewahrungService {

	public const HINWEIS = 'Voreinstellung ohne Rechtsverbindlichkeit. Sie orientiert sich '
		. 'an § 10 Abs. 3 der hessischen Schul-Datenschutzverordnung (SchDSV), der eine '
		. 'Löschung spätestens ein Jahr nach Ende des Schuljahres vorsieht. Die Übertragbarkeit '
		. 'auf den Schulserver und die Fristen nach Anlage 3 SchDSV sind vor dem Produktivbetrieb '
		. 'mit der schulischen Datenschutzbeauftragung abzustimmen.';

	public const RECHTSGRUNDLAGE = [
		'verordnung' => 'Verordnung über die Verarbeitung personenbezogener Daten in Schulen '
			. 'und statistische Erhebungen an Schulen (SchDSV) vom 4. Februar 2009',
		'privat' => '§ 10 Abs. 3 SchDSV — spätestens ein Jahr nach Ende des Schuljahres',
		'akte' => '§ 10 Abs. 1 und 4 SchDSV — Fristen nach Anlage 3, Vernichtung erst nach '
			. 'Abstimmung mit dem Staatsarchiv; deshalb keine automatische Frist',
		'uebergang' => '§ 3 Abs. 2 Satz 2 SchDSV — aktenrelevante Daten sind unverzüglich '
			. 'zur Schülerakte zu nehmen',
		'einsicht' => '§ 72 Abs. 5 HSchG in Verbindung mit § 1 Abs. 7 SchDSV',
	];

	/**
	 * Ausgangswerte in Monaten.
	 *
	 * `privat` = 12 folgt § 10 Abs. 3 SchDSV. `akte` = 0 bedeutet ausdrücklich
	 * keine automatische Frist, weil Anlage 3 und die Abstimmung mit dem
	 * Staatsarchiv vorgehen. `klassenteam` hat keine unmittelbare Entsprechung
	 * in der Verordnung und ist eine Festlegung der Schule.
	 */
	private const VORGABE = [
		ZugriffService::SICHT_PRIVAT => 12,
		ZugriffService::SICHT_KLASSENTEAM => 24,
		ZugriffService::SICHT_AKTE => 0,
	];

	public function __construct(
		private IDBConnection $db,
		private IAppConfig $config,
		private BeobachtungService $beobachtungen,
		private ProtokollService $protokoll,
		private AblageService $ablage,
	) {
	}

	public function fristen(): array {
		$fristen = [];
		foreach (self::VORGABE as $stufe => $vorgabe) {
			$fristen[$stufe] = $this->config->getValueInt(
				Application::APP_ID, 'frist_' . $stufe, $vorgabe
			);
		}
		return [
			'monate' => $fristen,
			'hinweis' => self::HINWEIS,
			'rechtsgrundlage' => self::RECHTSGRUNDLAGE,
		];
	}

	public function setzeFrist(string $stufe, int $monate): void {
		if (!in_array($stufe, ZugriffService::STUFEN_ALLE, true)) {
			throw new \InvalidArgumentException('Unbekannte Sichtbarkeitsstufe: ' . $stufe);
		}
		$this->config->setValueInt(Application::APP_ID, 'frist_' . $stufe, max(0, $monate));
	}

	/**
	 * Trägt für alle Beobachtungen ohne Löschdatum das Ablaufdatum nach.
	 * Läuft idempotent und kann jederzeit erneut aufgerufen werden.
	 */
	public function fristenNachtragen(): int {
		$fristen = $this->fristen()['monate'];
		$geaendert = 0;

		foreach ($fristen as $stufe => $monate) {
			if ($monate <= 0) {
				continue;
			}
			$q = $this->db->getQueryBuilder();
			$q->update('kidseye_beobachtung')
				->set('loeschen_ab', $q->createNamedParameter(
					// SQL-neutral: in PHP gerechnet, deshalb je Stufe ein Lauf
					(new \DateTime())->modify('+' . $monate . ' months')->format('Y-m-d')
				))
				->where($q->expr()->eq('sichtbarkeit', $q->createNamedParameter($stufe)))
				->andWhere($q->expr()->isNull('loeschen_ab'))
				->andWhere($q->expr()->isNull('geloescht_am'));
			$geaendert += $q->executeStatement();
		}
		return $geaendert;
	}

	/**
	 * Beobachtungen, deren Frist abgelaufen ist. Nur Kennzeichnung —
	 * gelöscht wird erst nach Bestätigung (7.7).
	 */
	public function faellig(string $nutzerId): array {
		$q = $this->db->getQueryBuilder();
		$this->beobachtungen->grundAbfrage($q);
		$q->andWhere($q->expr()->eq('b.nutzer_id', $q->createNamedParameter($nutzerId)))
			->andWhere($q->expr()->isNotNull('b.loeschen_ab'))
			->andWhere($q->expr()->lte('b.loeschen_ab',
				$q->createNamedParameter((new \DateTime())->format('Y-m-d'))))
			->andWhere($q->expr()->isNull('b.geloescht_am'))
			->orderBy('b.loeschen_ab');
		return $this->beobachtungen->hole($q);
	}

	/**
	 * Schuljahresende-Ritual (7.8): private Rohbeobachtungen des abgelaufenen
	 * Schuljahres zur Löschung vorschlagen. Einträge der Stufen `klassenteam`
	 * und `akte` sind ausdrücklich nicht vorausgewählt.
	 */
	public function schuljahresendeVorschlag(string $nutzerId, string $bis): array {
		$q = $this->db->getQueryBuilder();
		$this->beobachtungen->grundAbfrage($q);
		$q->andWhere($q->expr()->eq('b.nutzer_id', $q->createNamedParameter($nutzerId)))
			->andWhere($q->expr()->lt('b.erfasst_am', $q->createNamedParameter($bis)))
			->andWhere($q->expr()->isNull('b.geloescht_am'))
			->orderBy('b.erfasst_am');
		$alle = $this->beobachtungen->hole($q);

		$vorschlag = [];
		foreach ($alle as $b) {
			$privat = $b['sichtbarkeit'] === ZugriffService::SICHT_PRIVAT;
			$vorschlag[] = [
				'id' => $b['id'],
				'kind' => $b['kind'],
				'erfasstAm' => $b['erfasstAm'],
				'sichtbarkeit' => $b['sichtbarkeit'],
				'text' => $b['text'] ?? $b['markerText'],
				// Nur private Rohnotizen sind vorausgewählt
				'vorausgewaehlt' => $privat,
			];
		}
		return [
			'eintraege' => $vorschlag,
			'vorausgewaehlt' => count(array_filter($vorschlag, static fn ($v) => $v['vorausgewaehlt'])),
			'hinweis' => self::HINWEIS,
		];
	}

	/**
	 * Löscht Beobachtungen samt der zugehörigen Dateien, sofern diese von
	 * keiner anderen Beobachtung referenziert werden (7.10).
	 *
	 * @param int[] $beobachtungIds
	 */
	public function loeschen(string $nutzerId, array $beobachtungIds): array {
		$ids = array_values(array_filter(array_map('intval', $beobachtungIds)));
		if ($ids === []) {
			return ['geloescht' => 0, 'dateien' => 0];
		}

		$q = $this->db->getQueryBuilder();
		$q->select('id', 'schueler_id', 'sichtbarkeit')->from('kidseye_beobachtung')
			->where($q->expr()->in('id', $q->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($q->expr()->eq('nutzer_id', $q->createNamedParameter($nutzerId)))
			->andWhere($q->expr()->isNull('geloescht_am'));
		$treffer = $q->executeQuery();
		$zuLoeschen = [];
		while ($z = $treffer->fetch()) {
			$zuLoeschen[(int)$z['id']] = [
				'schuelerId' => (int)$z['schueler_id'],
				'sichtbarkeit' => $z['sichtbarkeit'],
			];
		}
		$treffer->closeCursor();

		if ($zuLoeschen === []) {
			return ['geloescht' => 0, 'dateien' => 0];
		}

		$dateien = $this->verwaisteDateien(array_keys($zuLoeschen));
		$jetzt = (new \DateTime())->format('Y-m-d H:i:s');

		$q = $this->db->getQueryBuilder();
		$q->update('kidseye_beobachtung')
			->set('geloescht_am', $q->createNamedParameter($jetzt))
			->where($q->expr()->in('id',
				$q->createNamedParameter(array_keys($zuLoeschen), IQueryBuilder::PARAM_INT_ARRAY)))
			->executeStatement();

		$entfernt = 0;
		foreach ($dateien as $fileId) {
			if ($this->ablage->entferne($nutzerId, $fileId)) {
				$entfernt++;
			}
		}

		foreach ($zuLoeschen as $id => $angabe) {
			$this->protokoll->anfuegen(
				$nutzerId, ProtokollService::AKTION_LOESCHUNG,
				$id, $angabe['schuelerId'], $angabe['sichtbarkeit'], 'geloescht'
			);
		}

		return ['geloescht' => count($zuLoeschen), 'dateien' => $entfernt];
	}

	/**
	 * Datei-IDs, die nach dem Löschen dieser Beobachtungen von keiner
	 * weiteren Beobachtung mehr referenziert werden.
	 *
	 * @param int[] $beobachtungIds
	 * @return int[]
	 */
	private function verwaisteDateien(array $beobachtungIds): array {
		$q = $this->db->getQueryBuilder();
		$q->selectDistinct('file_id')->from('kidseye_beob_datei')
			->where($q->expr()->in('beobachtung_id',
				$q->createNamedParameter($beobachtungIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$treffer = $q->executeQuery();
		$kandidaten = array_map('intval', $treffer->fetchAll(\PDO::FETCH_COLUMN) ?: []);
		$treffer->closeCursor();

		if ($kandidaten === []) {
			return [];
		}

		$q = $this->db->getQueryBuilder();
		$q->selectDistinct('file_id')->from('kidseye_beob_datei')
			->where($q->expr()->in('file_id',
				$q->createNamedParameter($kandidaten, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($q->expr()->notIn('beobachtung_id',
				$q->createNamedParameter($beobachtungIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$treffer = $q->executeQuery();
		$nochBenutzt = array_map('intval', $treffer->fetchAll(\PDO::FETCH_COLUMN) ?: []);
		$treffer->closeCursor();

		return array_values(array_diff($kandidaten, $nochBenutzt));
	}
}

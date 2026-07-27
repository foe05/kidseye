<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Schülerimport aus CSV mit Vorschau und Dublettenerkennung (Kapitel 3.4).
 *
 * Der Import schreibt nie ohne ausdrückliche Bestätigung: `vorschau()` liefert
 * die erkannten Datensätze samt Dubletten und Fehlern, `uebernehmen()` schreibt
 * erst danach.
 */
class CsvImportService {

	private const SPALTEN = ['vorname', 'nachname', 'klasse', 'geburtsjahr'];

	public function __construct(
		private IDBConnection $db,
		private StammdatenService $stammdaten,
	) {
	}

	/**
	 * @return array{zeilen:array, neu:int, dublette:int, fehler:int, spalten:array}
	 */
	public function vorschau(string $csv, int $schuljahrId): array {
		[$kopf, $daten] = $this->zerlege($csv);
		if ($kopf === []) {
			return ['zeilen' => [], 'neu' => 0, 'dublette' => 0, 'fehler' => 1,
				'spalten' => [], 'meldung' => 'Die Datei enthält keine Kopfzeile.'];
		}

		$fehlend = array_diff(['vorname', 'nachname', 'klasse'], $kopf);
		if ($fehlend !== []) {
			return ['zeilen' => [], 'neu' => 0, 'dublette' => 0, 'fehler' => 1,
				'spalten' => $kopf,
				'meldung' => 'Es fehlen die Spalten: ' . implode(', ', $fehlend)
					. '. Erwartet werden: ' . implode(', ', self::SPALTEN) . '.'];
		}

		$klassenNachName = [];
		foreach ($this->stammdaten->klassen($schuljahrId) as $k) {
			$klassenNachName[mb_strtolower($k['name'])] = $k['id'];
		}

		$zeilen = [];
		$zaehler = ['neu' => 0, 'dublette' => 0, 'fehler' => 0];

		foreach ($daten as $nr => $satz) {
			$vorname = trim($satz['vorname'] ?? '');
			$nachname = trim($satz['nachname'] ?? '');
			$klasse = trim($satz['klasse'] ?? '');
			$jahr = trim($satz['geburtsjahr'] ?? '');

			$eintrag = [
				'zeile' => $nr + 2, // Kopfzeile plus Null-Index
				'vorname' => $vorname,
				'nachname' => $nachname,
				'klasse' => $klasse,
				'geburtsjahr' => $jahr === '' ? null : (int)$jahr,
			];

			if ($vorname === '' || $nachname === '' || $klasse === '') {
				$eintrag['status'] = 'fehler';
				$eintrag['hinweis'] = 'Vorname, Nachname und Klasse sind Pflicht.';
				$zaehler['fehler']++;
				$zeilen[] = $eintrag;
				continue;
			}

			$klasseId = $klassenNachName[mb_strtolower($klasse)] ?? null;
			$eintrag['klasseId'] = $klasseId;
			$eintrag['klasseNeu'] = $klasseId === null;

			$vorhandenId = $klasseId === null
				? null
				: $this->kindInKlasse($klasseId, $vorname, $nachname);

			if ($vorhandenId !== null) {
				$eintrag['status'] = 'dublette';
				$eintrag['schuelerId'] = $vorhandenId;
				$eintrag['hinweis'] = 'Dieses Kind ist in „' . $klasse
					. '" bereits erfasst; es wird kein zweiter Datensatz angelegt.';
				$zaehler['dublette']++;
			} else {
				$eintrag['status'] = 'neu';
				$eintrag['hinweis'] = $klasseId === null
					? 'Die Klasse „' . $klasse . '" wird neu angelegt.'
					: null;
				$zaehler['neu']++;
			}
			$zeilen[] = $eintrag;
		}

		return [
			'zeilen' => $zeilen,
			'neu' => $zaehler['neu'],
			'dublette' => $zaehler['dublette'],
			'fehler' => $zaehler['fehler'],
			'spalten' => $kopf,
			'meldung' => null,
		];
	}

	/**
	 * Übernimmt die bestätigten Zeilen. Dubletten und Fehlerzeilen werden
	 * übersprungen — sie legen ausdrücklich keinen zweiten Datensatz an.
	 */
	public function uebernehmen(string $csv, int $schuljahrId): array {
		$vorschau = $this->vorschau($csv, $schuljahrId);
		if ($vorschau['meldung'] !== null) {
			throw new \RuntimeException($vorschau['meldung']);
		}

		$angelegt = 0;
		$klassenAngelegt = [];

		$this->db->beginTransaction();
		try {
			$klassenNachName = [];
			foreach ($this->stammdaten->klassen($schuljahrId) as $k) {
				$klassenNachName[mb_strtolower($k['name'])] = $k['id'];
			}

			foreach ($vorschau['zeilen'] as $zeile) {
				if ($zeile['status'] !== 'neu') {
					continue;
				}
				$schluessel = mb_strtolower($zeile['klasse']);
				if (!isset($klassenNachName[$schluessel])) {
					$klassenNachName[$schluessel] =
						$this->stammdaten->klasseAnlegen($schuljahrId, $zeile['klasse']);
					$klassenAngelegt[] = $zeile['klasse'];
				}
				$schuelerId = $this->stammdaten->schuelerAnlegen(
					$zeile['vorname'], $zeile['nachname'], $zeile['geburtsjahr']
				);
				$this->stammdaten->inKlasse($klassenNachName[$schluessel], $schuelerId);
				$angelegt++;
			}

			foreach (array_unique(array_values($klassenNachName)) as $klasseId) {
				$this->stammdaten->klassenbildAlphabetisch((int)$klasseId);
			}

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		return [
			'angelegt' => $angelegt,
			'uebersprungen' => $vorschau['dublette'] + $vorschau['fehler'],
			'klassenAngelegt' => array_values(array_unique($klassenAngelegt)),
		];
	}

	/** @return array{0:string[], 1:array<int,array<string,string>>} */
	private function zerlege(string $csv): array {
		$csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
		$zeilen = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
		if ($zeilen === [] || trim($zeilen[0]) === '') {
			return [[], []];
		}

		$trenner = $this->trennzeichen($zeilen[0]);
		$kopf = array_map(
			static fn ($s) => mb_strtolower(trim($s, " \t\"'")),
			str_getcsv($zeilen[0], $trenner)
		);

		$daten = [];
		foreach (array_slice($zeilen, 1) as $zeile) {
			if (trim($zeile) === '') {
				continue;
			}
			$werte = str_getcsv($zeile, $trenner);
			$satz = [];
			foreach ($kopf as $i => $name) {
				$satz[$name] = $werte[$i] ?? '';
			}
			$daten[] = $satz;
		}
		return [$kopf, $daten];
	}

	/** Semikolon ist in deutschen Tabellenprogrammen der Normalfall. */
	private function trennzeichen(string $kopfzeile): string {
		return substr_count($kopfzeile, ';') >= substr_count($kopfzeile, ',') ? ';' : ',';
	}

	private function kindInKlasse(int $klasseId, string $vorname, string $nachname): ?int {
		$q = $this->db->getQueryBuilder();
		$q->select('s.id')->from('kidseye_klassen_zug', 'z')
			->innerJoin('z', 'kidseye_schueler', 's', 's.id = z.schueler_id')
			->where($q->expr()->eq('z.klasse_id', $q->createNamedParameter($klasseId, IQueryBuilder::PARAM_INT)))
			->andWhere($q->expr()->eq(
				$q->func()->lower('s.vorname'), $q->createNamedParameter(mb_strtolower($vorname))
			))
			->andWhere($q->expr()->eq(
				$q->func()->lower('s.nachname'), $q->createNamedParameter(mb_strtolower($nachname))
			))
			->setMaxResults(1);
		$treffer = $q->executeQuery();
		$id = $treffer->fetchOne();
		$treffer->closeCursor();
		return $id === false ? null : (int)$id;
	}
}

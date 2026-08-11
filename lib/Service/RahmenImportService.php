<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCA\KidsEye\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Importiert einen Kompetenzrahmen aus dem dokumentierten JSON-Format.
 *
 * Grundsatz aus design.md D5: Rahmen sind Daten, nicht Code. Ein Rahmen für ein
 * anderes Bundesland oder ein schuleigenes Raster ist ein Import, keine Änderung
 * an dieser Klasse.
 *
 * Der Import ist alles-oder-nichts: bei einem Validierungsfehler wird nichts
 * geschrieben und der Fehler nennt die betroffenen Knoten.
 */
class RahmenImportService {

	private const ARTEN = [
		'bereich', 'dimension', 'kompetenzbereich',
		'bildungsstandard', 'inhaltsfeld', 'leitstruktur',
	];
	private const EBENEN = ['ueberfachlich', 'fachlich'];
	private const BEZUGSSTUFEN = ['jgst_2', 'jgst_4'];

	public function __construct(
		private IDBConnection $db,
	) {
	}

	/**
	 * @param array $daten Dekodierter Inhalt einer Rahmendatei
	 * @return array{versionId:int, knoten:int, korrespondenzen:int}
	 * @throws RahmenImportFehler
	 */
	public function importiere(array $daten): array {
		$fehler = $this->pruefe($daten);
		if ($fehler !== []) {
			throw new RahmenImportFehler($fehler);
		}

		$this->db->beginTransaction();
		try {
			$rahmenId = $this->rahmenSichern($daten['rahmen']);
			$versionId = $this->versionAnlegen($rahmenId, $daten['version']);

			// Zwei Durchgänge: erst alle Knoten, dann die Elternbezüge und
			// Korrespondenzen — sonst müsste die Datei topologisch sortiert sein.
			$idNachKennung = [];
			foreach ($daten['knoten'] as $knoten) {
				$idNachKennung[$knoten['kennung']] = $this->knotenAnlegen($versionId, $knoten);
			}
			foreach ($daten['knoten'] as $knoten) {
				if (isset($knoten['eltern'])) {
					$this->elternSetzen(
						$idNachKennung[$knoten['kennung']],
						$idNachKennung[$knoten['eltern']]
					);
				}
			}

			$korr = 0;
			foreach ($daten['korrespondenzen'] ?? [] as $paar) {
				$this->korrespondenzAnlegen(
					$idNachKennung[$paar['von']],
					$idNachKennung[$paar['nach']]
				);
				$korr++;
			}

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		return [
			'versionId' => $versionId,
			'knoten' => count($daten['knoten']),
			'korrespondenzen' => $korr,
		];
	}

	/**
	 * Vollständige Prüfung vor dem ersten Schreibzugriff.
	 *
	 * @return string[] Leere Liste bedeutet gültig.
	 */
	public function pruefe(array $daten): array {
		$fehler = [];

		foreach (['rahmen', 'version', 'knoten'] as $pflicht) {
			if (!isset($daten[$pflicht])) {
				$fehler[] = "Abschnitt '$pflicht' fehlt.";
			}
		}
		if ($fehler !== []) {
			return $fehler;
		}

		$faecher = [];
		foreach ($daten['faecher'] ?? [] as $fach) {
			$faecher[$fach['kennung']] = true;
		}

		$kennungen = [];
		foreach ($daten['knoten'] as $i => $k) {
			$wo = $k['kennung'] ?? "Knoten #$i";

			if (!isset($k['kennung'], $k['art'], $k['ebene'], $k['bezeichnung'])) {
				$fehler[] = "$wo: kennung, art, ebene und bezeichnung sind Pflicht.";
				continue;
			}
			if (isset($kennungen[$k['kennung']])) {
				$fehler[] = "$wo: Kennung kommt mehrfach vor.";
			}
			$kennungen[$k['kennung']] = $i;

			if (!in_array($k['art'], self::ARTEN, true)) {
				$fehler[] = "$wo: unbekannte Art '{$k['art']}'.";
			}
			if (!in_array($k['ebene'], self::EBENEN, true)) {
				$fehler[] = "$wo: unbekannte Ebene '{$k['ebene']}'.";
			}
			if (isset($k['bezugsstufe']) && !in_array($k['bezugsstufe'], self::BEZUGSSTUFEN, true)) {
				$fehler[] = "$wo: unbekannte Bezugsstufe '{$k['bezugsstufe']}'.";
			}

			// D5/D2: überfachliche Knoten sind fachneutral, fachliche brauchen ein Fach.
			if ($k['ebene'] === 'ueberfachlich' && isset($k['fach'])) {
				$fehler[] = "$wo: überfachliche Knoten dürfen kein Fach tragen.";
			}
			if ($k['ebene'] === 'fachlich') {
				if (!isset($k['fach'])) {
					$fehler[] = "$wo: fachliche Knoten brauchen ein Fach.";
				} elseif (!isset($faecher[$k['fach']])) {
					$fehler[] = "$wo: Fach '{$k['fach']}' ist nicht in 'faecher' deklariert.";
				}
			}
		}

		foreach ($daten['knoten'] as $k) {
			if (isset($k['eltern']) && !isset($kennungen[$k['eltern']])) {
				$fehler[] = "{$k['kennung']}: Elternknoten '{$k['eltern']}' existiert nicht.";
			}
		}

		foreach ($this->zyklen($daten['knoten']) as $kennung) {
			$fehler[] = "$kennung: Zyklus im Knotenbaum.";
		}

		foreach ($daten['korrespondenzen'] ?? [] as $i => $paar) {
			foreach (['von', 'nach'] as $seite) {
				if (!isset($paar[$seite]) || !isset($kennungen[$paar[$seite]])) {
					$fehler[] = "Korrespondenz #$i: '$seite' verweist ins Leere.";
				}
			}
		}

		return $fehler;
	}

	/**
	 * @param array[] $knoten
	 * @return string[] Kennungen, die in einem Zyklus liegen
	 */
	private function zyklen(array $knoten): array {
		$eltern = [];
		foreach ($knoten as $k) {
			if (isset($k['kennung'], $k['eltern'])) {
				$eltern[$k['kennung']] = $k['eltern'];
			}
		}

		$betroffen = [];
		foreach (array_keys($eltern) as $start) {
			$gesehen = [];
			$aktuell = $start;
			while (isset($eltern[$aktuell])) {
				if (isset($gesehen[$aktuell])) {
					$betroffen[$start] = true;
					break;
				}
				$gesehen[$aktuell] = true;
				$aktuell = $eltern[$aktuell];
			}
		}
		return array_keys($betroffen);
	}

	private function rahmenSichern(array $rahmen): int {
		$q = $this->db->getQueryBuilder();
		$q->select('id')->from('kidseye_rahmen')
			->where($q->expr()->eq('kennung', $q->createNamedParameter($rahmen['kennung'])));
		$treffer = $q->executeQuery();
		$id = $treffer->fetchOne();
		$treffer->closeCursor();

		if ($id !== false) {
			return (int)$id;
		}

		$q = $this->db->getQueryBuilder();
		$q->insert('kidseye_rahmen')->values([
			'kennung' => $q->createNamedParameter($rahmen['kennung']),
			'name' => $q->createNamedParameter($rahmen['name']),
			'herausgeber' => $q->createNamedParameter($rahmen['herausgeber'] ?? null),
		])->executeStatement();

		return $q->getLastInsertId();
	}

	private function versionAnlegen(int $rahmenId, array $version): int {
		$q = $this->db->getQueryBuilder();
		$q->insert('kidseye_rahmen_vers')->values([
			'rahmen_id' => $q->createNamedParameter($rahmenId, IQueryBuilder::PARAM_INT),
			'kennung' => $q->createNamedParameter($version['kennung']),
			'name' => $q->createNamedParameter($version['name']),
			'gueltig_ab' => $q->createNamedParameter($version['gueltigAb'] ?? null),
			'quelle' => $q->createNamedParameter($version['quelle'] ?? null),
			'importiert_am' => $q->createNamedParameter(
				(new \DateTime())->format('Y-m-d H:i:s')
			),
		])->executeStatement();

		return $q->getLastInsertId();
	}

	private function knotenAnlegen(int $versionId, array $k): int {
		$q = $this->db->getQueryBuilder();
		$q->insert('kidseye_knoten')->values([
			'version_id' => $q->createNamedParameter($versionId, IQueryBuilder::PARAM_INT),
			'kennung' => $q->createNamedParameter($k['kennung']),
			'art' => $q->createNamedParameter($k['art']),
			'ebene' => $q->createNamedParameter($k['ebene']),
			'fach_kennung' => $q->createNamedParameter($k['fach'] ?? null),
			'bezeichnung' => $q->createNamedParameter($k['bezeichnung']),
			'beschreibung' => $q->createNamedParameter($k['beschreibung'] ?? null),
			'struktur_name' => $q->createNamedParameter($k['strukturName'] ?? null),
			'bezugsstufe' => $q->createNamedParameter($k['bezugsstufe'] ?? null),
			'sortierung' => $q->createNamedParameter($k['sortierung'] ?? 0, IQueryBuilder::PARAM_INT),
			'waehlbar' => $q->createNamedParameter(
				$k['waehlbar'] ?? true, IQueryBuilder::PARAM_BOOL
			),
		])->executeStatement();

		return $q->getLastInsertId();
	}

	private function elternSetzen(int $knotenId, int $elternId): void {
		$q = $this->db->getQueryBuilder();
		$q->update('kidseye_knoten')
			->set('eltern_id', $q->createNamedParameter($elternId, IQueryBuilder::PARAM_INT))
			->where($q->expr()->eq('id', $q->createNamedParameter($knotenId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	private function korrespondenzAnlegen(int $von, int $nach): void {
		$q = $this->db->getQueryBuilder();
		$q->insert('kidseye_knoten_korr')->values([
			'von_knoten_id' => $q->createNamedParameter($von, IQueryBuilder::PARAM_INT),
			'nach_knoten_id' => $q->createNamedParameter($nach, IQueryBuilder::PARAM_INT),
		])->executeStatement();
	}

	/** Pfad zum mitgelieferten Rahmen im App-Verzeichnis. */
	public static function mitgelieferterPfad(string $datei): string {
		$appPfad = \OCP\Server::get(IAppManager::class)->getAppPath(Application::APP_ID);

		return $appPfad . '/data/rahmen/' . $datei;
	}
}

<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Berichte: Elterngespräch, Zeugniskonferenz, Mappe, DSGVO-Auskunft
 * (Kapitel 7.9, 8.5, 8.6, 5b.6).
 *
 * Durchgehalten wird die Grenze aus dem Konzept: ein Bericht enthält
 * ausschließlich **erfasste Beobachtungen und daraus abgeleitete Zählwerte**.
 * kidseye formuliert keine pädagogische Einschätzung, keinen Förderplan und
 * keine Empfehlung — das Material kommt aus dem System, der Text von der
 * Lehrkraft.
 *
 * Zur Ausgabe: das PDF erzeugt der PdfService ohne zusätzliche Abhängigkeit.
 * Die hier ebenfalls vorhandene HTML-Fassung dient der Vorschau im Browser.
 */
class BerichtService {

	public const KEIN_TEXT_HINWEIS = 'Dieses Dokument enthält ausschließlich erfasste '
		. 'Beobachtungen und Zählwerte. Die pädagogische Einschätzung trifft die Lehrkraft.';

	public function __construct(
		private IDBConnection $db,
		private AuswertungService $auswertung,
		private StammdatenService $stammdaten,
		private ProtokollService $protokoll,
		private ZugriffService $zugriff,
		private BeobachtungService $beobachtungen,
		private SkalaService $skala,
	) {
	}

	/**
	 * Bericht für Elterngespräch oder Zeugniskonferenz (8.5).
	 *
	 * @param array $filter von, bis, kontextIds, stufen, zweck, mitArbeitsproben
	 */
	public function bericht(string $nutzerId, int $schuelerId, array $filter = []): array {
		$kind = $this->kind($schuelerId);
		$zeitleiste = $this->auswertung->zeitleiste($nutzerId, $schuelerId, $filter);
		$uebersicht = $this->auswertung->belegUebersicht($nutzerId, $schuelerId, $filter);

		$eintraege = $zeitleiste['eintraege'];
		if (empty($filter['mitArbeitsproben'])) {
			foreach ($eintraege as &$e) {
				$e['dateien'] = [];
			}
			unset($e);
		}

		return [
			'art' => 'bericht',
			'kind' => $kind,
			'zeitraum' => ['von' => $zeitleiste['von'], 'bis' => $zeitleiste['bis']],
			'erstelltAm' => (new \DateTime())->format(\DateTimeInterface::ATOM),
			'erstelltVon' => $nutzerId,
			'eintraege' => $eintraege,
			'uebersicht' => $uebersicht,
			'versionen' => $zeitleiste['versionen'],
			'skala' => $this->skala->aktiveSkala(),
			'hinweise' => array_values(array_filter([
				self::KEIN_TEXT_HINWEIS,
				AuswertungService::HINWEIS_BELEGE,
				$zeitleiste['versionen']['hinweis'] ?? null,
				$this->skala->aktiveSkala() !== null ? SkalaService::HINWEIS : null,
			])),
		];
	}

	/** Mappe eines Verwendungszwecks als Bericht (5b.6). */
	public function mappenBericht(string $nutzerId, int $schuelerId, string $zweckKennung, array $filter = []): array {
		$mappe = $this->auswertung->mappe($nutzerId, $schuelerId, $zweckKennung, $filter);
		$eintraege = $mappe['eintraege'];
		if (empty($filter['mitArbeitsproben'])) {
			foreach ($eintraege as &$e) {
				$e['dateien'] = [];
			}
			unset($e);
		}

		return [
			'art' => 'mappe',
			'kind' => $this->kind($schuelerId),
			'zweck' => $mappe['zweck'],
			'erstelltAm' => (new \DateTime())->format(\DateTimeInterface::ATOM),
			'erstelltVon' => $nutzerId,
			'eintraege' => $eintraege,
			'hinweise' => array_values(array_filter([
				self::KEIN_TEXT_HINWEIS,
				$mappe['hinweis'],
			])),
		];
	}

	/**
	 * Auskunft nach Artikel 15 DSGVO (7.9).
	 *
	 * Enthält alle Beobachtungen der Stufen `klassenteam` und `akte` —
	 * ausdrücklich KEINE der Stufe `privat`. Private Aufzeichnungen der
	 * Lehrkraft sind nicht Teil der Schülerakte.
	 *
	 * Der Vorgang wird protokolliert.
	 */
	public function auskunft(string $nutzerId, int $schuelerId): array {
		$kind = $this->kind($schuelerId);

		$q = $this->db->getQueryBuilder();
		$this->beobachtungen->grundAbfrage($q);
		$q->andWhere($q->expr()->eq('b.schueler_id', $q->createNamedParameter($schuelerId, IQueryBuilder::PARAM_INT)))
			// Die Sichtbarkeitsstufe entscheidet, nicht die erfassende Person:
			// eine Auskunft umfasst die schulischen Unterlagen aller Lehrkräfte.
			->andWhere($q->expr()->in('b.sichtbarkeit', $q->createNamedParameter(
				[ZugriffService::SICHT_KLASSENTEAM, ZugriffService::SICHT_AKTE],
				IQueryBuilder::PARAM_STR_ARRAY
			)))
			->andWhere($q->expr()->isNull('b.geloescht_am'))
			->orderBy('b.erfasst_am');
		$eintraege = $this->beobachtungen->hole($q);

		$arbeitsproben = [];
		foreach ($eintraege as $e) {
			foreach ($e['dateien'] as $datei) {
				$arbeitsproben[] = [
					'beobachtungId' => $e['id'],
					'erfasstAm' => $e['erfasstAm'],
					'fileId' => $datei['fileId'],
					'dateiname' => $datei['dateiname'],
				];
			}
		}

		$this->protokoll->anfuegen(
			$nutzerId, ProtokollService::AKTION_AUSKUNFT,
			null, $schuelerId, null, null,
			'Auskunft nach Art. 15 DSGVO erteilt, ' . count($eintraege) . ' Einträge.'
		);

		return [
			'art' => 'auskunft',
			'kind' => $kind,
			'erstelltAm' => (new \DateTime())->format(\DateTimeInterface::ATOM),
			'erstelltVon' => $nutzerId,
			'rechtsgrundlage' => 'Artikel 15 DSGVO',
			'gesamt' => count($eintraege),
			'eintraege' => $eintraege,
			'arbeitsproben' => $arbeitsproben,
			'hinweise' => [
				'Enthalten sind alle Beobachtungen der Stufen „Klassenteam" und „Akte".',
				'Persönliche Aufzeichnungen der Lehrkraft (Stufe „privat") sind nicht Teil '
					. 'der Schülerakte und deshalb nicht enthalten.',
				self::KEIN_TEXT_HINWEIS,
			],
		];
	}

	/**
	 * Druckfertiges HTML zu einem der obigen Berichte.
	 * Bewusst ohne externe Abhängigkeit und ohne JavaScript.
	 */
	public function alsHtml(array $bericht): string {
		$e = static fn (?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		$datum = static fn (?string $iso): string => $iso === null
			? '' : (new \DateTime($iso))->format('d.m.Y H:i');

		$titel = match ($bericht['art']) {
			'auskunft' => 'Auskunft nach Artikel 15 DSGVO',
			'mappe' => 'Mappe „' . $bericht['zweck']['name'] . '"',
			default => 'Beobachtungsbericht',
		};

		$h = [];
		$h[] = '<!doctype html><html lang="de"><head><meta charset="utf-8">';
		$h[] = '<title>' . $e($titel . ' – ' . $bericht['kind']['anzeige']) . '</title>';
		$h[] = '<style>'
			. 'body{font:11pt/1.5 Georgia,serif;margin:2cm;color:#16213A}'
			. 'h1{font-size:18pt;margin:0 0 .2em}h2{font-size:13pt;margin:1.6em 0 .4em;'
			. 'border-top:1px solid #D9DEE8;padding-top:.5em}'
			. '.kopf{color:#4A5670;font-size:9pt;margin-bottom:1.5em}'
			. 'table{border-collapse:collapse;width:100%;font-size:10pt}'
			. 'th,td{text-align:left;vertical-align:top;padding:.35em .5em;border-bottom:1px solid #E8EBF1}'
			. 'th{font-size:8.5pt;text-transform:uppercase;letter-spacing:.08em;color:#7C86A0}'
			. '.knoten{color:#2E4FA3;font-size:9pt}'
			. '.hinweis{background:#F7F7F4;border:1px solid #D9DEE8;padding:.8em 1em;'
			. 'font-size:9pt;color:#4A5670;margin-top:2em}'
			. '.hinweis p{margin:.3em 0}'
			. '@media print{body{margin:1.5cm}h2{page-break-after:avoid}tr{page-break-inside:avoid}}'
			. '</style></head><body>';

		$h[] = '<h1>' . $e($titel) . '</h1>';
		$h[] = '<div class="kopf">'
			. $e($bericht['kind']['anzeige'])
			. ($bericht['kind']['klasse'] !== null ? ' · ' . $e($bericht['kind']['klasse']) : '')
			. ' · erstellt am ' . $e($datum($bericht['erstelltAm']))
			. ' von ' . $e($bericht['erstelltVon'])
			. (isset($bericht['zeitraum']['von'])
				? ' · Zeitraum ab ' . $e($bericht['zeitraum']['von']) : '')
			. '</div>';

		if (!empty($bericht['uebersicht']['bereiche'])) {
			$h[] = '<h2>Belegübersicht</h2>';
			foreach ($bericht['uebersicht']['bereiche'] as $bereich) {
				$h[] = '<h3 style="font-size:11pt;margin:.8em 0 .2em">'
					. $e($bereich['ebene'] === 'ueberfachlich'
						? 'Überfachliche Kompetenzen'
						: 'Fach: ' . ($bereich['fach'] ?? '—'))
					. '</h3><table><tr><th>Kompetenz</th><th style="width:5em">Belege</th></tr>';
				foreach ($bereich['knoten'] as $knoten) {
					$h[] = '<tr><td>' . $e($knoten['bezeichnung']) . '</td>'
						. '<td>' . (int)$knoten['anzahl'] . '</td></tr>';
				}
				$h[] = '</table>';
			}
		}

		$h[] = '<h2>Beobachtungen (' . count($bericht['eintraege']) . ')</h2>';
		if ($bericht['eintraege'] === []) {
			$h[] = '<p>Für den gewählten Zeitraum liegen keine Beobachtungen vor.</p>';
		} else {
			$h[] = '<table><tr><th style="width:8em">Zeitpunkt</th><th style="width:7em">Kontext</th>'
				. '<th>Beobachtung</th></tr>';
			foreach ($bericht['eintraege'] as $eintrag) {
				$inhalt = $eintrag['text'] ?? $eintrag['markerText'] ?? '';
				$knoten = implode(' · ', array_map(
					static fn ($k) => $k['bezeichnung'], $eintrag['knoten']
				));
				$h[] = '<tr><td>' . $e($datum($eintrag['erfasstAm'])) . '</td>'
					. '<td>' . $e($eintrag['kontext'] ?? '—') . '</td>'
					. '<td>' . $e($inhalt)
					. ($knoten !== '' ? '<br><span class="knoten">' . $e($knoten) . '</span>' : '')
					. (count($eintrag['dateien']) > 0
						? '<br><span class="knoten">' . count($eintrag['dateien'])
							. ' Arbeitsprobe(n)</span>' : '')
					. '</td></tr>';
			}
			$h[] = '</table>';
		}

		if (!empty($bericht['hinweise'])) {
			$h[] = '<div class="hinweis">';
			foreach ($bericht['hinweise'] as $hinweis) {
				$h[] = '<p>' . $e($hinweis) . '</p>';
			}
			$h[] = '</div>';
		}

		$h[] = '</body></html>';
		return implode("\n", $h);
	}

	private function kind(int $schuelerId): array {
		$q = $this->db->getQueryBuilder();
		$q->select('s.id', 's.vorname', 's.nachname', 's.geburtsjahr')
			->selectAlias('k.name', 'klasse')
			->from('kidseye_schueler', 's')
			->leftJoin('s', 'kidseye_klassen_zug', 'z', 'z.schueler_id = s.id')
			->leftJoin('z', 'kidseye_klasse', 'k', 'k.id = z.klasse_id')
			->where($q->expr()->eq('s.id', $q->createNamedParameter($schuelerId, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		$treffer = $q->executeQuery();
		$z = $treffer->fetch();
		$treffer->closeCursor();
		if ($z === false) {
			throw new \InvalidArgumentException('Unbekanntes Kind.');
		}
		return [
			'id' => (int)$z['id'],
			'anzeige' => $z['vorname'] . ' ' . $z['nachname'],
			'geburtsjahr' => $z['geburtsjahr'] !== null ? (int)$z['geburtsjahr'] : null,
			'klasse' => $z['klasse'],
		];
	}
}

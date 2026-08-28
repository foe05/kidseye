<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Erzeugt eine vollständige Beispielklasse samt Beobachtungsverlauf.
 *
 * Warum das mehr ist als Bequemlichkeit: Lücken-Radar, Kompetenz-Übersicht
 * und die Berichte lassen sich auf einer leeren Datenbank nicht beurteilen.
 * Dort steht bei jedem Kind „noch nie beobachtet" und in jeder Zelle „·" —
 * ob richtig gerechnet wird, ist daran nicht zu erkennen. Genauso wenig
 * fallen Darstellungsfehler auf, die erst mit Inhalt entstehen: eine
 * Filterzeile mit langen Kindernamen bricht auf leerer Datenbank nie um.
 *
 * Die Daten sind deshalb absichtlich *ungleichmäßig*:
 *
 *  - Ein paar Kinder kommen häufig vor, die meisten mittel, drei fast nie
 *    und zwei überhaupt nicht. Sonst zeigte der Lücken-Radar entweder alle
 *    oder keinen — und wäre in beiden Fällen nicht zu beurteilen.
 *  - Die Marker verteilen sich schief über die überfachlichen Dimensionen,
 *    damit die Kompetenz-Übersicht ein Muster hat und nicht eine Fläche.
 *  - Rund ein Fünftel trägt Freitext und ist damit kuratierungsbedürftig
 *    (D4) — sonst bliebe die Inbox leer.
 *
 * Erfunden ist nur der Inhalt, nicht der Weg: geschrieben wird über
 * BeobachtungService::erfassen(), also über denselben Pfad wie ein Tap auf
 * dem Gerät. Ein Fehler in der Zuordnung fiele hier genauso an wie dort.
 */
class BeispieldatenService {

	/** Klassen mit diesem Zusatz gelten als erzeugt und sind entfernbar. */
	public const KENNZEICHEN = '(Beispiel)';

	/**
	 * Vorname, Nachname. Bewusst mit Doppelnamen und einem sehr langen
	 * Nachnamen: das ist der Fall, an dem Filterzeilen und Kacheln brechen.
	 */
	private const KINDER = [
		['Amira', 'Yıldırım'], ['Ben', 'Krause'], ['Charlotte', 'Wagner-Baumgartner'],
		['David', 'Nowak'], ['Elif', 'Demir'], ['Finn', 'Schulz'],
		['Greta', 'Hoffmann'], ['Hasan', 'Öztürk'], ['Ida', 'Brandt'],
		['Jonas', 'Meier'], ['Katharina', 'Schwarzenbeck-Hohenlohe'],
		['Leon', 'Fischer'], ['Mila', 'Weber'], ['Noah', 'Richter'],
		['Olivia', 'Kowalski'], ['Paul', 'Neumann'], ['Quentin', 'Vogel'],
		['Rosa', 'Lehmann'], ['Samuel', 'Braun'], ['Theresa', 'Köhler'],
		['Uma', 'Schneider'], ['Viktor', 'Zimmermann'],
	];

	/** Tischgruppen für das Klassenbild (D11) — keine Raumgeometrie. */
	private const GRUPPEN = ['Tisch A', 'Tisch B', 'Tisch C', 'Tisch D'];

	/**
	 * Freitexte für die kuratierungsbedürftigen Einträge. Beschreibend
	 * formuliert, wie die Marker selbst — was hier steht, liest im Zweifel
	 * jemand, der die App zum ersten Mal sieht.
	 */
	private const NOTIZEN = [
		'Hat der Nachbarin den Rechenweg erklärt, ohne dass ich gefragt habe.',
		'Kam heute schwer in die Arbeitsphase; nach dem Wechsel des Platzes ging es.',
		'Liest inzwischen längere Abschnitte am Stück, verliert die Zeile nicht mehr.',
		'Hat den Streit auf dem Schulhof selbst geschlichtet.',
		'Bei offenen Aufgaben unsicher, fragt sofort nach der richtigen Lösung.',
		'Hat die Gruppenarbeit organisiert und die Aufgaben verteilt.',
		'Erklärt eigene Ideen bereitwillig, hört anderen dabei aber selten zu.',
		'Braucht bei Textaufgaben deutlich länger, rechnet dann aber sicher.',
		'Bringt regelmäßig Fundstücke von zu Hause mit und erzählt dazu.',
		'War heute sehr still, hat auf Ansprache nur kurz geantwortet.',
	];

	public function __construct(
		private IDBConnection $db,
		private StammdatenService $stammdaten,
		private KontextService $kontexte,
		private MarkerService $marker,
		private StundeService $stunden,
		private BeobachtungService $beobachtungen,
	) {
	}

	/**
	 * Legt die Beispielklasse an und füllt sie.
	 *
	 * @param string $nutzerId Lehrkraft, der die Beobachtungen gehören
	 * @param int    $wochen   Zeitraum rückwärts ab heute
	 * @return array{klasseId:int,klasse:string,kinder:int,stunden:int,beobachtungen:int,ohneBeobachtung:int}
	 *
	 * @throws \InvalidArgumentException wenn Voraussetzungen fehlen
	 */
	public function erzeuge(string $nutzerId, string $name = '3d', int $wochen = 8): array {
		$klassenname = trim($name) . ' ' . self::KENNZEICHEN;

		$schuljahr = $this->stammdaten->aktivesSchuljahr();
		if ($schuljahr === null) {
			throw new \InvalidArgumentException(
				'Es ist kein Schuljahr aktiv. Zuerst `occ kidseye:einrichten --schuljahr 2026/27`.'
			);
		}

		$kontexte = $this->kontexte->alle();
		if ($kontexte === []) {
			throw new \InvalidArgumentException(
				'Es sind keine Unterrichtskontexte angelegt. Zuerst `occ kidseye:einrichten`.'
			);
		}

		foreach ($this->stammdaten->klassen($schuljahr['id']) as $vorhanden) {
			if ($vorhanden['name'] === $klassenname) {
				throw new \InvalidArgumentException(
					'Die Klasse „' . $klassenname . '" gibt es schon. '
					. 'Mit --entfernen lässt sie sich vorher wegräumen.'
				);
			}
		}

		// Immer dieselbe Streuung: ein zweiter Aufruf ergibt dieselbe Klasse,
		// und ein Befund von gestern ist heute noch nachvollziehbar.
		mt_srand(20260828);

		$klasseId = $this->stammdaten->klasseAnlegen($schuljahr['id'], $klassenname);

		$schuelerIds = [];
		foreach (self::KINDER as $i => [$vorname, $nachname]) {
			$schuelerId = $this->stammdaten->schuelerAnlegen($vorname, $nachname, 2018);
			$this->stammdaten->inKlasse($klasseId, $schuelerId);
			$schuelerIds[] = $schuelerId;
			$this->gruppeSetzen($klasseId, $schuelerId, self::GRUPPEN[intdiv($i, 6) % count(self::GRUPPEN)]);
		}

		// Der Anwendungsfall, der die Auswertung überhaupt trägt: eine
		// Lehrkraft beobachtet in allen Kontexten ihrer Klasse.
		$this->stammdaten->lehrauftraegeSetzen(
			$nutzerId, $klasseId, array_column($kontexte, 'id'), true
		);

		[$stundenZahl, $beobachtungen, $ohne] = $this->verlaufErzeugen(
			$nutzerId, $klasseId, $kontexte, $schuelerIds, $wochen
		);

		return [
			'klasseId' => $klasseId,
			'klasse' => $klassenname,
			'kinder' => count($schuelerIds),
			'stunden' => $stundenZahl,
			'beobachtungen' => $beobachtungen,
			'ohneBeobachtung' => $ohne,
		];
	}

	/**
	 * Räumt eine erzeugte Klasse restlos weg — Beobachtungen eingeschlossen.
	 *
	 * Anders als StammdatenService::klasseLoeschen(), das Beobachtungen
	 * ausdrücklich schützt: hier ist bekannt, dass sie erzeugt sind. Die
	 * Sicherung dagegen ist das Kennzeichen im Namen — was nicht „(Beispiel)"
	 * heißt, wird hier nicht angefasst.
	 *
	 * @throws \InvalidArgumentException bei einer Klasse ohne Kennzeichen
	 */
	public function entferne(int $klasseId): array {
		$klasse = $this->stammdaten->klasseNachId($klasseId);
		if ($klasse === null) {
			throw new \InvalidArgumentException('Unbekannte Klasse.');
		}
		if (!str_contains($klasse['name'], self::KENNZEICHEN)) {
			throw new \InvalidArgumentException(
				'„' . $klasse['name'] . '" ist keine erzeugte Beispielklasse. '
				. 'Echte Klassen werden hier nicht gelöscht.'
			);
		}

		$kinder = array_column($this->stammdaten->kinderDerKlasse($klasseId), 'id');
		$beobachtungIds = $this->beobachtungIds($klasseId);

		$this->db->beginTransaction();
		try {
			if ($beobachtungIds !== []) {
				foreach (['kidseye_beob_knoten', 'kidseye_beob_zweck', 'kidseye_beob_datei',
					'kidseye_nachtrag'] as $tabelle) {
					$q = $this->db->getQueryBuilder();
					$q->delete($tabelle)
						->where($q->expr()->in('beobachtung_id',
							$q->createNamedParameter($beobachtungIds, IQueryBuilder::PARAM_INT_ARRAY)))
						->executeStatement();
				}
			}

			foreach (['kidseye_beobachtung', 'kidseye_stunde', 'kidseye_klassenbild',
				'kidseye_klassen_zug', 'kidseye_lehrauftrag'] as $tabelle) {
				$q = $this->db->getQueryBuilder();
				$q->delete($tabelle)
					->where($q->expr()->eq('klasse_id', $q->createNamedParameter($klasseId, IQueryBuilder::PARAM_INT)))
					->executeStatement();
			}

			// Die Kinder sind mit der Klasse entstanden und gehen mit ihr.
			// Bei einer echten Klasse wäre das falsch — Kinder überleben den
			// Klassenwechsel (D13). Hier gab es sie vorher nicht.
			if ($kinder !== []) {
				$q = $this->db->getQueryBuilder();
				$q->delete('kidseye_schueler')
					->where($q->expr()->in('id',
						$q->createNamedParameter($kinder, IQueryBuilder::PARAM_INT_ARRAY)))
					->executeStatement();
			}

			$q = $this->db->getQueryBuilder();
			$q->delete('kidseye_klasse')
				->where($q->expr()->eq('id', $q->createNamedParameter($klasseId, IQueryBuilder::PARAM_INT)))
				->executeStatement();

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		return [
			'klasse' => $klasse['name'],
			'kinder' => count($kinder),
			'beobachtungen' => count($beobachtungIds),
		];
	}

	/** Alle erzeugten Beispielklassen des aktiven Schuljahrs. */
	public function beispielklassen(): array {
		$schuljahr = $this->stammdaten->aktivesSchuljahr();
		if ($schuljahr === null) {
			return [];
		}
		return array_values(array_filter(
			$this->stammdaten->klassen($schuljahr['id']),
			static fn ($k) => str_contains($k['name'], self::KENNZEICHEN)
		));
	}

	// ------------------------------------------------------------------ Intern

	/**
	 * Der eigentliche Verlauf: je Woche mehrere Stunden, je Stunde mehrere
	 * Beobachtungen — zurückdatiert bis $wochen Wochen vor heute.
	 *
	 * @return array{0:int,1:int,2:int} Stunden, Beobachtungen, Kinder ohne Eintrag
	 */
	private function verlaufErzeugen(
		string $nutzerId,
		int $klasseId,
		array $kontexte,
		array $schuelerIds,
		int $wochen,
	): array {
		// Gewichte je Kind: wer wie oft vorkommt. Die letzten beiden bleiben
		// bei 0 — sie sind der Fall, für den es den Lücken-Radar gibt.
		$gewichte = [];
		foreach ($schuelerIds as $i => $id) {
			$gewichte[$id] = match (true) {
				$i >= count($schuelerIds) - 2 => 0,
				$i >= count($schuelerIds) - 5 => 1,
				$i < 4 => 6,
				default => 3,
			};
		}

		$stundenZahl = 0;
		$beobachtungen = 0;
		$gesehen = [];

		for ($woche = $wochen; $woche >= 1; $woche--) {
			// Drei bis fünf Stunden je Woche, in wechselnden Kontexten
			foreach (array_slice($this->gemischt($kontexte), 0, mt_rand(3, 5)) as $kontext) {
				$marker = $this->marker->fuerKontext($kontext['id']);
				$begonnen = (new \DateTime())
					->modify('-' . $woche . ' weeks')
					->modify('+' . mt_rand(0, 4) . ' days')
					->setTime(mt_rand(8, 12), [0, 15, 30, 45][mt_rand(0, 3)]);

				$stunde = $this->stunden->starten(
					$nutzerId, $klasseId, (int)$kontext['id'], null, $begonnen
				);
				$this->stunden->beenden((int)$stunde['id']);
				$stundenZahl++;

				foreach ($this->kinderDerStunde($schuelerIds, $gewichte) as $schuelerId) {
					$erfasstAm = (clone $begonnen)->modify('+' . mt_rand(2, 80) . ' minutes');

					// Rund ein Fünftel mit Freitext — das ist genau der Anteil,
					// der laut D4 in die Inbox geht.
					$mitText = mt_rand(1, 100) <= 20;
					$eingabe = [
						'schuelerId' => $schuelerId,
						'erfasstAm' => $erfasstAm->format(\DateTimeInterface::ATOM),
						'clientUuid' => 'beispiel-' . $stunde['id'] . '-' . $schuelerId,
					];
					if ($mitText || $marker === []) {
						$eingabe['text'] = self::NOTIZEN[mt_rand(0, count(self::NOTIZEN) - 1)];
					} else {
						// Schief verteilt: die ersten Marker eines Satzes kommen
						// häufiger vor als die letzten. Eine Gleichverteilung
						// ergäbe eine Kompetenz-Übersicht ohne jedes Muster.
						$eingabe['markerId'] = $marker[min(
							(int)floor(abs($this->normal()) * count($marker)),
							count($marker) - 1
						)]['id'];
					}

					$this->beobachtungen->erfassen($nutzerId, $eingabe, $stunde);
					$beobachtungen++;
					$gesehen[$schuelerId] = true;
				}
			}
		}

		return [$stundenZahl, $beobachtungen, count($schuelerIds) - count($gesehen)];
	}

	/** Welche Kinder kommen in dieser Stunde vor? Nach Gewicht, nicht gleich. */
	private function kinderDerStunde(array $schuelerIds, array $gewichte): array {
		$treffer = [];
		foreach ($schuelerIds as $id) {
			if ($gewichte[$id] > 0 && mt_rand(1, 12) <= $gewichte[$id]) {
				$treffer[] = $id;
			}
		}
		return $treffer;
	}

	/** Grobe Normalverteilung um 0 — reicht, um eine Schieflage zu erzeugen. */
	private function normal(): float {
		return ((mt_rand(0, 1000) + mt_rand(0, 1000) + mt_rand(0, 1000)) / 3000 - 0.5) * 1.6;
	}

	private function gemischt(array $liste): array {
		shuffle($liste);
		return $liste;
	}

	private function gruppeSetzen(int $klasseId, int $schuelerId, string $gruppe): void {
		$q = $this->db->getQueryBuilder();
		$q->update('kidseye_klassenbild')
			->set('gruppe', $q->createNamedParameter($gruppe))
			->where($q->expr()->eq('klasse_id', $q->createNamedParameter($klasseId, IQueryBuilder::PARAM_INT)))
			->andWhere($q->expr()->eq('schueler_id', $q->createNamedParameter($schuelerId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/** @return int[] */
	private function beobachtungIds(int $klasseId): array {
		$q = $this->db->getQueryBuilder();
		$q->select('id')->from('kidseye_beobachtung')
			->where($q->expr()->eq('klasse_id', $q->createNamedParameter($klasseId, IQueryBuilder::PARAM_INT)));
		$treffer = $q->executeQuery();
		$ids = array_map('intval', array_column($treffer->fetchAll(), 'id'));
		$treffer->closeCursor();
		return $ids;
	}
}

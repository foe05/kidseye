<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Zugriffsprüfung auf Beobachtungen (Kapitel 3.7, D7).
 *
 * In der ersten Ausbaustufe gilt der Einzelbetrieb: Beobachtungen sieht nur,
 * wer sie erfasst hat. Die Stufe `klassenteam` ist im Datenmodell vorhanden,
 * aber nicht erreichbar — deshalb steht die Regel hier an genau einer Stelle
 * und lässt sich für v2 an genau dieser Stelle öffnen.
 *
 * Der Lehrauftrag steuert weiterhin, welche Klassen und Kontexte eine
 * Lehrkraft beim Stundenstart überhaupt auswählen kann.
 */
class ZugriffService {

	public const SICHT_PRIVAT = 'privat';
	public const SICHT_KLASSENTEAM = 'klassenteam';
	public const SICHT_AKTE = 'akte';

	/** In v1 erreichbare Stufen. `klassenteam` folgt mit dem Mehrbenutzerbetrieb. */
	public const STUFEN_V1 = [self::SICHT_PRIVAT, self::SICHT_AKTE];
	public const STUFEN_ALLE = [self::SICHT_PRIVAT, self::SICHT_KLASSENTEAM, self::SICHT_AKTE];

	public function __construct(
		private IDBConnection $db,
		private StammdatenService $stammdaten,
	) {
	}

	/** Ist der Mehrbenutzerbetrieb freigeschaltet? In v1 nein. */
	public function mehrbenutzerBetrieb(): bool {
		return false;
	}

	/** Welche Sichtbarkeitsstufen darf die Oberfläche anbieten? */
	public function waehlbareStufen(): array {
		return $this->mehrbenutzerBetrieb() ? self::STUFEN_ALLE : self::STUFEN_V1;
	}

	/**
	 * Schränkt eine Abfrage auf die für diese Lehrkraft sichtbaren
	 * Beobachtungen ein. Einzige Stelle, an der die Sichtbarkeitsregel steht.
	 *
	 * @param string $alias Tabellenalias der Beobachtungstabelle
	 */
	public function sichtbarkeitsFilter(IQueryBuilder $q, string $nutzerId, string $alias = 'b'): void {
		$q->andWhere($q->expr()->isNull($alias . '.geloescht_am'));

		if (!$this->mehrbenutzerBetrieb()) {
			// v1: ausschließlich eigene Beobachtungen
			$q->andWhere($q->expr()->eq($alias . '.nutzer_id', $q->createNamedParameter($nutzerId)));
			return;
		}

		// v2: eigene Beobachtungen plus alles, was für das Klassenteam oder
		// die Akte freigegeben ist — begrenzt auf Klassen mit Lehrauftrag.
		$klassen = array_map(
			static fn ($l) => $l['klasseId'],
			$this->stammdaten->lehrauftraege($nutzerId)
		);
		$geteilt = $q->expr()->in(
			$alias . '.sichtbarkeit',
			$q->createNamedParameter([self::SICHT_KLASSENTEAM, self::SICHT_AKTE], IQueryBuilder::PARAM_STR_ARRAY)
		);
		if ($klassen !== []) {
			$geteilt = $q->expr()->andX($geteilt, $q->expr()->in(
				$alias . '.klasse_id',
				$q->createNamedParameter($klassen, IQueryBuilder::PARAM_INT_ARRAY)
			));
		} else {
			$geteilt = $q->expr()->literal('1 = 0');
		}

		$q->andWhere($q->expr()->orX(
			$q->expr()->eq($alias . '.nutzer_id', $q->createNamedParameter($nutzerId)),
			$geteilt
		));
	}

	public function darfSehen(string $nutzerId, array $beobachtung): bool {
		if ($beobachtung['geloeschtAm'] !== null) {
			return false;
		}
		if ($beobachtung['nutzerId'] === $nutzerId) {
			return true;
		}
		if (!$this->mehrbenutzerBetrieb()) {
			return false;
		}
		if ($beobachtung['sichtbarkeit'] === self::SICHT_PRIVAT) {
			return false;
		}
		return $beobachtung['klasseId'] !== null
			&& $this->stammdaten->hatLehrauftrag($nutzerId, $beobachtung['klasseId']);
	}

	/** Ändern und Löschen bleibt immer bei der erfassenden Lehrkraft. */
	public function darfAendern(string $nutzerId, array $beobachtung): bool {
		return $beobachtung['nutzerId'] === $nutzerId
			&& $beobachtung['geloeschtAm'] === null
			&& $beobachtung['sichtbarkeit'] !== self::SICHT_AKTE;
	}

	/** @throws ZugriffVerweigert */
	public function verlangeSehen(string $nutzerId, array $beobachtung): void {
		if (!$this->darfSehen($nutzerId, $beobachtung)) {
			throw new ZugriffVerweigert('Diese Beobachtung ist für dich nicht sichtbar.');
		}
	}

	/** @throws ZugriffVerweigert */
	public function verlangeAendern(string $nutzerId, array $beobachtung): void {
		if ($beobachtung['nutzerId'] !== $nutzerId) {
			throw new ZugriffVerweigert('Nur die erfassende Lehrkraft kann diese Beobachtung ändern.');
		}
		if ($beobachtung['sichtbarkeit'] === self::SICHT_AKTE) {
			throw new ZugriffVerweigert(
				'Beobachtungen in der Akte sind unveränderlich. '
				. 'Korrekturen erfolgen als Nachtrag.'
			);
		}
		if ($beobachtung['geloeschtAm'] !== null) {
			throw new ZugriffVerweigert('Diese Beobachtung ist gelöscht.');
		}
	}

	/** @throws ZugriffVerweigert */
	public function verlangeLehrauftrag(string $nutzerId, int $klasseId, ?int $kontextId = null): void {
		if (!$this->stammdaten->hatLehrauftrag($nutzerId, $klasseId, $kontextId)) {
			throw new ZugriffVerweigert(
				'Für diese Klasse liegt kein Lehrauftrag vor.'
			);
		}
	}
}

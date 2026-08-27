<?php

declare(strict_types=1);

namespace OCA\KidsEye\Controller;

use OCA\KidsEye\Service\AblageService;
use OCA\KidsEye\Service\BeobachtungService;
use OCA\KidsEye\Service\MarkerService;
use OCA\KidsEye\Service\RollenService;
use OCA\KidsEye\Service\StammdatenService;
use OCA\KidsEye\Service\StundeService;
use OCA\KidsEye\Service\ZweckService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Erfassungsbildschirm: Stunde, Klassenbild, Marker, Beobachtungen (Kapitel 4 und 5).
 */
class ErfassungController extends ApiController {

	public function __construct(
		IRequest $request,
		RollenService $rollen,
		LoggerInterface $logger,
		private StundeService $stunden,
		private BeobachtungService $beobachtungen,
		private MarkerService $marker,
		private StammdatenService $stammdaten,
		private ZweckService $zwecke,
		private AblageService $ablage,
	) {
		parent::__construct($request, $rollen, $logger);
	}

	/** Einstieg: laufende Stunde, Nachfrage oder Startdialog (4.4, 4.5). */
	#[NoAdminRequired]
	public function einstieg(): DataResponse {
		return $this->fuehreAus(function (string $nutzerId) {
			$einstieg = $this->stunden->einstieg($nutzerId);
			$einstieg['auswahl'] = $einstieg['status'] === 'keine'
				? $this->stunden->startAuswahl($nutzerId)
				: null;
			$einstieg['ablage'] = $this->ablage->pruefeAblage($nutzerId);
			return $einstieg;
		});
	}

	#[NoAdminRequired]
	public function startAuswahl(): DataResponse {
		return $this->fuehreAus(fn (string $n) => $this->stunden->startAuswahl($n));
	}

	#[NoAdminRequired]
	public function stundeStarten(int $klasseId, int $kontextId, ?int $inhaltsfeldId = null): DataResponse {
		return $this->fuehreAus(fn (string $n) =>
			$this->stunden->starten($n, $klasseId, $kontextId, $inhaltsfeldId));
	}

	#[NoAdminRequired]
	public function stundeBeenden(): DataResponse {
		return $this->fuehreAus(function (string $nutzerId) {
			$this->stunden->laufendeBeenden($nutzerId);
			return ['beendet' => true];
		});
	}

	/**
	 * Alles, was der Erfassungsbildschirm für eine laufende Stunde braucht:
	 * Klassenbild mit Beobachtungsstand und der Markersatz des Kontexts.
	 */
	#[NoAdminRequired]
	public function bildschirm(): DataResponse {
		return $this->fuehreAus(function (string $nutzerId) {
			$stunde = $this->stunden->laufende($nutzerId);
			if ($stunde === null) {
				return ['stunde' => null];
			}

			$kinder = $this->stammdaten->kinderDerKlasse($stunde['klasseId']);
			$stand = $this->beobachtungen->klassenStand($nutzerId, $stunde['klasseId']);

			foreach ($kinder as &$kind) {
				$kind['stand'] = $stand[$kind['id']]
					?? ['heute' => 0, 'letzte' => null, 'tageHer' => null];
			}
			unset($kind);

			return [
				'stunde' => $stunde,
				'kinder' => $kinder,
				'marker' => $this->marker->fuerKontext($stunde['kontextId']),
				'zwecke' => array_values(array_filter(
					$this->zwecke->alle(),
					// Regelbasierte Zwecke füllen sich selbst — sie stehen
					// nicht als Antippfläche im Erfassungsdialog (D16).
					static fn ($z) => !$z['regelbasiert']
				)),
			];
		});
	}

	/** Eine Beobachtung. Der Ein-Tap-Pfad landet hier. */
	#[NoAdminRequired]
	public function erfassen(
		int $schuelerId,
		?int $markerId = null,
		?string $text = null,
		array $zwecke = [],
		bool $gemerkt = false,
		?string $clientUuid = null,
		?string $erfasstAm = null,
	): DataResponse {
		return $this->fuehreAus(fn (string $n) => $this->beobachtungen->erfassen($n, [
			'schuelerId' => $schuelerId,
			'markerId' => $markerId,
			'text' => $text,
			'zwecke' => $zwecke,
			'gemerkt' => $gemerkt,
			'clientUuid' => $clientUuid,
			'erfasstAm' => $erfasstAm,
		]));
	}

	/** Sammelbeobachtung: eine Notiz, je Kind ein eigener Eintrag (5.10). */
	#[NoAdminRequired]
	public function erfassenMehrere(
		array $schuelerIds,
		?int $markerId = null,
		?string $text = null,
		array $zwecke = [],
		?string $clientUuid = null,
		?string $erfasstAm = null,
	): DataResponse {
		return $this->fuehreAus(fn (string $n) => $this->beobachtungen->erfassenFuerMehrere(
			$n, $schuelerIds,
			['markerId' => $markerId, 'text' => $text, 'zwecke' => $zwecke,
				'clientUuid' => $clientUuid, 'erfasstAm' => $erfasstAm]
		));
	}

	/**
	 * Warteschlange der Offline-Erfassung. Der Dublettenschutz sitzt im
	 * Dienst: ein doppelt gesendeter Eintrag legt nichts neu an (D9).
	 */
	#[NoAdminRequired]
	public function synchronisieren(array $eintraege): DataResponse {
		return $this->fuehreAus(function (string $nutzerId) use ($eintraege) {
			$ergebnis = ['uebernommen' => [], 'fehler' => []];
			foreach ($eintraege as $eintrag) {
				try {
					// Ohne Kennung greift der Dublettenschutz nicht: der Eintrag
					// würde bei jeder Übertragung neu angelegt. Lieber hier
					// zurückweisen — die übrigen Einträge derselben Übertragung
					// bleiben davon unberührt.
					if (empty($eintrag['clientUuid'])) {
						throw new \InvalidArgumentException(
							'Ein Eintrag der Warteschlange braucht eine clientUuid.'
						);
					}

					$beobachtung = $this->beobachtungen->erfassen($nutzerId, $eintrag);
					$ergebnis['uebernommen'][] = [
						'clientUuid' => $eintrag['clientUuid'] ?? null,
						'id' => $beobachtung['id'] ?? null,
						// 'neu' oder 'bereits_vorhanden' — beides ein Erfolg.
						'zustand' => !empty($beobachtung['bereitsVorhanden'])
							? 'bereits_vorhanden'
							: 'neu',
					];
				} catch (\Throwable $e) {
					// Ein einzelner kaputter Eintrag darf die Warteschlange
					// nicht blockieren.
					$this->logger->warning('kidseye: Eintrag nicht übernehmbar', ['exception' => $e]);
					$ergebnis['fehler'][] = [
						'clientUuid' => $eintrag['clientUuid'] ?? null,
						'meldung' => $e->getMessage(),
					];
				}
			}
			return $ergebnis;
		});
	}

	#[NoAdminRequired]
	public function zuruecknehmen(int $id): DataResponse {
		return $this->fuehreAus(fn (string $n) => [
			'zurueckgenommen' => $this->beobachtungen->zuruecknehmen($n, $id),
		]);
	}

	#[NoAdminRequired]
	public function heute(int $schuelerId): DataResponse {
		return $this->fuehreAus(fn (string $n) => $this->beobachtungen->heute($n, $schuelerId));
	}

	/** Gastkind-Suche über alle Klassen mit Lehrauftrag (5.16). */
	#[NoAdminRequired]
	public function suche(string $q = ''): DataResponse {
		return $this->fuehreAus(fn (string $n) => $this->stammdaten->erreichbareKinder($n, $q));
	}

	/**
	 * Arbeitsprobe anhängen. Der Client liefert bereits verkleinerte und von
	 * EXIF befreite Daten (5.8) — hier wird nur abgelegt.
	 */
	#[NoAdminRequired]
	public function fotoAnhaengen(int $beobachtungId, string $daten, string $endung = 'jpg'): DataResponse {
		return $this->fuehreAus(function (string $nutzerId) use ($beobachtungId, $daten, $endung) {
			$beobachtung = $this->beobachtungen->nachId($beobachtungId);
			if ($beobachtung === null || $beobachtung['nutzerId'] !== $nutzerId) {
				throw new \InvalidArgumentException('Unbekannte Beobachtung.');
			}

			$pruefung = $this->ablage->pruefeAblage($nutzerId);
			if (!$pruefung['ok']) {
				throw new \RuntimeException($pruefung['grund'] ?? 'Ablage nicht verfügbar.');
			}

			$roh = base64_decode(preg_replace('#^data:[^;]+;base64,#', '', $daten) ?? '', true);
			if ($roh === false || $roh === '') {
				throw new \InvalidArgumentException('Die Bilddaten konnten nicht gelesen werden.');
			}

			$klasse = $beobachtung['klasseId'] !== null
				? $this->stammdaten->klasseNachId($beobachtung['klasseId'])
				: null;

			$fileId = $this->ablage->legeAb(
				$nutzerId,
				str_replace('/', '-', $klasse['schuljahr'] ?? 'ohne-schuljahr'),
				$klasse['name'] ?? 'ohne-klasse',
				$beobachtung['kuerzel'],
				$roh,
				$endung
			);
			$this->beobachtungen->dateiAnhaengen($beobachtungId, $fileId);
			return ['fileId' => $fileId];
		});
	}
}

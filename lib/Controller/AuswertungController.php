<?php

declare(strict_types=1);

namespace OCA\KidsEye\Controller;

use OCA\KidsEye\Service\AufbewahrungService;
use OCA\KidsEye\Service\AuswertungService;
use OCA\KidsEye\Service\BeobachtungService;
use OCA\KidsEye\Service\BerichtService;
use OCA\KidsEye\Service\InboxService;
use OCA\KidsEye\Service\RahmenService;
use OCA\KidsEye\Service\RollenService;
use OCA\KidsEye\Service\SichtbarkeitService;
use OCA\KidsEye\Service\SkalaService;
use OCA\KidsEye\Service\ZweckService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCA\KidsEye\Service\PdfService;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Wochendurchgang und Auswertung (Kapitel 6, 7, 8).
 */
class AuswertungController extends ApiController {

	public function __construct(
		IRequest $request,
		RollenService $rollen,
		LoggerInterface $logger,
		private InboxService $inbox,
		private AuswertungService $auswertung,
		private BerichtService $berichte,
		private BeobachtungService $beobachtungen,
		private SichtbarkeitService $sichtbarkeit,
		private AufbewahrungService $aufbewahrung,
		private ZweckService $zwecke,
		private SkalaService $skala,
		private PdfService $pdf,
	) {
		parent::__construct($request, $rollen, $logger);
	}

	// ------------------------------------------------------------------- Inbox

	#[NoAdminRequired]
	public function inbox(): DataResponse {
		return $this->fuehreAus(fn (string $n) => $this->inbox->offen($n));
	}

	#[NoAdminRequired]
	public function inboxVorschlaege(int $id): DataResponse {
		return $this->fuehreAus(fn () => $this->inbox->vorschlaege($id));
	}

	#[NoAdminRequired]
	public function inboxZuordnen(array $beobachtungIds, array $knotenIds, bool $erledigen = true): DataResponse {
		return $this->fuehreAus(fn (string $n) => [
			'bearbeitet' => $this->inbox->sammelZuordnung($n, $beobachtungIds, $knotenIds, $erledigen),
		]);
	}

	#[NoAdminRequired]
	public function inboxErledigen(array $beobachtungIds): DataResponse {
		return $this->fuehreAus(fn (string $n) => [
			'erledigt' => $this->inbox->erledigen($n, $beobachtungIds),
		]);
	}

	#[NoAdminRequired]
	public function merken(int $id, bool $gemerkt = true): DataResponse {
		return $this->fuehreAus(function (string $nutzerId) use ($id, $gemerkt) {
			$this->inbox->merken($nutzerId, $id, $gemerkt);
			return ['gemerkt' => $gemerkt];
		});
	}

	#[NoAdminRequired]
	public function umhaengen(int $id, int $schuelerId): DataResponse {
		return $this->fuehreAus(function (string $nutzerId) use ($id, $schuelerId) {
			$this->beobachtungen->umhaengen($nutzerId, $id, $schuelerId);
			return $this->beobachtungen->nachId($id);
		});
	}

	#[NoAdminRequired]
	public function luecken(int $klasseId, int $tage = InboxService::LUECKE_TAGE_STANDARD): DataResponse {
		return $this->fuehreAus(fn (string $n) => $this->inbox->luecken($n, $klasseId, $tage));
	}

	// ------------------------------------------------------------ Sichtbarkeit

	#[NoAdminRequired]
	public function stufen(): DataResponse {
		return $this->fuehreAus(fn () => [
			'waehlbar' => $this->sichtbarkeit->waehlbareStufen(),
			'hinweis' => 'kidseye läuft im Einzelbetrieb: Beobachtungen sieht nur, '
				. 'wer sie erfasst hat.',
		]);
	}

	#[NoAdminRequired]
	public function sichtbarkeitSetzen(int $id, string $stufe): DataResponse {
		return $this->fuehreAus(fn (string $n) => $this->sichtbarkeit->setzen($n, $id, $stufe));
	}

	#[NoAdminRequired]
	public function nachtragen(int $id, string $text): DataResponse {
		return $this->fuehreAus(fn (string $n) => [
			'id' => $this->sichtbarkeit->nachtragen($n, $id, $text),
			'nachtraege' => $this->sichtbarkeit->nachtraege($id),
		]);
	}

	// ------------------------------------------------------------ Aufbewahrung

	#[NoAdminRequired]
	public function faellig(): DataResponse {
		return $this->fuehreAus(fn (string $n) => [
			'eintraege' => $this->aufbewahrung->faellig($n),
			'fristen' => $this->aufbewahrung->fristen(),
		]);
	}

	#[NoAdminRequired]
	public function schuljahresende(string $bis): DataResponse {
		return $this->fuehreAus(fn (string $n) =>
			$this->aufbewahrung->schuljahresendeVorschlag($n, $bis));
	}

	#[NoAdminRequired]
	public function loeschen(array $beobachtungIds): DataResponse {
		return $this->fuehreAus(fn (string $n) => $this->aufbewahrung->loeschen($n, $beobachtungIds));
	}

	// -------------------------------------------------------------- Auswertung

	#[NoAdminRequired]
	public function zeitleiste(int $schuelerId, ?string $von = null, ?string $bis = null,
		array $kontextIds = [], array $knotenIds = [], ?string $zweck = null,
		?string $art = null, array $stufen = []): DataResponse {
		return $this->fuehreAus(fn (string $n) => $this->auswertung->zeitleiste($n, $schuelerId, [
			'von' => $von, 'bis' => $bis, 'kontextIds' => $kontextIds,
			'knotenIds' => $knotenIds, 'zweck' => $zweck, 'art' => $art, 'stufen' => $stufen,
		]));
	}

	#[NoAdminRequired]
	public function heatmap(int $klasseId, string $ebene = RahmenService::EBENE_UEBERFACHLICH,
		?string $fach = null, ?string $von = null, ?string $bis = null): DataResponse {
		return $this->fuehreAus(fn (string $n) => $this->auswertung->heatmap(
			$n, $klasseId, $ebene, $fach, ['von' => $von, 'bis' => $bis]
		));
	}

	#[NoAdminRequired]
	public function mappe(int $schuelerId, string $zweck, ?string $von = null, ?string $bis = null): DataResponse {
		return $this->fuehreAus(fn (string $n) => $this->auswertung->mappe(
			$n, $schuelerId, $zweck, ['von' => $von, 'bis' => $bis]
		));
	}

	#[NoAdminRequired]
	public function einschaetzung(int $zuordnungId, ?int $stufeId = null): DataResponse {
		return $this->fuehreAus(function () use ($zuordnungId, $stufeId) {
			// Wirft BewertungNichtZulaessig, wenn der Knoten überfachlich ist (2.9)
			$this->skala->setzeEinschaetzung($zuordnungId, $stufeId);
			return ['gesetzt' => true, 'hinweis' => SkalaService::HINWEIS];
		});
	}

	// ---------------------------------------------------------------- Berichte

	#[NoAdminRequired]
	public function bericht(int $schuelerId, ?string $von = null, ?string $bis = null,
		array $kontextIds = [], array $stufen = [], ?string $zweck = null,
		bool $mitArbeitsproben = false): DataResponse {
		return $this->fuehreAus(function (string $nutzerId) use (
			$schuelerId, $von, $bis, $kontextIds, $stufen, $zweck, $mitArbeitsproben
		) {
			$filter = ['von' => $von, 'bis' => $bis, 'kontextIds' => $kontextIds,
				'stufen' => $stufen, 'mitArbeitsproben' => $mitArbeitsproben];
			return $zweck === null
				? $this->berichte->bericht($nutzerId, $schuelerId, $filter)
				: $this->berichte->mappenBericht($nutzerId, $schuelerId, $zweck, $filter);
		});
	}

	/** Bericht als PDF (8.5). */
	#[NoAdminRequired]
	public function berichtDruck(int $schuelerId, ?string $von = null, ?string $bis = null,
		array $kontextIds = [], array $stufen = [], ?string $zweck = null,
		bool $mitArbeitsproben = false) {
		try {
			$nutzerId = $this->rollen->verlangeLehrkraft();
			$filter = ['von' => $von, 'bis' => $bis, 'kontextIds' => $kontextIds,
				'stufen' => $stufen, 'mitArbeitsproben' => $mitArbeitsproben];
			$bericht = $zweck === null
				? $this->berichte->bericht($nutzerId, $schuelerId, $filter)
				: $this->berichte->mappenBericht($nutzerId, $schuelerId, $zweck, $filter);

			return new DataDownloadResponse(
				$this->pdf->erzeuge($bericht, $mitArbeitsproben ? $nutzerId : null),
				'kidseye-bericht-' . $schuelerId . '.pdf',
				'application/pdf'
			);
		} catch (\Throwable $e) {
			$this->logger->error('kidseye: Bericht nicht erzeugbar', ['exception' => $e]);
			return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/** Auskunft nach Artikel 15 DSGVO (7.9). Wird protokolliert. */
	#[NoAdminRequired]
	public function auskunft(int $schuelerId): DataResponse {
		return $this->fuehreAus(fn (string $n) => $this->berichte->auskunft($n, $schuelerId));
	}

	#[NoAdminRequired]
	public function auskunftDruck(int $schuelerId) {
		try {
			$nutzerId = $this->rollen->verlangeLehrkraft();
			// Die Auskunft weist Arbeitsproben mit aus (7.9)
			return new DataDownloadResponse(
				$this->pdf->erzeuge($this->berichte->auskunft($nutzerId, $schuelerId), $nutzerId),
				'kidseye-auskunft-' . $schuelerId . '.pdf',
				'application/pdf'
			);
		} catch (\Throwable $e) {
			$this->logger->error('kidseye: Auskunft nicht erzeugbar', ['exception' => $e]);
			return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}
}

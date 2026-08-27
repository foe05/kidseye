<?php

declare(strict_types=1);

namespace OCA\KidsEye\Controller;

use OCA\KidsEye\Service\AblageService;
use OCA\KidsEye\Service\AufbewahrungService;
use OCA\KidsEye\Service\CsvImportService;
use OCA\KidsEye\Service\DiagnoseService;
use OCA\KidsEye\Service\KontextService;
use OCA\KidsEye\Service\MarkerService;
use OCA\KidsEye\Service\RahmenService;
use OCA\KidsEye\Service\RollenService;
use OCA\KidsEye\Service\SkalaService;
use OCA\KidsEye\Service\StammdatenService;
use OCA\KidsEye\Service\ZweckService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Verwaltung: Klassen, Kinder, Kontexte, Marker, Zwecke, Einrichtung
 * (Kapitel 3, 5.2b, 5b.2, 5.22, 7.6).
 */
class VerwaltungController extends ApiController {

	public function __construct(
		IRequest $request,
		RollenService $rollen,
		LoggerInterface $logger,
		private StammdatenService $stammdaten,
		private KontextService $kontexte,
		private MarkerService $marker,
		private ZweckService $zwecke,
		private CsvImportService $csv,
		private RahmenService $rahmen,
		private SkalaService $skala,
		private AufbewahrungService $aufbewahrung,
		private AblageService $ablage,
		private DiagnoseService $diagnose,
	) {
		parent::__construct($request, $rollen, $logger);
	}

	/**
	 * Einrichtungsstand — was fehlt noch, damit kidseye benutzbar ist?
	 *
	 * Die Prüfpunkte kommen aus dem DiagnoseService, aus dem auch
	 * `occ kidseye:pruefen` liest. Nur so können Oberfläche und Befehl nicht
	 * auseinanderlaufen: Wer einen Punkt ergänzt, ergänzt ihn an einer Stelle
	 * (design.md E2).
	 *
	 * Daneben stehen die Werte, die die Oberfläche für anderes braucht — das
	 * Schuljahr etwa auch in der Stammdatenverwaltung.
	 */
	#[NoAdminRequired]
	public function einrichtung(): DataResponse {
		return $this->fuehreAus(function (string $nutzerId) {
			$versionId = $this->rahmen->aktiveVersionId();

			return [
				'punkte' => $this->diagnose->pruefen($nutzerId),
				'rahmen' => ['vorhanden' => $versionId !== null, 'versionId' => $versionId],
				'schuljahr' => $this->stammdaten->aktivesSchuljahr(),
				'kontexte' => count($this->kontexte->alle()),
				'zwecke' => count($this->zwecke->alle()),
				'gruppen' => $this->rollen->gruppenVorhanden(),
				'ablage' => $this->ablage->pruefeAblage($nutzerId),
				'fristen' => $this->aufbewahrung->fristen(),
				'skala' => $this->skala->aktiveSkala(),
			];
		});
	}

	// -------------------------------------------------------------- Stammdaten

	#[NoAdminRequired]
	public function klassen(): DataResponse {
		return $this->fuehreAus(function () {
			$schuljahr = $this->stammdaten->aktivesSchuljahr();
			return $schuljahr === null ? [] : $this->stammdaten->klassen($schuljahr['id']);
		});
	}

	#[NoAdminRequired]
	public function kinder(int $klasseId): DataResponse {
		return $this->fuehreAus(function (string $nutzerId) use ($klasseId) {
			$this->stammdaten->hatLehrauftrag($nutzerId, $klasseId)
				|| $this->rollen->verlangeLeitung();
			return $this->stammdaten->kinderDerKlasse($klasseId);
		});
	}

	public function schuljahrAnlegen(string $kennung, string $beginn, string $ende, bool $aktiv = true): DataResponse {
		return $this->fuehreAus(
			fn () => ['id' => $this->stammdaten->schuljahrAnlegen($kennung, $beginn, $ende, $aktiv)],
			true
		);
	}

	public function klasseAnlegen(string $name): DataResponse {
		return $this->fuehreAus(function () use ($name) {
			$schuljahr = $this->stammdaten->aktivesSchuljahr();
			if ($schuljahr === null) {
				throw new \InvalidArgumentException(
					'Es ist kein Schuljahr aktiv. Bitte zuerst ein Schuljahr anlegen.'
				);
			}
			return ['id' => $this->stammdaten->klasseAnlegen($schuljahr['id'], $name)];
		}, true);
	}

	#[NoAdminRequired]
	public function lehrauftraege(): DataResponse {
		return $this->fuehreAus(function (string $nutzerId) {
			$schuljahr = $this->stammdaten->aktivesSchuljahr();
			return $this->stammdaten->lehrauftraege($nutzerId, $schuljahr['id'] ?? null);
		});
	}

	public function schuelerAnlegen(string $vorname, string $nachname, int $klasseId, ?int $geburtsjahr = null): DataResponse {
		return $this->fuehreAus(function () use ($vorname, $nachname, $klasseId, $geburtsjahr) {
			$schuelerId = $this->stammdaten->schuelerAnlegen($vorname, $nachname, $geburtsjahr);
			$this->stammdaten->inKlasse($klasseId, $schuelerId);
			return ['id' => $schuelerId, 'kinder' => $this->stammdaten->kinderDerKlasse($klasseId)];
		}, true);
	}

	public function lehrauftragAnlegen(string $nutzerId, int $klasseId, int $kontextId, bool $klassenlehrkraft = false): DataResponse {
		return $this->fuehreAus(
			fn () => ['id' => $this->stammdaten->lehrauftragAnlegen(
				$nutzerId, $klasseId, $kontextId, $klassenlehrkraft
			)],
			true
		);
	}

	// ------------------------------------------------------------- Klassenbild

	#[NoAdminRequired]
	public function klassenbildSpeichern(int $klasseId, array $eintraege): DataResponse {
		return $this->fuehreAus(function (string $nutzerId) use ($klasseId, $eintraege) {
			if (!$this->stammdaten->hatLehrauftrag($nutzerId, $klasseId)) {
				$this->rollen->verlangeLeitung();
			}
			$this->stammdaten->klassenbildSpeichern($klasseId, $eintraege);
			return $this->stammdaten->kinderDerKlasse($klasseId);
		});
	}

	#[NoAdminRequired]
	public function klassenbildZuruecksetzen(int $klasseId): DataResponse {
		return $this->fuehreAus(function (string $nutzerId) use ($klasseId) {
			if (!$this->stammdaten->hatLehrauftrag($nutzerId, $klasseId)) {
				$this->rollen->verlangeLeitung();
			}
			$this->stammdaten->klassenbildAlphabetisch($klasseId);
			return $this->stammdaten->kinderDerKlasse($klasseId);
		});
	}

	// -------------------------------------------------------------- CSV-Import

	public function importVorschau(string $csv): DataResponse {
		return $this->fuehreAus(function () use ($csv) {
			$schuljahr = $this->stammdaten->aktivesSchuljahr();
			if ($schuljahr === null) {
				throw new \InvalidArgumentException('Es ist kein Schuljahr aktiv.');
			}
			return $this->csv->vorschau($csv, $schuljahr['id']);
		}, true);
	}

	public function importUebernehmen(string $csv): DataResponse {
		return $this->fuehreAus(function () use ($csv) {
			$schuljahr = $this->stammdaten->aktivesSchuljahr();
			if ($schuljahr === null) {
				throw new \InvalidArgumentException('Es ist kein Schuljahr aktiv.');
			}
			return $this->csv->uebernehmen($csv, $schuljahr['id']);
		}, true);
	}

	// ---------------------------------------------------------------- Rollover

	public function rolloverVorschlag(int $schuljahrId): DataResponse {
		return $this->fuehreAus(
			fn () => $this->stammdaten->rolloverVorschlag($schuljahrId), true
		);
	}

	public function rolloverAusfuehren(int $nachSchuljahrId, array $bestaetigt): DataResponse {
		return $this->fuehreAus(
			fn () => $this->stammdaten->rolloverAusfuehren($nachSchuljahrId, $bestaetigt), true
		);
	}

	// ---------------------------------------------------------------- Kontexte

	#[NoAdminRequired]
	public function kontexte(): DataResponse {
		return $this->fuehreAus(fn () => $this->kontexte->alle(false));
	}

	public function kontextAnlegen(string $kennung, string $name, string $art, ?string $fach = null): DataResponse {
		return $this->fuehreAus(
			fn () => ['id' => $this->kontexte->anlegen($kennung, $name, $art, $fach)], true
		);
	}

	// ----------------------------------------------------------------- Marker

	#[NoAdminRequired]
	public function marker(int $kontextId): DataResponse {
		return $this->fuehreAus(function () use ($kontextId) {
			$kontext = $this->kontexte->nachId($kontextId);
			$versionId = $this->rahmen->aktiveVersionId();
			return [
				'kontext' => $kontext,
				'marker' => $this->marker->fuerKontext($kontextId, false),
				'hoechstzahl' => MarkerService::HOECHSTZAHL_SICHTBAR,
				// Nur überfachliche Dimensionen sind als Markerziel sinnvoll:
				// ein Marker gilt kontextübergreifend.
				'dimensionen' => $versionId === null ? [] : $this->rahmen->knoten(
					$versionId, RahmenService::EBENE_UEBERFACHLICH, null, ['dimension']
				),
				'zwecke' => $this->zwecke->alle(),
			];
		});
	}

	#[NoAdminRequired]
	public function markerSpeichern(int $kontextId, array $marker): DataResponse {
		return $this->fuehreAus(function () use ($kontextId, $marker) {
			$this->marker->satzSpeichern($kontextId, $marker);
			return $this->marker->fuerKontext($kontextId, false);
		});
	}

	// ----------------------------------------------------------------- Zwecke

	#[NoAdminRequired]
	public function zwecke(): DataResponse {
		return $this->fuehreAus(fn () => $this->zwecke->alle(false));
	}

	#[NoAdminRequired]
	public function zweckAnlegen(string $kennung, string $name, bool $regelbasiert = false, array $knoten = []): DataResponse {
		return $this->fuehreAus(
			fn () => ['id' => $this->zwecke->anlegen($kennung, $name, $regelbasiert, $knoten)]
		);
	}

	#[NoAdminRequired]
	public function zweckAendern(int $id, ?string $name = null, ?bool $aktiv = null): DataResponse {
		return $this->fuehreAus(function () use ($id, $name, $aktiv) {
			if ($name !== null) {
				$this->zwecke->umbenennen($id, $name);
			}
			if ($aktiv !== null) {
				$this->zwecke->aktivSetzen($id, $aktiv);
			}
			return $this->zwecke->alle(false);
		});
	}

	// ------------------------------------------------------------ Einstellungen

	public function gruppenSetzen(?string $lehrkraft = null, ?string $leitung = null): DataResponse {
		return $this->fuehreAus(function () use ($lehrkraft, $leitung) {
			if ($lehrkraft !== null) {
				$this->rollen->setzeLehrkraftGruppe($lehrkraft);
			}
			if ($leitung !== null) {
				$this->rollen->setzeLeitungGruppe($leitung);
			}
			return $this->rollen->gruppenVorhanden();
		}, true);
	}

	public function ablagePfadSetzen(string $pfad): DataResponse {
		return $this->fuehreAus(function (string $nutzerId) use ($pfad) {
			$this->ablage->setzeGruppenordnerPfad($pfad);
			return $this->ablage->pruefeAblage($nutzerId);
		}, true);
	}

	public function fristSetzen(string $stufe, int $monate): DataResponse {
		return $this->fuehreAus(function () use ($stufe, $monate) {
			$this->aufbewahrung->setzeFrist($stufe, $monate);
			$this->aufbewahrung->fristenNachtragen();
			return $this->aufbewahrung->fristen();
		}, true);
	}

	public function skalaAnlegen(string $name, array $stufen): DataResponse {
		return $this->fuehreAus(function () use ($name, $stufen) {
			$this->skala->anlegen($name, $stufen);
			return $this->skala->aktiveSkala();
		}, true);
	}
}

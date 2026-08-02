<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCA\KidsEye\AppInfo\Application;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Ablage der Arbeitsproben im schuleigenen Gruppenordner (D8).
 *
 * Bewusst NICHT der persönliche Ordner der Lehrkraft: die Dateien gehören
 * der Schule. Damit lösen sich „Lehrkraft verlässt die Schule" und die Frage
 * nach dem Eigentum der schulischen Unterlagen von selbst.
 *
 * Gespeichert wird ausschließlich die Nextcloud-Datei-ID; sie überlebt
 * Umbenennen und Verschieben.
 */
class AblageService {

	public function __construct(
		private IRootFolder $root,
		private IAppConfig $config,
		private LoggerInterface $logger,
	) {
	}

	public function gruppenordnerPfad(): string {
		return $this->config->getValueString(
			Application::APP_ID, 'ablage_pfad', '/Beobachtung'
		);
	}

	public function setzeGruppenordnerPfad(string $pfad): void {
		$this->config->setValueString(Application::APP_ID, 'ablage_pfad', $pfad);
	}

	/**
	 * Ist die Fotofunktion benutzbar? Wenn nicht, wird sie deaktiviert und
	 * gemeldet — Marker- und Freitexterfassung laufen weiter.
	 *
	 * @return array{ok:bool, pfad:string, grund:?string}
	 */
	public function pruefeAblage(string $nutzerId): array {
		$pfad = $this->gruppenordnerPfad();
		try {
			$ordner = $this->root->getUserFolder($nutzerId);
			if (!$ordner->nodeExists($pfad)) {
				return ['ok' => false, 'pfad' => $pfad,
					'grund' => 'Der Ordner "' . $pfad . '" existiert nicht. '
						. 'Bitte als Gruppenordner der Schule anlegen und freigeben.'];
			}
			$ziel = $ordner->get($pfad);
			if (!$ziel instanceof Folder) {
				return ['ok' => false, 'pfad' => $pfad,
					'grund' => '"' . $pfad . '" ist kein Ordner.'];
			}
			if (!$ziel->isCreatable()) {
				return ['ok' => false, 'pfad' => $pfad,
					'grund' => 'Der Ordner "' . $pfad . '" ist nicht beschreibbar.'];
			}
			return ['ok' => true, 'pfad' => $pfad, 'grund' => null];
		} catch (\Throwable $e) {
			$this->logger->warning('kidseye: Ablage nicht prüfbar', ['exception' => $e]);
			return ['ok' => false, 'pfad' => $pfad, 'grund' => $e->getMessage()];
		}
	}

	/**
	 * Legt eine Arbeitsprobe ab und liefert die Nextcloud-Datei-ID.
	 *
	 * Pfad nach Schuljahr, Klasse und Kind (D8):
	 *   /Beobachtung/2026-27/3a/mia-m/2026-07-27-1430-17.jpg
	 *
	 * @param string $inhalt Bereits clientseitig verkleinerte, von EXIF
	 *                       befreite Bilddaten.
	 */
	public function legeAb(
		string $nutzerId,
		string $schuljahr,
		string $klasse,
		string $schuelerKuerzel,
		string $inhalt,
		string $endung = 'jpg',
	): int {
		$basis = $this->root->getUserFolder($nutzerId);
		$ordner = $this->ordnerKette($basis, [
			trim($this->gruppenordnerPfad(), '/'),
			$this->sicher($schuljahr),
			$this->sicher($klasse),
			$this->sicher($schuelerKuerzel),
		]);

		$name = (new \DateTime())->format('Y-m-d-His')
			. '-' . bin2hex(random_bytes(3)) . '.' . $this->sicher($endung);

		$datei = $ordner->newFile($name, $inhalt);
		return $datei->getId();
	}

	/**
	 * Entfernt eine Datei, sofern sie von keiner anderen Beobachtung
	 * referenziert wird. Die Referenzprüfung macht der Aufrufer.
	 */
	public function entferne(string $nutzerId, int $fileId): bool {
		try {
			$treffer = $this->root->getUserFolder($nutzerId)->getById($fileId);
			if ($treffer === []) {
				return false;
			}
			$treffer[0]->delete();
			return true;
		} catch (NotFoundException) {
			return false;
		} catch (\Throwable $e) {
			$this->logger->warning('kidseye: Datei nicht löschbar', [
				'fileId' => $fileId, 'exception' => $e,
			]);
			return false;
		}
	}

	public function inhalt(string $nutzerId, int $fileId): ?string {
		try {
			$treffer = $this->root->getUserFolder($nutzerId)->getById($fileId);
			return $treffer === [] ? null : $treffer[0]->getContent();
		} catch (\Throwable) {
			return null;
		}
	}

	/** @param string[] $teile */
	private function ordnerKette(Folder $basis, array $teile): Folder {
		$aktuell = $basis;
		foreach ($teile as $teil) {
			if ($teil === '') {
				continue;
			}
			$aktuell = $aktuell->nodeExists($teil)
				? $aktuell->get($teil)
				: $aktuell->newFolder($teil);
			if (!$aktuell instanceof Folder) {
				throw new \RuntimeException('Pfadbestandteil "' . $teil . '" ist kein Ordner.');
			}
		}
		return $aktuell;
	}

	/** Nur unbedenkliche Zeichen in Pfadbestandteilen. */
	private function sicher(string $wert): string {
		$wert = str_replace(['/', '\\', "\0", '..'], '', $wert);
		return trim($wert) === '' ? 'unbenannt' : trim($wert);
	}
}

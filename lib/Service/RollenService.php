<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCA\KidsEye\AppInfo\Application;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserSession;

/**
 * Rollen kommen aus Nextcloud-Gruppen (Kapitel 1.4).
 *
 * Bewusst grobkörnig: die Gruppen entscheiden nur, WER die App und die
 * Verwaltung benutzen darf. Welche Klasse und welches Fach jemand
 * unterrichtet, steht im Lehrauftrag — Nextcloud-Gruppen sind flach und
 * können das Tripel Lehrkraft × Klasse × Kontext nicht abbilden (D13).
 */
class RollenService {

	public function __construct(
		private IGroupManager $gruppen,
		private IUserSession $session,
		private IAppConfig $config,
	) {
	}

	public function lehrkraftGruppe(): string {
		return $this->config->getValueString(
			Application::APP_ID, 'gruppe_lehrkraft', Application::GRUPPE_LEHRKRAFT_DEFAULT
		);
	}

	public function leitungGruppe(): string {
		return $this->config->getValueString(
			Application::APP_ID, 'gruppe_leitung', Application::GRUPPE_LEITUNG_DEFAULT
		);
	}

	public function setzeLehrkraftGruppe(string $name): void {
		$this->config->setValueString(Application::APP_ID, 'gruppe_lehrkraft', $name);
	}

	public function setzeLeitungGruppe(string $name): void {
		$this->config->setValueString(Application::APP_ID, 'gruppe_leitung', $name);
	}

	public function aktuellerNutzer(): ?string {
		return $this->session->getUser()?->getUID();
	}

	public function istLehrkraft(?string $nutzerId = null): bool {
		$nutzerId ??= $this->aktuellerNutzer();
		if ($nutzerId === null) {
			return false;
		}
		// Serveradministration darf immer, sonst käme niemand an die Einrichtung.
		if ($this->gruppen->isAdmin($nutzerId)) {
			return true;
		}
		return $this->gruppen->isInGroup($nutzerId, $this->lehrkraftGruppe())
			|| $this->istLeitung($nutzerId);
	}

	public function istLeitung(?string $nutzerId = null): bool {
		$nutzerId ??= $this->aktuellerNutzer();
		if ($nutzerId === null) {
			return false;
		}
		return $this->gruppen->isAdmin($nutzerId)
			|| $this->gruppen->isInGroup($nutzerId, $this->leitungGruppe());
	}

	/** @throws ZugriffVerweigert */
	public function verlangeLehrkraft(): string {
		$nutzerId = $this->aktuellerNutzer();
		if ($nutzerId === null || !$this->istLehrkraft($nutzerId)) {
			throw new ZugriffVerweigert(
				'Für kidseye ist eine Mitgliedschaft in der Gruppe "'
				. $this->lehrkraftGruppe() . '" erforderlich.'
			);
		}
		return $nutzerId;
	}

	/** @throws ZugriffVerweigert */
	public function verlangeLeitung(): string {
		$nutzerId = $this->aktuellerNutzer();
		if ($nutzerId === null || !$this->istLeitung($nutzerId)) {
			throw new ZugriffVerweigert(
				'Diese Funktion ist der Gruppe "' . $this->leitungGruppe() . '" vorbehalten.'
			);
		}
		return $nutzerId;
	}

	/** Existieren die konfigurierten Gruppen überhaupt? Für die Einrichtungsprüfung. */
	public function gruppenVorhanden(): array {
		return [
			'lehrkraft' => [
				'name' => $this->lehrkraftGruppe(),
				'existiert' => $this->gruppen->groupExists($this->lehrkraftGruppe()),
			],
			'leitung' => [
				'name' => $this->leitungGruppe(),
				'existiert' => $this->gruppen->groupExists($this->leitungGruppe()),
			],
		];
	}
}

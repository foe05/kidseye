<?php

declare(strict_types=1);

namespace OCA\KidsEye\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'kidseye';

	/**
	 * Nextcloud-Gruppe, deren Mitglieder kidseye überhaupt benutzen dürfen.
	 * Der tatsächliche Gruppenname ist über die Verwaltung konfigurierbar;
	 * das hier ist nur die Voreinstellung.
	 */
	public const GRUPPE_LEHRKRAFT_DEFAULT = 'kidseye-lehrkraft';
	public const GRUPPE_LEITUNG_DEFAULT = 'kidseye-leitung';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		// Dienste werden über Autowiring aufgelöst; hier stehen später
		// Event-Listener und Kapazitäten.
	}

	public function boot(IBootContext $context): void {
	}
}

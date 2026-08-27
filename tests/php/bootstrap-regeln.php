<?php
declare(strict_types=1);

require_once __DIR__ . '/stubs/ocp.php';
require_once __DIR__ . '/stubs/console.php';

// Schlichter PSR-4-Autoloader für OCA\KidsEye — hier läuft kein Composer.
spl_autoload_register(static function (string $klasse): void {
	$praefix = 'OCA\\KidsEye\\';
	if (!str_starts_with($klasse, $praefix)) {
		return;
	}
	$pfad = __DIR__ . '/../../lib/'
		. str_replace('\\', '/', substr($klasse, strlen($praefix))) . '.php';
	if (file_exists($pfad)) {
		require_once $pfad;
	}
});

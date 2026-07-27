<?php
declare(strict_types=1);

// Die Tests laufen im Kontext einer Nextcloud-Installation:
//   cd apps/kidseye && ../../vendor/bin/phpunit
// Ohne Server-Autoloader lässt sich OCP nicht auflösen.
$serverRoot = getenv('NEXTCLOUD_ROOT') ?: __DIR__ . '/../../../..';
if (file_exists($serverRoot . '/lib/base.php')) {
    require_once $serverRoot . '/lib/base.php';
    \OC_App::loadApp('kidseye');
} else {
    fwrite(STDERR,
        "kidseye: Nextcloud-Wurzel nicht gefunden.\n" .
        "Die Tests brauchen eine Nextcloud-Installation; Pfad über\n" .
        "NEXTCLOUD_ROOT setzen oder die App unter apps/ ablegen.\n"
    );
    exit(1);
}

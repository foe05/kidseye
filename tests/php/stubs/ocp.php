<?php
/**
 * Minimale OCP-Stubs für die reinen Regeltests.
 *
 * Die Regeln aus RegelnTest.php sind Geschäftslogik ohne Datenbankzugriff.
 * Damit sie sich ohne vollständige Nextcloud-Installation ausführen lassen,
 * genügen die Typen, gegen die die Dienste konstruiert werden. Für Tests
 * mit echtem Datenbankzugriff bleibt der Weg über tests/php/bootstrap.php.
 */
declare(strict_types=1);

namespace OCP\DB\QueryBuilder {
	interface IQueryBuilder {
		public const PARAM_INT = 1;
		public const PARAM_STR = 2;
		public const PARAM_BOOL = 5;
		public const PARAM_INT_ARRAY = 101;
		public const PARAM_STR_ARRAY = 102;
	}
}

namespace OCP {
	interface IDBConnection {
		public function tableExists(string $table): bool;
		public function getQueryBuilder(): \OCP\DB\QueryBuilder\IQueryBuilder;
	}
	interface IAppConfig {}
	interface IConfig {
		public function getSystemValueBool(string $key, bool $default = false): bool;
	}
	interface IGroupManager {}
	interface IUserSession {}
	interface IRequest {}
}

namespace OCP\App {
	interface IAppManager {
		public function getAppPath(string $appId): string;
	}
}

namespace OCP\Files {
	interface IRootFolder {}
	interface Folder {}
}

namespace OCP\AppFramework {
	/**
	 * OCA\KidsEye\AppInfo\Application erbt hiervon. Gebraucht wird die Klasse
	 * in den Tests nur wegen ihrer Konstanten — allen voran APP_ID, gegen die
	 * jeder Controller konstruiert wird.
	 */
	class App {
		public function __construct(string $appName, array $urlParams = []) {
		}
	}

	/**
	 * Die Basis, gegen die alle kidseye-Controller konstruiert werden.
	 * Mehr als appName und request braucht ApiController nicht.
	 */
	abstract class Controller {
		public function __construct(
			protected string $appName,
			protected \OCP\IRequest $request,
		) {
		}
	}

	class Http {
		public const STATUS_OK = 200;
		public const STATUS_BAD_REQUEST = 400;
		public const STATUS_FORBIDDEN = 403;
		public const STATUS_UNPROCESSABLE_ENTITY = 422;
		public const STATUS_INTERNAL_SERVER_ERROR = 500;
	}
}

namespace OCP\AppFramework\Bootstrap {
	interface IBootstrap {}
	interface IBootContext {}
	interface IRegistrationContext {}
}

namespace OCP\AppFramework\Http {
	class DataResponse {
		public function __construct(
			private mixed $daten = null,
			private int $status = 200,
		) {
		}

		public function getData(): mixed {
			return $this->daten;
		}

		public function getStatus(): int {
			return $this->status;
		}
	}
}

namespace Psr\Log {
	/**
	 * Mit den Methoden von PSR-3, damit ein Mock sie kennt: die Controller
	 * protokollieren im Fehlerfall, und ein Mock ohne diese Methoden ließe
	 * jeden Test am Protokollaufruf scheitern statt an der Sache.
	 */
	interface LoggerInterface {
		public function emergency(string|\Stringable $message, array $context = []): void;
		public function alert(string|\Stringable $message, array $context = []): void;
		public function critical(string|\Stringable $message, array $context = []): void;
		public function error(string|\Stringable $message, array $context = []): void;
		public function warning(string|\Stringable $message, array $context = []): void;
		public function notice(string|\Stringable $message, array $context = []): void;
		public function info(string|\Stringable $message, array $context = []): void;
		public function debug(string|\Stringable $message, array $context = []): void;
		public function log($level, string|\Stringable $message, array $context = []): void;
	}
}

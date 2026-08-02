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
	interface IDBConnection {}
	interface IAppConfig {}
	interface IConfig {}
	interface IGroupManager {}
	interface IUserSession {}
	interface IRequest {}
}

namespace OCP\Files {
	interface IRootFolder {}
	interface Folder {}
}

namespace Psr\Log {
	interface LoggerInterface {}
}

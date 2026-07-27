<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

/**
 * Der Import wurde vollständig abgebrochen; es wurden keine Teildaten geschrieben.
 */
class RahmenImportFehler extends \RuntimeException {

	/** @param string[] $fehler */
	public function __construct(
		private array $fehler,
	) {
		parent::__construct(
			'Rahmendatei ist ungültig (' . count($fehler) . ' Fehler): '
			. implode(' | ', array_slice($fehler, 0, 5))
			. (count($fehler) > 5 ? ' …' : '')
		);
	}

	/** @return string[] */
	public function getFehler(): array {
		return $this->fehler;
	}
}

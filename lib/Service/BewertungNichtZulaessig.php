<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

/**
 * Es wurde versucht, eine Einschätzung an einem Knoten zu setzen, der laut
 * Kerncurriculum keine trägt — in aller Regel eine überfachliche Dimension.
 */
class BewertungNichtZulaessig extends \RuntimeException {
}

<?php
/**
 * Minimale Symfony-Console-Stubs für den Test von occ-Befehlen.
 *
 * Wie bei stubs/ocp.php gilt: hier läuft kein Composer. Gebraucht wird von
 * Symfony\Component\Console nur so viel, wie ein kidseye-Befehl berührt —
 * Optionen entgegennehmen, Zeilen schreiben, einen Rückgabewert liefern.
 *
 * Der Ersatz für CommandTester steht als KonsolenLauf unten: er füttert einen
 * Befehl mit Optionen, sammelt die Ausgabe ein und gibt den Rückgabewert
 * zurück. Mehr braucht die Prüfung nicht, und alles davon ist das, was occ
 * später tatsächlich tut.
 */
declare(strict_types=1);

namespace Symfony\Component\Console\Input {
	interface InputInterface {
		public function getOption(string $name): mixed;
	}

	class InputOption {
		public const VALUE_NONE = 1;
		public const VALUE_REQUIRED = 2;
		public const VALUE_OPTIONAL = 4;
	}

	/** Hält die Optionen samt ihrer Vorgabewerte. */
	class ArrayInput implements InputInterface {
		public function __construct(
			private array $optionen = [],
			private array $vorgaben = [],
		) {
		}

		public function getOption(string $name): mixed {
			return $this->optionen[$name] ?? $this->vorgaben[$name] ?? null;
		}
	}
}

namespace Symfony\Component\Console\Output {
	interface OutputInterface {
		public function writeln(string $nachricht): void;
	}

	/** Sammelt die Ausgabe, statt sie zu schreiben. */
	class SammelAusgabe implements OutputInterface {
		private array $zeilen = [];

		public function writeln(string $nachricht): void {
			$this->zeilen[] = $nachricht;
		}

		public function inhalt(): string {
			return implode("\n", $this->zeilen);
		}
	}
}

namespace Symfony\Component\Console\Helper {
	class Table {
		public function __construct(...$egal) {
		}
	}
}

namespace Symfony\Component\Console\Command {

	use Symfony\Component\Console\Input\InputInterface;
	use Symfony\Component\Console\Output\OutputInterface;

	abstract class Command {
		public const SUCCESS = 0;
		public const FAILURE = 1;

		private string $name = '';
		private array $optionen = [];

		/** Die Befehle rufen parent::__construct() auf. */
		public function __construct(?string $name = null) {
			if ($name !== null) {
				$this->name = $name;
			}
		}

		protected function configure(): void {
		}

		abstract protected function execute(InputInterface $input, OutputInterface $output): int;

		public function setName(string $name): static {
			$this->name = $name;
			return $this;
		}

		public function getName(): string {
			return $this->name;
		}

		public function setDescription(string $text): static {
			return $this;
		}

		public function addOption(
			string $name,
			?string $kurz = null,
			?int $art = null,
			string $beschreibung = '',
			mixed $vorgabe = null,
		): static {
			$this->optionen[$name] = $vorgabe;
			return $this;
		}

		/** @return array<string,mixed> Optionsname => Vorgabewert */
		public function vorgaben(): array {
			$this->configure();
			return $this->optionen;
		}

		public function starten(InputInterface $input, OutputInterface $output): int {
			return $this->execute($input, $output);
		}
	}
}

namespace OCA\KidsEye\Tests {

	use Symfony\Component\Console\Command\Command;
	use Symfony\Component\Console\Input\ArrayInput;
	use Symfony\Component\Console\Output\SammelAusgabe;

	/** Der Ersatz für CommandTester. */
	class KonsolenLauf {
		public function __construct(
			public readonly int $rueckgabe,
			public readonly string $ausgabe,
		) {
		}

		public static function von(Command $befehl, array $optionen = []): self {
			$ausgabe = new SammelAusgabe();
			$rueckgabe = $befehl->starten(
				new ArrayInput($optionen, $befehl->vorgaben()),
				$ausgabe
			);

			return new self($rueckgabe, $ausgabe->inhalt());
		}
	}
}

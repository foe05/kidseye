<?php

declare(strict_types=1);

namespace OCA\KidsEye\Command;

use OCA\KidsEye\Service\DiagnoseService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * occ kidseye:pruefen
 *
 * Zeigt den Einrichtungsstand, ohne etwas zu verändern.
 *
 * Getrennt von kidseye:einrichten, weil beide Befehle Verschiedenes tun:
 * einrichten schreibt, pruefen liest ausschließlich. Deshalb ist der Befehl
 * jederzeit gefahrlos aufrufbar — auch auf einer Produktivinstallation, auch
 * mehrfach, auch von jemandem, der gerade nicht sicher ist, was er tut
 * (design.md E1).
 *
 * Der Rückgabewert trägt die Aussage: 0, wenn alle Pflichtpunkte erfüllt
 * sind, sonst 1. Damit ist der Befehl in einem Installationsskript brauchbar.
 */
class Pruefen extends Command {

	public function __construct(
		private DiagnoseService $diagnose,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('kidseye:pruefen')
			->setDescription('Einrichtungsstand prüfen — verändert nichts')
			->addOption(
				'nutzer',
				null,
				InputOption::VALUE_REQUIRED,
				'Nutzerkennung für die nutzerabhängigen Punkte (Ablage der Arbeitsproben)'
			)
			->addOption(
				'output',
				null,
				InputOption::VALUE_REQUIRED,
				'Ausgabeform: plain oder json',
				'plain'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$nutzer = $input->getOption('nutzer');
		$punkte = $this->diagnose->pruefen($nutzer === null ? null : (string)$nutzer);
		$fertig = $this->diagnose->alleErfuellt($punkte);

		if ($input->getOption('output') === 'json') {
			$output->writeln((string)json_encode(
				['erfuellt' => $fertig, 'punkte' => $punkte],
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
			));
			return $fertig ? Command::SUCCESS : Command::FAILURE;
		}

		// Kein Punkt bricht den Durchlauf ab: Wer eine Erstinstallation
		// aufsetzt, will alle offenen Punkte auf einmal sehen, nicht einen
		// nach dem anderen.
		foreach ($punkte as $punkt) {
			$output->writeln($this->zeichen($punkt['zustand']) . ' ' . $punkt['titel']);
			$output->writeln('   ' . $punkt['meldung']);
			if ($punkt['abhilfe'] !== null) {
				$output->writeln('   <info>→ ' . $punkt['abhilfe'] . '</info>');
			}
		}

		$output->writeln('');
		$output->writeln($fertig
			? '<info>kidseye ist einsatzbereit.</info>'
			: '<comment>kidseye ist noch nicht einsatzbereit — siehe die offenen Punkte oben.</comment>');

		return $fertig ? Command::SUCCESS : Command::FAILURE;
	}

	private function zeichen(string $zustand): string {
		return match ($zustand) {
			DiagnoseService::ERFUELLT => '<info>[ok]</info>  ',
			DiagnoseService::OFFEN => '<error>[offen]</error>',
			default => '<comment>[?]</comment>   ',
		};
	}
}

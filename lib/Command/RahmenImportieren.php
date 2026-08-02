<?php

declare(strict_types=1);

namespace OCA\KidsEye\Command;

use OCA\KidsEye\Service\RahmenImportFehler;
use OCA\KidsEye\Service\RahmenImportService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * occ kidseye:rahmen:import [datei] [--pruefen]
 *
 * Ohne Argument wird der mitgelieferte hessische Rahmen eingespielt.
 */
class RahmenImportieren extends Command {

	public function __construct(
		private RahmenImportService $import,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('kidseye:rahmen:import')
			->setDescription('Kompetenzrahmen aus einer JSON-Datei einspielen')
			->addArgument(
				'datei',
				InputArgument::OPTIONAL,
				'Pfad zur Rahmendatei; ohne Angabe der mitgelieferte hessische Rahmen'
			)
			->addOption(
				'pruefen',
				null,
				InputOption::VALUE_NONE,
				'Nur prüfen, nichts schreiben'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$datei = $input->getArgument('datei')
			?? $this->import->mitgelieferterPfad('hessen-primarstufe-2011.json');

		if (!is_readable($datei)) {
			$output->writeln("<error>Datei nicht lesbar: $datei</error>");
			return Command::FAILURE;
		}

		$daten = json_decode((string)file_get_contents($datei), true);
		if (!is_array($daten)) {
			$output->writeln('<error>Kein gültiges JSON: ' . json_last_error_msg() . '</error>');
			return Command::FAILURE;
		}

		$fehler = $this->import->pruefe($daten);
		if ($fehler !== []) {
			$output->writeln('<error>Rahmendatei ist ungültig:</error>');
			foreach ($fehler as $zeile) {
				$output->writeln("  - $zeile");
			}
			return Command::FAILURE;
		}

		if ($input->getOption('pruefen')) {
			$output->writeln('<info>Datei ist gültig.</info> '
				. count($daten['knoten']) . ' Knoten, nichts geschrieben.');
			return Command::SUCCESS;
		}

		try {
			$ergebnis = $this->import->importiere($daten);
		} catch (RahmenImportFehler $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return Command::FAILURE;
		}

		$output->writeln(sprintf(
			'<info>Importiert.</info> Rahmenversion %d, %d Knoten, %d Korrespondenzen.',
			$ergebnis['versionId'],
			$ergebnis['knoten'],
			$ergebnis['korrespondenzen']
		));
		return Command::SUCCESS;
	}
}

<?php

declare(strict_types=1);

namespace OCA\KidsEye\Command;

use OCA\KidsEye\Service\KontextService;
use OCA\KidsEye\Service\MarkerService;
use OCA\KidsEye\Service\RahmenImportService;
use OCA\KidsEye\Service\StammdatenService;
use OCA\KidsEye\Service\ZweckService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * occ kidseye:einrichten
 *
 * Bringt eine frische Installation in einen benutzbaren Zustand:
 * Kompetenzrahmen, Unterrichtskontexte, Verwendungszwecke, Markervorschläge
 * und optional ein Schuljahr.
 *
 * Mehrfach aufrufbar — es wird nur angelegt, was fehlt.
 */
class Einrichten extends Command {

	public function __construct(
		private RahmenImportService $rahmenImport,
		private KontextService $kontexte,
		private ZweckService $zwecke,
		private MarkerService $marker,
		private StammdatenService $stammdaten,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('kidseye:einrichten')
			->setDescription('Rahmen, Kontexte, Zwecke und Markervorschläge anlegen')
			->addOption('schuljahr', null, InputOption::VALUE_REQUIRED,
				'Schuljahr anlegen und aktivieren, z. B. 2026/27')
			->addOption('ohne-rahmen', null, InputOption::VALUE_NONE,
				'Den Kompetenzrahmen nicht einspielen');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		// Reihenfolge ist nicht beliebig: Marker verweisen auf Knoten des
		// Rahmens und auf Verwendungszwecke, Kontexte auf Fächer des Rahmens.
		if (!$input->getOption('ohne-rahmen')) {
			$datei = $this->rahmenImport->mitgelieferterPfad('hessen-primarstufe-2011.json');
			if (!is_readable($datei)) {
				$output->writeln("<error>Rahmendatei nicht lesbar: $datei</error>");
				return Command::FAILURE;
			}
			$daten = json_decode((string)file_get_contents($datei), true);
			$fehler = $this->rahmenImport->pruefe($daten);
			if ($fehler !== []) {
				$output->writeln('<error>Rahmendatei ist ungültig:</error>');
				foreach (array_slice($fehler, 0, 10) as $zeile) {
					$output->writeln("  - $zeile");
				}
				return Command::FAILURE;
			}
			$ergebnis = $this->rahmenImport->importiere($daten);
			$output->writeln(sprintf(
				'<info>Kompetenzrahmen:</info> Version %d mit %d Knoten und %d Korrespondenzen.',
				$ergebnis['versionId'], $ergebnis['knoten'], $ergebnis['korrespondenzen']
			));
		}

		$output->writeln('<info>Unterrichtskontexte:</info> '
			. $this->kontexte->vorgabeAnlegen() . ' neu angelegt.');

		$output->writeln('<info>Verwendungszwecke:</info> '
			. $this->zwecke->vorgabeAnlegen() . ' neu angelegt.');

		$zahl = $this->marker->vorschlaegeAnlegen();
		$output->writeln('<info>Schnellmarker:</info> ' . $zahl . ' Vorschläge angelegt.');
		if ($zahl > 0) {
			$output->writeln('  <comment>Die Marker sind ausdrücklich nur ein Vorschlag. '
				. 'Welche sechs Wörter auf dem Bildschirm stehen, entscheidet die Lehrkraft.</comment>');
		}

		$schuljahr = $input->getOption('schuljahr');
		if ($schuljahr !== null) {
			if ($this->stammdaten->aktivesSchuljahr() === null) {
				$jahr = (int)substr((string)$schuljahr, 0, 4);
				$this->stammdaten->schuljahrAnlegen(
					(string)$schuljahr, $jahr . '-08-01', ($jahr + 1) . '-07-31', true
				);
				$output->writeln('<info>Schuljahr:</info> ' . $schuljahr . ' angelegt und aktiviert.');
			} else {
				$output->writeln('<comment>Es ist bereits ein Schuljahr aktiv — übersprungen.</comment>');
			}
		}

		$output->writeln('');
		$output->writeln('Noch zu erledigen:');
		$output->writeln('  - Nextcloud-Gruppen "kidseye-lehrkraft" und "kidseye-leitung" anlegen');
		$output->writeln('  - Gruppenordner "/Beobachtung" für die Arbeitsproben einrichten');
		$output->writeln('  - Klassen, Kinder und Lehraufträge anlegen (oder per CSV importieren)');
		$output->writeln('  - Aufbewahrungsfristen mit der Datenschutzbeauftragung abstimmen');
		return Command::SUCCESS;
	}
}

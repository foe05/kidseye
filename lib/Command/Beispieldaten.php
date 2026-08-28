<?php

declare(strict_types=1);

namespace OCA\KidsEye\Command;

use OCA\KidsEye\Service\BeispieldatenService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * occ kidseye:beispieldaten
 *
 * Legt eine vollständige Beispielklasse mit Beobachtungsverlauf an — oder
 * räumt sie wieder weg.
 *
 * Gedacht für zwei Dinge, die auf einer leeren Datenbank nicht gehen:
 * die Auswertungen beurteilen (Lücken-Radar und Kompetenz-Übersicht zeigen
 * dort nichts als Leermeldungen) und die Darstellung mit echten Inhalten
 * gegenprüfen — lange Kindernamen in Filterzeilen, volle Klassenbilder,
 * eine Inbox mit Einträgen.
 *
 * Alles Erzeugte trägt „(Beispiel)" im Klassennamen. --entfernen fasst
 * ausschließlich solche Klassen an.
 */
class Beispieldaten extends Command {

	public function __construct(
		private BeispieldatenService $beispiel,
		private IUserManager $nutzer,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('kidseye:beispieldaten')
			->setDescription('Beispielklasse mit Beobachtungsverlauf anlegen oder entfernen')
			->addOption('nutzer', null, InputOption::VALUE_REQUIRED,
				'Nextcloud-Kennung der Lehrkraft, der die Beobachtungen gehören')
			->addOption('klasse', null, InputOption::VALUE_REQUIRED,
				'Name der Klasse ohne Zusatz, Voreinstellung 3d', '3d')
			->addOption('wochen', null, InputOption::VALUE_REQUIRED,
				'Zeitraum rückwärts ab heute, Voreinstellung 8', '8')
			->addOption('entfernen', null, InputOption::VALUE_NONE,
				'Alle erzeugten Beispielklassen samt Beobachtungen löschen');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		if ($input->getOption('entfernen')) {
			return $this->entfernen($output);
		}

		$nutzerId = (string)$input->getOption('nutzer');
		if ($nutzerId === '') {
			$output->writeln('<error>--nutzer fehlt.</error> Die Beobachtungen brauchen eine '
				. 'Lehrkraft; ohne sie wären sie für niemanden sichtbar.');
			return Command::FAILURE;
		}
		if (!$this->nutzer->userExists($nutzerId)) {
			$output->writeln('<error>Unbekannte Nextcloud-Kennung: ' . $nutzerId . '</error>');
			$output->writeln('  Gemeint ist der Anmeldename, nicht der angezeigte Name.');
			return Command::FAILURE;
		}

		$wochen = max(1, (int)$input->getOption('wochen'));

		try {
			$e = $this->beispiel->erzeuge($nutzerId, (string)$input->getOption('klasse'), $wochen);
		} catch (\InvalidArgumentException $ex) {
			$output->writeln('<error>' . $ex->getMessage() . '</error>');
			return Command::FAILURE;
		}

		$output->writeln('<info>Angelegt:</info> Klasse ' . $e['klasse']);
		$output->writeln(sprintf(
			'  %d Kinder, %d Stunden über %d Wochen, %d Beobachtungen.',
			$e['kinder'], $e['stunden'], $wochen, $e['beobachtungen']
		));
		$output->writeln('  Lehraufträge für alle Unterrichtskontexte auf ' . $nutzerId . '.');
		$output->writeln('');
		$output->writeln('Absichtlich ungleich verteilt, damit die Auswertungen etwas zeigen:');
		$output->writeln(sprintf(
			'  - %d Kinder ohne jede Beobachtung, drei weitere nur vereinzelt '
			. '→ Lücken-Radar', $e['ohneBeobachtung']
		));
		$output->writeln('  - Marker schief über die Dimensionen verteilt → Kompetenz-Übersicht');
		$output->writeln('  - rund ein Fünftel mit Freitext → Inbox („Nacharbeiten")');
		$output->writeln('');
		$output->writeln('<comment>Wegräumen mit: occ kidseye:beispieldaten --entfernen</comment>');
		return Command::SUCCESS;
	}

	private function entfernen(OutputInterface $output): int {
		$klassen = $this->beispiel->beispielklassen();
		if ($klassen === []) {
			$output->writeln('Keine Beispielklasse gefunden — nichts zu tun.');
			return Command::SUCCESS;
		}

		foreach ($klassen as $klasse) {
			try {
				$e = $this->beispiel->entferne($klasse['id']);
			} catch (\InvalidArgumentException $ex) {
				$output->writeln('<error>' . $ex->getMessage() . '</error>');
				return Command::FAILURE;
			}
			$output->writeln(sprintf(
				'<info>Entfernt:</info> %s — %d Kinder, %d Beobachtungen.',
				$e['klasse'], $e['kinder'], $e['beobachtungen']
			));
		}
		return Command::SUCCESS;
	}
}

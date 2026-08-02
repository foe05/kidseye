<?php

declare(strict_types=1);

namespace OCA\KidsEye\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use OCP\IDBConnection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * occ kidseye:rahmen:list
 *
 * Zeigt die eingespielten Rahmenversionen mit ihrer Knotenzahl.
 */
class RahmenAuflisten extends Command {

	public function __construct(
		private IDBConnection $db,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('kidseye:rahmen:list')
			->setDescription('Eingespielte Kompetenzrahmen und ihre Versionen anzeigen');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$q = $this->db->getQueryBuilder();
		$q->select('v.id', 'v.kennung', 'v.name', 'v.gueltig_ab', 'v.importiert_am')
			->selectAlias('r.name', 'rahmen_name')
			->selectAlias('r.kennung', 'rahmen_kennung')
			->from('kidseye_rahmen_vers', 'v')
			->innerJoin('v', 'kidseye_rahmen', 'r', 'r.id = v.rahmen_id')
			->orderBy('r.kennung')
			->addOrderBy('v.kennung');

		$treffer = $q->executeQuery();
		$zeilen = [];
		while ($zeile = $treffer->fetch()) {
			$zeilen[] = [
				'id' => (int)$zeile['id'],
				'rahmen' => $zeile['rahmen_kennung'],
				'version' => $zeile['kennung'],
				'name' => $zeile['name'],
				'gueltigAb' => $zeile['gueltig_ab'],
				'knoten' => $this->knotenZahl((int)$zeile['id']),
			];
		}
		$treffer->closeCursor();

		if ($zeilen === []) {
			$output->writeln('<comment>Kein Kompetenzrahmen eingespielt.</comment>');
			$output->writeln('Einspielen mit: <info>occ kidseye:rahmen:import</info>');
			return Command::SUCCESS;
		}

		$tabelle = new Table($output);
		$tabelle->setHeaders(['ID', 'Rahmen', 'Version', 'Name', 'Gültig ab', 'Knoten']);
		foreach ($zeilen as $zeile) {
			$tabelle->addRow(array_values($zeile));
		}
		$tabelle->render();
		return Command::SUCCESS;
	}

	private function knotenZahl(int $versionId): int {
		$q = $this->db->getQueryBuilder();
		$q->select($q->func()->count('*'))
			->from('kidseye_knoten')
			->where($q->expr()->eq('version_id', $q->createNamedParameter($versionId)));
		$treffer = $q->executeQuery();
		$zahl = (int)$treffer->fetchOne();
		$treffer->closeCursor();
		return $zahl;
	}
}

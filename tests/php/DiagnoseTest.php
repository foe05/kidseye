<?php

declare(strict_types=1);

namespace OCA\KidsEye\Tests;

use OCA\KidsEye\Service\AblageService;
use OCA\KidsEye\Service\DiagnoseService;
use OCA\KidsEye\Service\RahmenService;
use OCA\KidsEye\Service\RollenService;
use OCA\KidsEye\Service\StammdatenService;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

/**
 * Der Einrichtungsstand (design.md E2, E3).
 *
 * Was hier geprüft wird, entscheidet darüber, ob eine Erstinstallation
 * verständlich scheitert oder rätselhaft. Jeder offene Punkt muss sagen, was
 * zu tun ist — sonst ist die Prüfung nur eine zweite Art, ratlos zu sein.
 */
class DiagnoseTest extends TestCase {

	private function dienst(
		bool $tabellenDa = true,
		?int $versionId = 1,
		?array $schuljahr = ['id' => 1, 'kennung' => '2026/27'],
		bool $gruppenDa = true,
		bool $lehrauftragDa = true,
		bool $ablageOk = true,
		bool $vollbild = true,
	): DiagnoseService {
		$db = $this->createMock(IDBConnection::class);
		$db->method('tableExists')->willReturn($tabellenDa);

		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueBool')->willReturn($vollbild);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(dirname(__DIR__, 2));

		$rahmen = $this->createMock(RahmenService::class);
		$rahmen->method('aktiveVersionId')->willReturn($versionId);

		$stammdaten = $this->createMock(StammdatenService::class);
		$stammdaten->method('aktivesSchuljahr')->willReturn($schuljahr);
		$stammdaten->method('lehrauftraegeVorhanden')->willReturn($lehrauftragDa);

		$rollen = $this->createMock(RollenService::class);
		$rollen->method('gruppenVorhanden')->willReturn([
			'lehrkraft' => ['name' => 'kidseye-lehrkraft', 'existiert' => $gruppenDa],
			'leitung' => ['name' => 'kidseye-leitung', 'existiert' => $gruppenDa],
		]);

		$ablage = $this->createMock(AblageService::class);
		$ablage->method('pruefeAblage')->willReturn($ablageOk
			? ['ok' => true, 'pfad' => 'Beobachtung', 'grund' => null]
			: ['ok' => false, 'pfad' => 'Beobachtung',
				'grund' => 'Der Ordner "Beobachtung" existiert nicht.']);

		return new DiagnoseService($db, $config, $appManager, $rahmen, $stammdaten, $rollen, $ablage);
	}

	/** @return array<string,array> */
	private function nachKennung(array $punkte): array {
		$karte = [];
		foreach ($punkte as $punkt) {
			$karte[$punkt['kennung']] = $punkt;
		}
		return $karte;
	}

	// ------------------------------------------------------------ Tabellen

	public function testDieTabellenlisteStimmtMitDenMigrationenUeberein(): void {
		// Genau der Abgleich, der fehlte, als die Anleitung von 21 Tabellen
		// sprach und die Migrationen 25 anlegten.
		$ausMigrationen = [];
		foreach (glob(dirname(__DIR__, 2) . '/lib/Migration/*.php') as $datei) {
			preg_match_all(
				"/createTable\('([a-z0-9_]+)'\)/",
				(string)file_get_contents($datei),
				$treffer
			);
			$ausMigrationen = array_merge($ausMigrationen, $treffer[1]);
		}

		sort($ausMigrationen);
		$gepflegt = DiagnoseService::TABELLEN;
		sort($gepflegt);

		$this->assertSame(
			$ausMigrationen,
			$gepflegt,
			'DiagnoseService::TABELLEN weicht von den createTable-Aufrufen in lib/Migration/ ab.'
		);
	}

	public function testFehlendeTabelleWirdBeimNamenGenannt(): void {
		$punkte = $this->nachKennung($this->dienst(tabellenDa: false)->pruefen('anna'));

		$this->assertSame(DiagnoseService::OFFEN, $punkte['tabellen']['zustand']);
		$this->assertStringContainsString('kidseye_beobachtung', $punkte['tabellen']['meldung']);
		$this->assertStringContainsString('app:enable', (string)$punkte['tabellen']['abhilfe']);
	}

	// --------------------------------------------------- Vollständiger Stand

	public function testVollstaendigEingerichteteInstallationIstErfuellt(): void {
		$dienst = $this->dienst();
		$punkte = $dienst->pruefen('anna');

		foreach ($punkte as $punkt) {
			$this->assertSame(
				DiagnoseService::ERFUELLT,
				$punkt['zustand'],
				'Punkt „' . $punkt['kennung'] . '" ist nicht erfüllt: ' . $punkt['meldung']
			);
			$this->assertNull($punkt['abhilfe'], 'Ein erfüllter Punkt braucht keine Abhilfe.');
		}

		$this->assertTrue($dienst->alleErfuellt($punkte));
	}

	public function testJederPunktTraegtEineMeldung(): void {
		foreach ($this->dienst()->pruefen('anna') as $punkt) {
			$this->assertNotSame('', $punkt['meldung']);
			$this->assertNotSame('', $punkt['titel']);
		}
	}

	// ------------------------------------------------------- Einzelne Lücken

	public function testFehlenderRahmenNenntDenEinrichtenBefehl(): void {
		$dienst = $this->dienst(versionId: null);
		$punkte = $dienst->pruefen('anna');
		$karte = $this->nachKennung($punkte);

		$this->assertSame(DiagnoseService::OFFEN, $karte['rahmen']['zustand']);
		$this->assertStringContainsString('kidseye:einrichten', (string)$karte['rahmen']['abhilfe']);
		$this->assertFalse($dienst->alleErfuellt($punkte));

		// Und nur dieser eine Punkt ist offen.
		$offen = array_filter($punkte, fn ($p) => $p['zustand'] === DiagnoseService::OFFEN);
		$this->assertCount(1, $offen);
	}

	public function testFehlenderLehrauftragWirdBenannt(): void {
		$karte = $this->nachKennung($this->dienst(lehrauftragDa: false)->pruefen('anna'));

		$this->assertSame(DiagnoseService::OFFEN, $karte['lehrauftraege']['zustand']);
		$this->assertStringContainsString('keine Stunde', $karte['lehrauftraege']['meldung']);
	}

	public function testFehlendeGruppeWirdBenannt(): void {
		$karte = $this->nachKennung($this->dienst(gruppenDa: false)->pruefen('anna'));

		$this->assertSame(DiagnoseService::OFFEN, $karte['gruppen']['zustand']);
		$this->assertStringContainsString('kidseye-lehrkraft', $karte['gruppen']['meldung']);
	}

	public function testAbgeschaltetesVollbildNenntDenConfigBefehl(): void {
		$karte = $this->nachKennung($this->dienst(vollbild: false)->pruefen('anna'));

		$this->assertSame(DiagnoseService::OFFEN, $karte['vollbild']['zustand']);
		$this->assertStringContainsString(
			'theming.standalone_window.enabled',
			(string)$karte['vollbild']['abhilfe']
		);
	}

	// ------------------------------------------------------- Ohne Nutzer

	public function testAblageOhneNutzerIstNichtPruefbar(): void {
		$dienst = $this->dienst();
		$punkte = $dienst->pruefen(null);
		$karte = $this->nachKennung($punkte);

		$this->assertSame(DiagnoseService::NICHT_PRUEFBAR, $karte['ablage']['zustand']);
		$this->assertStringContainsString('--nutzer', (string)$karte['ablage']['abhilfe']);
	}

	public function testNichtPruefbarVerhindertDenGesamterfolgNicht(): void {
		// Sonst könnte ein Aufruf ohne Nutzer nie mit 0 enden und wäre in
		// einem Installationsskript nicht zu gebrauchen.
		$dienst = $this->dienst();

		$this->assertTrue($dienst->alleErfuellt($dienst->pruefen(null)));
	}

	public function testNichtErreichbareAblageIstOffenNichtUnpruefbar(): void {
		$dienst = $this->dienst(ablageOk: false);
		$punkte = $dienst->pruefen('anna');
		$karte = $this->nachKennung($punkte);

		$this->assertSame(DiagnoseService::OFFEN, $karte['ablage']['zustand']);
		$this->assertFalse($dienst->alleErfuellt($punkte));
	}

	// ------------------------------------------------------------- Symbol

	public function testSymbolPunktPrueftDenAuslieferstand(): void {
		// Läuft gegen das echte img/-Verzeichnis des Repos.
		$karte = $this->nachKennung($this->dienst()->pruefen('anna'));

		$this->assertSame(DiagnoseService::ERFUELLT, $karte['symbol']['zustand']);
	}
}

<?php

declare(strict_types=1);

namespace OCA\KidsEye\Tests;

use OCA\KidsEye\Command\Pruefen;
use OCA\KidsEye\Service\DiagnoseService;
use PHPUnit\Framework\TestCase;

/**
 * occ kidseye:pruefen (design.md E1).
 *
 * Der Rückgabewert ist der eigentliche Gegenstand: Er entscheidet, ob der
 * Befehl in einem Installationsskript brauchbar ist. Die Ausgabe muss jeden
 * offenen Punkt nennen — nicht nur den ersten.
 */
class PruefenTest extends TestCase {

	private function punkt(string $kennung, string $zustand, bool $pflicht = true): array {
		return [
			'kennung' => $kennung,
			'titel' => 'Titel ' . $kennung,
			'pflicht' => $pflicht,
			'zustand' => $zustand,
			'meldung' => 'Meldung zu ' . $kennung,
			'abhilfe' => $zustand === DiagnoseService::ERFUELLT ? null : 'Abhilfe zu ' . $kennung,
		];
	}

	private function befehl(array $punkte): Pruefen {
		$diagnose = $this->createMock(DiagnoseService::class);
		$diagnose->method('pruefen')->willReturn($punkte);
		$diagnose->method('alleErfuellt')->willReturnCallback(
			fn (array $p) => !array_filter(
				$p,
				fn ($x) => $x['pflicht'] && $x['zustand'] === DiagnoseService::OFFEN
			)
		);

		return new Pruefen($diagnose);
	}

	public function testHeisstKidseyePruefen(): void {
		$befehl = $this->befehl([]);
		$befehl->vorgaben();

		$this->assertSame('kidseye:pruefen', $befehl->getName());
	}

	public function testVollstaendigerStandEndetMitNull(): void {
		$lauf = KonsolenLauf::von($this->befehl([
			$this->punkt('tabellen', DiagnoseService::ERFUELLT),
			$this->punkt('rahmen', DiagnoseService::ERFUELLT),
		]));

		$this->assertSame(0, $lauf->rueckgabe);
		$this->assertStringContainsString('einsatzbereit', $lauf->ausgabe);
	}

	public function testOffenerPunktEndetMitEins(): void {
		$lauf = KonsolenLauf::von($this->befehl([
			$this->punkt('tabellen', DiagnoseService::ERFUELLT),
			$this->punkt('rahmen', DiagnoseService::OFFEN),
		]));

		$this->assertSame(1, $lauf->rueckgabe);
		$this->assertStringContainsString('noch nicht einsatzbereit', $lauf->ausgabe);
	}

	public function testNichtPruefbarVerhindertNullNicht(): void {
		$lauf = KonsolenLauf::von($this->befehl([
			$this->punkt('ablage', DiagnoseService::NICHT_PRUEFBAR),
		]));

		$this->assertSame(0, $lauf->rueckgabe);
	}

	public function testKeinPunktBrichtDenDurchlaufAb(): void {
		// Wer eine Erstinstallation aufsetzt, will alle offenen Punkte auf
		// einmal sehen, nicht einen nach dem anderen.
		$lauf = KonsolenLauf::von($this->befehl([
			$this->punkt('tabellen', DiagnoseService::OFFEN),
			$this->punkt('rahmen', DiagnoseService::OFFEN),
			$this->punkt('gruppen', DiagnoseService::OFFEN),
		]));

		foreach (['tabellen', 'rahmen', 'gruppen'] as $kennung) {
			$this->assertStringContainsString('Meldung zu ' . $kennung, $lauf->ausgabe);
			$this->assertStringContainsString('Abhilfe zu ' . $kennung, $lauf->ausgabe);
		}
	}

	public function testErfuellterPunktZeigtKeineAbhilfe(): void {
		$lauf = KonsolenLauf::von($this->befehl([
			$this->punkt('tabellen', DiagnoseService::ERFUELLT),
		]));

		$this->assertStringNotContainsString('→', $lauf->ausgabe);
	}

	public function testJsonAusgabeIstGueltigUndVollstaendig(): void {
		$lauf = KonsolenLauf::von(
			$this->befehl([
				$this->punkt('tabellen', DiagnoseService::ERFUELLT),
				$this->punkt('rahmen', DiagnoseService::OFFEN),
			]),
			['output' => 'json']
		);

		$gelesen = json_decode($lauf->ausgabe, true);

		$this->assertIsArray($gelesen, 'Die Ausgabe muss gültiges JSON sein.');
		$this->assertFalse($gelesen['erfuellt']);
		$this->assertCount(2, $gelesen['punkte']);

		foreach ($gelesen['punkte'] as $punkt) {
			$this->assertArrayHasKey('kennung', $punkt);
			$this->assertArrayHasKey('zustand', $punkt);
			$this->assertArrayHasKey('meldung', $punkt);
		}
	}

	public function testJsonAusgabeTraegtDenselbenRueckgabewert(): void {
		$lauf = KonsolenLauf::von(
			$this->befehl([$this->punkt('rahmen', DiagnoseService::OFFEN)]),
			['output' => 'json']
		);

		$this->assertSame(1, $lauf->rueckgabe);
	}

	public function testNutzerOptionWirdDurchgereicht(): void {
		$diagnose = $this->createMock(DiagnoseService::class);
		$diagnose->expects($this->once())
			->method('pruefen')
			->with('anna')
			->willReturn([]);
		$diagnose->method('alleErfuellt')->willReturn(true);

		KonsolenLauf::von(new Pruefen($diagnose), ['nutzer' => 'anna']);
	}

	public function testOhneNutzerOptionWirdNullUebergeben(): void {
		$diagnose = $this->createMock(DiagnoseService::class);
		$diagnose->expects($this->once())
			->method('pruefen')
			->with(null)
			->willReturn([]);
		$diagnose->method('alleErfuellt')->willReturn(true);

		KonsolenLauf::von(new Pruefen($diagnose));
	}
}

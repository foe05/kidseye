<?php

declare(strict_types=1);

namespace OCA\KidsEye\Tests;

use OCA\KidsEye\Service\AblageService;
use OCA\KidsEye\Service\PdfService;
use PHPUnit\Framework\TestCase;

/**
 * Kapitel 8.5 — echter PDF-Export ohne zusätzliche Abhängigkeit.
 *
 * Geprüft wird die Struktur des erzeugten Dokuments: ein PDF, das kein Leser
 * öffnen kann, hilft vor keinem Elterngespräch.
 */
class PdfTest extends TestCase {

	private function dienst(?AblageService $ablage = null): PdfService {
		return new PdfService($ablage ?? $this->createMock(AblageService::class));
	}

	private function bericht(array $ueberschreiben = []): array {
		return array_merge([
			'art' => 'bericht',
			'kind' => ['id' => 1, 'anzeige' => 'Mia Müller', 'klasse' => '3a'],
			'zeitraum' => ['von' => '2026-02-01', 'bis' => null],
			'erstelltAm' => '2026-07-27T10:00:00+00:00',
			'erstelltVon' => 'lehrerin',
			'eintraege' => [[
				'id' => 1,
				'erfasstAm' => '2026-07-20T09:37:00+00:00',
				'kontext' => 'Mathematik',
				'sichtbarkeit' => 'akte',
				'text' => 'Mia hat Jonas beim Rechenweg geholfen und ihn Schritt für Schritt erklärt.',
				'markerText' => null,
				'knoten' => [['bezeichnung' => 'Rücksichtnahme und Solidarität']],
				'nachtraege' => [],
				'dateien' => [],
			]],
			'uebersicht' => ['bereiche' => [[
				'ebene' => 'ueberfachlich',
				'fach' => null,
				'knoten' => [['bezeichnung' => 'Rücksichtnahme und Solidarität', 'anzahl' => 7]],
				'summe' => 7,
			]]],
			'hinweise' => ['Belege, keine Bewertung.'],
		], $ueberschreiben);
	}

	public function testErzeugtEinGueltigesPdf(): void {
		$pdf = $this->dienst()->erzeuge($this->bericht());

		$this->assertStringStartsWith('%PDF-1.4', $pdf);
		$this->assertStringEndsWith("%%EOF\n", $pdf);
		$this->assertStringContainsString('/Type /Catalog', $pdf);
		$this->assertStringContainsString('/Type /Pages', $pdf);
		$this->assertStringContainsString('/Type /Page ', $pdf);
		$this->assertStringContainsString('trailer', $pdf);
		$this->assertStringContainsString('startxref', $pdf);
	}

	public function testDieQuerverweistabelleStimmtMitDenObjektenUeberein(): void {
		$pdf = $this->dienst()->erzeuge($this->bericht());

		preg_match('/xref\s+0 (\d+)/', $pdf, $treffer);
		$angekuendigt = (int)$treffer[1];
		$vorhanden = preg_match_all('/^\d+ 0 obj$/m', $pdf);

		// Die Tabelle zählt den freien Eintrag 0 mit
		$this->assertSame($vorhanden + 1, $angekuendigt);
	}

	public function testJedesObjektIstAnDerAngegebenenStelleZuFinden(): void {
		$pdf = $this->dienst()->erzeuge($this->bericht());

		preg_match('/xref\s+0 \d+\s+0000000000 65535 f \n(.*?)trailer/s', $pdf, $treffer);
		preg_match_all('/(\d{10}) 00000 n/', $treffer[1], $stellen);

		foreach ($stellen[1] as $nummer => $stelle) {
			$this->assertSame(
				($nummer + 1) . ' 0 obj',
				substr($pdf, (int)$stelle, strlen(($nummer + 1) . ' 0 obj')),
				'Objekt ' . ($nummer + 1) . ' steht nicht an der angegebenen Stelle.'
			);
		}
	}

	public function testUmlauteWerdenNachWinAnsiUebersetzt(): void {
		$pdf = $this->dienst()->erzeuge($this->bericht());

		$this->assertStringContainsString('/Encoding /WinAnsiEncoding', $pdf);
		// „Müller" muss als Latin-1-Byte auftauchen, nicht als UTF-8-Folge
		$this->assertStringContainsString('M' . chr(0xFC) . 'ller', $pdf);
		$this->assertStringNotContainsString('MÃ¼ller', $pdf);
	}

	public function testKlammernImTextWerdenMaskiert(): void {
		$bericht = $this->bericht();
		$bericht['eintraege'][0]['text'] = 'Klammer (auf) und Schrägstrich \\ dazu';

		$pdf = $this->dienst()->erzeuge($bericht);

		$this->assertStringContainsString('\\(auf\\)', $pdf);
		$this->assertStringContainsString('\\\\', $pdf);
	}

	public function testLangeBerichteBekommenMehrereSeiten(): void {
		$eintraege = [];
		for ($i = 0; $i < 60; $i++) {
			$eintraege[] = [
				'id' => $i,
				'erfasstAm' => '2026-07-20T09:00:00+00:00',
				'kontext' => 'Deutsch',
				'sichtbarkeit' => 'privat',
				'text' => 'Beobachtung Nummer ' . $i . ' mit ausreichend Text, damit die '
					. 'Zeile umbricht und die Seite tatsächlich voll wird.',
				'markerText' => null,
				'knoten' => [],
				'nachtraege' => [],
				'dateien' => [],
			];
		}
		$pdf = $this->dienst()->erzeuge($this->bericht(['eintraege' => $eintraege]));

		$this->assertGreaterThan(1, preg_match_all('/\/Type \/Page /', $pdf));
		$this->assertMatchesRegularExpression('/\/Count [2-9]\d*/', $pdf);
	}

	public function testBettetEineArbeitsprobeAlsJpegEin(): void {
		// 1x1-Pixel-JPEG, damit der SOF-Marker echt geparst wird
		$jpeg = base64_decode(
			'/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
			. 'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAA'
			. 'AAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='
		);
		$ablage = $this->createMock(AblageService::class);
		$ablage->method('inhalt')->willReturn($jpeg);

		$bericht = $this->bericht();
		$bericht['eintraege'][0]['dateien'] = [['fileId' => 42, 'dateiname' => 'probe.jpg']];

		$pdf = $this->dienst($ablage)->erzeuge($bericht, 'lehrerin');

		$this->assertStringContainsString('/Subtype /Image', $pdf);
		// Die JPEG-Daten wandern unverändert hinein, ohne Neukodierung
		$this->assertStringContainsString('/Filter /DCTDecode', $pdf);
		$this->assertStringContainsString('/XObject', $pdf);
		$this->assertStringContainsString($jpeg, $pdf);
	}

	public function testOhneNutzerKennungBleibenBilderAussen(): void {
		$bericht = $this->bericht();
		$bericht['eintraege'][0]['dateien'] = [['fileId' => 42, 'dateiname' => 'probe.jpg']];

		$pdf = $this->dienst()->erzeuge($bericht, null);

		$this->assertStringNotContainsString('/Subtype /Image', $pdf);
	}

	public function testAuskunftBekommtDenRichtigenTitel(): void {
		$pdf = $this->dienst()->erzeuge($this->bericht(['art' => 'auskunft']));

		$this->assertStringContainsString('Auskunft nach Artikel 15 DSGVO', $pdf);
	}

	public function testMappeNenntIhrenVerwendungszweck(): void {
		$pdf = $this->dienst()->erzeuge($this->bericht([
			'art' => 'mappe',
			'zweck' => ['name' => 'Förderplan', 'kennung' => 'foerderplan'],
		]));

		$this->assertStringContainsString('F' . chr(0xF6) . 'rderplan', $pdf);
	}
}

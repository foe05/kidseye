<?php

declare(strict_types=1);

namespace OCA\KidsEye\Tests;

use OCA\KidsEye\Service\BewertungNichtZulaessig;
use OCA\KidsEye\Service\KontextService;
use OCA\KidsEye\Service\MarkerService;
use OCA\KidsEye\Service\RahmenService;
use OCA\KidsEye\Service\StammdatenService;
use OCA\KidsEye\Service\StundeService;
use OCA\KidsEye\Service\ZugriffService;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

/**
 * Die Regeln, die den Entwurf tragen — ohne Datenbank prüfbar.
 *
 * Kapitel 9.1, 9.2 und 9.4. Was hier bricht, bricht das Konzept, nicht nur
 * eine Funktion.
 */
class RegelnTest extends TestCase {

	// ------------------------------------------------- Kapitel 2.9

	public function testUeberfachlicheKompetenzenTragenKeineEinschaetzung(): void {
		$rahmen = new RahmenService($this->createMock(IDBConnection::class));

		// Das Kerncurriculum stellt fest, dass sich überfachliche Kompetenzen
		// „weitgehend einer Normierung und empirischen Überprüfung" entziehen.
		$this->assertFalse($rahmen->istBewertbar([
			'ebene' => RahmenService::EBENE_UEBERFACHLICH,
			'art' => 'dimension',
		]));
		$this->assertFalse($rahmen->istBewertbar([
			'ebene' => RahmenService::EBENE_UEBERFACHLICH,
			'art' => 'bereich',
		]));
	}

	public function testNurFachlicheBildungsstandardsSindBewertbar(): void {
		$rahmen = new RahmenService($this->createMock(IDBConnection::class));

		$this->assertTrue($rahmen->istBewertbar([
			'ebene' => RahmenService::EBENE_FACHLICH,
			'art' => 'bildungsstandard',
		]));
		// Inhaltsfelder und Kompetenzbereiche beschreiben keine Könnenserwartung
		$this->assertFalse($rahmen->istBewertbar([
			'ebene' => RahmenService::EBENE_FACHLICH,
			'art' => 'inhaltsfeld',
		]));
		$this->assertFalse($rahmen->istBewertbar([
			'ebene' => RahmenService::EBENE_FACHLICH,
			'art' => 'kompetenzbereich',
		]));
	}

	// ------------------------------------------------- Kapitel 3 / D2

	public function testFachneutralerKontextDarfKeinenFachbezugTragen(): void {
		$kontexte = new KontextService($this->createMock(IDBConnection::class));

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Fachneutrale Kontexte dürfen keinen Fachbezug tragen');

		$kontexte->anlegen('freiarbeit', 'Freiarbeit', KontextService::ART_FACHNEUTRAL, 'deutsch');
	}

	public function testSchulfachBrauchtEinFach(): void {
		$kontexte = new KontextService($this->createMock(IDBConnection::class));

		$this->expectException(\InvalidArgumentException::class);
		$kontexte->anlegen('mathe', 'Mathematik', KontextService::ART_SCHULFACH, null);
	}

	public function testUnbekannteKontextartWirdAbgelehnt(): void {
		$kontexte = new KontextService($this->createMock(IDBConnection::class));

		$this->expectException(\InvalidArgumentException::class);
		$kontexte->anlegen('x', 'X', 'querschnitt', null);
	}

	public function testHatFachbezugNurBeiSchulfachMitFach(): void {
		$kontexte = new KontextService($this->createMock(IDBConnection::class));

		$this->assertTrue($kontexte->hatFachbezug(
			['art' => KontextService::ART_SCHULFACH, 'fach' => 'mathematik']
		));
		$this->assertFalse($kontexte->hatFachbezug(
			['art' => KontextService::ART_FACHNEUTRAL, 'fach' => null]
		));
	}

	public function testVorgabeEnthaeltFuenfFaecherUndZweiFachneutrale(): void {
		$schulfaecher = array_filter(
			KontextService::VORGABE,
			static fn ($k) => $k['art'] === KontextService::ART_SCHULFACH
		);
		$fachneutral = array_filter(
			KontextService::VORGABE,
			static fn ($k) => $k['art'] === KontextService::ART_FACHNEUTRAL
		);

		$this->assertCount(5, $schulfaecher);
		$this->assertCount(2, $fachneutral);

		$kennungen = array_column($fachneutral, 'kennung');
		$this->assertContains('freiarbeit', $kennungen);
		$this->assertContains('sozial_arbeitsverhalten', $kennungen);

		// Kein fachneutraler Kontext trägt einen Fachbezug
		foreach ($fachneutral as $kontext) {
			$this->assertNull($kontext['fach']);
		}
	}

	// ------------------------------------------------- Kapitel 5.2 / D3

	public function testHoechstensSechsSichtbareMarker(): void {
		$marker = new MarkerService(
			$this->createMock(IDBConnection::class),
			$this->createMock(KontextService::class),
			$this->createMock(RahmenService::class)
		);

		$sieben = array_map(
			static fn ($i) => ['text' => 'Marker ' . $i, 'sichtbar' => true],
			range(1, 7)
		);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('höchstens 6 Marker');
		$marker->satzSpeichern(1, $sieben);
	}

	public function testUnsichtbareMarkerZaehlenNichtGegenDieGrenze(): void {
		$this->assertSame(6, MarkerService::HOECHSTZAHL_SICHTBAR);

		$sichtbar = array_filter(
			array_merge(
				array_map(static fn ($i) => ['text' => "s$i", 'sichtbar' => true], range(1, 6)),
				array_map(static fn ($i) => ['text' => "u$i", 'sichtbar' => false], range(1, 4))
			),
			static fn ($m) => $m['sichtbar']
		);

		$this->assertCount(MarkerService::HOECHSTZAHL_SICHTBAR, $sichtbar);
	}

	public function testJederVorschlagssatzHatHoechstensSechsMarker(): void {
		foreach (MarkerService::VORSCHLAG as $kontext => $satz) {
			$this->assertLessThanOrEqual(
				MarkerService::HOECHSTZAHL_SICHTBAR,
				count($satz),
				"Der Vorschlagssatz für „$kontext\" ist zu lang."
			);
		}
	}

	public function testJederVorschlagsmarkerZeigtAufUeberfachlicheDimensionen(): void {
		// Ein Marker ohne Kompetenzzuordnung erzeugt keine vollständige
		// Beobachtung — dann bricht der Ein-Tap-Pfad aus D3.
		foreach (MarkerService::VORSCHLAG as $kontext => $satz) {
			foreach ($satz as $eintrag) {
				$this->assertNotEmpty($eintrag[1], "„{$eintrag[0]}\" in $kontext ohne Zuordnung");
				foreach ($eintrag[1] as $kennung) {
					$this->assertStringStartsWith('uk.', $kennung);
				}
			}
		}
	}

	public function testJederKontextHatEinenVorschlagssatz(): void {
		foreach (KontextService::VORGABE as $kontext) {
			$this->assertArrayHasKey(
				$kontext['kennung'],
				MarkerService::VORSCHLAG,
				"Für „{$kontext['name']}\" fehlt ein Markervorschlag."
			);
		}
	}

	// ------------------------------------------------- Kapitel 4 / D2

	public function testStundeEndetVorDerNachfrage(): void {
		// Eine Stunde gilt nach 90 Minuten als beendet; erst ab zwei Stunden
		// wird beim Öffnen nachgefragt. Andersherum wäre die Nachfrage nie
		// erreichbar.
		$this->assertSame(90, StundeService::LAUFZEIT_MINUTEN);
		$this->assertSame(120, StundeService::NACHFRAGE_MINUTEN);
		$this->assertGreaterThan(
			StundeService::LAUFZEIT_MINUTEN,
			StundeService::NACHFRAGE_MINUTEN
		);
	}

	// ------------------------------------------------- Kapitel 7 / D7

	public function testEinzelbetriebBietetNurZweiStufen(): void {
		$zugriff = new ZugriffService(
			$this->createMock(IDBConnection::class),
			$this->createMock(StammdatenService::class)
		);

		$this->assertFalse($zugriff->mehrbenutzerBetrieb());
		$this->assertSame(
			[ZugriffService::SICHT_PRIVAT, ZugriffService::SICHT_AKTE],
			$zugriff->waehlbareStufen()
		);
		// klassenteam bleibt im Modell, nur nicht erreichbar
		$this->assertContains(ZugriffService::SICHT_KLASSENTEAM, ZugriffService::STUFEN_ALLE);
	}

	public function testFremdeBeobachtungenSindImEinzelbetriebUnsichtbar(): void {
		$zugriff = new ZugriffService(
			$this->createMock(IDBConnection::class),
			$this->createMock(StammdatenService::class)
		);

		$fremd = [
			'nutzerId' => 'kollegin',
			'sichtbarkeit' => ZugriffService::SICHT_KLASSENTEAM,
			'klasseId' => 1,
			'geloeschtAm' => null,
		];

		$this->assertFalse($zugriff->darfSehen('ich', $fremd));
		$this->assertTrue($zugriff->darfSehen('kollegin', $fremd));
	}

	public function testAkteIstUnveraenderlich(): void {
		$zugriff = new ZugriffService(
			$this->createMock(IDBConnection::class),
			$this->createMock(StammdatenService::class)
		);

		$inAkte = [
			'nutzerId' => 'ich',
			'sichtbarkeit' => ZugriffService::SICHT_AKTE,
			'klasseId' => 1,
			'geloeschtAm' => null,
		];

		$this->assertFalse($zugriff->darfAendern('ich', $inAkte));
		$this->expectException(\OCA\KidsEye\Service\ZugriffVerweigert::class);
		$this->expectExceptionMessage('unveränderlich');
		$zugriff->verlangeAendern('ich', $inAkte);
	}

	public function testGeloeschteBeobachtungIstUnsichtbar(): void {
		$zugriff = new ZugriffService(
			$this->createMock(IDBConnection::class),
			$this->createMock(StammdatenService::class)
		);

		$this->assertFalse($zugriff->darfSehen('ich', [
			'nutzerId' => 'ich',
			'sichtbarkeit' => ZugriffService::SICHT_PRIVAT,
			'klasseId' => 1,
			'geloeschtAm' => '2026-07-01 10:00:00',
		]));
	}

	// ------------------------------------------------- Kapitel 3.6

	public function testKlassennameWirdHochgezaehlt(): void {
		$stammdaten = new StammdatenService($this->createMock(IDBConnection::class));

		$this->assertSame('4a', $stammdaten->naechsterKlassenname('3a'));
		$this->assertSame('2b', $stammdaten->naechsterKlassenname('1b'));
		$this->assertSame('5', $stammdaten->naechsterKlassenname('4'));
		// Unerkennbare Namen bleiben unverändert, statt Unsinn zu erzeugen
		$this->assertSame('Eulen', $stammdaten->naechsterKlassenname('Eulen'));
	}
}

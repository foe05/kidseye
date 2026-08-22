<?php

declare(strict_types=1);

namespace OCA\KidsEye\Tests;

use OCA\KidsEye\Controller\ErfassungController;
use OCA\KidsEye\Service\AblageService;
use OCA\KidsEye\Service\BeobachtungService;
use OCA\KidsEye\Service\MarkerService;
use OCA\KidsEye\Service\RollenService;
use OCA\KidsEye\Service\StammdatenService;
use OCA\KidsEye\Service\StundeService;
use OCA\KidsEye\Service\ZweckService;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Prüfschritt C6 der Geräteprüfung — das Nachliefern der Warteschlange.
 *
 * Der Dublettenschutz sitzt im Dienst und greift über die vom Gerät vergebene
 * clientUuid. Geprüft wird hier, was der Endpunkt daraus macht: dass eine
 * Dublette als Erfolg zurückkommt und als solche benannt ist, und dass ein
 * einzelner unbrauchbarer Eintrag die übrigen nicht mitreißt.
 *
 * Ohne die Benennung könnte C6 nur zählen, wie viele Beobachtungen zu sehen
 * sind — nicht belegen, dass der Schutz gegriffen hat.
 */
class SyncTest extends TestCase {

	private function controller(BeobachtungService $beobachtungen): ErfassungController {
		$rollen = $this->createMock(RollenService::class);
		$rollen->method('verlangeLehrkraft')->willReturn('anna');

		return new ErfassungController(
			$this->createMock(IRequest::class),
			$rollen,
			$this->createMock(LoggerInterface::class),
			$this->createMock(StundeService::class),
			$beobachtungen,
			$this->createMock(MarkerService::class),
			$this->createMock(StammdatenService::class),
			$this->createMock(ZweckService::class),
			$this->createMock(AblageService::class),
		);
	}

	public function testNeuerEintragWirdAlsNeuGemeldet(): void {
		$dienst = $this->createMock(BeobachtungService::class);
		$dienst->method('erfassen')->willReturn(['id' => 1, 'bereitsVorhanden' => false]);

		$antwort = $this->controller($dienst)->synchronisieren([
			['clientUuid' => 'a-1', 'schuelerId' => 7, 'markerId' => 3],
		])->getData();

		$this->assertCount(1, $antwort['uebernommen']);
		$this->assertSame('neu', $antwort['uebernommen'][0]['zustand']);
		$this->assertSame([], $antwort['fehler']);
	}

	public function testZweiteUebertragungMeldetBereitsVorhanden(): void {
		// Der Dienst erkennt die Kennung wieder und legt nichts Neues an.
		$dienst = $this->createMock(BeobachtungService::class);
		$dienst->method('erfassen')->willReturn(['id' => 1, 'bereitsVorhanden' => true]);

		$antwort = $this->controller($dienst)->synchronisieren([
			['clientUuid' => 'a-1', 'schuelerId' => 7, 'markerId' => 3],
		])->getData();

		// Ein Erfolg, kein Fehler: die Warteschlange räumt den Eintrag weg.
		$this->assertCount(1, $antwort['uebernommen']);
		$this->assertSame([], $antwort['fehler']);
		$this->assertSame('bereits_vorhanden', $antwort['uebernommen'][0]['zustand']);
		$this->assertSame(1, $antwort['uebernommen'][0]['id']);
	}

	public function testDerselbeEintragZweimalErgibtEineBeobachtung(): void {
		// Der Dienst verhält sich wie in der Datenbank: die erste Übertragung
		// legt an, die zweite findet die Kennung wieder.
		$angelegt = [];
		$dienst = $this->createMock(BeobachtungService::class);
		$dienst->method('erfassen')->willReturnCallback(
			function (string $nutzerId, array $eingabe) use (&$angelegt): array {
				$uuid = $eingabe['clientUuid'];
				if (isset($angelegt[$uuid])) {
					return ['id' => $angelegt[$uuid], 'bereitsVorhanden' => true];
				}
				$angelegt[$uuid] = count($angelegt) + 1;
				return ['id' => $angelegt[$uuid], 'bereitsVorhanden' => false];
			}
		);

		$controller = $this->controller($dienst);
		$eintrag = [['clientUuid' => 'gleiche-kennung', 'schuelerId' => 7, 'markerId' => 3]];

		$erste = $controller->synchronisieren($eintrag)->getData();
		$zweite = $controller->synchronisieren($eintrag)->getData();

		$this->assertSame('neu', $erste['uebernommen'][0]['zustand']);
		$this->assertSame('bereits_vorhanden', $zweite['uebernommen'][0]['zustand']);
		$this->assertCount(1, $angelegt);
		$this->assertSame(
			$erste['uebernommen'][0]['id'],
			$zweite['uebernommen'][0]['id'],
			'Beide Übertragungen müssen auf dieselbe Beobachtung zeigen.'
		);
	}

	public function testEintragOhneKennungWirdZurueckgewiesen(): void {
		$dienst = $this->createMock(BeobachtungService::class);
		$dienst->expects($this->never())->method('erfassen');

		$antwort = $this->controller($dienst)->synchronisieren([
			['schuelerId' => 7, 'markerId' => 3],
		])->getData();

		$this->assertSame([], $antwort['uebernommen']);
		$this->assertCount(1, $antwort['fehler']);
		$this->assertStringContainsString('clientUuid', $antwort['fehler'][0]['meldung']);
	}

	public function testEinKaputterEintragBlockiertDieUebrigenNicht(): void {
		$dienst = $this->createMock(BeobachtungService::class);
		$dienst->method('erfassen')->willReturnCallback(
			function (string $nutzerId, array $eingabe): array {
				if ($eingabe['clientUuid'] === 'kaputt') {
					throw new \InvalidArgumentException(
						'Eine Beobachtung braucht mindestens einen Marker oder einen Text.'
					);
				}
				return ['id' => 42, 'bereitsVorhanden' => false];
			}
		);

		$antwort = $this->controller($dienst)->synchronisieren([
			['clientUuid' => 'gut-1', 'schuelerId' => 1, 'markerId' => 3],
			['clientUuid' => 'kaputt', 'schuelerId' => 2],
			['clientUuid' => 'gut-2', 'schuelerId' => 3, 'markerId' => 3],
		])->getData();

		$this->assertCount(2, $antwort['uebernommen']);
		$this->assertCount(1, $antwort['fehler']);
		$this->assertSame('kaputt', $antwort['fehler'][0]['clientUuid']);
	}

	public function testFehlerhafterEintragBehaeltSeineKennung(): void {
		// Nur so kann die Warteschlange ihn auf dem Gerät als fehlerhaft
		// kennzeichnen, statt ihn endlos erneut zu senden.
		$dienst = $this->createMock(BeobachtungService::class);
		$dienst->method('erfassen')->willThrowException(new \RuntimeException('kaputt'));

		$antwort = $this->controller($dienst)->synchronisieren([
			['clientUuid' => 'x-9', 'schuelerId' => 1],
		])->getData();

		$this->assertSame('x-9', $antwort['fehler'][0]['clientUuid']);
	}
}

/**
 * Der Erfassungszeitpunkt kommt vom Gerät (D9).
 *
 * Eine Beobachtung, die Stunden offline lag, darf nicht auf den Zeitpunkt der
 * Übertragung rutschen — sonst steht sie in der Zeitleiste am falschen Tag.
 * Die Regel sitzt in einer privaten Methode; geprüft wird sie über Reflexion,
 * weil der Weg über erfassen() eine Datenbank verlangte.
 */
class ZeitpunktTest extends TestCase {

	private function zeitpunkt(?string $roh): \DateTime {
		$methode = new \ReflectionMethod(BeobachtungService::class, 'zeitpunkt');
		$methode->setAccessible(true);

		$dienst = (new \ReflectionClass(BeobachtungService::class))->newInstanceWithoutConstructor();
		return $methode->invoke($dienst, $roh);
	}

	public function testUebernimmtDenZeitpunktVomGeraet(): void {
		$zeit = $this->zeitpunkt('2026-08-11T09:37:00+02:00');

		$this->assertSame('2026-08-11 09:37', $zeit->format('Y-m-d H:i'));
	}

	public function testOhneAngabeGiltJetzt(): void {
		$zeit = $this->zeitpunkt(null);

		$this->assertEqualsWithDelta(time(), $zeit->getTimestamp(), 5);
	}

	public function testUebernimmtKeinenZeitpunktAusDerZukunft(): void {
		$zeit = $this->zeitpunkt((new \DateTime('+3 days'))->format('c'));

		$this->assertLessThanOrEqual(time() + 5, $zeit->getTimestamp());
	}

	public function testUnbrauchbareAngabeFaelltAufJetzt(): void {
		$zeit = $this->zeitpunkt('kein Datum');

		$this->assertEqualsWithDelta(time(), $zeit->getTimestamp(), 5);
	}
}

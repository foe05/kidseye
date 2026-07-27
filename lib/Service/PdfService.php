<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

/**
 * Schlanker PDF-Schreiber für Berichte (Kapitel 8.5).
 *
 * Bewusst ohne zusätzliche Abhängigkeit: eine Schulinstallation soll die App
 * aus dem App Store einspielen können, ohne dass jemand composer bemüht.
 * Der Umfang ist genau auf das zugeschnitten, was ein Beobachtungsbericht
 * braucht — Fließtext, Tabellenzeilen und eingebettete Arbeitsproben.
 *
 * Zwei Kniffe halten das klein:
 *  - Die 14 Standardschriften eines PDF-Lesers brauchen keine Einbettung.
 *    Mit WinAnsiEncoding sind Umlaute und ß abgedeckt.
 *  - JPEG-Daten wandern unverändert als DCTDecode-Datenstrom hinein. Es wird
 *    nichts neu kodiert, die Bilder sind ohnehin schon clientseitig
 *    verkleinert (D8).
 */
class PdfService {

	private const A4_BREITE = 595.28;
	private const A4_HOEHE = 841.89;
	private const RAND = 56.7; // 2 cm

	/** @var string[] Objekte, Index 0 entspricht Objektnummer 1 */
	private array $objekte = [];
	/** @var string[] Fertige Seiteninhalte */
	private array $seiten = [];
	/** @var array<string,array{obj:int,breite:int,hoehe:int}> */
	private array $bilder = [];

	private string $inhalt = '';
	private float $y = 0.0;
	private int $seitenzahl = 0;

	/**
	 * Breiten der Helvetica-Standardschrift in 1/1000 em.
	 * Nur die tatsächlich gebrauchten Bereiche; alles andere fällt auf 556.
	 */
	private const BREITEN = [
		32 => 278, 33 => 278, 34 => 355, 39 => 191, 40 => 333, 41 => 333,
		44 => 278, 45 => 333, 46 => 278, 47 => 278,
		48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556,
		53 => 556, 54 => 556, 55 => 556, 56 => 556, 57 => 556,
		58 => 278, 59 => 278, 63 => 556,
		73 => 278, 74 => 500, 76 => 556, 77 => 833,
		102 => 278, 105 => 222, 106 => 222, 108 => 222, 109 => 833,
		114 => 333, 116 => 278, 119 => 722,
	];

	public function __construct(
		private AblageService $ablage,
	) {
	}

	/**
	 * Erzeugt das PDF zu einem Bericht aus dem BerichtService.
	 *
	 * @param string|null $nutzerId Für den Zugriff auf Arbeitsproben; null
	 *                              lässt Bilder weg.
	 */
	public function erzeuge(array $bericht, ?string $nutzerId = null): string {
		$this->objekte = [];
		$this->seiten = [];
		$this->bilder = [];
		$this->seitenzahl = 0;

		$titel = match ($bericht['art']) {
			'auskunft' => 'Auskunft nach Artikel 15 DSGVO',
			'mappe' => 'Mappe „' . ($bericht['zweck']['name'] ?? '') . '"',
			default => 'Beobachtungsbericht',
		};

		$this->neueSeite();
		$this->zeile($titel, 17, true);
		$this->abstand(4);

		$kopf = $bericht['kind']['anzeige'];
		if (!empty($bericht['kind']['klasse'])) {
			$kopf .= ' · ' . $bericht['kind']['klasse'];
		}
		$kopf .= ' · erstellt am ' . $this->datum($bericht['erstelltAm'])
			. ' von ' . $bericht['erstelltVon'];
		$this->absatz($kopf, 8.5, 0.42);
		if (!empty($bericht['zeitraum']['von'])) {
			$this->absatz('Zeitraum ab ' . $bericht['zeitraum']['von'], 8.5, 0.42);
		}
		$this->abstand(10);

		foreach ($bericht['uebersicht']['bereiche'] ?? [] as $bereich) {
			$this->ueberschrift($bereich['ebene'] === 'ueberfachlich'
				? 'Überfachliche Kompetenzen'
				: 'Fach: ' . ($bereich['fach'] ?? '—'));
			foreach ($bereich['knoten'] as $knoten) {
				$this->tabellenzeile($knoten['bezeichnung'], (string)$knoten['anzahl']);
			}
			$this->abstand(6);
		}

		$this->ueberschrift('Beobachtungen (' . count($bericht['eintraege']) . ')');
		if ($bericht['eintraege'] === []) {
			$this->absatz('Für den gewählten Zeitraum liegen keine Beobachtungen vor.', 10);
		}
		foreach ($bericht['eintraege'] as $eintrag) {
			$this->beobachtung($eintrag, $nutzerId);
		}

		if (!empty($bericht['hinweise'])) {
			$this->abstand(12);
			$this->ueberschrift('Hinweise');
			foreach ($bericht['hinweise'] as $hinweis) {
				$this->absatz('• ' . $hinweis, 8.5, 0.42);
			}
		}

		$this->seiteAbschliessen();
		return $this->zusammenbauen();
	}

	// ------------------------------------------------------------ Bausteine

	private function beobachtung(array $eintrag, ?string $nutzerId): void {
		$this->platzPruefen(48);

		$this->absatz(
			$this->datum($eintrag['erfasstAm'])
			. ($eintrag['kontext'] ? ' · ' . $eintrag['kontext'] : '')
			. ' · ' . $eintrag['sichtbarkeit'],
			8, 0.45
		);
		$this->absatz((string)($eintrag['text'] ?? $eintrag['markerText'] ?? ''), 10);

		if ($eintrag['knoten'] !== []) {
			$this->absatz(
				implode(' · ', array_column($eintrag['knoten'], 'bezeichnung')),
				8, 0.35
			);
		}
		foreach ($eintrag['nachtraege'] ?? [] as $nachtrag) {
			$this->absatz(
				'Nachtrag ' . $this->datum($nachtrag['erstelltAm']) . ': ' . $nachtrag['text'],
				8.5, 0.4
			);
		}

		// Arbeitsproben nur, wenn der Bericht sie ausdrücklich enthält
		if ($nutzerId !== null) {
			foreach ($eintrag['dateien'] as $datei) {
				$this->bild($nutzerId, (int)$datei['fileId']);
			}
		}

		$this->abstand(4);
		$this->trennlinie();
		$this->abstand(4);
	}

	private function bild(string $nutzerId, int $fileId): void {
		$roh = $this->ablage->inhalt($nutzerId, $fileId);
		if ($roh === null) {
			return;
		}
		$masse = $this->jpegMasse($roh);
		if ($masse === null) {
			// Kein JPEG — ohne Neukodierung nicht einbettbar
			$this->absatz('[Arbeitsprobe liegt nicht als JPEG vor]', 8, 0.45);
			return;
		}

		$maxBreite = self::A4_BREITE - 2 * self::RAND;
		$breite = min($maxBreite, 260.0);
		$hoehe = $breite * $masse['hoehe'] / $masse['breite'];
		if ($hoehe > 320.0) {
			$hoehe = 320.0;
			$breite = $hoehe * $masse['breite'] / $masse['hoehe'];
		}

		$this->platzPruefen($hoehe + 12);

		$schluessel = 'B' . $fileId;
		if (!isset($this->bilder[$schluessel])) {
			$objektNummer = $this->objektAnlegen(
				"<< /Type /XObject /Subtype /Image /Width {$masse['breite']} "
				. "/Height {$masse['hoehe']} /ColorSpace /"
				. ($masse['kanaele'] === 1 ? 'DeviceGray' : 'DeviceRGB')
				. ' /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($roh)
				. " >>\nstream\n" . $roh . "\nendstream"
			);
			$this->bilder[$schluessel] = ['obj' => $objektNummer,
				'breite' => $masse['breite'], 'hoehe' => $masse['hoehe']];
		}

		$this->y -= $hoehe;
		$this->inhalt .= sprintf(
			"q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n",
			$breite, $hoehe, self::RAND, $this->y, $schluessel
		);
		$this->y -= 8;
	}

	private function ueberschrift(string $text): void {
		$this->platzPruefen(30);
		$this->abstand(6);
		$this->zeile($text, 11.5, true);
		$this->abstand(3);
	}

	private function tabellenzeile(string $links, string $rechts): void {
		$this->platzPruefen(16);
		$this->y -= 12;
		$breite = self::A4_BREITE - 2 * self::RAND - 40;
		$this->text($this->kuerzen($links, 9, $breite), self::RAND, $this->y, 9);
		$this->text($rechts, self::A4_BREITE - self::RAND - 24, $this->y, 9);
	}

	private function absatz(string $text, float $groesse, float $grau = 0.0): void {
		$breite = self::A4_BREITE - 2 * self::RAND;
		foreach ($this->umbrechen($text, $groesse, $breite) as $zeile) {
			$this->platzPruefen(groesse: $groesse);
			$this->y -= $groesse * 1.42;
			$this->text($zeile, self::RAND, $this->y, $groesse, false, $grau);
		}
	}

	private function zeile(string $text, float $groesse, bool $fett = false): void {
		$this->platzPruefen(groesse: $groesse);
		$this->y -= $groesse * 1.35;
		$this->text($text, self::RAND, $this->y, $groesse, $fett);
	}

	private function trennlinie(): void {
		$this->inhalt .= sprintf(
			"0.85 G %.2F %.2F m %.2F %.2F l S 0 G\n",
			self::RAND, $this->y, self::A4_BREITE - self::RAND, $this->y
		);
	}

	private function abstand(float $punkte): void {
		$this->y -= $punkte;
	}

	private function text(string $text, float $x, float $y, float $groesse, bool $fett = false, float $grau = 0.0): void {
		$this->inhalt .= sprintf(
			"BT %.3F g /%s %.1F Tf %.2F %.2F Td (%s) Tj ET 0 g\n",
			$grau, $fett ? 'F2' : 'F1', $groesse, $x, $y, $this->maskieren($text)
		);
	}

	// ------------------------------------------------------------ Seiten

	private function platzPruefen(float $noetig = 0.0, float $groesse = 0.0): void {
		$bedarf = $noetig > 0 ? $noetig : $groesse * 1.6;
		if ($this->y - $bedarf < self::RAND) {
			$this->seiteAbschliessen();
			$this->neueSeite();
		}
	}

	private function neueSeite(): void {
		$this->inhalt = '';
		$this->y = self::A4_HOEHE - self::RAND;
		$this->seitenzahl++;
	}

	private function seiteAbschliessen(): void {
		if ($this->inhalt === '') {
			return;
		}
		$this->text(
			'Seite ' . $this->seitenzahl,
			self::A4_BREITE - self::RAND - 40, self::RAND - 18, 8, false, 0.55
		);
		$this->seiten[] = $this->inhalt;
		$this->inhalt = '';
	}

	// ------------------------------------------------------------ Ausgabe

	private function zusammenbauen(): string {
		// Objektnummern: 1 Katalog, 2 Seitenbaum, 3+4 Schriften, dann
		// je Seite Inhalt und Seitenobjekt. Bilder liegen bereits vor.
		$bildObjekte = $this->objekte;
		$this->objekte = [];

		$katalog = $this->objektAnlegen('<< /Type /Catalog /Pages 2 0 R >>');
		$seitenbaum = $this->objektAnlegen('');
		$f1 = $this->objektAnlegen(
			'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'
		);
		$f2 = $this->objektAnlegen(
			'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'
		);

		$versatz = count($this->objekte);
		foreach ($bildObjekte as $bild) {
			$this->objekte[] = $bild;
		}
		foreach ($this->bilder as $schluessel => $angabe) {
			$this->bilder[$schluessel]['obj'] = $angabe['obj'] + $versatz;
		}

		$xobjekte = '';
		foreach ($this->bilder as $schluessel => $angabe) {
			$xobjekte .= "/$schluessel {$angabe['obj']} 0 R ";
		}
		$ressourcen = '<< /Font << /F1 ' . $f1 . ' 0 R /F2 ' . $f2 . ' 0 R >>'
			. ($xobjekte !== '' ? ' /XObject << ' . $xobjekte . '>>' : '') . ' >>';

		$seitenIds = [];
		foreach ($this->seiten as $seiteninhalt) {
			$inhaltId = $this->objektAnlegen(
				'<< /Length ' . strlen($seiteninhalt) . " >>\nstream\n" . $seiteninhalt . "\nendstream"
			);
			$seitenIds[] = $this->objektAnlegen(
				'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
				. sprintf('%.2F %.2F', self::A4_BREITE, self::A4_HOEHE) . ']'
				. ' /Resources ' . $ressourcen . ' /Contents ' . $inhaltId . ' 0 R >>'
			);
		}

		$kinder = implode(' ', array_map(static fn ($id) => $id . ' 0 R', $seitenIds));
		$this->objekte[$seitenbaum - 1] =
			'<< /Type /Pages /Kids [' . $kinder . '] /Count ' . count($seitenIds) . ' >>';

		$pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$stellen = [];
		foreach ($this->objekte as $i => $objekt) {
			$stellen[] = strlen($pdf);
			$pdf .= ($i + 1) . " 0 obj\n" . $objekt . "\nendobj\n";
		}

		$xref = strlen($pdf);
		$pdf .= 'xref' . "\n0 " . (count($this->objekte) + 1) . "\n0000000000 65535 f \n";
		foreach ($stellen as $stelle) {
			$pdf .= sprintf("%010d 00000 n \n", $stelle);
		}
		$pdf .= '<< /Size ' . (count($this->objekte) + 1) . ' /Root ' . $katalog . " 0 R >>\n"
			. "startxref\n" . $xref . "\n%%EOF\n";

		// trailer-Schlüsselwort gehört vor das Wörterbuch
		return str_replace(
			"<< /Size " . (count($this->objekte) + 1),
			"trailer\n<< /Size " . (count($this->objekte) + 1),
			$pdf
		);
	}

	private function objektAnlegen(string $inhalt): int {
		$this->objekte[] = $inhalt;
		return count($this->objekte);
	}

	// ------------------------------------------------------------ Hilfen

	/** @return string[] */
	private function umbrechen(string $text, float $groesse, float $breite): array {
		$text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
		if ($text === '') {
			return [];
		}
		$zeilen = [];
		$aktuell = '';
		foreach (explode(' ', $text) as $wort) {
			$probe = $aktuell === '' ? $wort : $aktuell . ' ' . $wort;
			if ($this->breite($probe, $groesse) > $breite && $aktuell !== '') {
				$zeilen[] = $aktuell;
				$aktuell = $wort;
			} else {
				$aktuell = $probe;
			}
		}
		if ($aktuell !== '') {
			$zeilen[] = $aktuell;
		}
		return $zeilen;
	}

	private function kuerzen(string $text, float $groesse, float $breite): string {
		if ($this->breite($text, $groesse) <= $breite) {
			return $text;
		}
		while ($text !== '' && $this->breite($text . '…', $groesse) > $breite) {
			$text = mb_substr($text, 0, mb_strlen($text) - 1);
		}
		return $text . '…';
	}

	private function breite(string $text, float $groesse): float {
		$latin = $this->nachLatin1($text);
		$summe = 0;
		for ($i = 0, $n = strlen($latin); $i < $n; $i++) {
			$summe += self::BREITEN[ord($latin[$i])] ?? 556;
		}
		return $summe / 1000 * $groesse;
	}

	private function maskieren(string $text): string {
		return str_replace(
			['\\', '(', ')', "\r", "\n"],
			['\\\\', '\\(', '\\)', '', ' '],
			$this->nachLatin1($text)
		);
	}

	/** WinAnsi entspricht für unsere Zwecke ISO-8859-1. */
	private function nachLatin1(string $text): string {
		$text = str_replace(
			['„', '"', '–', '—', '‚', '‘', '’', '…', '·', '⟳', '●'],
			['"', '"', '-', '-', "'", "'", "'", '...', '-', '', '*'],
			$text
		);
		return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
	}

	private function datum(?string $iso): string {
		if ($iso === null || $iso === '') {
			return '';
		}
		try {
			return (new \DateTime($iso))->format('d.m.Y H:i');
		} catch (\Throwable) {
			return $iso;
		}
	}

	/** @return array{breite:int,hoehe:int,kanaele:int}|null */
	private function jpegMasse(string $daten): array|null {
		if (strncmp($daten, "\xFF\xD8", 2) !== 0) {
			return null;
		}
		$i = 2;
		$n = strlen($daten);
		while ($i + 9 < $n) {
			if ($daten[$i] !== "\xFF") {
				$i++;
				continue;
			}
			$marker = ord($daten[$i + 1]);
			// SOF0 bis SOF15, ohne DHT (C4), DNL (C8) und DAC (CC)
			if ($marker >= 0xC0 && $marker <= 0xCF
				&& !in_array($marker, [0xC4, 0xC8, 0xCC], true)) {
				return [
					'hoehe' => (ord($daten[$i + 5]) << 8) | ord($daten[$i + 6]),
					'breite' => (ord($daten[$i + 7]) << 8) | ord($daten[$i + 8]),
					'kanaele' => ord($daten[$i + 9]),
				];
			}
			$laenge = (ord($daten[$i + 2]) << 8) | ord($daten[$i + 3]);
			$i += 2 + max($laenge, 2);
		}
		return null;
	}
}

<?php

declare(strict_types=1);

namespace OCA\KidsEye\Service;

use OCA\KidsEye\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IDBConnection;

/**
 * Einrichtungsstand: was fehlt noch, damit kidseye benutzbar ist?
 *
 * Ein Register statt verstreuter Einzelprüfungen. Der occ-Befehl
 * kidseye:pruefen und der Reiter „Einrichtung" lesen beide hieraus — nur so
 * können sie nicht auseinanderlaufen (design.md E2).
 *
 * Jeder Punkt trägt seine Abhilfe mit sich. Wer bei einer Erstinstallation
 * feststeckt, soll aus der Meldung erfahren, was zu tun ist, statt in der
 * Anleitung suchen zu müssen.
 */
class DiagnoseService {

	public const ERFUELLT = 'erfuellt';
	public const OFFEN = 'offen';
	public const NICHT_PRUEFBAR = 'nicht_pruefbar';

	/**
	 * Die Tabellen, die die beiden Migrationen anlegen.
	 *
	 * Die Liste wird von Hand gepflegt und von DiagnoseTest gegen die
	 * createTable-Aufrufe in lib/Migration/ gehalten. Weicht sie ab, schlägt
	 * der Test fehl. Genau dieser Abgleich fehlte, als docs/INSTALLATION.md
	 * von 21 Tabellen sprach und die Migrationen 25 anlegten.
	 */
	public const TABELLEN = [
		'kidseye_beobachtung',
		'kidseye_beob_datei',
		'kidseye_beob_knoten',
		'kidseye_beob_zweck',
		'kidseye_klasse',
		'kidseye_klassenbild',
		'kidseye_klassen_zug',
		'kidseye_knoten',
		'kidseye_knoten_korr',
		'kidseye_kontext',
		'kidseye_lehrauftrag',
		'kidseye_marker',
		'kidseye_marker_knoten',
		'kidseye_marker_zweck',
		'kidseye_nachtrag',
		'kidseye_protokoll',
		'kidseye_rahmen',
		'kidseye_rahmen_vers',
		'kidseye_schueler',
		'kidseye_schuljahr',
		'kidseye_skala',
		'kidseye_skala_stufe',
		'kidseye_stunde',
		'kidseye_zweck',
		'kidseye_zweck_knoten',
	];

	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
		private IAppManager $appManager,
		private RahmenService $rahmen,
		private StammdatenService $stammdaten,
		private RollenService $rollen,
		private AblageService $ablage,
	) {
	}

	/**
	 * Alle Prüfpunkte.
	 *
	 * @param ?string $nutzerId Für die nutzerabhängigen Punkte. Ohne Angabe
	 *                          werden sie als „nicht prüfbar" geführt statt
	 *                          einen Fehler zu werfen — occ läuft ohne
	 *                          angemeldeten Nutzer.
	 * @return list<array{kennung:string,titel:string,pflicht:bool,zustand:string,meldung:string,abhilfe:?string}>
	 */
	public function pruefen(?string $nutzerId = null): array {
		return [
			$this->tabellen(),
			$this->kompetenzrahmen(),
			$this->schuljahr(),
			$this->gruppen(),
			$this->lehrauftraege(),
			$this->ablage($nutzerId),
			$this->vollbild(),
			$this->symbol(),
		];
	}

	/**
	 * Ist alles erfüllt, was erfüllt sein muss?
	 *
	 * „Nicht prüfbar" steht dem nicht entgegen: sonst könnte ein Aufruf ohne
	 * Nutzer nie mit 0 enden, und der Befehl wäre in einem
	 * Installationsskript nicht zu gebrauchen.
	 */
	public function alleErfuellt(array $punkte): bool {
		foreach ($punkte as $punkt) {
			if ($punkt['pflicht'] && $punkt['zustand'] === self::OFFEN) {
				return false;
			}
		}
		return true;
	}

	// ------------------------------------------------------- Einzelne Punkte

	private function tabellen(): array {
		$fehlend = [];
		foreach (self::TABELLEN as $tabelle) {
			if (!$this->db->tableExists($tabelle)) {
				$fehlend[] = $tabelle;
			}
		}

		$zahl = count(self::TABELLEN);
		return $this->punkt(
			'tabellen',
			'Tabellen',
			$fehlend === [],
			$fehlend === []
				? 'Alle ' . $zahl . ' Tabellen vorhanden.'
				: count($fehlend) . ' von ' . $zahl . ' Tabellen fehlen: ' . implode(', ', $fehlend),
			'occ app:disable kidseye && occ app:enable kidseye — dabei laufen die Migrationen erneut.'
		);
	}

	private function kompetenzrahmen(): array {
		$versionId = $this->rahmen->aktiveVersionId();

		return $this->punkt(
			'rahmen',
			'Kompetenzrahmen',
			$versionId !== null,
			$versionId !== null
				? 'Rahmenversion ' . $versionId . ' ist aktiv.'
				: 'Es ist keine Rahmenversion aktiv.',
			'occ kidseye:einrichten --schuljahr <jahr>'
		);
	}

	private function schuljahr(): array {
		$schuljahr = $this->stammdaten->aktivesSchuljahr();

		return $this->punkt(
			'schuljahr',
			'Schuljahr',
			$schuljahr !== null,
			$schuljahr !== null
				? 'Aktives Schuljahr: ' . $schuljahr['kennung']
				: 'Kein Schuljahr aktiv.',
			'occ kidseye:einrichten --schuljahr <jahr>'
		);
	}

	private function gruppen(): array {
		$gruppen = $this->rollen->gruppenVorhanden();
		$fehlend = [];
		foreach ($gruppen as $gruppe) {
			if (empty($gruppe['existiert'])) {
				$fehlend[] = $gruppe['name'];
			}
		}

		return $this->punkt(
			'gruppen',
			'Zugriffsgruppen',
			$fehlend === [],
			$fehlend === []
				? 'Beide Gruppen vorhanden.'
				: 'Nicht angelegt: ' . implode(', ', $fehlend),
			'occ group:add <name> — danach die Lehrkräfte hinzufügen.'
		);
	}

	private function lehrauftraege(): array {
		$vorhanden = $this->stammdaten->lehrauftraegeVorhanden();

		return $this->punkt(
			'lehrauftraege',
			'Lehraufträge',
			$vorhanden,
			$vorhanden
				? 'Mindestens ein Lehrauftrag liegt vor.'
				: 'Kein Lehrauftrag angelegt — ohne Lehrauftrag lässt sich keine Stunde starten.',
			'In der App unter „Klassen & Kinder → Lehraufträge" je Lehrkraft, Klasse '
				. 'und Unterrichtskontext einen Auftrag anlegen.'
		);
	}

	private function ablage(?string $nutzerId): array {
		if ($nutzerId === null) {
			return $this->punkt(
				'ablage',
				'Ablage der Arbeitsproben',
				null,
				'Ohne Nutzer nicht prüfbar — der Ordner wird im Namen einer Lehrkraft geöffnet.',
				'occ kidseye:pruefen --nutzer <kennung>'
			);
		}

		$stand = $this->ablage->pruefeAblage($nutzerId);

		return $this->punkt(
			'ablage',
			'Ablage der Arbeitsproben',
			(bool)($stand['ok'] ?? false),
			($stand['ok'] ?? false)
				? 'Ordner „' . $stand['pfad'] . '" gefunden und beschreibbar.'
				: (string)($stand['grund'] ?? 'Ablage nicht erreichbar.'),
			'Gruppenordner „' . ($stand['pfad'] ?? 'Beobachtung') . '" anlegen und der '
				. 'Lehrkraftgruppe Schreibrechte geben.'
		);
	}

	private function vollbild(): array {
		$aktiv = $this->config->getSystemValueBool('theming.standalone_window.enabled', true);

		return $this->punkt(
			'vollbild',
			'Vollbild auf dem Gerät',
			$aktiv,
			$aktiv
				? 'theming.standalone_window.enabled steht auf true.'
				: 'theming.standalone_window.enabled steht auf false — das Symbol auf dem '
					. 'Home-Bildschirm startet mit Browserleiste statt im Vollbild.',
			'occ config:system:set theming.standalone_window.enabled --value=true --type=boolean'
		);
	}

	/**
	 * Manifest und Symbol.
	 *
	 * Fehlt das Symbol, ersetzt iOS es beim Ablegen auf dem Home-Bildschirm
	 * durch einen Bildschirmabzug der Seite — die Anwendung ist dann benutzbar,
	 * aber auf dem Gerät nicht wiederzuerkennen.
	 */
	private function symbol(): array {
		$verzeichnis = $this->appManager->getAppPath(Application::APP_ID) . '/img/';
		$manifest = $verzeichnis . 'manifest.json';

		if (!is_readable($manifest)) {
			return $this->punkt(
				'symbol',
				'Symbol für den Home-Bildschirm',
				false,
				'img/manifest.json fehlt oder ist nicht lesbar.',
				'Die App erneut ausrollen — die Datei gehört zum Auslieferstand.'
			);
		}

		$gelesen = json_decode((string)file_get_contents($manifest), true);
		if (!is_array($gelesen)) {
			return $this->punkt(
				'symbol',
				'Symbol für den Home-Bildschirm',
				false,
				'img/manifest.json ist kein gültiges JSON.',
				'Die App erneut ausrollen.'
			);
		}

		$fehlend = [];
		foreach ($gelesen['icons'] ?? [] as $symbol) {
			if (isset($symbol['src']) && !is_readable($verzeichnis . $symbol['src'])) {
				$fehlend[] = $symbol['src'];
			}
		}

		return $this->punkt(
			'symbol',
			'Symbol für den Home-Bildschirm',
			$fehlend === [],
			$fehlend === []
				? 'Manifest und Symbole werden ausgeliefert.'
				: 'Im Manifest genannt, aber nicht vorhanden: ' . implode(', ', $fehlend),
			'node tools/symbol-erzeugen.mjs erzeugt das Symbol; danach erneut ausrollen.'
		);
	}

	// ---------------------------------------------------------------- Aufbau

	/** @param ?bool $erfuellt null bedeutet „nicht prüfbar". */
	private function punkt(
		string $kennung,
		string $titel,
		?bool $erfuellt,
		string $meldung,
		?string $abhilfe,
		bool $pflicht = true,
	): array {
		$zustand = match ($erfuellt) {
			true => self::ERFUELLT,
			false => self::OFFEN,
			null => self::NICHT_PRUEFBAR,
		};

		return [
			'kennung' => $kennung,
			'titel' => $titel,
			'pflicht' => $pflicht,
			'zustand' => $zustand,
			'meldung' => $meldung,
			'abhilfe' => $zustand === self::ERFUELLT ? null : $abhilfe,
		];
	}
}

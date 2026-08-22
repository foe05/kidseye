<?php

declare(strict_types=1);

namespace OCA\KidsEye\Controller;

use OCA\KidsEye\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Util;

class PageController extends Controller {

	public function __construct(
		IRequest $request,
		private IInitialState $initialState,
		private IURLGenerator $urlGenerator,
		private IAppManager $appManager,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Verwaltung, Inbox und Auswertung: normale Nextcloud-Oberfläche.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		Util::addStyle(Application::APP_ID, 'kidseye');
		Util::addScript(Application::APP_ID, 'kidseye-main');
		return new TemplateResponse(Application::APP_ID, 'index');
	}

	/**
	 * Unterrichtsmodus: Vollbild ohne Nextcloud-Kopfleiste und Navigation.
	 *
	 * RENDER_AS_BASE fällt auf core/templates/layout.base.php. Dieses Layout
	 * enthält weder den Manifest-Link noch die apple-mobile-web-app-Metaangaben
	 * — beides wird hier per Util::addHeader() nachgerüstet, damit „Zum
	 * Home-Bildschirm" ein eigenes kidseye-Symbol erzeugt (design.md D14).
	 *
	 * Das Basis-Layout bringt auch deutlich weniger Formular-CSS mit als das
	 * volle Nextcloud-Layout. Deshalb steht css/kidseye.css an beiden
	 * Einstiegspunkten und nicht nur an der Verwaltungsoberfläche: es ist der
	 * einzige Weg, auf dem der Startdialog dieselbe Grundlage bekommt wie die
	 * Verwaltung (formularelemente-vereinheitlichen, E1).
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function unterricht(): TemplateResponse {
		Util::addHeader('link', [
			'rel' => 'manifest',
			'href' => $this->urlGenerator->linkToRoute(Application::APP_ID . '.page.manifest'),
			'crossorigin' => 'use-credentials',
		]);
		Util::addHeader('meta', ['name' => 'apple-mobile-web-app-capable', 'content' => 'yes']);
		Util::addHeader('meta', ['name' => 'apple-mobile-web-app-title', 'content' => 'kidseye']);
		Util::addHeader('meta', [
			'name' => 'apple-mobile-web-app-status-bar-style',
			'content' => 'default',
		]);
		Util::addHeader('meta', [
			'name' => 'viewport',
			'content' => 'width=device-width, initial-scale=1, viewport-fit=cover',
		]);

		Util::addStyle(Application::APP_ID, 'kidseye');
		Util::addScript(Application::APP_ID, 'kidseye-unterricht');

		return new TemplateResponse(
			Application::APP_ID,
			'unterricht',
			[],
			TemplateResponse::RENDER_AS_BASE
		);
	}

	/**
	 * Das Manifest für „Zum Home-Bildschirm".
	 *
	 * Warum über einen Controller und nicht als ausgelieferte Datei: start_url,
	 * scope und die Symbolpfade hängen von der Instanz ab. Als relative Pfade
	 * in img/manifest.json lösten sie gegen den Ort des Manifests
	 * (/apps/kidseye/img/) auf und ergaben /apps/kidseye/apps/kidseye/unterricht
	 * — das Symbol landete auf einer nicht vorhandenen Seite, und scope umfasste
	 * die Anwendung nicht.
	 *
	 * linkToRoute trägt beides zugleich: den Unterordner einer Nextcloud, die
	 * nicht im Wurzelverzeichnis liegt, und die Form /index.php/apps/… auf
	 * Instanzen ohne umgeschriebene Adressen. Weil start_url und scope aus
	 * derselben Quelle stammen, enthält der eine Wert den anderen immer.
	 *
	 * Die instanzunabhängigen Teile — Name, Farben, Anzeigeart, Symbolliste —
	 * stehen weiterhin in img/manifest.json.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function manifest(): DataDisplayResponse {
		$manifest = $this->vorlage();

		$manifest['start_url'] = $this->urlGenerator->linkToRoute(Application::APP_ID . '.page.unterricht');
		$manifest['scope'] = $this->urlGenerator->linkToRoute(Application::APP_ID . '.page.index');

		foreach ($manifest['icons'] ?? [] as $i => $symbol) {
			if (isset($symbol['src'])) {
				$manifest['icons'][$i]['src'] =
					$this->urlGenerator->imagePath(Application::APP_ID, $symbol['src']);
			}
		}

		return new DataDisplayResponse(
			json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
			Http::STATUS_OK,
			['Content-Type' => 'application/manifest+json']
		);
	}

	/**
	 * Die mitgelieferten, instanzunabhängigen Teile des Manifests.
	 *
	 * Fehlt oder bricht die Datei, bleibt das Manifest gültig und das Symbol
	 * benutzbar — nur ohne Namen und Farben. Ein 404 an dieser Stelle würde
	 * „Zum Home-Bildschirm" ganz verhindern. Dass die Datei fehlt, meldet
	 * stattdessen der Einrichtungsstand.
	 */
	private function vorlage(): array {
		$pfad = $this->appManager->getAppPath(Application::APP_ID) . '/img/manifest.json';
		$roh = is_readable($pfad) ? file_get_contents($pfad) : false;
		$gelesen = $roh === false ? null : json_decode($roh, true);

		return is_array($gelesen) ? $gelesen : [];
	}
}

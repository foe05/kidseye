<?php

declare(strict_types=1);

namespace OCA\KidsEye\Controller;

use OCA\KidsEye\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {

	public function __construct(
		IRequest $request,
		private IInitialState $initialState,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Verwaltung, Inbox und Auswertung: normale Nextcloud-Oberfläche.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
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
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function unterricht(): TemplateResponse {
		Util::addHeader('link', [
			'rel' => 'manifest',
			'href' => \OCP\Server::get(\OCP\IURLGenerator::class)
				->imagePath(Application::APP_ID, 'manifest.json'),
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

		Util::addScript(Application::APP_ID, 'kidseye-unterricht');

		return new TemplateResponse(
			Application::APP_ID,
			'unterricht',
			[],
			TemplateResponse::RENDER_AS_BASE
		);
	}
}

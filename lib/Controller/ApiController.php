<?php

declare(strict_types=1);

namespace OCA\KidsEye\Controller;

use OCA\KidsEye\AppInfo\Application;
use OCA\KidsEye\Service\BewertungNichtZulaessig;
use OCA\KidsEye\Service\RollenService;
use OCA\KidsEye\Service\ZugriffVerweigert;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Gemeinsames Verhalten aller kidseye-Endpunkte.
 *
 * Fehler werden hier zu verständlichen Meldungen: die Erfassung läuft auf
 * einem Tablet mitten im Unterricht — eine nackte 500 hilft dort niemandem.
 */
abstract class ApiController extends Controller {

	public function __construct(
		IRequest $request,
		protected RollenService $rollen,
		protected LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Führt eine Aktion aus und übersetzt bekannte Ausnahmen in Antworten.
	 *
	 * @param callable(string):mixed $aktion bekommt die Nutzerkennung
	 */
	protected function fuehreAus(callable $aktion, bool $leitungNoetig = false): DataResponse {
		try {
			$nutzerId = $leitungNoetig
				? $this->rollen->verlangeLeitung()
				: $this->rollen->verlangeLehrkraft();
			return new DataResponse($aktion($nutzerId));
		} catch (ZugriffVerweigert $e) {
			return new DataResponse(
				['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN
			);
		} catch (BewertungNichtZulaessig $e) {
			return new DataResponse(
				['fehler' => $e->getMessage()], Http::STATUS_UNPROCESSABLE_ENTITY
			);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(
				['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST
			);
		} catch (\Throwable $e) {
			$this->logger->error('kidseye: unerwarteter Fehler', ['exception' => $e]);
			return new DataResponse(
				['fehler' => 'Da ist etwas schiefgegangen. Die Beobachtung ist nicht verloren — '
					. 'sie bleibt auf dem Gerät und wird erneut gesendet.'],
				Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}
	}
}

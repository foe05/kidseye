<?php

declare(strict_types=1);

return [
	'routes' => [
		// --- Seiten -------------------------------------------------------
		// Verwaltung, Inbox und Auswertung in der Nextcloud-Oberfläche (D10)
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		// Erfassungsbildschirm im Vollbild. Startziel des Home-Bildschirm-
		// Symbols, siehe img/manifest.json und D14.
		['name' => 'page#unterricht', 'url' => '/unterricht', 'verb' => 'GET'],
		// Manifest für „Zum Home-Bildschirm". Als Route statt als Datei, weil
		// start_url, scope und Symbolpfade von der Instanz abhängen.
		['name' => 'page#manifest', 'url' => '/manifest.webmanifest', 'verb' => 'GET'],

		// --- Erfassung (Kapitel 4 und 5) ----------------------------------
		['name' => 'erfassung#einstieg', 'url' => '/api/v1/einstieg', 'verb' => 'GET'],
		['name' => 'erfassung#startAuswahl', 'url' => '/api/v1/stunde/auswahl', 'verb' => 'GET'],
		['name' => 'erfassung#stundeStarten', 'url' => '/api/v1/stunde', 'verb' => 'POST'],
		['name' => 'erfassung#stundeBeenden', 'url' => '/api/v1/stunde', 'verb' => 'DELETE'],
		['name' => 'erfassung#bildschirm', 'url' => '/api/v1/bildschirm', 'verb' => 'GET'],
		['name' => 'erfassung#erfassen', 'url' => '/api/v1/beobachtung', 'verb' => 'POST'],
		['name' => 'erfassung#erfassenMehrere', 'url' => '/api/v1/beobachtung/mehrere', 'verb' => 'POST'],
		['name' => 'erfassung#synchronisieren', 'url' => '/api/v1/beobachtung/sync', 'verb' => 'POST'],
		['name' => 'erfassung#zuruecknehmen', 'url' => '/api/v1/beobachtung/{id}', 'verb' => 'DELETE'],
		['name' => 'erfassung#heute', 'url' => '/api/v1/kind/{schuelerId}/heute', 'verb' => 'GET'],
		['name' => 'erfassung#suche', 'url' => '/api/v1/kinder/suche', 'verb' => 'GET'],
		['name' => 'erfassung#fotoAnhaengen', 'url' => '/api/v1/beobachtung/{beobachtungId}/foto', 'verb' => 'POST'],

		// --- Verwaltung (Kapitel 3, 5.2b, 5b.2) ---------------------------
		['name' => 'verwaltung#einrichtung', 'url' => '/api/v1/einrichtung', 'verb' => 'GET'],
		['name' => 'verwaltung#klassen', 'url' => '/api/v1/klassen', 'verb' => 'GET'],
		['name' => 'verwaltung#kinder', 'url' => '/api/v1/klassen/{klasseId}/kinder', 'verb' => 'GET'],
		['name' => 'verwaltung#schuljahrAnlegen', 'url' => '/api/v1/schuljahr', 'verb' => 'POST'],
		['name' => 'verwaltung#klasseAnlegen', 'url' => '/api/v1/klassen', 'verb' => 'POST'],
		['name' => 'verwaltung#lehrauftraege', 'url' => '/api/v1/lehrauftrag', 'verb' => 'GET'],
		['name' => 'verwaltung#lehrauftragAnlegen', 'url' => '/api/v1/lehrauftrag', 'verb' => 'POST'],
		['name' => 'verwaltung#schuelerAnlegen', 'url' => '/api/v1/schueler', 'verb' => 'POST'],
		['name' => 'verwaltung#klassenbildSpeichern', 'url' => '/api/v1/klassen/{klasseId}/bild', 'verb' => 'PUT'],
		['name' => 'verwaltung#klassenbildZuruecksetzen', 'url' => '/api/v1/klassen/{klasseId}/bild', 'verb' => 'DELETE'],
		['name' => 'verwaltung#importVorschau', 'url' => '/api/v1/import/vorschau', 'verb' => 'POST'],
		['name' => 'verwaltung#importUebernehmen', 'url' => '/api/v1/import', 'verb' => 'POST'],
		['name' => 'verwaltung#rolloverVorschlag', 'url' => '/api/v1/rollover/vorschlag', 'verb' => 'GET'],
		['name' => 'verwaltung#rolloverAusfuehren', 'url' => '/api/v1/rollover', 'verb' => 'POST'],
		['name' => 'verwaltung#kontexte', 'url' => '/api/v1/kontexte', 'verb' => 'GET'],
		['name' => 'verwaltung#kontextAnlegen', 'url' => '/api/v1/kontexte', 'verb' => 'POST'],
		['name' => 'verwaltung#marker', 'url' => '/api/v1/kontexte/{kontextId}/marker', 'verb' => 'GET'],
		['name' => 'verwaltung#markerSpeichern', 'url' => '/api/v1/kontexte/{kontextId}/marker', 'verb' => 'PUT'],
		['name' => 'verwaltung#zwecke', 'url' => '/api/v1/zwecke', 'verb' => 'GET'],
		['name' => 'verwaltung#zweckAnlegen', 'url' => '/api/v1/zwecke', 'verb' => 'POST'],
		['name' => 'verwaltung#zweckAendern', 'url' => '/api/v1/zwecke/{id}', 'verb' => 'PUT'],
		['name' => 'verwaltung#gruppenSetzen', 'url' => '/api/v1/einstellungen/gruppen', 'verb' => 'PUT'],
		['name' => 'verwaltung#ablagePfadSetzen', 'url' => '/api/v1/einstellungen/ablage', 'verb' => 'PUT'],
		['name' => 'verwaltung#fristSetzen', 'url' => '/api/v1/einstellungen/frist', 'verb' => 'PUT'],
		['name' => 'verwaltung#skalaAnlegen', 'url' => '/api/v1/einstellungen/skala', 'verb' => 'POST'],

		// --- Wochendurchgang und Auswertung (Kapitel 6, 7, 8) -------------
		['name' => 'auswertung#inbox', 'url' => '/api/v1/inbox', 'verb' => 'GET'],
		['name' => 'auswertung#inboxVorschlaege', 'url' => '/api/v1/inbox/{id}/vorschlaege', 'verb' => 'GET'],
		['name' => 'auswertung#inboxZuordnen', 'url' => '/api/v1/inbox/zuordnen', 'verb' => 'POST'],
		['name' => 'auswertung#inboxErledigen', 'url' => '/api/v1/inbox/erledigen', 'verb' => 'POST'],
		['name' => 'auswertung#merken', 'url' => '/api/v1/beobachtung/{id}/merken', 'verb' => 'PUT'],
		['name' => 'auswertung#umhaengen', 'url' => '/api/v1/beobachtung/{id}/umhaengen', 'verb' => 'PUT'],
		['name' => 'auswertung#luecken', 'url' => '/api/v1/klassen/{klasseId}/luecken', 'verb' => 'GET'],
		['name' => 'auswertung#stufen', 'url' => '/api/v1/sichtbarkeit/stufen', 'verb' => 'GET'],
		['name' => 'auswertung#sichtbarkeitSetzen', 'url' => '/api/v1/beobachtung/{id}/sichtbarkeit', 'verb' => 'PUT'],
		['name' => 'auswertung#nachtragen', 'url' => '/api/v1/beobachtung/{id}/nachtrag', 'verb' => 'POST'],
		['name' => 'auswertung#faellig', 'url' => '/api/v1/aufbewahrung/faellig', 'verb' => 'GET'],
		['name' => 'auswertung#schuljahresende', 'url' => '/api/v1/aufbewahrung/schuljahresende', 'verb' => 'GET'],
		['name' => 'auswertung#loeschen', 'url' => '/api/v1/aufbewahrung/loeschen', 'verb' => 'POST'],
		['name' => 'auswertung#zeitleiste', 'url' => '/api/v1/kind/{schuelerId}/zeitleiste', 'verb' => 'GET'],
		['name' => 'auswertung#heatmap', 'url' => '/api/v1/klassen/{klasseId}/heatmap', 'verb' => 'GET'],
		['name' => 'auswertung#mappe', 'url' => '/api/v1/kind/{schuelerId}/mappe', 'verb' => 'GET'],
		['name' => 'auswertung#einschaetzung', 'url' => '/api/v1/zuordnung/{zuordnungId}/einschaetzung', 'verb' => 'PUT'],
		['name' => 'auswertung#bericht', 'url' => '/api/v1/kind/{schuelerId}/bericht', 'verb' => 'GET'],
		['name' => 'auswertung#berichtDruck', 'url' => '/api/v1/kind/{schuelerId}/bericht/druck', 'verb' => 'GET'],
		['name' => 'auswertung#auskunft', 'url' => '/api/v1/kind/{schuelerId}/auskunft', 'verb' => 'GET'],
		['name' => 'auswertung#auskunftDruck', 'url' => '/api/v1/kind/{schuelerId}/auskunft/druck', 'verb' => 'GET'],
	],
];

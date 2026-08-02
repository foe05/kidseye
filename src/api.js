import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const basis = (pfad) => generateUrl('/apps/kidseye/api/v1' + pfad)

async function holen(pfad, params = {}) {
	const { data } = await axios.get(basis(pfad), { params })
	return data
}

async function senden(pfad, nutzlast = {}, methode = 'post') {
	const { data } = await axios[methode](basis(pfad), nutzlast)
	return data
}

export default {
	// --- Erfassung ----------------------------------------------------
	einstieg: () => holen('/einstieg'),
	startAuswahl: () => holen('/stunde/auswahl'),
	stundeStarten: (klasseId, kontextId, inhaltsfeldId = null) =>
		senden('/stunde', { klasseId, kontextId, inhaltsfeldId }),
	stundeBeenden: () => senden('/stunde', {}, 'delete'),
	bildschirm: () => holen('/bildschirm'),
	erfassen: (beobachtung) => senden('/beobachtung', beobachtung),
	erfassenMehrere: (nutzlast) => senden('/beobachtung/mehrere', nutzlast),
	synchronisieren: (eintraege) => senden('/beobachtung/sync', { eintraege }),
	zuruecknehmen: (id) => senden(`/beobachtung/${id}`, {}, 'delete'),
	heute: (schuelerId) => holen(`/kind/${schuelerId}/heute`),
	kinderSuche: (q) => holen('/kinder/suche', { q }),
	fotoAnhaengen: (beobachtungId, daten, endung = 'jpg') =>
		senden(`/beobachtung/${beobachtungId}/foto`, { daten, endung }),

	// --- Verwaltung ---------------------------------------------------
	einrichtung: () => holen('/einrichtung'),
	klassen: () => holen('/klassen'),
	kinder: (klasseId) => holen(`/klassen/${klasseId}/kinder`),
	lehrauftraege: () => holen('/lehrauftrag'),
	schuljahrAnlegen: (nutzlast) => senden('/schuljahr', nutzlast),
	klasseAnlegen: (name) => senden('/klassen', { name }),
	schuelerAnlegen: (nutzlast) => senden('/schueler', nutzlast),
	lehrauftragAnlegen: (nutzlast) => senden('/lehrauftrag', nutzlast),
	klassenbildSpeichern: (klasseId, eintraege) =>
		senden(`/klassen/${klasseId}/bild`, { eintraege }, 'put'),
	klassenbildZuruecksetzen: (klasseId) =>
		senden(`/klassen/${klasseId}/bild`, {}, 'delete'),
	importVorschau: (csv) => senden('/import/vorschau', { csv }),
	importUebernehmen: (csv) => senden('/import', { csv }),
	rolloverVorschlag: (schuljahrId) => holen('/rollover/vorschlag', { schuljahrId }),
	rolloverAusfuehren: (nachSchuljahrId, bestaetigt) =>
		senden('/rollover', { nachSchuljahrId, bestaetigt }),
	kontexte: () => holen('/kontexte'),
	marker: (kontextId) => holen(`/kontexte/${kontextId}/marker`),
	markerSpeichern: (kontextId, marker) =>
		senden(`/kontexte/${kontextId}/marker`, { marker }, 'put'),
	zwecke: () => holen('/zwecke'),
	zweckAnlegen: (nutzlast) => senden('/zwecke', nutzlast),
	zweckAendern: (id, nutzlast) => senden(`/zwecke/${id}`, nutzlast, 'put'),

	// --- Wochendurchgang ----------------------------------------------
	inbox: () => holen('/inbox'),
	inboxVorschlaege: (id) => holen(`/inbox/${id}/vorschlaege`),
	inboxZuordnen: (beobachtungIds, knotenIds, erledigen = true) =>
		senden('/inbox/zuordnen', { beobachtungIds, knotenIds, erledigen }),
	inboxErledigen: (beobachtungIds) => senden('/inbox/erledigen', { beobachtungIds }),
	merken: (id, gemerkt = true) => senden(`/beobachtung/${id}/merken`, { gemerkt }, 'put'),
	umhaengen: (id, schuelerId) =>
		senden(`/beobachtung/${id}/umhaengen`, { schuelerId }, 'put'),
	luecken: (klasseId, tage) => holen(`/klassen/${klasseId}/luecken`, { tage }),

	// --- Datenschutz --------------------------------------------------
	stufen: () => holen('/sichtbarkeit/stufen'),
	sichtbarkeitSetzen: (id, stufe) =>
		senden(`/beobachtung/${id}/sichtbarkeit`, { stufe }, 'put'),
	nachtragen: (id, text) => senden(`/beobachtung/${id}/nachtrag`, { text }),
	faellig: () => holen('/aufbewahrung/faellig'),
	schuljahresende: (bis) => holen('/aufbewahrung/schuljahresende', { bis }),
	loeschen: (beobachtungIds) => senden('/aufbewahrung/loeschen', { beobachtungIds }),

	// --- Auswertung ---------------------------------------------------
	zeitleiste: (schuelerId, filter = {}) => holen(`/kind/${schuelerId}/zeitleiste`, filter),
	heatmap: (klasseId, filter = {}) => holen(`/klassen/${klasseId}/heatmap`, filter),
	mappe: (schuelerId, zweck, filter = {}) =>
		holen(`/kind/${schuelerId}/mappe`, { zweck, ...filter }),
	einschaetzung: (zuordnungId, stufeId) =>
		senden(`/zuordnung/${zuordnungId}/einschaetzung`, { stufeId }, 'put'),
	bericht: (schuelerId, filter = {}) => holen(`/kind/${schuelerId}/bericht`, filter),
	auskunft: (schuelerId) => holen(`/kind/${schuelerId}/auskunft`),

	// Beide liefern ein fertiges PDF zum Herunterladen.
	berichtDruckUrl: (schuelerId, filter = {}) => {
		const p = new URLSearchParams(
			Object.entries(filter).filter(([, w]) => w !== null && w !== undefined && w !== '')
		)
		return basis(`/kind/${schuelerId}/bericht/druck`) + '?' + p.toString()
	},
	auskunftDruckUrl: (schuelerId) => basis(`/kind/${schuelerId}/auskunft/druck`),
}

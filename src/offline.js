import { openDB } from 'idb'

/**
 * Offline-Warteschlange der Erfassung (Kapitel 5.11–5.14, D9).
 *
 * Grundsatz: Speichern wartet nie. Jede Beobachtung geht zuerst in die
 * lokale Datenbank und wird danach im Hintergrund übertragen. Bei 20
 * Erfassungen am Tag ist jede Wartesekunde zwanzigfach spürbar — hängt das
 * Speichern ein einziges Mal, gilt die App im Kopf der Lehrkraft als kaputt.
 *
 * Auch eine abgelaufene Nextcloud-Sitzung darf die Erfassung nicht
 * blockieren: die Warteschlange übersteht einen Authentifizierungsfehler und
 * liefert nach erneuter Anmeldung nach.
 */

const DB_NAME = 'kidseye'
const DB_VERSION = 1
const SPEICHER = 'warteschlange'
const ENTWURF = 'entwurf'

let dbPromise = null

function db() {
	if (!dbPromise) {
		dbPromise = openDB(DB_NAME, DB_VERSION, {
			upgrade(datenbank) {
				if (!datenbank.objectStoreNames.contains(SPEICHER)) {
					const speicher = datenbank.createObjectStore(SPEICHER, { keyPath: 'clientUuid' })
					speicher.createIndex('erstelltAm', 'erstelltAm')
					speicher.createIndex('zustand', 'zustand')
				}
				if (!datenbank.objectStoreNames.contains(ENTWURF)) {
					datenbank.createObjectStore(ENTWURF, { keyPath: 'schluessel' })
				}
			},
		})
	}
	return dbPromise
}

/** Vom Client vergebene Kennung — Grundlage des Dublettenschutzes. */
export function neueUuid() {
	if (globalThis.crypto?.randomUUID) {
		return globalThis.crypto.randomUUID()
	}
	return 'k-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10)
}

/**
 * Nimmt eine Beobachtung entgegen. Kehrt sofort zurück — ohne Netzzugriff.
 */
export async function einreihen(beobachtung) {
	const eintrag = {
		...beobachtung,
		clientUuid: beobachtung.clientUuid || neueUuid(),
		// Echter Erfassungszeitpunkt vom Gerät, damit offline erfasste
		// Einträge nicht auf den Zeitpunkt der Übertragung rutschen.
		erfasstAm: beobachtung.erfasstAm || new Date().toISOString(),
		erstelltAm: Date.now(),
		zustand: 'offen',
		versuche: 0,
	}
	const datenbank = await db()
	await datenbank.put(SPEICHER, eintrag)
	return eintrag
}

export async function offene() {
	const datenbank = await db()
	const alle = await datenbank.getAll(SPEICHER)
	return alle
		.filter((e) => e.zustand !== 'fertig')
		.sort((a, b) => a.erstelltAm - b.erstelltAm)
}

export async function anzahlOffen() {
	return (await offene()).length
}

export async function entfernen(clientUuid) {
	const datenbank = await db()
	await datenbank.delete(SPEICHER, clientUuid)
}

/** Rückgängig innerhalb des Undo-Fensters, solange noch nicht übertragen. */
export async function zuruecknehmen(clientUuid) {
	const datenbank = await db()
	const eintrag = await datenbank.get(SPEICHER, clientUuid)
	if (!eintrag) {
		return false
	}
	await datenbank.delete(SPEICHER, clientUuid)
	// Wurde bereits übertragen, muss der Server ihn löschen
	return eintrag.serverId || null
}

async function markiere(clientUuid, aenderung) {
	const datenbank = await db()
	const eintrag = await datenbank.get(SPEICHER, clientUuid)
	if (eintrag) {
		await datenbank.put(SPEICHER, { ...eintrag, ...aenderung })
	}
}

/**
 * Erkennt eine fehlende Anmeldung.
 *
 * Der Status allein reicht nicht. Nextcloud beantwortet eine abgelaufene
 * Sitzung je nach Instanz und Aufrufform unterschiedlich: mit 401, mit einer
 * Umleitung auf die Anmeldeseite (der axios folgt und die mit 200 endet), oder
 * mit der Anmeldeseite selbst. Wird nur der Status geprüft, gilt der zweite
 * und dritte Fall als „keine Verbindung" — und die Lehrkraft sucht mitten im
 * Unterricht das WLAN, statt sich neu anzumelden.
 *
 * Geprüft wird deshalb an drei Merkmalen. Vorgebeugt wird dem Fall zusätzlich
 * in api.js mit dem Kopfzeilenfeld X-Requested-With; die drei Merkmale sind
 * die Absicherung für den Fall, dass es nicht greift.
 */
export function istAbgemeldet(antwortOderFehler) {
	const antwort = antwortOderFehler?.response ?? antwortOderFehler

	if (!antwort) {
		return false
	}

	// 1. Der geradlinige Fall.
	if (antwort.status === 401 || antwort.status === 403) {
		return true
	}

	// 2. HTML, wo JSON erwartet wurde — die Anmeldeseite kam durch.
	//
	// Der Rumpf kann hier in zwei Formen ankommen: als vollständige
	// axios-Antwort, oder als das, was api.js daraus zurückgibt — nämlich nur
	// die Nutzlast. Im zweiten Fall ist die Anmeldeseite eine blanke
	// Zeichenkette, und genau so kommt sie aus der Erfassung her an.
	const istHtml = (wert) => typeof wert === 'string' && /^\s*<(!doctype|html)/i.test(wert)
	if (istHtml(antwort) || istHtml(antwort.data)) {
		return true
	}

	const typ = antwort.headers?.['content-type'] ?? antwort.headers?.get?.('content-type') ?? ''
	if (typeof typ === 'string' && typ.includes('text/html')) {
		return true
	}

	// 3. Die Endadresse trägt den Anmeldepfad — axios ist einer Umleitung gefolgt.
	const ziel = antwort.request?.responseURL ?? antwort.request?.res?.responseUrl ?? ''
	return typeof ziel === 'string' && /\/login(\?|$|\/)/.test(ziel)
}

/**
 * Überträgt die Warteschlange.
 *
 * @param {Function} senden  async (eintraege) => { uebernommen, fehler }
 * @returns {Promise<{gesendet:number, offen:number, blockiert:boolean, grund:?string}>}
 *          grund ist 'abgemeldet', 'keine-verbindung' oder null
 */
export async function synchronisieren(senden) {
	const wartend = await offene()
	if (wartend.length === 0) {
		return { gesendet: 0, offen: 0, blockiert: false, grund: null }
	}

	const zurueckstellen = async (grund) => {
		for (const eintrag of wartend) {
			await markiere(eintrag.clientUuid, { versuche: (eintrag.versuche || 0) + 1 })
		}
		return {
			gesendet: 0,
			offen: wartend.length,
			blockiert: grund === 'abgemeldet',
			grund,
		}
	}

	let antwort
	try {
		antwort = await senden(wartend.map(zuNutzlast))
	} catch (fehler) {
		// Kein Netz oder abgelaufene Sitzung: nichts verwerfen, später erneut.
		// Genau hier entscheidet sich, ob ein Sitzungsablauf mitten im
		// Unterricht Daten kostet — er tut es nicht.
		return zurueckstellen(istAbgemeldet(fehler) ? 'abgemeldet' : 'keine-verbindung')
	}

	// Die Übertragung kam zurück, aber nicht als auswertbare Antwort: Das ist
	// die Umleitung auf die Anmeldeseite, die mit 200 endet. Ohne diese Prüfung
	// gälte sie als erfolgreiche Übertragung von null Einträgen — die
	// Warteschlange bliebe stehen, ohne dass jemand den Grund erführe.
	if (istAbgemeldet(antwort)) {
		return zurueckstellen('abgemeldet')
	}
	if (!antwort || typeof antwort !== 'object' || !Array.isArray(antwort.uebernommen)) {
		return zurueckstellen('keine-verbindung')
	}

	for (const treffer of antwort?.uebernommen || []) {
		if (treffer.clientUuid) {
			await entfernen(treffer.clientUuid)
		}
	}
	for (const fehler of antwort?.fehler || []) {
		if (fehler.clientUuid) {
			await markiere(fehler.clientUuid, { zustand: 'fehler', meldung: fehler.meldung })
		}
	}

	return {
		gesendet: (antwort?.uebernommen || []).length,
		offen: await anzahlOffen(),
		blockiert: false,
		grund: null,
	}
}

function zuNutzlast(eintrag) {
	const { erstelltAm, zustand, versuche, meldung, serverId, ...nutzlast } = eintrag
	return nutzlast
}

// --------------------------------------------------------------- Entwurf

/**
 * Entwurfsspeicherung (5.14): geht das Gerät während der Texteingabe zu,
 * ist der Text beim nächsten Öffnen unverändert da.
 */
export async function entwurfSpeichern(schluessel, inhalt) {
	const datenbank = await db()
	await datenbank.put(ENTWURF, { schluessel, inhalt, zeit: Date.now() })
}

export async function entwurfLesen(schluessel) {
	const datenbank = await db()
	const eintrag = await datenbank.get(ENTWURF, schluessel)
	return eintrag?.inhalt ?? null
}

export async function entwurfLoeschen(schluessel) {
	const datenbank = await db()
	await datenbank.delete(ENTWURF, schluessel)
}

export async function alleslLoeschen() {
	const datenbank = await db()
	await datenbank.clear(SPEICHER)
	await datenbank.clear(ENTWURF)
}

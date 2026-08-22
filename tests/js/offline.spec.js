import { describe, it, expect, beforeEach, vi } from 'vitest'
import {
	einreihen, offene, anzahlOffen, synchronisieren, zuruecknehmen,
	entwurfSpeichern, entwurfLesen, entwurfLoeschen, alleslLoeschen, neueUuid,
	istAbgemeldet,
} from '../../src/offline.js'

/**
 * Kapitel 9.3 — Offline-Warteschlange.
 *
 * Die drei Fälle, an denen der Erfassungs-Workflow hängt: Netzabbruch,
 * Sitzungsablauf und Dublettenschutz. Geht einer davon schief, verliert die
 * Lehrkraft Beobachtungen mitten im Unterricht.
 */
describe('Offline-Warteschlange', () => {
	beforeEach(async () => {
		await alleslLoeschen()
	})

	it('nimmt eine Beobachtung ohne Netzzugriff an', async () => {
		const eintrag = await einreihen({ schuelerId: 7, markerId: 3 })

		expect(eintrag.clientUuid).toBeTruthy()
		expect(eintrag.erfasstAm).toBeTruthy()
		expect(await anzahlOffen()).toBe(1)
	})

	it('vergibt einen Zeitstempel vom Gerät, nicht vom Server', async () => {
		const vorher = Date.now()
		const eintrag = await einreihen({ schuelerId: 1 })
		const zeit = new Date(eintrag.erfasstAm).getTime()

		expect(zeit).toBeGreaterThanOrEqual(vorher - 1000)
		expect(zeit).toBeLessThanOrEqual(Date.now() + 1000)
	})

	it('übernimmt einen vorgegebenen Erfassungszeitpunkt', async () => {
		const eintrag = await einreihen({
			schuelerId: 1,
			erfasstAm: '2026-07-27T09:37:00.000Z',
		})

		expect(eintrag.erfasstAm).toBe('2026-07-27T09:37:00.000Z')
	})

	it('behält die Reihenfolge der Erfassung bei', async () => {
		await einreihen({ schuelerId: 1, clientUuid: 'a' })
		await einreihen({ schuelerId: 2, clientUuid: 'b' })
		await einreihen({ schuelerId: 3, clientUuid: 'c' })

		const wartend = await offene()
		expect(wartend.map((e) => e.clientUuid)).toEqual(['a', 'b', 'c'])
	})

	it('legt bei gleicher clientUuid keinen zweiten Eintrag an', async () => {
		await einreihen({ schuelerId: 1, clientUuid: 'gleich' })
		await einreihen({ schuelerId: 1, clientUuid: 'gleich' })

		expect(await anzahlOffen()).toBe(1)
	})

	it('entfernt übernommene Einträge nach der Übertragung', async () => {
		const a = await einreihen({ schuelerId: 1 })
		const b = await einreihen({ schuelerId: 2 })

		const senden = vi.fn(async (eintraege) => ({
			uebernommen: eintraege.map((e) => ({ clientUuid: e.clientUuid, id: 99 })),
			fehler: [],
		}))
		const ergebnis = await synchronisieren(senden)

		expect(senden).toHaveBeenCalledOnce()
		expect(ergebnis.gesendet).toBe(2)
		expect(await anzahlOffen()).toBe(0)
		expect([a.clientUuid, b.clientUuid]).toHaveLength(2)
	})

	it('sendet keine internen Felder mit', async () => {
		await einreihen({ schuelerId: 5, text: 'hilft' })

		let empfangen = null
		await synchronisieren(async (eintraege) => {
			empfangen = eintraege[0]
			return { uebernommen: [], fehler: [] }
		})

		expect(empfangen).toHaveProperty('schuelerId', 5)
		expect(empfangen).not.toHaveProperty('zustand')
		expect(empfangen).not.toHaveProperty('versuche')
		expect(empfangen).not.toHaveProperty('erstelltAm')
	})

	it('verwirft bei Netzabbruch nichts', async () => {
		await einreihen({ schuelerId: 1 })
		await einreihen({ schuelerId: 2 })

		const ergebnis = await synchronisieren(async () => {
			throw new Error('Network Error')
		})

		expect(ergebnis.gesendet).toBe(0)
		expect(ergebnis.offen).toBe(2)
		expect(await anzahlOffen()).toBe(2)
	})

	// D15/D9: Genau hier entscheidet sich, ob ein Sitzungsablauf mitten im
	// Unterricht Daten kostet.
	it('übersteht einen Sitzungsablauf und meldet ihn als blockiert', async () => {
		await einreihen({ schuelerId: 1 })

		const fehler = new Error('Unauthorized')
		fehler.response = { status: 401 }
		const ergebnis = await synchronisieren(async () => {
			throw fehler
		})

		expect(ergebnis.blockiert).toBe(true)
		expect(await anzahlOffen()).toBe(1)
	})

	it('liefert nach erneuter Anmeldung vollständig nach', async () => {
		await einreihen({ schuelerId: 1 })
		await einreihen({ schuelerId: 2 })

		const abgelehnt = new Error('Unauthorized')
		abgelehnt.response = { status: 401 }
		await synchronisieren(async () => {
			throw abgelehnt
		})
		expect(await anzahlOffen()).toBe(2)

		const ergebnis = await synchronisieren(async (eintraege) => ({
			uebernommen: eintraege.map((e) => ({ clientUuid: e.clientUuid })),
			fehler: [],
		}))

		expect(ergebnis.gesendet).toBe(2)
		expect(await anzahlOffen()).toBe(0)
	})

	it('blockiert die Warteschlange nicht wegen eines einzelnen kaputten Eintrags', async () => {
		await einreihen({ schuelerId: 1, clientUuid: 'gut' })
		await einreihen({ schuelerId: 0, clientUuid: 'kaputt' })

		await synchronisieren(async () => ({
			uebernommen: [{ clientUuid: 'gut' }],
			fehler: [{ clientUuid: 'kaputt', meldung: 'Unbekanntes Kind' }],
		}))

		const rest = await offene()
		expect(rest).toHaveLength(1)
		expect(rest[0].clientUuid).toBe('kaputt')
		expect(rest[0].zustand).toBe('fehler')
	})

	it('nimmt einen noch nicht übertragenen Eintrag spurlos zurück', async () => {
		const eintrag = await einreihen({ schuelerId: 4 })

		const serverId = await zuruecknehmen(eintrag.clientUuid)

		expect(serverId).toBeNull()
		expect(await anzahlOffen()).toBe(0)
	})

	it('erzeugt eindeutige Kennungen', () => {
		const kennungen = new Set(Array.from({ length: 500 }, () => neueUuid()))
		expect(kennungen.size).toBe(500)
	})
})

describe('Entwurfsspeicherung', () => {
	beforeEach(async () => {
		await alleslLoeschen()
	})

	// 5.14: Geht das Gerät während der Texteingabe zu, muss der Text da sein.
	it('übersteht das Schließen der Anwendung', async () => {
		await entwurfSpeichern('notiz-1-7', 'Mia hat Jonas geholfen')

		expect(await entwurfLesen('notiz-1-7')).toBe('Mia hat Jonas geholfen')
	})

	it('hält Entwürfe je Kind und Stunde auseinander', async () => {
		await entwurfSpeichern('notiz-1-7', 'für Mia')
		await entwurfSpeichern('notiz-1-8', 'für Tim')

		expect(await entwurfLesen('notiz-1-7')).toBe('für Mia')
		expect(await entwurfLesen('notiz-1-8')).toBe('für Tim')
	})

	it('liefert null für einen unbekannten Entwurf', async () => {
		expect(await entwurfLesen('gibt-es-nicht')).toBeNull()
	})

	it('löscht einen Entwurf nach dem Sichern', async () => {
		await entwurfSpeichern('notiz-1-7', 'Text')
		await entwurfLoeschen('notiz-1-7')

		expect(await entwurfLesen('notiz-1-7')).toBeNull()
	})
})


/**
 * Prüfschritt C4 der Geräteprüfung.
 *
 * Bis hierher wurde die fehlende Anmeldung allein am Status erkannt. Antwortet
 * eine Instanz stattdessen mit einer Umleitung auf die Anmeldeseite, kommt die
 * Antwort mit 200 und HTML zurück — sie sah aus wie eine geglückte Übertragung
 * von null Einträgen. Die Warteschlange blieb stehen, und der Hinweis nannte
 * den falschen Grund.
 */
describe('Erkennung der fehlenden Anmeldung', () => {
	const alsFehler = (antwort) => ({ response: antwort })

	it('erkennt den Status 401', () => {
		expect(istAbgemeldet(alsFehler({ status: 401 }))).toBe(true)
	})

	it('erkennt den Status 403', () => {
		expect(istAbgemeldet(alsFehler({ status: 403 }))).toBe(true)
	})

	it('erkennt HTML mit Status 200 als Anmeldeseite', () => {
		const antwort = {
			status: 200,
			headers: { 'content-type': 'text/html; charset=utf-8' },
			data: '<!DOCTYPE html><html><body>Anmeldung</body></html>',
		}

		expect(istAbgemeldet(antwort)).toBe(true)
	})

	it('erkennt HTML auch ohne Inhaltstyp in der Kopfzeile', () => {
		const antwort = { status: 200, data: '<!doctype html><html></html>' }

		expect(istAbgemeldet(antwort)).toBe(true)
	})

	it('erkennt den Anmeldepfad in der Endadresse', () => {
		const antwort = {
			status: 200,
			request: { responseURL: 'https://cloud.example/login?redirect_url=%2Fapps%2Fkidseye' },
		}

		expect(istAbgemeldet(antwort)).toBe(true)
	})

	it('hält eine gültige Antwort nicht für eine Abmeldung', () => {
		const antwort = {
			status: 200,
			headers: { 'content-type': 'application/json' },
			data: { uebernommen: [], fehler: [] },
			request: { responseURL: 'https://cloud.example/apps/kidseye/api/v1/beobachtung/sync' },
		}

		expect(istAbgemeldet(antwort)).toBe(false)
	})

	it('hält einen Netzfehler ohne Antwort nicht für eine Abmeldung', () => {
		expect(istAbgemeldet(new Error('Network Error'))).toBe(false)
		expect(istAbgemeldet(undefined)).toBe(false)
	})
})

describe('Warteschlange bei gestörter Übertragung', () => {
	beforeEach(async () => {
		await alleslLoeschen()
	})

	const dreiEinreihen = async () => {
		await einreihen({ schuelerId: 1, markerId: 1 })
		await einreihen({ schuelerId: 2, markerId: 1 })
		await einreihen({ schuelerId: 3, markerId: 1 })
	}

	it('nennt bei Status 401 den Grund „abgemeldet" und behält alles', async () => {
		await dreiEinreihen()
		const ergebnis = await synchronisieren(() => {
			throw { response: { status: 401 } }
		})

		expect(ergebnis.grund).toBe('abgemeldet')
		expect(ergebnis.blockiert).toBe(true)
		expect(await anzahlOffen()).toBe(3)
	})

	it('nennt bei einer Umleitung mit 200 ebenfalls „abgemeldet"', async () => {
		await dreiEinreihen()
		// Genau der Fall, der vorher als geglückte Übertragung durchging.
		const ergebnis = await synchronisieren(async () => '<!DOCTYPE html><html></html>')

		expect(ergebnis.grund).toBe('abgemeldet')
		expect(ergebnis.blockiert).toBe(true)
		expect(ergebnis.gesendet).toBe(0)
		expect(await anzahlOffen()).toBe(3)
	})

	it('nennt bei einem Netzfehler „keine-verbindung" und behält alles', async () => {
		await dreiEinreihen()
		const ergebnis = await synchronisieren(() => {
			throw new Error('Network Error')
		})

		expect(ergebnis.grund).toBe('keine-verbindung')
		expect(ergebnis.blockiert).toBe(false)
		expect(await anzahlOffen()).toBe(3)
	})

	it('behält alles bei einer Antwort ohne auswertbaren Inhalt', async () => {
		await dreiEinreihen()
		const ergebnis = await synchronisieren(async () => null)

		expect(ergebnis.grund).toBe('keine-verbindung')
		expect(await anzahlOffen()).toBe(3)
	})

	it('meldet nach geglückter Übertragung keinen Grund', async () => {
		const eintrag = await einreihen({ schuelerId: 4, markerId: 2 })
		const ergebnis = await synchronisieren(async (eintraege) => ({
			uebernommen: eintraege.map((e) => ({ clientUuid: e.clientUuid, id: 99 })),
			fehler: [],
		}))

		expect(ergebnis.grund).toBeNull()
		expect(ergebnis.gesendet).toBe(1)
		expect(await anzahlOffen()).toBe(0)
		expect(eintrag.clientUuid).toBeTruthy()
	})
})

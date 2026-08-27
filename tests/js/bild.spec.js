import { describe, it, expect } from 'vitest'
import { passeAn, MAX_KANTE, QUALITAET } from '../../src/bild.js'

/**
 * Kapitel 5.8 / D8 — Fotos vor dem Hochladen verkleinern.
 *
 * Zwei Gründe, beide im Fundament: EXIF-Ortsdaten dürfen das Gerät nicht
 * verlassen, und im Schul-WLAN ist der Unterschied zwischen 3 MB und 400 KB
 * der zwischen „geht durch" und „hängt".
 */
describe('Bildaufbereitung', () => {
	it('lässt kleine Bilder unverändert', () => {
		expect(passeAn(800, 600, 2000)).toEqual({ breite: 800, hoehe: 600 })
	})

	it('lässt ein Bild genau auf der Grenze unverändert', () => {
		expect(passeAn(2000, 1500, 2000)).toEqual({ breite: 2000, hoehe: 1500 })
	})

	it('verkleinert ein iPad-Foto im Querformat auf die lange Kante', () => {
		const { breite, hoehe } = passeAn(4032, 3024, 2000)

		expect(breite).toBe(2000)
		expect(hoehe).toBe(1500)
	})

	it('verkleinert ein Hochformat auf die lange Kante', () => {
		const { breite, hoehe } = passeAn(3024, 4032, 2000)

		expect(hoehe).toBe(2000)
		expect(breite).toBe(1500)
	})

	it('behält das Seitenverhältnis bei', () => {
		const vorher = 4032 / 3024
		const { breite, hoehe } = passeAn(4032, 3024, 2000)

		expect(breite / hoehe).toBeCloseTo(vorher, 2)
	})

	it('erzeugt niemals eine Kantenlänge von null', () => {
		const { breite, hoehe } = passeAn(10000, 3, 2000)

		expect(breite).toBeGreaterThanOrEqual(1)
		expect(hoehe).toBeGreaterThanOrEqual(1)
	})

	it('hält die Vorgaben aus dem Entwurf ein', () => {
		// 2000 px bei Qualität 0,8 — daraus folgt die Rechnung
		// 1,7 GB → 0,3 GB pro Lehrkraft und Jahr aus design.md D8
		expect(MAX_KANTE).toBe(2000)
		expect(QUALITAET).toBe(0.8)
	})
})

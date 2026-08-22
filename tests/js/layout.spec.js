import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const lies = (pfad) => readFileSync(resolve(process.cwd(), pfad), 'utf8')

/**
 * Kapitel 9.7 — Bedienbarkeit auf iPad und Handy.
 *
 * Rechnerische Prüfung der Maße aus D10: bei 20 Kindern und vier Kacheln pro
 * Reihe muss das Touchziel auf jedem unterstützten Gerät über dem
 * Mindestmaß von 44 px liegen.
 */
describe('Touchziele und Raster', () => {
	const SPALTEN = 4
	const ABSTAND = 4.8 // .3rem
	const MINDESTMASS = 44

	function kachelBreite(geraeteBreite, planAnteil = 1, seitenpolster = 24) {
		const nutzbar = (geraeteBreite - seitenpolster) * planAnteil
		return (nutzbar - ABSTAND * (SPALTEN - 1)) / SPALTEN
	}

	it('erreicht auf dem kleinsten Handy deutlich mehr als 44 px', () => {
		const breite = kachelBreite(375)

		expect(breite).toBeGreaterThan(MINDESTMASS)
		// entspricht der Rechnung „~84 px" aus design.md D10
		expect(Math.round(breite)).toBe(84)
	})

	it('ist auf dem iPad im Hochformat komfortabel', () => {
		// Zweispaltig: die Planspalte bekommt rund 57 Prozent
		const breite = kachelBreite(820, 0.57)

		expect(breite).toBeGreaterThan(MINDESTMASS * 2)
	})

	it('ist auf dem iPad im Querformat komfortabel', () => {
		const breite = kachelBreite(1180, 0.57)

		expect(breite).toBeGreaterThan(MINDESTMASS * 3)
	})

	it('zeigt 20 Kinder in fünf Reihen', () => {
		expect(Math.ceil(20 / SPALTEN)).toBe(5)
	})
})

describe('Erfassungsbildschirm', () => {
	const quelle = lies('src/components/Unterricht.vue')

	it('setzt eine Mindesthöhe für die Kacheln', () => {
		expect(quelle).toMatch(/\.ke-kachel\s*\{[^}]*min-height:\s*56px/)
	})

	it('hält den Breakpoint bei 700 px ein', () => {
		expect(quelle).toContain('window.innerWidth >= 700')
	})

	it('gibt allen Antippflächen mindestens 44 px', () => {
		for (const regel of ['.ke-mark', '.ke-foto', '.ke-suchtreffer']) {
			const treffer = quelle.match(
				new RegExp(regel.replace('.', '\\.') + '[^{]*\\{[^}]*min-height:\\s*(\\d+)px')
			)
			expect(treffer, regel + ' hat keine Mindesthöhe').not.toBeNull()
			expect(Number(treffer[1])).toBeGreaterThanOrEqual(44)
		}
	})

	it('berücksichtigt die sicheren Bereiche des Geräts', () => {
		expect(quelle).toContain('env(safe-area-inset-top)')
		expect(quelle).toContain('env(safe-area-inset-bottom)')
	})

	it('respektiert eine reduzierte Bewegungsvorliebe', () => {
		expect(quelle).toContain('prefers-reduced-motion')
	})

	// 5.5: Ein Tap auf einen Marker sichert und schließt, ohne zweiten Schritt
	it('sichert einen Marker ohne Bestätigungsschritt', () => {
		expect(quelle).toMatch(/async markerTippen\(m\)\s*\{\s*await this\.sichern/)
	})

	// 5.15: Rückgängig statt Bestätigung, mindestens fünf Sekunden
	it('bietet mindestens fünf Sekunden zum Rückgängigmachen', () => {
		const treffer = quelle.match(/const UNDO_MS = (\d+)/)
		expect(treffer).not.toBeNull()
		expect(Number(treffer[1])).toBeGreaterThanOrEqual(5000)
	})

	it('schreibt zuerst lokal und wartet nicht auf das Netz', () => {
		// In sichern() steht einreihen() vor jedem Netzaufruf
		const sichern = quelle.slice(quelle.indexOf('async sichern('))
		const lokal = sichern.indexOf('einreihen(')
		const netz = sichern.indexOf('this.synchronisieren()')
		expect(lokal).toBeGreaterThan(-1)
		expect(netz).toBeGreaterThan(lokal)
	})
})

describe('Manifest für den Home-Bildschirm', () => {
	const manifest = JSON.parse(lies('img/manifest.json'))
	const controller = lies('lib/Controller/PageController.php')

	/*
	 * start_url und scope standen bis zur Installationshärtung im Manifest —
	 * als „../apps/kidseye/unterricht". Gegen den Ort des Manifests
	 * (/apps/kidseye/img/) aufgelöst ergab das /apps/kidseye/apps/kidseye/…
	 *
	 * Die Prüfung an dieser Stelle lautete toContain('/apps/kidseye/unterricht')
	 * und war grün: der falsche Wert enthält die gesuchte Zeichenkette. Beide
	 * Werte kommen jetzt aus dem PageController; die vollständige Prüfung
	 * steht in tests/js/manifest.spec.js.
	 */
	it('startet unmittelbar im Erfassungsbildschirm', () => {
		expect(manifest.start_url).toBeUndefined()
		expect(controller).toContain("linkToRoute(Application::APP_ID . '.page.unterricht')")
	})

	it('begrenzt den Geltungsbereich auf die App', () => {
		expect(manifest.scope).toBeUndefined()
		expect(controller).toContain("\$manifest['scope'] = \$this->urlGenerator->linkToRoute")
	})

	it('läuft im Vollbild ohne Browserleiste', () => {
		expect(manifest.display).toBe('standalone')
	})

	it('bringt ein eigenes Symbol mit', () => {
		expect(manifest.icons.length).toBeGreaterThan(0)
	})
})

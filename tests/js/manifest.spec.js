import { describe, it, expect } from 'vitest'
import { existsSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const lies = (pfad) => readFileSync(resolve(process.cwd(), pfad), 'utf8')
const manifest = JSON.parse(lies('img/manifest.json'))

/**
 * Kapitel 0.2b und D14 — „Zum Home-Bildschirm".
 *
 * Zwei Fehler, die diese Prüfungen ausschließen, standen beide im
 * Auslieferstand und wären erst auf dem Gerät aufgefallen:
 *
 *  1. Das Manifest verlangte favicon-touch.png, die Datei fehlte. iOS
 *     ersetzt ein nicht auflösbares Symbol durch einen Bildschirmabzug.
 *  2. start_url und scope standen als „../apps/kidseye/…" im Manifest.
 *     Gegen den Ort des Manifests (/apps/kidseye/img/) aufgelöst ergab das
 *     /apps/kidseye/apps/kidseye/unterricht — das Symbol wäre auf einer
 *     nicht vorhandenen Seite gelandet.
 *
 * Erzeugt wird das Symbol mit tools/symbol-erzeugen.mjs:
 *     npm exec --yes --package sharp -- node tools/symbol-erzeugen.mjs
 */
describe('Manifest für den Home-Bildschirm', () => {
	it('nennt nur Symbole, die auch ausgeliefert werden', () => {
		expect(manifest.icons.length).toBeGreaterThan(0)

		for (const symbol of manifest.icons) {
			expect(existsSync(resolve(process.cwd(), 'img', symbol.src))).toBe(true)
		}
	})

	it('führt ein 512×512-PNG als Symbol', () => {
		const png = manifest.icons.find((s) => s.type === 'image/png')

		expect(png).toBeDefined()
		expect(png.sizes).toBe('512x512')
	})

	it('nennt die Symbole ohne Pfad, damit der Controller sie auflösen kann', () => {
		for (const symbol of manifest.icons) {
			expect(symbol.src).not.toContain('/')
		}
	})

	it('startet im Vollbild', () => {
		expect(manifest.display).toBe('standalone')
		expect(manifest.short_name).toBe('kidseye')
	})

	it('enthält keine instanzabhängigen Adressen', () => {
		// start_url und scope kommen aus dem PageController. Stünden sie hier,
		// wären sie auf jeder Instanz falsch, die nicht im Wurzelverzeichnis
		// liegt oder keine umgeschriebenen Adressen hat.
		expect(manifest.start_url).toBeUndefined()
		expect(manifest.scope).toBeUndefined()
	})
})

describe('Auslieferung des Manifests', () => {
	const routen = lies('appinfo/routes.php')
	const controller = lies('lib/Controller/PageController.php')

	it('ist als eigene Route erreichbar', () => {
		expect(routen).toContain("'name' => 'page#manifest'")
	})

	it('wird im Erfassungsbildschirm verlinkt', () => {
		expect(controller).toMatch(/'rel'\s*=>\s*'manifest'/)
		expect(controller).toContain("linkToRoute(Application::APP_ID . '.page.manifest')")
	})

	it('setzt start_url und scope aus derselben Quelle', () => {
		// Nur so enthält scope die start_url in jeder der vier Kombinationen
		// aus Unterordner und umgeschriebenen Adressen.
		expect(controller).toContain("\$manifest['start_url'] = \$this->urlGenerator->linkToRoute")
		expect(controller).toContain("\$manifest['scope'] = \$this->urlGenerator->linkToRoute")
	})

	it('löst die Symbolpfade über den URL-Erzeuger auf', () => {
		expect(controller).toContain('imagePath(Application::APP_ID, $symbol[\'src\'])')
	})

	it('liefert den richtigen Inhaltstyp', () => {
		expect(controller).toContain("'Content-Type' => 'application/manifest+json'")
	})
})

describe('Auflösung der Adressen', () => {
	// Bildet nach, wie ein Browser die vom Controller gesetzten Werte
	// auflöst — für beide Formen, die linkToRoute erzeugen kann.
	const faelle = [
		['im Wurzelverzeichnis', 'https://cloud.example', '/apps/kidseye/'],
		['im Unterordner', 'https://example.org', '/nextcloud/apps/kidseye/'],
		['ohne umgeschriebene Adressen', 'https://cloud.example', '/index.php/apps/kidseye/'],
	]

	for (const [name, host, wurzel] of faelle) {
		it(`hält start_url innerhalb von scope — ${name}`, () => {
			const scope = new URL(wurzel, host)
			const start = new URL(wurzel + 'unterricht', host)

			expect(start.href.startsWith(scope.href)).toBe(true)
		})
	}

	it('erkennt die alte, doppelte Auflösung als Fehler', () => {
		const manifestOrt = 'https://cloud.example/apps/kidseye/img/manifest.json'
		const alt = new URL('../apps/kidseye/unterricht', manifestOrt)

		expect(alt.pathname).toBe('/apps/kidseye/apps/kidseye/unterricht')
		expect(alt.pathname).not.toBe('/apps/kidseye/unterricht')
	})
})

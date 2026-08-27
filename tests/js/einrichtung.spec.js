import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const lies = (pfad) => readFileSync(resolve(process.cwd(), pfad), 'utf8')

/**
 * Der Einrichtungsstand in der Oberfläche (design.md E2).
 *
 * Der Reiter „Einrichtung" und `occ kidseye:pruefen` müssen dasselbe sagen.
 * Sie tun das, weil beide aus dem DiagnoseService lesen — geprüft wird hier,
 * dass diese Kopplung besteht und niemand wieder eine zweite, von Hand
 * gepflegte Liste danebenstellt.
 */
describe('Einrichtungsstand', () => {
	const oberflaeche = lies('src/components/Einrichtung.vue')
	const controller = lies('lib/Controller/VerwaltungController.php')
	const dienst = lies('lib/Service/DiagnoseService.php')

	it('liest die Punkte aus dem Register statt aus einer eigenen Liste', () => {
		expect(oberflaeche).toContain('v-for="punkt in stand.punkte"')
		expect(controller).toContain("'punkte' => \$this->diagnose->pruefen(\$nutzerId)")
	})

	it('führt keine zweite Wahrheit über die Serveroption mehr', () => {
		// standaloneFenster stand früher eigens im Endpunkt; jetzt gehört die
		// Aussage dem Register.
		expect(controller).not.toContain('standaloneFenster')
		expect(oberflaeche).not.toContain('standaloneFenster')
		expect(dienst).toContain('theming.standalone_window.enabled')
	})

	it('zeigt zu jedem offenen Punkt die Abhilfe', () => {
		expect(oberflaeche).toContain('punkt.abhilfe')
	})

	it('kennt den Punkt „Lehraufträge" mit seiner Folge', () => {
		// Der Schritt, den man bei der Einrichtung am leichtesten vergisst.
		expect(dienst).toContain("'lehrauftraege'")
		expect(dienst).toContain('keine Stunde starten')
	})

	it('kennt den Punkt „Symbol für den Home-Bildschirm"', () => {
		expect(dienst).toContain('Symbol für den Home-Bildschirm')
		expect(dienst).toContain('tools/symbol-erzeugen.mjs')
	})

	it('behandelt „nicht prüfbar" nicht als Mangel', () => {
		expect(oberflaeche).toContain("punkt.zustand === 'nicht_pruefbar'")
	})

	it('behält das Schuljahr im Endpunkt — die Stammdaten lesen es mit', () => {
		expect(controller).toContain("'schuljahr' => \$this->stammdaten->aktivesSchuljahr()")
		expect(lies('src/components/Stammdaten.vue')).toContain('einrichtung.schuljahr')
	})
})

/**
 * Die Prüfpunkte selbst.
 *
 * Jeder offene Punkt muss sagen, was zu tun ist — sonst ist die Prüfung nur
 * eine zweite Art, ratlos zu sein.
 */
describe('Prüfpunkte des Registers', () => {
	const dienst = lies('lib/Service/DiagnoseService.php')

	const erwartet = [
		'tabellen', 'rahmen', 'schuljahr', 'gruppen',
		'lehrauftraege', 'ablage', 'vollbild', 'symbol',
	]

	for (const kennung of erwartet) {
		it(`führt den Punkt „${kennung}"`, () => {
			expect(dienst).toContain(`'${kennung}',`)
		})
	}

	it('nennt zu den occ-Punkten den Befehl, der sie erfüllt', () => {
		expect(dienst).toContain('occ kidseye:einrichten')
		expect(dienst).toContain('occ group:add')
		expect(dienst).toContain('occ config:system:set theming.standalone_window.enabled')
		expect(dienst).toContain('occ app:disable kidseye && occ app:enable kidseye')
	})
})

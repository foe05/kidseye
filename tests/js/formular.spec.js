import { describe, it, expect } from 'vitest'
import { readFileSync, readdirSync } from 'node:fs'
import { resolve } from 'node:path'

const lies = (pfad) => readFileSync(resolve(process.cwd(), pfad), 'utf8')

const css = lies('css/kidseye.css')

/**
 * Change „formularelemente-vereinheitlichen".
 *
 * Im Muster von layout.spec.js: geprüft wird Quelltext, nicht gerendertes
 * Layout. jsdom rechnet kein Layout — eine Prüfung über
 * getBoundingClientRect wäre immer grün (design.md E2).
 *
 * Die Prüfungen aus Gruppe „Mindestmaß" halten den Zustand, statt ihn nur
 * einmal herzustellen: sie schlagen an, sobald irgendwo wieder eine
 * Mindesthöhe unter 44 px auftaucht.
 */

/**
 * Zerlegt einen Selektor an den Kommas, die nicht in Klammern stehen.
 * `:where(#kidseye-main, #kidseye-unterricht) a, … b` sind zwei Selektoren,
 * nicht vier — ein glattes split(',') zerschneidet das :where().
 */
function teile(selektor) {
	const stuecke = []
	let tiefe = 0
	let aktuell = ''
	for (const zeichen of selektor) {
		if (zeichen === '(') {
			tiefe++
		} else if (zeichen === ')') {
			tiefe--
		}
		if (zeichen === ',' && tiefe === 0) {
			stuecke.push(aktuell)
			aktuell = ''
			continue
		}
		aktuell += zeichen
	}
	stuecke.push(aktuell)
	return stuecke.map((t) => t.trim()).filter(Boolean)
}

/** Zerlegt eine Stildatei in Paare aus Selektor und Regelinhalt. */
function regeln(quelle) {
	const ohneKommentare = quelle.replace(/\/\*[\s\S]*?\*\//g, '')
	const gefunden = []
	for (const treffer of ohneKommentare.matchAll(/([^{}]+)\{([^{}]*)\}/g)) {
		const selektor = treffer[1].trim()
		// @media-Kopfzeilen und dergleichen sind keine Regeln
		if (selektor.startsWith('@')) {
			continue
		}
		gefunden.push({ selektor, inhalt: treffer[2] })
	}
	return gefunden
}

describe('Stildatei css/kidseye.css', () => {
	/*
	 * Die Zusicherung ist: keine Regel dieser Datei wirkt außerhalb der App.
	 * Dafür muss der Wurzelselektor vorkommen — er muss aber nicht am Anfang
	 * stehen. Die Stufen der Belegdichte hängen zusätzlich am Thema und
	 * stehen deshalb unter
	 * `:where(body[data-theme-dark], …) :where(#kidseye-main, …)`.
	 * Ein vorangestellter Themenselektor engt weiter ein, statt zu öffnen;
	 * die Zusicherung bleibt.
	 */
	const WURZEL = ':where(#kidseye-main, #kidseye-unterricht)'

	it('steht mit jeder Regel unter einem der beiden Wurzelselektoren', () => {
		for (const { selektor } of regeln(css)) {
			for (const einzeln of teile(selektor)) {
				expect(
					einzeln.includes(WURZEL),
					'Regel ohne Wurzelselektor: ' + einzeln
				).toBe(true)
				// Was hinter der Wurzel steht, muss an ihr hängen — nicht ein
				// weiterer, eigenständiger Selektor daneben sein.
				const rest = einzeln.slice(einzeln.indexOf(WURZEL) + WURZEL.length)
				expect(
					rest,
					'Wurzelselektor greift nicht auf den Rest durch: ' + einzeln
				).toMatch(/^($|[\s.:[])/)
			}
		}
	})

	/*
	 * :where() steuert null Spezifität bei. Das ist der Grund, warum diese
	 * Datei den durchgestalteten Erfassungsbildschirm nicht überschreibt:
	 * ein Wurzelselektor mit ID (1,0,1) schlüge jeden scoped
	 * Komponentenstil (0,2,0), :where(#…) tut es nicht.
	 */
	it('lässt den Komponentenstilen den Vorrang', () => {
		expect(css).not.toMatch(/^\s*#kidseye-(main|unterricht)\b/m)
		expect(css).toContain(':where(#kidseye-main, #kidseye-unterricht)')
	})

	it('gestaltet alle vier Elementarten gemeinsam', () => {
		const grundregel = regeln(css).find((r) => /min-height:\s*var\(--ke-hoehe\)/.test(r.inhalt))
		expect(grundregel, 'keine gemeinsame Grundregel gefunden').toBeDefined()

		for (const art of ['select', 'textarea', 'button', 'input']) {
			expect(grundregel.selektor, art + ' fehlt in der Grundregel').toContain(' ' + art)
		}
		for (const eigenschaft of ['border:', 'border-radius:', 'padding:', 'background-color:', 'color:']) {
			expect(grundregel.inhalt, eigenschaft + ' fehlt').toContain(eigenschaft)
		}
	})

	it('nimmt Ankreuzfelder und Auswahlknöpfe aus', () => {
		expect(css).toMatch(/input:not\(\[type="checkbox"\], \[type="radio"\]\)/)
	})

	it('führt 44 px als Maß und 48 px für die bestätigende Handlung', () => {
		expect(css).toMatch(/--ke-hoehe:\s*44px/)
		expect(css).toMatch(/--ke-hoehe-primaer:\s*48px/)

		const primaer = regeln(css).find((r) => r.selektor.endsWith('.primary'))
		expect(primaer, '.primary nicht gestaltet').toBeDefined()
		expect(primaer.inhalt).toMatch(/min-height:\s*var\(--ke-hoehe-primaer\)/)
	})

	it('bezieht den Radius über die Nextcloud-Variable mit Rückfallwert', () => {
		expect(css).toMatch(/--ke-radius:\s*var\(--border-radius,\s*[^)]+\)/)
	})

	it('schaltet die native Darstellung der Auswahlfelder ab und ersetzt den Pfeil', () => {
		const select = regeln(css).find(
			(r) => r.selektor === ':where(#kidseye-main, #kidseye-unterricht) select'
		)
		expect(select).toBeDefined()
		expect(select.inhalt).toMatch(/appearance:\s*none/)
		// data:-URI, keine zweite Datei, die beim Ausliefern fehlen kann
		expect(select.inhalt).toMatch(/background-image:\s*url\("data:image\/svg\+xml,/)
		expect(select.inhalt).not.toMatch(/url\(["']?(?!data:)[^)]*\.(svg|png)/)
	})

	it('lässt die Mehrfachauswahlen nativ', () => {
		const mehrfach = regeln(css).find((r) => r.selektor.endsWith('select[multiple]'))
		expect(mehrfach, 'select[multiple] nicht behandelt').toBeDefined()
		expect(mehrfach.inhalt).toMatch(/appearance:\s*auto/)
		expect(mehrfach.inhalt).toMatch(/background-image:\s*none/)
	})

	it('setzt color-scheme für das native Auswahlmenü', () => {
		expect(css).toMatch(/color-scheme:\s*light dark/)
	})

	it('zeigt den Fokus über :focus-visible und nicht über :focus', () => {
		const fokus = regeln(css).find((r) => r.selektor.includes(':focus-visible'))
		expect(fokus, 'keine :focus-visible-Regel').toBeDefined()
		// Mehr als ein Farbton Unterschied: zusätzlicher Ring plus Rahmen
		expect(fokus.inhalt).toMatch(/outline:\s*2px solid/)
		expect(fokus.inhalt).toMatch(/border-color:/)

		// Nach einer Berührung bleibt kein Ring stehen
		expect(css).toMatch(/:focus:not\(:focus-visible\)\s*\{[^}]*outline:\s*none/)
	})

	it('macht gesperrte Elemente nicht allein über Farbe kenntlich', () => {
		const gesperrt = regeln(css).find((r) => r.selektor.includes(':disabled'))
		expect(gesperrt, 'keine :disabled-Regel').toBeDefined()
		expect(gesperrt.inhalt, 'nur Farbe/Deckkraft').toMatch(/border-style:\s*dashed/)
	})

	it('stellt den Platzhaltereintrag zurückhaltender dar', () => {
		expect(css).toMatch(/select:has\(option:disabled:checked\)/)
	})

	it('bringt die gemeinsamen Klassen ke-feld und ke-filterzeile mit', () => {
		const selektoren = regeln(css).map((r) => r.selektor).join(' ')
		expect(selektoren).toContain('.ke-feld')
		expect(selektoren).toContain('.ke-filterzeile')

		const feld = regeln(css).find((r) => r.selektor.endsWith('.ke-feld'))
		expect(feld.inhalt).toMatch(/flex-direction:\s*column/)

		// Beschriftungsgröße wie zuvor in den Komponenten
		const beschriftung = regeln(css).find((r) => r.selektor.endsWith('.ke-feld > span'))
		expect(beschriftung.inhalt).toMatch(/font-size:\s*\.8rem/)
		// Die Beschriftung stand auf opacity: .8 und war damit blasser als der
		// Wert, den sie benennt. Sie trägt jetzt die volle Schriftfarbe; das
		// Abblenden ist dem Nebentext vorbehalten (--ke-leise).
		expect(beschriftung.inhalt).toMatch(/color:\s*var\(--ke-schrift\)/)
		expect(beschriftung.inhalt).not.toMatch(/opacity/)
	})

	it('gibt Filterfeldern eine Breitenspanne und schneidet langen Text ab', () => {
		const spanne = regeln(css).find((r) => r.selektor.endsWith('.ke-filterzeile .ke-feld'))
		expect(spanne, 'keine Breitenspanne für Filterfelder').toBeDefined()
		expect(spanne.inhalt).toMatch(/min-width:/)
		expect(spanne.inhalt, 'min-width allein ließ das Feld mitwachsen').toMatch(/max-width:/)

		const abschneiden = regeln(css).find(
			(r) => r.selektor.includes('.ke-filterzeile select') && /text-overflow/.test(r.inhalt)
		)
		expect(abschneiden, 'langer Eintragstext wird nicht abgeschnitten').toBeDefined()
	})

	it('bezieht jede Farbangabe über eine Nextcloud-Variable mit Rückfallwert', () => {
		for (const treffer of css.matchAll(/var\(\s*(--color-[\w-]+)\s*([,)])/g)) {
			expect(treffer[2], treffer[1] + ' steht ohne Rückfallwert').toBe(',')
		}

		/*
		 * Eine benannte Ausnahme: die Stufen der Belegdichte
		 * (`--ke-heat-*`) tragen feste Werte.
		 *
		 * Eine Skala muss über ihre ganze Länge geprüft sein.
		 * `--color-primary-element` ist pro Instanz frei einstellbar — mit
		 * einer hellgelben Instanzfarbe wären die oberen Stufen unlesbar,
		 * und der Kontrast wäre nicht mehr nachrechenbar. Die fünf Stufen
		 * sind einzeln gegen ihre Schriftfarbe gerechnet und stehen mit dem
		 * Ergebnis in `css/kidseye.css` kommentiert.
		 *
		 * Die Ausnahme gilt genau für diese Zeilen, nicht für die Datei.
		 */
		const nurEigenes = css
			.replace(/\/\*[\s\S]*?\*\//g, '')
			.replace(/url\("data:[^"]*"\)/g, 'url(…)')
			.replace(/var\(--[\w-]+\s*,[^()]*\)/g, 'var(…)')
			.replace(/^\s*--ke-heat-[\w-]+:\s*#[0-9a-fA-F]{3,8};\s*$/gm, '')
		expect(nurEigenes, 'Farbwert ohne Nextcloud-Variable').not.toMatch(/#[0-9a-fA-F]{3,8}\b/)
		expect(nurEigenes).not.toMatch(/\b(rgb|rgba|hsl|hsla)\(/)
	})

	/*
	 * Die Skala selbst: eine Hue, hell nach dunkel, fünf Stufen plus die
	 * Nullstufe — und für beide Themen definiert.
	 *
	 * Keine Ampel: die Zahlen sind Belegzahlen. Eine Rot-nach-Grün-Skala
	 * läse sich als Urteil über das Kind, ausgerechnet auf den überfachlichen
	 * Kompetenzen, die nach dem Kerncurriculum keine Skala tragen.
	 */
	it('führt die Belegdichte als einhuige Skala in beiden Themen', () => {
		for (const stufe of ['0', '1', '2', '3', '4', '5']) {
			expect(css, 'Stufe ' + stufe + ' fehlt').toMatch(
				new RegExp('--ke-heat-' + stufe + ':\\s*#[0-9a-fA-F]{6}')
			)
		}
		// Eigene Stufen fürs dunkle Thema, nicht die hellen umgedreht
		expect(css).toMatch(/prefers-color-scheme:\s*dark/)
		expect(css).toContain('body[data-theme-dark]')

		// Keine Ampel: Grün- und Rottöne haben in der Skala nichts zu suchen
		const skala = [...css.matchAll(/--ke-heat-[\d]+:\s*(#[0-9a-fA-F]{6})/g)].map((t) => t[1])
		expect(skala.length).toBeGreaterThanOrEqual(12)
		for (const wert of skala) {
			const r = parseInt(wert.slice(1, 3), 16)
			const b = parseInt(wert.slice(5, 7), 16)
			// Blau führt durchgehend — außer bei der neutralen Nullstufe,
			// die sich zur Arbeitsfläche zurücknimmt.
			const neutral = Math.abs(r - b) < 12
			expect(neutral || b > r, 'Stufe ' + wert + ' ist keine Blaustufe').toBe(true)
		}
	})
})

describe('Mindestmaß 44 px', () => {
	/*
	 * Namentlich geführte Ausnahmen. Jede steht am Ort ihrer Regel
	 * kommentiert — sichtbar, statt die Prüfung aufzuweichen (design.md E5).
	 */
	const AUSNAHMEN = {
		'.kb-gruppe': 'nachrangiges Eingabefeld in einer Kachel des Klassenbilds',
	}

	const dateien = [
		...readdirSync(resolve(process.cwd(), 'src/components'))
			.filter((n) => n.endsWith('.vue'))
			.map((n) => 'src/components/' + n),
		'css/kidseye.css',
	]

	it('findet in keiner Komponente und keiner Stildatei eine Mindesthöhe darunter', () => {
		const zuKlein = []

		for (const pfad of dateien) {
			const quelle = lies(pfad)
			const stil = pfad.endsWith('.vue')
				? quelle.slice(quelle.indexOf('<style'))
				: quelle

			for (const { selektor, inhalt } of regeln(stil)) {
				const treffer = inhalt.match(/min-height:\s*(\d+)px/)
				if (!treffer) {
					continue
				}
				const hoehe = Number(treffer[1])
				const ausgenommen = Object.keys(AUSNAHMEN).some((a) => selektor.includes(a))
				if (hoehe < 44 && !ausgenommen) {
					zuKlein.push(pfad + ': ' + selektor + ' → ' + hoehe + 'px')
				}
			}
		}

		expect(zuKlein, 'Bedienelemente unter 44 px:\n' + zuKlein.join('\n')).toEqual([])
	})

	it('führt die früheren Werte 32, 34, 36 und 40 px nirgends mehr', () => {
		for (const pfad of dateien) {
			const stil = lies(pfad)
			for (const wert of [32, 34, 36, 40]) {
				expect(
					stil,
					pfad + ' setzt noch min-height: ' + wert + 'px'
				).not.toMatch(new RegExp('min-height:\\s*' + wert + 'px'))
			}
		}
	})

	it('kennzeichnet jede Ausnahme am Ort ihrer Regel', () => {
		for (const name of Object.keys(AUSNAHMEN)) {
			const quelle = dateien.map(lies).find((q) => q.includes(name + ' {'))
			expect(quelle, name + ' nicht gefunden').toBeDefined()
			expect(quelle, name + ' ist nicht als Ausnahme kommentiert')
				.toMatch(/Ausnahme vom Mindestmaß 44 px/)
		}
	})
})

describe('Einbindung der Stildatei', () => {
	const controller = lies('lib/Controller/PageController.php')

	it('bindet die Stildatei an beiden Einstiegspunkten ein', () => {
		const treffer = controller.match(/Util::addStyle\(Application::APP_ID, 'kidseye'\);/g)
		expect(treffer, 'Util::addStyle fehlt').not.toBeNull()
		expect(treffer.length, 'nicht an beiden Einstiegspunkten').toBe(2)

		// Je einmal im vollen Layout und im Basis-Layout
		const index = controller.slice(controller.indexOf('function index('))
		const unterricht = controller.slice(controller.indexOf('function unterricht('))
		expect(index.slice(0, index.indexOf('}'))).toContain("Util::addStyle(Application::APP_ID, 'kidseye')")
		expect(unterricht.slice(0, unterricht.indexOf('return'))).toContain("Util::addStyle(Application::APP_ID, 'kidseye')")
	})

	/*
	 * Vue 2 ersetzt das Mount-Element beim Einhängen: aus
	 * <div id="kidseye-main"></div> in templates/index.php wird das
	 * gerenderte Wurzelelement der Komponente — ohne die ID. Stünde sie nur
	 * in templates/, träfe der Wurzelselektor der Stildatei zur Laufzeit auf
	 * nichts, und zwar lautlos.
	 */
	it('trägt die Wurzel-IDs an den Wurzelelementen der Komponenten', () => {
		expect(lies('src/components/App.vue')).toContain('id="kidseye-main"')
		expect(lies('src/components/Unterricht.vue')).toContain('id="kidseye-unterricht"')
	})
})

/**
 * Nebentext wird eingefärbt, nicht abgeblendet.
 *
 * Der Befund, der dazu geführt hat: 34 Regeln nahmen Text über `opacity`
 * zurück, mehrere davon auf .55 bis .65. Auf weißem Grund landet #222 bei .6
 * auf rund #8e8e8e — 3,0:1 statt der nötigen 4,5:1. Die Werte stapelten sich
 * obendrein: ein Hinweisblock auf .8 mit einem <small> auf .7 darin stand
 * effektiv auf .56, und beim Schreiben rechnet das niemand nach.
 *
 * Erlaubt bleibt Deckkraft dort, wo sie ein ganzes Bedienelement zurücknimmt
 * und ein zweites Merkmal die Aussage trägt — ein gestrichelter Rahmen etwa.
 * Diese Fälle stehen namentlich unten; wer einen weiteren braucht, trägt ihn
 * mit Begründung ein, statt die Prüfung zu lockern.
 */
describe('Nebentext trägt Farbe, keine Deckkraft', () => {
	const AUSNAHMEN = [
		// Gesperrtes Bedienelement — zusätzlich gestrichelter Rahmen und
		// not-allowed-Zeiger; die Deckkraft ist nicht das einzige Merkmal.
		{ datei: 'css/kidseye.css', wert: '.55' },
		// Kachel eines lange nicht beobachteten Kindes (D12). Das Signal ist
		// der gestrichelte Rahmen; .85 hält den Namen lesbar.
		{ datei: 'src/components/Unterricht.vue', wert: '.85' },
	]

	const dateien = [
		'css/kidseye.css',
		...['App', 'Auswertung', 'Einrichtung', 'Inbox', 'Klassenbild',
			'MarkerVerwaltung', 'Stammdaten', 'StundeStart', 'Unterricht']
			.map((n) => `src/components/${n}.vue`),
	]

	it('blendet nirgends Text über opacity ab', () => {
		const gefunden = []
		for (const datei of dateien) {
			const treffer = lies(datei).match(/opacity:\s*([\d.]+)/g) || []
			for (const roh of treffer) {
				const wert = roh.replace(/opacity:\s*/, '')
				const erlaubt = AUSNAHMEN.some(
					(a) => a.datei === datei && a.wert === wert
				)
				if (!erlaubt) {
					gefunden.push(`${datei}: opacity: ${wert}`)
				}
			}
		}
		expect(gefunden, 'Deckkraft statt Farbe').toEqual([])
	})

	it('führt den Nebentext als eigene Farbe mit geprüftem Rückfall', () => {
		const css = lies('css/kidseye.css')
		// Nextclouds eigener Wert für Nebentext, in beiden Themen gegen den
		// Arbeitsgrund geprüft. Der Rückfall greift im Basis-Layout des
		// Erfassungsbildschirms, wo weniger Nextcloud-CSS geladen ist.
		expect(css).toMatch(/--ke-leise:\s*var\(--color-text-maxcontrast,\s*#6b6b6b\)/)
	})

	it('setzt im Erfassungsbildschirm keine Themenfarbe ohne Rückfall', () => {
		// RENDER_AS_BASE bringt die Nextcloud-Variablen nicht zwingend mit.
		// `color: var(--color-main-text)` fiele dort auf die geerbte Farbe
		// zurück — im Zweifel auf die des Browsers.
		const ohneRueckfall = (lies('src/components/Unterricht.vue')
			.match(/var\(--color-[a-z-]+\)/g) || [])
		expect(ohneRueckfall, 'Themenfarbe ohne Rückfall').toEqual([])
	})
})

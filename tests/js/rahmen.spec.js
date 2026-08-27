import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const rahmen = JSON.parse(
	readFileSync(resolve(process.cwd(), 'data/rahmen/hessen-primarstufe-2011.json'), 'utf8')
)
const knoten = rahmen.knoten
const kennungen = new Set(knoten.map((k) => k.kennung))
const vonArt = (art) => knoten.filter((k) => k.art === art)
const vonFach = (fach) => knoten.filter((k) => k.fach === fach)

/**
 * Kapitel 9.4 — der Kompetenzrahmen als Datenbestand.
 *
 * Geprüft wird gegen die amtlichen Dokumente „Bildungsstandards und
 * Inhaltsfelder – Das neue Kerncurriculum für Hessen, Primarstufe"
 * (Hessisches Kultusministerium, 2011). Verschiebt sich hier etwas
 * unbemerkt, sind alle Auswertungen falsch.
 */
describe('Kompetenzrahmen Hessen Primarstufe', () => {
	it('ist ein wohlgeformter Rahmen mit Version', () => {
		expect(rahmen.rahmen.kennung).toBe('hessen-primarstufe')
		expect(rahmen.version.kennung).toBe('2011')
		expect(rahmen.version.quelle).toContain('kultus.hessen.de')
	})

	it('hat durchgehend eindeutige Kennungen', () => {
		expect(kennungen.size).toBe(knoten.length)
	})

	it('verweist mit jedem Elternbezug auf einen vorhandenen Knoten', () => {
		for (const k of knoten) {
			if (k.eltern) {
				expect(kennungen.has(k.eltern), `${k.kennung} → ${k.eltern}`).toBe(true)
			}
		}
	})

	it('enthält keinen Zyklus im Knotenbaum', () => {
		const eltern = new Map(knoten.filter((k) => k.eltern).map((k) => [k.kennung, k.eltern]))
		for (const start of eltern.keys()) {
			const gesehen = new Set()
			let aktuell = start
			while (eltern.has(aktuell)) {
				expect(gesehen.has(aktuell), `Zyklus bei ${start}`).toBe(false)
				gesehen.add(aktuell)
				aktuell = eltern.get(aktuell)
			}
		}
	})
})

describe('Überfachliche Kompetenzen (Teil A, Kapitel 2)', () => {
	it('hat genau vier Bereiche', () => {
		const bereiche = vonArt('bereich').map((k) => k.bezeichnung)

		expect(bereiche).toEqual([
			'Personale Kompetenz',
			'Sozialkompetenz',
			'Lernkompetenz',
			'Sprachkompetenz',
		])
	})

	it('hat genau 15 Dimensionen', () => {
		expect(vonArt('dimension')).toHaveLength(15)
	})

	it('verteilt die Dimensionen wie im Kerncurriculum', () => {
		const zahl = (bereich) =>
			vonArt('dimension').filter((k) => k.eltern === bereich).length

		expect(zahl('uk.personal')).toBe(3)
		expect(zahl('uk.sozial')).toBe(6)
		expect(zahl('uk.lern')).toBe(3)
		expect(zahl('uk.sprach')).toBe(3)
	})

	// Teil A ist in allen zwölf Fächern der Primarstufe wortgleich, deshalb
	// tragen diese Knoten ausdrücklich keinen Fachbezug.
	it('führt alle überfachlichen Knoten fachneutral', () => {
		for (const k of knoten.filter((n) => n.ebene === 'ueberfachlich')) {
			expect(k.fach, k.kennung).toBeUndefined()
		}
	})

	it('hinterlegt zu jeder Dimension den Wortlaut des Kerncurriculums', () => {
		for (const k of vonArt('dimension')) {
			expect(k.beschreibung, k.kennung).toBeTruthy()
			expect(k.beschreibung.length).toBeGreaterThan(40)
		}
	})

	// Kapitel 2.9: „Im Unterschied zu den fachlichen Standards entziehen sich
	// die überfachlichen Kompetenzen weitgehend einer Normierung."
	it('sieht für überfachliche Knoten keine Bezugsstufe vor', () => {
		for (const k of knoten.filter((n) => n.ebene === 'ueberfachlich')) {
			expect(k.bezugsstufe, k.kennung).toBeUndefined()
		}
	})
})

describe('Fachliche Ebene', () => {
	it('deckt die fünf festgelegten Fächer ab', () => {
		expect(rahmen.faecher.map((f) => f.kennung)).toEqual([
			'deutsch', 'mathematik', 'sachunterricht', 'kunst', 'ethik',
		])
	})

	it('gibt jedem fachlichen Knoten ein Fach', () => {
		for (const k of knoten.filter((n) => n.ebene === 'fachlich')) {
			expect(k.fach, k.kennung).toBeTruthy()
		}
	})

	it('nennt für jedes Fach ein deklariertes Fach', () => {
		const erlaubt = new Set(rahmen.faecher.map((f) => f.kennung))
		for (const k of knoten.filter((n) => n.fach)) {
			expect(erlaubt.has(k.fach), k.kennung).toBe(true)
		}
	})

	it('hat die Kompetenzbereiche je Fach gemäß Kapitel 4', () => {
		const zahl = (fach) =>
			vonFach(fach).filter((k) => k.art === 'kompetenzbereich').length

		expect(zahl('deutsch')).toBe(4)
		expect(zahl('mathematik')).toBe(6)
		expect(zahl('sachunterricht')).toBe(3)
		expect(zahl('kunst')).toBe(3)
		expect(zahl('ethik')).toBe(5)
	})

	it('führt die Inhaltsfelder aus Kapitel 5', () => {
		const felder = (fach) =>
			vonFach(fach).filter((k) => k.art === 'inhaltsfeld').map((k) => k.bezeichnung)

		expect(felder('mathematik')).toEqual([
			'Muster und Strukturen', 'Zahl und Operation', 'Raum und Form',
			'Größen und Messen', 'Daten und Zufall',
		])
		expect(felder('sachunterricht')).toEqual([
			'Gesellschaft und Politik', 'Natur', 'Raum', 'Technik', 'Geschichte und Zeit',
		])
		expect(felder('kunst')).toHaveLength(6)
		expect(felder('ethik')).toHaveLength(5)
		// KC Deutsch, Kapitel 6.1: drei Inhaltsfelder je Kompetenzbereich
		expect(felder('deutsch')).toHaveLength(12)
	})

	it('hat Bildungsstandards aus Kapitel 6.1 in allen Fächern', () => {
		const zahl = (fach) =>
			vonFach(fach).filter((k) => k.art === 'bildungsstandard').length

		expect(zahl('deutsch')).toBe(65)
		expect(zahl('mathematik')).toBe(26)
		expect(zahl('sachunterricht')).toBe(28)
		expect(zahl('kunst')).toBe(15)
		expect(zahl('ethik')).toBe(21)
		expect(vonArt('bildungsstandard')).toHaveLength(155)
	})

	// Kapitel 6.1 beschreibt ausschließlich das Ende der Jahrgangsstufe 4.
	it('gibt jedem Bildungsstandard die Bezugsstufe jgst_4', () => {
		for (const k of vonArt('bildungsstandard')) {
			expect(k.bezugsstufe, k.kennung).toBe('jgst_4')
		}
	})

	it('hängt jeden Bildungsstandard an einen Kompetenzbereich', () => {
		const bereiche = new Set(vonArt('kompetenzbereich').map((k) => k.kennung))
		for (const k of vonArt('bildungsstandard')) {
			expect(bereiche.has(k.eltern), k.kennung).toBe(true)
		}
	})
})

describe('Zwei Achsen statt Hierarchie (D6)', () => {
	// „Bildungsstandards und Inhaltsfelder stehen in einem korrespondierenden
	// Verhältnis zueinander" — also gekreuzt, nicht geschachtelt.
	it('schachtelt Inhaltsfelder nicht unter Bildungsstandards', () => {
		const standards = new Set(vonArt('bildungsstandard').map((k) => k.kennung))
		for (const k of vonArt('inhaltsfeld')) {
			expect(standards.has(k.eltern ?? ''), k.kennung).toBe(false)
		}
	})

	it('verknüpft sie stattdessen als Korrespondenzen', () => {
		expect(rahmen.korrespondenzen.length).toBeGreaterThan(0)
		for (const c of rahmen.korrespondenzen) {
			expect(kennungen.has(c.von), c.von).toBe(true)
			expect(kennungen.has(c.nach), c.nach).toBe(true)
		}
	})

	it('ordnet jedem Deutsch-Inhaltsfeld genau einen Kompetenzbereich zu', () => {
		const deutsch = rahmen.korrespondenzen.filter((c) => c.nach.startsWith('de.if.'))
		expect(deutsch).toHaveLength(12)
		// drei Inhaltsfelder je Kompetenzbereich
		const jeBereich = {}
		for (const c of deutsch) {
			jeBereich[c.von] = (jeBereich[c.von] || 0) + 1
		}
		expect(Object.values(jeBereich)).toEqual([3, 3, 3, 3])
	})

	it('verknüpft in Mathematik alle Kompetenzbereiche mit allen Inhaltsfeldern', () => {
		// So steht es im Kerncurriculum: „Alle genannten Kompetenzbereiche
		// können mit den Inhaltsfeldern verknüpft werden."
		const paare = rahmen.korrespondenzen.filter((c) => c.von.startsWith('ma.kb.'))
		expect(paare).toHaveLength(6 * 5)
	})
})

describe('Leitstrukturen (D2b)', () => {
	it('führt die fachspezifische Querstruktur mit', () => {
		const leit = vonArt('leitstruktur')
		expect(leit.length).toBeGreaterThan(0)
		for (const k of leit) {
			expect(k.strukturName, k.kennung).toBeTruthy()
		}
	})

	// In v1 geseedet, aber nicht zur Zuordnung angeboten — sie später
	// nachzurüsten hieße, den Seed aller Fächer zu erneuern.
	it('bietet Leitstrukturen nicht zur Auswahl an', () => {
		for (const k of vonArt('leitstruktur')) {
			expect(k.waehlbar, k.kennung).toBe(false)
		}
	})

	it('benennt die Kunst-Kernbereiche wie im Kerncurriculum', () => {
		const kern = vonFach('kunst')
			.filter((k) => k.art === 'leitstruktur')
			.map((k) => k.bezeichnung)

		expect(kern).toEqual([
			'Begegnung mit Bildern', 'Einordnung von Bildern', 'Gestaltung von Bildern',
		])
	})
})

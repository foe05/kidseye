import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const lies = (pfad) => readFileSync(resolve(process.cwd(), pfad), 'utf8')

/** Schneidet einen Methodenrumpf heraus, damit die Prüfung nicht auf eine
 *  zufällige Fundstelle anderswo in der Datei trifft. */
const rumpf = (quelle, von, bis) => {
	const start = quelle.indexOf(von)
	expect(start, `"${von}" nicht gefunden`).toBeGreaterThan(-1)
	const ende = quelle.indexOf(bis, start)
	return quelle.slice(start, ende > -1 ? ende : undefined)
}

/**
 * Markerkennungen überleben das Speichern (D9).
 *
 * Die Warteschlange auf dem Gerät führt Beobachtungen mit `markerId`, und eine
 * offline erfasste Beobachtung wird oft erst Stunden später übertragen. Legte
 * das Speichern der Marker den Satz neu an, zeigte jede wartende Beobachtung
 * auf eine gelöschte Zeile: `nachId()` gäbe null zurück, `erfassen()` wiese
 * sie mit „braucht mindestens einen Marker oder einen Text" ab, und
 * `offene()` reichte sie bei jeder weiteren Übertragung erneut ein — der
 * Zähler ginge nie auf 0.
 *
 * Der Fehler war lautlos: die Oberfläche sah danach richtig aus, weil erfasste
 * Beobachtungen ihren Markertext selbst tragen. Nur die Verknüpfung war weg.
 */
describe('Markerkennungen beim Speichern', () => {
	const dienst = lies('lib/Service/MarkerService.php')
	const oberflaeche = lies('src/components/MarkerVerwaltung.vue')
	const satzSpeichern = rumpf(dienst, 'public function satzSpeichern', 'private function aendern')

	it('legt den Satz eines Kontexts nicht mehr komplett neu an', () => {
		// Der alte Weg: alle Zeilen des Kontexts löschen, dann neu einfügen.
		// Genau daran verbrannten die Kennungen.
		expect(satzSpeichern).not.toContain("delete('kidseye_marker')")
	})

	it('ändert bekannte Marker, statt sie zu ersetzen', () => {
		expect(satzSpeichern).toContain('in_array($id, $alt, true)')
		expect(satzSpeichern).toContain('$this->aendern(')
		expect(dienst).toContain("$q->update('kidseye_marker')")
	})

	it('legt nur Marker ohne bekannte Kennung neu an', () => {
		expect(satzSpeichern).toContain('$this->anlegen(')
		expect(satzSpeichern).toContain("isset($m['id']) ? (int)$m['id'] : null")
	})

	it('löscht einen entfallenen Marker nur, wenn keine Beobachtung auf ihn zeigt', () => {
		const entfernen = rumpf(dienst, 'private function entfernenOderVerbergen', 'public function anlegen')
		// Erst nachsehen, ob er noch gebraucht wird …
		expect(entfernen).toContain("from('kidseye_beobachtung')")
		// … sonst nur unsichtbar schalten, damit die Auswertung nachschlagen kann.
		expect(entfernen).toContain("$q->update('kidseye_marker')")
		expect(entfernen).toContain("delete('kidseye_marker')")
	})

	it('führt die Kennung in der Oberfläche vom Laden bis zum Speichern mit', () => {
		const laden = rumpf(oberflaeche, 'async laden()', 'hinzufuegen()')
		expect(laden).toContain('id: m.id,')
		// Ein neuer Marker hat noch keine — der Server legt ihn dann an.
		expect(oberflaeche).toContain('{ id: null, text: \'\'')
		// speichern() filtert nur leere Zeilen weg und darf nichts abschneiden.
		const speichern = rumpf(oberflaeche, 'async speichern()', '},\n\t},')
		expect(speichern).toContain('api.markerSpeichern(this.kontextId, gefuellt)')
		expect(speichern).not.toContain('.map(')
	})
})

/**
 * Erzeugt img/favicon-touch.png aus img/app.svg.
 *
 * Das Symbol liegt als fertige Datei im Repo und entsteht nicht zur Bauzeit
 * (design.md E6): Das Frontend wird laut docs/INSTALLATION.md häufig auf einem
 * anderen Rechner gebaut und per rsync übertragen. Ein Symbol, das nur beim
 * Bauen entsteht, fehlt bei jedem Weg, der js/ fertig mitbringt.
 *
 * Deshalb ist sharp auch keine Projektabhängigkeit — der Aufruf holt es sich
 * für den einen Lauf:
 *
 *     npm exec --yes --package sharp -- node tools/symbol-erzeugen.mjs
 *
 * Danach die erzeugte Datei mit einchecken. Ändert sich img/app.svg, ist das
 * Symbol von Hand nachzuziehen; tests/js/manifest.spec.js prüft nur, dass es
 * vorhanden ist, nicht ob es zum SVG passt.
 */

import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import sharp from 'sharp'

const KANTE = 512
const HINTERGRUND = '#F7F7F4' // background_color aus img/manifest.json
const TINTE = '#2E4FA3' // theme_color aus img/manifest.json

const wurzel = join(dirname(fileURLToPath(import.meta.url)), '..')
const quelle = join(wurzel, 'img', 'app.svg')
const ziel = join(wurzel, 'img', 'favicon-touch.png')

let svg = readFileSync(quelle, 'utf8')

// app.svg trägt keine fill-Angabe und wäre damit schwarz. Für das Symbol wird
// dieselbe Tinte gesetzt, die das Manifest als theme_color führt.
if (!svg.includes('fill=')) {
	svg = svg.replace('<path ', `<path fill="${TINTE}" `)
}

// Ohne diesen Schritt rastert sharp die 16×16 des Quellbilds und skaliert das
// Ergebnis hoch — das Symbol wäre unscharf. Mit gesetzter Größe rastert es
// direkt in Zielauflösung; das viewBox bleibt unangetastet.
svg = svg.replace('width="16" height="16"', `width="${KANTE}" height="${KANTE}"`)

// Deckender Hintergrund, weil iOS transparente Bereiche eines
// Home-Bildschirm-Symbols schwarz unterlegt.
const { width, height } = await sharp(Buffer.from(svg))
	.flatten({ background: HINTERGRUND })
	.png({ compressionLevel: 9 })
	.toFile(ziel)

console.log(`img/favicon-touch.png: ${width}×${height}`)

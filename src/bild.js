/**
 * Fotos vor dem Hochladen aufbereiten (Kapitel 5.8, D8).
 *
 * Zwei Gründe, warum das ins Fundament gehört und nicht in die Politur:
 *
 *  1. Datenschutz — das Neuzeichnen auf ein Canvas entfernt sämtliche
 *     EXIF-Daten, insbesondere den Aufnahmeort. Ein Foto einer Arbeitsprobe
 *     soll nicht verraten, wo das Kind zur Schule geht.
 *
 *  2. Schul-WLAN — aus rund 3 MB werden rund 400 KB. Das ist der Unterschied
 *     zwischen „geht durch" und „hängt", und über ein Jahr gerechnet der
 *     zwischen 1,7 GB und 0,3 GB pro Lehrkraft.
 */

export const MAX_KANTE = 2000
export const QUALITAET = 0.8

/**
 * @param {File|Blob} datei
 * @returns {Promise<{daten:string, breite:number, hoehe:number, bytes:number}>}
 *          daten als data-URL, bereit zum Senden
 */
export async function aufbereiten(datei, maxKante = MAX_KANTE, qualitaet = QUALITAET) {
	const bild = await ladeBild(datei)
	const { breite, hoehe } = passeAn(bild.width, bild.height, maxKante)

	const canvas = document.createElement('canvas')
	canvas.width = breite
	canvas.height = hoehe

	const ctx = canvas.getContext('2d')
	// Weisser Grund, damit Bilder mit Alphakanal in JPEG nicht schwarz werden
	ctx.fillStyle = '#ffffff'
	ctx.fillRect(0, 0, breite, hoehe)
	ctx.drawImage(bild, 0, 0, breite, hoehe)

	if (bild.close) {
		bild.close()
	}

	// Das Neuzeichnen verwirft alle Metadaten der Quelldatei — ausdrücklich
	// gewollt, nicht ein Nebeneffekt.
	const daten = canvas.toDataURL('image/jpeg', qualitaet)

	return {
		daten,
		breite,
		hoehe,
		bytes: Math.round((daten.length - daten.indexOf(',') - 1) * 0.75),
	}
}

export function passeAn(breite, hoehe, maxKante) {
	const groesste = Math.max(breite, hoehe)
	if (groesste <= maxKante) {
		return { breite, hoehe }
	}
	const faktor = maxKante / groesste
	return {
		breite: Math.max(1, Math.round(breite * faktor)),
		hoehe: Math.max(1, Math.round(hoehe * faktor)),
	}
}

async function ladeBild(datei) {
	// createImageBitmap berücksichtigt die EXIF-Ausrichtung und ist schneller;
	// ältere Safari-Fassungen kennen es nicht.
	if (typeof createImageBitmap === 'function') {
		try {
			return await createImageBitmap(datei, { imageOrientation: 'from-image' })
		} catch (e) {
			// weiter mit dem Rückfallweg
		}
	}
	return await new Promise((erfuellen, ablehnen) => {
		const url = URL.createObjectURL(datei)
		const bild = new Image()
		bild.onload = () => {
			URL.revokeObjectURL(url)
			erfuellen(bild)
		}
		bild.onerror = () => {
			URL.revokeObjectURL(url)
			ablehnen(new Error('Das Bild konnte nicht gelesen werden.'))
		}
		bild.src = url
	})
}

<template>
	<!--
		Markerverwaltung (Kapitel 5.2b).

		Pflichtbestandteil, keine Nachrüstung: welche sechs Wörter auf dem
		Erfassungsbildschirm stehen, entscheidet die Lehrkraft — nicht das
		System. Die mitgelieferten Sätze sind ausdrücklich nur ein Vorschlag.
	-->
	<div class="mv">
		<h2>Schnellmarker</h2>

		<label class="ke-feld mv-feld">
			<span>Unterrichtskontext</span>
			<select v-model.number="kontextId" @change="laden">
				<option v-for="k in kontexte" :key="k.id" :value="k.id">
					{{ k.name }}<template v-if="k.art === 'fachneutral'"> (fachneutral)</template>
				</option>
			</select>
		</label>

		<p class="mv-hinweis">
			Höchstens {{ hoechstzahl }} Marker gleichzeitig sichtbar. Formuliere sie
			beschreibend statt bewertend — „sucht Kontakt" trägt weiter als „stört".
			Ein Marker mit Verwendungszweck merkt die Beobachtung ohne zusätzlichen
			Tap vor.
		</p>

		<p v-if="fehler" class="mv-fehler" role="alert">{{ fehler }}</p>

		<div v-for="(m, i) in marker" :key="i" class="mv-zeile">
			<input v-model="m.text" type="text" class="mv-text" placeholder="Markertext" aria-label="Markertext">

			<select v-model="m.knoten" multiple class="mv-knoten" size="4" aria-label="Kompetenzzuordnung">
				<option v-for="d in dimensionen" :key="d.kennung" :value="d.kennung">
					{{ d.bezeichnung }}
				</option>
			</select>

			<select v-model="m.zwecke" multiple class="mv-zwecke" size="2" aria-label="Verwendungszwecke">
				<option v-for="z in zwecke" :key="z.kennung" :value="z.kennung">
					{{ z.name }}
				</option>
			</select>

			<label class="mv-sichtbar">
				<input v-model="m.sichtbar" type="checkbox"> sichtbar
			</label>

			<button class="mv-weg" aria-label="Entfernen" @click="marker.splice(i, 1)">✕</button>
		</div>

		<div class="mv-aktionen">
			<button :disabled="marker.length >= 12" @click="hinzufuegen">Marker hinzufügen</button>
			<button class="primary" @click="speichern">Speichern</button>
			<span v-if="sichtbareZahl > hoechstzahl" class="mv-warnung">
				{{ sichtbareZahl }} sichtbar — höchstens {{ hoechstzahl }} erlaubt
			</span>
		</div>
	</div>
</template>

<script>
import api from '../api.js'

export default {
	name: 'MarkerVerwaltung',

	data() {
		return {
			kontexte: [],
			kontextId: null,
			marker: [],
			dimensionen: [],
			zwecke: [],
			hoechstzahl: 6,
			fehler: null,
		}
	},

	computed: {
		sichtbareZahl() {
			return this.marker.filter((m) => m.sichtbar).length
		},
	},

	async mounted() {
		this.kontexte = await api.kontexte()
		this.kontextId = this.kontexte[0]?.id ?? null
		if (this.kontextId) {
			await this.laden()
		}
	},

	methods: {
		async laden() {
			this.fehler = null
			const daten = await api.marker(this.kontextId)
			this.hoechstzahl = daten.hoechstzahl
			this.dimensionen = daten.dimensionen
			this.zwecke = daten.zwecke
			// Die Kennung wird mitgeführt und beim Speichern zurückgesendet.
			// Ohne sie legte der Server den Satz neu an, und eine auf einem
			// Gerät wartende Beobachtung verlöre ihren Marker (D9).
			this.marker = daten.marker.map((m) => ({
				id: m.id,
				text: m.text,
				knoten: [...m.knoten],
				zwecke: m.zwecke.map((z) => z.kennung),
				sichtbar: m.sichtbar,
			}))
		},

		hinzufuegen() {
			this.marker.push({ id: null, text: '', knoten: [], zwecke: [], sichtbar: true })
		},

		async speichern() {
			this.fehler = null
			const gefuellt = this.marker.filter((m) => m.text.trim() !== '')
			try {
				await api.markerSpeichern(this.kontextId, gefuellt)
				await this.laden()
			} catch (e) {
				this.fehler = e.response?.data?.fehler || 'Speichern fehlgeschlagen.'
			}
		},
	},
}
</script>

<style scoped>
.mv { padding: 1rem; max-width: 54rem; }
.mv h2 { margin-top: 0; }
/* .ke-feld ordnet Beschriftung und Feld an; hier bleibt nur die Breite. */
.mv-feld { max-width: 22rem; }
.mv-hinweis {
	margin: 1rem 0; font-size: .82rem; line-height: 1.6; opacity: .8;
	border-left: 2px solid var(--color-border); padding-left: .75rem;
}
.mv-fehler {
	border: 1px solid var(--color-error, #8b2a2a);
	background: var(--color-error-hover, #fbeaea);
	padding: .6rem .8rem; border-radius: var(--border-radius); font-size: .85rem;
}
.mv-zeile {
	display: grid;
	/* Die letzte Spalte auf das Maß des Entfernen-Knopfes festgelegt: mit
	   44 px statt 40 px sprengte sie als `auto` die Zeile beim Umbruch. */
	grid-template-columns: 1fr 1fr 12rem auto 44px;
	gap: .5rem; align-items: start;
	padding: .5rem 0; border-bottom: 1px solid var(--color-border);
}
@media (max-width: 46rem) {
	.mv-zeile { grid-template-columns: 1fr; }
}
.mv-text { width: 100%; }
.mv-knoten, .mv-zwecke { width: 100%; font-size: .78rem; }
.mv-sichtbar { font-size: .78rem; display: flex; align-items: center; gap: .3rem; }
.mv-weg {
	background: none; border: 0; cursor: pointer;
	/* Höhe aus css/kidseye.css; die Breite muss hier stehen, weil ein
	   einzelnes ✕ sonst schmaler bleibt als das Antippmaß. */
	font-size: 1rem; min-width: 44px;
}
.mv-aktionen { display: flex; gap: .5rem; align-items: center; margin-top: 1rem; flex-wrap: wrap; }
.mv-warnung { font-size: .8rem; color: var(--color-error, #8b2a2a); }
</style>

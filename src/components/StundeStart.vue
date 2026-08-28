<template>
	<!--
		Startdialog einer Unterrichtsstunde (Kapitel 4.2).

		Der einzige Schritt, den die Lehrkraft pro Stunde von Hand macht.
		Klasse und Kontext sind mit der zuletzt genutzten Kombination
		vorbelegt; das Inhaltsfeld erscheint nur bei Schulfächern, weil
		fachneutrale Kontexte keine fachliche Achse haben (D2).
	-->
	<form class="start" @submit.prevent="absenden">
		<h2>Stunde starten</h2>

		<label class="ke-feld">
			<span>Klasse</span>
			<select v-model.number="klasseId" @change="kontextId = null">
				<option v-for="k in klassen" :key="k.id" :value="k.id">{{ k.name }}</option>
			</select>
		</label>

		<label class="ke-feld">
			<span>Kontext</span>
			<select v-model.number="kontextId" :disabled="!klasseId" @change="inhaltsfeldId = null">
				<option :value="null" disabled>bitte wählen</option>
				<option v-for="c in kontexte" :key="c.id" :value="c.id">{{ c.name }}</option>
			</select>
		</label>

		<label v-if="inhaltsfelder.length" class="ke-feld">
			<span>Schwerpunkt <em>optional</em></span>
			<select v-model.number="inhaltsfeldId">
				<option :value="null">— kein Schwerpunkt —</option>
				<option v-for="i in inhaltsfelder" :key="i.id" :value="i.id">
					{{ i.bezeichnung }}
				</option>
			</select>
			<small>
				Mit Schwerpunkt erben alle Beobachtungen dieser Stunde das Inhaltsfeld —
				ohne einen zusätzlichen Tap.
			</small>
		</label>

		<p v-else-if="gewaehlterKontext && gewaehlterKontext.art === 'fachneutral'" class="hinweis">
			„{{ gewaehlterKontext.name }}" ist fachneutral. Beobachtungen werden hier
			überfachlichen Kompetenzen zugeordnet, nicht einem Fach.
		</p>

		<!--
			Der Startdialog steht und fällt mit dem Lehrauftrag: die beiden
			Auswahlfelder darüber werden ausschließlich aus ihm gefüllt. Fehlt er,
			sind sie leer — und das sieht aus wie ein kaputter Bildschirm, nicht wie
			ein fehlender Eintrag. Deshalb steht hier, wohin es geht, und nicht nur,
			was fehlt.
		-->
		<p v-if="klassen.length === 0" class="hinweis">
			Für <strong>{{ kennung || 'diese Kennung' }}</strong> ist noch kein
			Lehrauftrag hinterlegt. Klasse und Kontext oben kommen ausschließlich
			daher — ohne Auftrag bleiben sie leer.
			<a :href="verwaltungUrl">In der Verwaltung unter „Klassen &amp; Kinder →
				Lehraufträge"</a> je Klasse und Unterrichtskontext einen Auftrag anlegen.
		</p>

		<button class="primary" type="submit" :disabled="!kontextId">Loslegen</button>
	</form>
</template>

<script>
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'StundeStart',

	props: {
		auswahl: {
			type: Object,
			default: null,
		},
	},

	data() {
		return {
			klasseId: null,
			kontextId: null,
			inhaltsfeldId: null,
			verwaltungUrl: generateUrl('/apps/kidseye/'),
		}
	},

	computed: {
		klassen() {
			return this.auswahl?.klassen || []
		},
		/** Für die Meldung: auf welche Kennung wurde vergeblich gesucht? */
		kennung() {
			return this.auswahl?.nutzerId || ''
		},
		gewaehlteKlasse() {
			return this.klassen.find((k) => k.id === this.klasseId) || null
		},
		kontexte() {
			return this.gewaehlteKlasse?.kontexte || []
		},
		gewaehlterKontext() {
			return this.kontexte.find((c) => c.id === this.kontextId) || null
		},
		inhaltsfelder() {
			return this.gewaehlterKontext?.inhaltsfelder || []
		},
	},

	watch: {
		auswahl: {
			immediate: true,
			handler() {
				this.vorbelegen()
			},
		},
	},

	methods: {
		/** Zuletzt genutzte Kombination vorbelegen (4.2). */
		vorbelegen() {
			if (!this.auswahl) {
				return
			}
			const letzte = this.auswahl.letzte
			const ersteKlasse = this.klassen[0]?.id ?? null

			this.klasseId = letzte && this.klassen.some((k) => k.id === letzte.klasseId)
				? letzte.klasseId
				: ersteKlasse

			const moeglich = this.kontexte
			this.kontextId = letzte && moeglich.some((c) => c.id === letzte.kontextId)
				? letzte.kontextId
				: (moeglich[0]?.id ?? null)

			this.inhaltsfeldId = letzte?.inhaltsfeldId ?? null
		},

		absenden() {
			if (!this.kontextId) {
				return
			}
			this.$emit('starten', {
				klasseId: this.klasseId,
				kontextId: this.kontextId,
				inhaltsfeldId: this.inhaltsfeldId,
			})
		},
	},
}
</script>

<style scoped>
.start {
	display: flex;
	flex-direction: column;
	gap: 1rem;
	padding: 1.5rem 1rem;
	max-width: 26rem;
	width: 100%;
	margin-inline: auto;
}
h2 { margin: 0; }
/* Anordnung, Beschriftungsgröße, Feldhöhe und -breite kommen aus
   css/kidseye.css (.ke-feld) — hier stand dieselbe Regel einmal von neun. */
.hinweis {
	margin: 0;
	font-size: .8rem;
	color: var(--ke-leise);
	line-height: 1.5;
	border-left: 2px solid var(--color-border);
	padding-left: .6rem;
}
/* .primary trägt seine 48 px aus css/kidseye.css. */
</style>

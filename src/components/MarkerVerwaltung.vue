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

		<p class="mv-stand" :class="{ 'mv-stand--zuviel': sichtbareZahl > hoechstzahl }">
			<strong>{{ sichtbareZahl }} von {{ hoechstzahl }}</strong> sichtbar
			<template v-if="sichtbareZahl > hoechstzahl">
				— so lässt sich nicht speichern.
			</template>
		</p>

		<!--
			Eine Karte je Marker statt einer Tabellenzeile.

			Vorher standen Text, Kompetenzen, Zwecke, Sichtbarkeit und Löschen in
			fünf Spalten nebeneinander, die Zuordnungen als native
			Mehrfachauswahl mit size="4". Damit war weder zu sehen, was gewählt
			ist — die Auswahl steht in einer Liste, die man erst rollen muss —
			noch, wozu ein Feld gehört: die Beschriftungen lagen nur als
			aria-label vor, sichtbar war nichts. Auf einem Tablet ist eine
			Mehrfachauswahl zudem kaum zu bedienen: Mehrfachauswahl braucht dort
			gedrückte Zusatztasten.

			Als Karte trägt jeder Marker seine Angaben untereinander, und die
			Zuordnungen stehen als Ankreuzfelder offen da — man sieht, was gewählt
			ist, ohne etwas aufzuklappen.
		-->
		<article v-for="(m, i) in marker" :key="i" class="mv-karte" :class="{ 'mv-karte--aus': !m.sichtbar }">
			<div class="mv-kopf">
				<span class="mv-nummer" aria-hidden="true">{{ i + 1 }}</span>
				<label class="ke-feld mv-textfeld">
					<span>Markertext</span>
					<input
						v-model="m.text"
						type="text"
						placeholder="z. B. erklärt seinen Rechenweg"
						@keydown.enter.prevent>
				</label>
				<label class="mv-schalter">
					<input v-model="m.sichtbar" type="checkbox">
					<span>auf dem Erfassungsbildschirm</span>
				</label>
				<button class="mv-weg" :aria-label="'Marker ' + (i + 1) + ' entfernen'" @click="entfernen(i)">
					✕
				</button>
			</div>

			<div class="mv-zuordnung">
				<fieldset class="mv-feldgruppe">
					<legend>Überfachliche Kompetenzen</legend>
					<p class="mv-feldhinweis">
						Trägt der Marker. Ein Tap darauf ordnet die Beobachtung ohne
						Nacharbeit zu.
					</p>
					<div class="mv-haken">
						<label v-for="d in dimensionen" :key="d.kennung" class="mv-haken-feld">
							<input v-model="m.knoten" type="checkbox" :value="d.kennung">
							<span>{{ d.bezeichnung }}</span>
						</label>
					</div>
				</fieldset>

				<fieldset class="mv-feldgruppe">
					<legend>Verwendungszweck <em>optional</em></legend>
					<p class="mv-feldhinweis">
						Merkt die Beobachtung für eine Mappe vor — ebenfalls ohne
						zusätzlichen Tap.
					</p>
					<div class="mv-haken">
						<label v-for="z in zwecke" :key="z.kennung" class="mv-haken-feld">
							<input v-model="m.zwecke" type="checkbox" :value="z.kennung">
							<span>{{ z.name }}</span>
						</label>
					</div>
				</fieldset>
			</div>
		</article>

		<p v-if="marker.length === 0" class="mv-leer">
			Für diesen Kontext ist noch kein Marker angelegt.
		</p>

		<div class="mv-aktionen">
			<button :disabled="marker.length >= 12" @click="hinzufuegen">Marker hinzufügen</button>
			<button class="primary" :disabled="sichtbareZahl > hoechstzahl" @click="speichern">
				Speichern
			</button>
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

		entfernen(i) {
			this.marker.splice(i, 1)
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
	margin: 1rem 0; font-size: .82rem; line-height: 1.6; color: var(--ke-leise);
	border-left: 2px solid var(--ke-rand); padding-left: .75rem;
}
.mv-fehler {
	border: 1px solid var(--ke-fehler);
	background: var(--color-error-hover, #fbeaea);
	padding: .6rem .8rem; border-radius: var(--ke-radius); font-size: .85rem;
}

/* Der Zähler steht oben, nicht unten bei den Knöpfen: die Grenze von sechs
   ist beim Ankreuzen zu beachten, nicht erst beim Speichern. */
.mv-stand { font-size: .85rem; margin: 1rem 0 .5rem; color: var(--ke-leise); }
.mv-stand--zuviel { color: var(--ke-fehler); font-weight: 600; }

.mv-leer { color: var(--ke-leise); font-size: .88rem; }

/* --- Eine Karte je Marker ------------------------------------------------ */

.mv-karte {
	border: 1px solid var(--ke-rand);
	border-radius: var(--ke-radius);
	padding: .75rem;
	margin-bottom: .75rem;
	background: var(--ke-grund);
}
/* Unsichtbar geschaltet: erkennbar am Rand, nicht am Verblassen — der Text
   muss lesbar bleiben, sonst lässt er sich nicht bearbeiten. */
.mv-karte--aus { border-style: dashed; }
.mv-karte--aus .mv-nummer { color: var(--ke-leise); border-color: var(--ke-rand); }

.mv-kopf {
	display: grid;
	grid-template-columns: auto 1fr auto auto;
	gap: .6rem;
	align-items: end;
}
.mv-nummer {
	display: inline-flex; align-items: center; justify-content: center;
	width: 1.6rem; height: 1.6rem; margin-bottom: .5rem;
	border: 1px solid var(--ke-betont); border-radius: 50%;
	font-size: .75rem; font-variant-numeric: tabular-nums;
	color: var(--ke-betont);
}
.mv-textfeld { min-width: 0; }
.mv-schalter {
	display: flex; align-items: center; gap: .35rem;
	font-size: .78rem; margin-bottom: .6rem; white-space: nowrap;
}
.mv-weg {
	background: none; border: 0; cursor: pointer;
	/* Höhe aus css/kidseye.css; die Breite muss hier stehen, weil ein
	   einzelnes ✕ sonst schmaler bleibt als das Antippmaß. */
	font-size: 1rem; min-width: 44px; color: var(--ke-leise);
}
.mv-weg:hover { color: var(--ke-fehler); }

/* Zwei Gruppen nebeneinander, solange Platz ist — darunter untereinander. */
.mv-zuordnung {
	display: grid;
	grid-template-columns: 1.6fr 1fr;
	gap: .75rem;
	margin-top: .75rem;
}
.mv-feldgruppe {
	border: 0; padding: 0; margin: 0; min-width: 0;
}
.mv-feldgruppe legend {
	padding: 0; font-size: .78rem; font-weight: 600; color: var(--ke-schrift);
}
.mv-feldgruppe legend em { font-style: normal; font-weight: 400; color: var(--ke-leise); }
.mv-feldhinweis {
	margin: .1rem 0 .4rem; font-size: .72rem; line-height: 1.4;
	color: var(--ke-leise);
}

/* Ankreuzfelder statt <select multiple>: die Auswahl steht offen da, und auf
   dem Tablet braucht es keine gedrückte Zusatztaste, um mehrere zu wählen. */
.mv-haken { display: flex; flex-direction: column; gap: .1rem; }
.mv-haken-feld {
	display: flex; align-items: center; gap: .4rem;
	/* Volle Zeile als Antippfläche, 44 px hoch wie überall sonst. */
	min-height: 44px; padding: 0 .35rem;
	font-size: .8rem; line-height: 1.3;
	border-radius: var(--ke-radius); cursor: pointer;
}
.mv-haken-feld:hover { background: var(--color-background-hover, rgba(0, 0, 0, .04)); }
.mv-haken-feld input { flex: none; }
.mv-haken-feld:has(input:checked) { font-weight: 600; }

.mv-aktionen { display: flex; gap: .5rem; align-items: center; margin-top: 1rem; flex-wrap: wrap; }

@media (max-width: 46rem) {
	.mv-kopf { grid-template-columns: auto 1fr auto; }
	/* Der Schalter rutscht unter das Textfeld statt daneben. */
	.mv-schalter { grid-column: 2 / -1; margin-bottom: 0; white-space: normal; }
	.mv-zuordnung { grid-template-columns: 1fr; }
}
</style>

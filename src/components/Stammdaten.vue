<template>
	<!--
		Stammdaten (Kapitel 3.3): Schuljahr, Klassen, Kinder, Lehraufträge.

		Der Lehrauftrag ist das Tripel Lehrkraft × Klasse × Unterrichtskontext.
		Ohne ihn lässt sich keine Stunde starten — deshalb steht er hier und
		nicht in einer Nebenansicht.
	-->
	<div class="sd">
		<h2>Klassen und Kinder</h2>

		<p v-if="fehler" class="sd-fehler" role="alert">{{ fehler }}</p>

		<section v-if="!schuljahr" class="sd-block">
			<h3>Schuljahr anlegen</h3>
			<p class="sd-klein">
				Ohne aktives Schuljahr lässt sich nichts weiter anlegen. Klassen und
				Lehraufträge hängen daran; Beobachtungen bleiben beim Kind und
				überleben den Wechsel.
			</p>
			<div class="sd-zeile">
				<input v-model="neuesSchuljahr" placeholder="2026/27" class="sd-eingabe" aria-label="Schuljahr">
				<button class="primary" @click="schuljahrAnlegen">Anlegen und aktivieren</button>
			</div>
		</section>

		<template v-else>
			<p class="sd-klein">Aktives Schuljahr: <strong>{{ schuljahr.kennung }}</strong></p>

			<section class="sd-block">
				<h3>Klassen</h3>
				<div class="sd-zeile">
					<input v-model="neueKlasse" placeholder="3a" class="sd-eingabe" aria-label="Name der Klasse">
					<button @click="klasseAnlegen">Klasse anlegen</button>
				</div>
				<div class="sd-chips">
					<button
						v-for="k in klassen"
						:key="k.id"
						class="sd-chip"
						:class="{ 'sd-chip--an': klasseId === k.id }"
						@click="klasseWaehlen(k.id)">
						{{ k.name }}
					</button>
				</div>
			</section>

			<section v-if="klasseId" class="sd-block">
				<h3>Kinder in {{ klasseName }}</h3>
				<div class="sd-zeile">
					<input v-model="neuKind.vorname" placeholder="Vorname" class="sd-eingabe" aria-label="Vorname">
					<input v-model="neuKind.nachname" placeholder="Nachname" class="sd-eingabe" aria-label="Nachname">
					<input
						v-model.number="neuKind.geburtsjahr"
						type="number"
						placeholder="Geburtsjahr"
						aria-label="Geburtsjahr"
						class="sd-eingabe sd-eingabe--kurz">
					<button @click="kindAnlegen">Hinzufügen</button>
				</div>
				<p v-if="kinder.length === 0" class="sd-klein">
					Noch keine Kinder. Für ganze Klassen ist der CSV-Import unter
					„Einrichtung" der schnellere Weg.
				</p>
				<ol v-else class="sd-kinder">
					<li v-for="k in kinder" :key="k.id">
						{{ k.vorname }} {{ k.nachname }}
						<small v-if="k.gruppe">{{ k.gruppe }}</small>
					</li>
				</ol>
			</section>

			<section v-if="klasseId" class="sd-block">
				<h3>Lehraufträge</h3>
				<p class="sd-klein">
					Je Kombination aus Klasse und Unterrichtskontext ein Auftrag. Nur
					dafür lässt sich später eine Stunde starten.
				</p>
				<div class="sd-zeile">
					<input v-model="neuAuftrag.nutzerId" placeholder="Nextcloud-Kennung" class="sd-eingabe" aria-label="Nextcloud-Kennung der Lehrkraft">
					<select v-model.number="neuAuftrag.kontextId" class="sd-eingabe" aria-label="Unterrichtskontext">
						<option :value="null" disabled>Kontext wählen</option>
						<option v-for="c in kontexte" :key="c.id" :value="c.id">{{ c.name }}</option>
					</select>
					<label class="sd-kl">
						<input v-model="neuAuftrag.klassenlehrkraft" type="checkbox"> Klassenlehrkraft
					</label>
					<button :disabled="!neuAuftrag.nutzerId || !neuAuftrag.kontextId" @click="auftragAnlegen">
						Anlegen
					</button>
				</div>
				<ul class="sd-auftraege">
					<li v-for="a in eigeneAuftraege" :key="a.id">
						{{ a.klasse }} · {{ a.kontext }}
						<small v-if="a.klassenlehrkraft">Klassenlehrkraft</small>
						<small v-if="a.kontextArt === 'fachneutral'">fachneutral</small>
					</li>
				</ul>
				<p class="sd-klein">
					Gezeigt werden die eigenen Aufträge. Aufträge für Kolleginnen und
					Kollegen lassen sich anlegen, erscheinen aber in deren Ansicht.
				</p>
			</section>
		</template>
	</div>
</template>

<script>
import api from '../api.js'

export default {
	name: 'Stammdaten',

	data() {
		return {
			schuljahr: null,
			klassen: [],
			klasseId: null,
			kinder: [],
			kontexte: [],
			eigeneAuftraege: [],
			neuesSchuljahr: '',
			neueKlasse: '',
			neuKind: { vorname: '', nachname: '', geburtsjahr: null },
			neuAuftrag: { nutzerId: '', kontextId: null, klassenlehrkraft: false },
			fehler: null,
		}
	},

	computed: {
		klasseName() {
			return this.klassen.find((k) => k.id === this.klasseId)?.name || ''
		},
	},

	async mounted() {
		await this.laden()
	},

	methods: {
		async laden() {
			const einrichtung = await api.einrichtung()
			this.schuljahr = einrichtung.schuljahr
			this.kontexte = await api.kontexte()
			if (this.schuljahr) {
				this.klassen = await api.klassen()
				this.eigeneAuftraege = await api.lehrauftraege()
				if (this.klassen.length && !this.klasseId) {
					await this.klasseWaehlen(this.klassen[0].id)
				}
			}
		},

		async klasseWaehlen(id) {
			this.klasseId = id
			this.kinder = await api.kinder(id)
		},

		async mitFehler(aktion) {
			this.fehler = null
			try {
				await aktion()
			} catch (e) {
				this.fehler = e.response?.data?.fehler || 'Das hat nicht geklappt.'
			}
		},

		schuljahrAnlegen() {
			return this.mitFehler(async () => {
				const jahr = parseInt(this.neuesSchuljahr.slice(0, 4), 10)
				if (!jahr) {
					throw new Error('Bitte im Format 2026/27 angeben.')
				}
				await api.schuljahrAnlegen({
					kennung: this.neuesSchuljahr,
					beginn: jahr + '-08-01',
					ende: (jahr + 1) + '-07-31',
					aktiv: true,
				})
				this.neuesSchuljahr = ''
				await this.laden()
			})
		},

		klasseAnlegen() {
			return this.mitFehler(async () => {
				await api.klasseAnlegen(this.neueKlasse)
				this.neueKlasse = ''
				this.klassen = await api.klassen()
			})
		},

		kindAnlegen() {
			return this.mitFehler(async () => {
				if (!this.neuKind.vorname || !this.neuKind.nachname) {
					throw new Error('Vorname und Nachname sind Pflicht.')
				}
				const antwort = await api.schuelerAnlegen({
					...this.neuKind,
					klasseId: this.klasseId,
				})
				this.kinder = antwort.kinder
				this.neuKind = { vorname: '', nachname: '', geburtsjahr: null }
			})
		},

		auftragAnlegen() {
			return this.mitFehler(async () => {
				await api.lehrauftragAnlegen({
					...this.neuAuftrag,
					klasseId: this.klasseId,
				})
				this.neuAuftrag = { nutzerId: '', kontextId: null, klassenlehrkraft: false }
				this.eigeneAuftraege = await api.lehrauftraege()
			})
		},
	},
}
</script>

<style scoped>
.sd { padding: 1rem; max-width: 46rem; }
.sd h2 { margin-top: 0; }
.sd h3 { font-size: 1rem; margin: 0 0 .5rem; }
.sd-block {
	margin-top: 1.5rem; padding-top: 1rem;
	border-top: 1px solid var(--color-border);
}
.sd-zeile { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; }
/* Höhe für Felder und Knöpfe kommt aus css/kidseye.css; hier bleibt nur,
   wie breit ein Feld in der Zeile mindestens sein soll. */
.sd-eingabe { min-width: 9rem; }
.sd-eingabe--kurz { min-width: 7rem; }
.sd-kl { display: flex; align-items: center; gap: .3rem; font-size: .8rem; }
.sd-klein { font-size: .8rem; opacity: .72; line-height: 1.55; margin: .5rem 0; }
.sd-chips { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .75rem; }
/* Klassenwahl: ein Knopf, kein Etikett — Höhe aus css/kidseye.css.
   Stand zuvor auf 36 px und damit unter dem Antippmaß. */
.sd-chip {
	padding: .3rem .7rem;
	border: 1px solid var(--color-border); border-radius: var(--border-radius);
	background: none; color: inherit; cursor: pointer;
}
.sd-chip--an {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element-light);
	font-weight: 600;
}
.sd-kinder { margin: .75rem 0 0; padding-left: 1.4rem; font-size: .9rem; line-height: 1.7; }
.sd-kinder small, .sd-auftraege small {
	opacity: .6; font-size: .72rem; margin-left: .4rem;
	border: 1px solid var(--color-border); border-radius: 3px; padding: 0 .25rem;
}
.sd-auftraege { list-style: none; padding: 0; margin: .75rem 0 0; font-size: .9rem; }
.sd-auftraege li { padding: .3rem 0; border-bottom: 1px solid var(--color-border); }
.sd-fehler {
	border: 1px solid var(--color-error, #8b2a2a);
	background: var(--color-error-hover, #fbeaea);
	padding: .6rem .8rem; border-radius: var(--border-radius); font-size: .85rem;
}
</style>

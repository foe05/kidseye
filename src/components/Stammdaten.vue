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
					<button :disabled="!neueKlasse.trim()" @click="klasseAnlegen">Klasse anlegen</button>
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

				<!--
					Löschen steht bei der gewählten Klasse, nicht als ✕ an jedem
					Chip: ein ✕ direkt neben der Auswahlfläche ist auf dem Tablet
					der klassische Fehlgriff.

					Abgewiesen wird im Dienst, nicht hier — eine Klasse mit
					Beobachtungen bleibt stehen, sonst fielen die Einträge aus
					jeder Auswertung, ohne gelöscht zu sein.
				-->
				<div v-if="klasseId" class="sd-loeschzeile">
					<button v-if="!loeschfrage" class="sd-loeschen" @click="loeschfrage = true">
						Klasse {{ klasseName }} löschen
					</button>
					<template v-else>
						<span class="sd-klein">
							<strong>{{ klasseName }}</strong> wirklich löschen?
							<template v-if="kinder.length">
								Die {{ kinder.length }} Kinder bleiben erhalten und können in
								einer anderen Klasse weitergeführt werden.
							</template>
						</span>
						<button class="sd-loeschen" @click="klasseLoeschen">Ja, löschen</button>
						<button @click="loeschfrage = false">Abbrechen</button>
					</template>
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
				<h3>Lehraufträge für {{ klasseName }}</h3>
				<!--
					Der Regelfall an einer Grundschule ist die Lehrkraft, die in
					ihrer Klasse alles beobachtet — nicht die Fachlehrkraft mit
					einem Fach. Einzeln angelegt waren das sieben Formulare mit
					siebenmal derselben Kennung; als Satz ist es ein Haken.

					Gesetzt wird der ganze Satz: was angekreuzt ist, gilt danach,
					was nicht, ist gelöst. Ein Kontext, in dem schon Stunden
					gehalten wurden, bleibt trotzdem stehen — ohne Lehrauftrag käme
					die Lehrkraft an die eigenen Beobachtungen nicht mehr heran.
				-->
				<div class="sd-zeile">
					<label class="ke-feld sd-kennung">
						<span>Nextcloud-Kennung</span>
						<input v-model="satz.nutzerId" placeholder="Anmeldename" @change="satzVorbelegen">
					</label>
					<label class="sd-kl">
						<input v-model="satz.klassenlehrkraft" type="checkbox"> Klassenlehrkraft
					</label>
				</div>

				<div class="sd-satzkopf">
					<span class="sd-klein">
						{{ satz.kontextIds.length }} von {{ kontexte.length }} gewählt
					</span>
					<button class="sd-mini" @click="alleWaehlen(true)">alle</button>
					<button class="sd-mini" @click="alleWaehlen(false)">keine</button>
				</div>

				<div class="sd-kontexte">
					<label v-for="c in kontexte" :key="c.id" class="sd-kontext">
						<input v-model="satz.kontextIds" type="checkbox" :value="c.id">
						<span>
							{{ c.name }}
							<small v-if="c.art === 'fachneutral'">fachneutral</small>
						</span>
					</label>
				</div>

				<div class="sd-zeile sd-satzaktionen">
					<button class="primary" :disabled="!satz.nutzerId" @click="satzSpeichern">
						Übernehmen
					</button>
					<span v-if="satzMeldung" class="sd-klein">{{ satzMeldung }}</span>
				</div>

				<h4 class="sd-unterkopf">Eigene Aufträge</h4>
				<ul v-if="eigeneAuftraege.length" class="sd-auftraege">
					<li v-for="a in eigeneAuftraege" :key="a.id">
						{{ a.klasse }} · {{ a.kontext }}
						<small v-if="a.klassenlehrkraft">Klassenlehrkraft</small>
						<small v-if="a.kontextArt === 'fachneutral'">fachneutral</small>
					</li>
				</ul>
				<p v-else class="sd-klein">
					Noch keine — ohne Auftrag bleibt der Startdialog im Unterrichtsmodus leer.
				</p>
				<p class="sd-klein">
					Aufträge für Kolleginnen und Kollegen lassen sich anlegen, erscheinen
					aber nur in deren Ansicht.
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
			// Eigene Nextcloud-Kennung. Im Einzelbetrieb (D13) legt die Lehrkraft
			// den Auftrag für sich selbst an — vorbelegt ist das ein Klick statt
			// einer Abschrift, bei der sich niemand vertippen kann.
			eigeneKennung: '',
			klassen: [],
			klasseId: null,
			kinder: [],
			kontexte: [],
			eigeneAuftraege: [],
			neuesSchuljahr: '',
			neueKlasse: '',
			neuKind: { vorname: '', nachname: '', geburtsjahr: null },
			// Der ganze Satz an Kontexten einer Lehrkraft in dieser Klasse.
			satz: { nutzerId: '', kontextIds: [], klassenlehrkraft: false },
			satzMeldung: null,
			loeschfrage: false,
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
			this.eigeneKennung = einrichtung.nutzerId || ''
			if (!this.satz.nutzerId) {
				this.satz.nutzerId = this.eigeneKennung
			}
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
			this.loeschfrage = false
			this.satzMeldung = null
			this.kinder = await api.kinder(id)
			this.satzVorbelegen()
		},

		/**
		 * Kreuzt an, was für diese Kennung in dieser Klasse schon gilt.
		 *
		 * Nur für die eigene Kennung: `lehrauftraege()` gibt ausschließlich die
		 * eigenen Aufträge heraus. Für eine fremde Kennung bliebe der Satz sonst
		 * scheinbar leer und das Übernehmen löste ihre bestehenden Aufträge —
		 * deshalb steht dort der Hinweis statt einer geratenen Vorbelegung.
		 */
		satzVorbelegen() {
			if (this.satz.nutzerId !== this.eigeneKennung) {
				this.satz.kontextIds = []
				this.satzMeldung = 'Für eine fremde Kennung ist der bestehende Satz hier '
					+ 'nicht sichtbar. Übernehmen setzt genau das, was angekreuzt ist.'
				return
			}
			this.satzMeldung = null
			const eigene = this.eigeneAuftraege.filter((a) => a.klasseId === this.klasseId)
			this.satz.kontextIds = eigene.map((a) => a.kontextId)
			this.satz.klassenlehrkraft = eigene.some((a) => a.klassenlehrkraft)
		},

		alleWaehlen(alle) {
			this.satz.kontextIds = alle ? this.kontexte.map((c) => c.id) : []
		},

		satzSpeichern() {
			return this.mitFehler(async () => {
				const ergebnis = await api.lehrauftraegeSetzen({
					nutzerId: this.satz.nutzerId.trim(),
					klasseId: this.klasseId,
					kontextIds: this.satz.kontextIds,
					klassenlehrkraft: this.satz.klassenlehrkraft,
				})
				this.eigeneAuftraege = await api.lehrauftraege()
				const teile = []
				if (ergebnis.angelegt) {
					teile.push(ergebnis.angelegt + ' angelegt')
				}
				if (ergebnis.entfernt) {
					teile.push(ergebnis.entfernt + ' gelöst')
				}
				if (ergebnis.behalten.length) {
					teile.push(ergebnis.behalten.length + ' behalten, weil dort schon '
						+ 'Stunden gehalten wurden')
				}
				this.satzMeldung = teile.length ? teile.join(', ') + '.' : 'Nichts zu ändern.'
			})
		},

		klasseLoeschen() {
			return this.mitFehler(async () => {
				await api.klasseLoeschen(this.klasseId)
				this.loeschfrage = false
				this.klasseId = null
				this.kinder = []
				this.klassen = await api.klassen()
				this.eigeneAuftraege = await api.lehrauftraege()
				if (this.klassen.length) {
					await this.klasseWaehlen(this.klassen[0].id)
				}
			})
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
				// Gesperrter Knopf ist ein Hinweis, keine Prüfung — die steht in
				// StammdatenService::klasseAnlegen() und wirft auch dann, wenn
				// jemand am Formular vorbei sendet.
				if (!this.neueKlasse.trim()) {
					return
				}
				await api.klasseAnlegen(this.neueKlasse.trim())
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
	},
}
</script>

<style scoped>
.sd { padding: 1rem; max-width: 46rem; }
.sd h2 { margin-top: 0; }
.sd h3 { font-size: 1rem; margin: 0 0 .5rem; }
.sd-block {
	margin-top: 1.5rem; padding-top: 1rem;
	border-top: 1px solid var(--ke-rand);
}
.sd-zeile { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; }
/* Höhe für Felder und Knöpfe kommt aus css/kidseye.css; hier bleibt nur,
   wie breit ein Feld in der Zeile mindestens sein soll. */
.sd-eingabe { min-width: 9rem; }
.sd-eingabe--kurz { min-width: 7rem; }
.sd-kl { display: flex; align-items: center; gap: .3rem; font-size: .8rem; }
.sd-klein { font-size: .8rem; color: var(--ke-leise); line-height: 1.55; margin: .5rem 0; }
.sd-chips { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .75rem; }
/* Klassenwahl: ein Knopf, kein Etikett — Höhe aus css/kidseye.css.
   Stand zuvor auf 36 px und damit unter dem Antippmaß. */
.sd-chip {
	padding: .3rem .7rem;
	border: 1px solid var(--ke-rand); border-radius: var(--ke-radius);
	background: none; color: inherit; cursor: pointer;
}
.sd-chip--an {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element-light);
	font-weight: 600;
}
.sd-kinder { margin: .75rem 0 0; padding-left: 1.4rem; font-size: .9rem; line-height: 1.7; }
.sd-kinder small, .sd-auftraege small {
	color: var(--ke-leise); font-size: .72rem; margin-left: .4rem;
	border: 1px solid var(--ke-rand); border-radius: 3px; padding: 0 .25rem;
}
.sd-kennung { max-width: 16rem; }
.sd-unterkopf { font-size: .85rem; margin: 1.25rem 0 .25rem; }

.sd-satzkopf {
	display: flex; align-items: center; gap: .5rem;
	flex-wrap: wrap; margin: .75rem 0 .35rem;
}
/* „alle" und „keine" sind Antippflächen wie jede andere — Höhe aus
   css/kidseye.css, hier nur Breite und Schriftgrad. */
.sd-mini {
	background: none; border: 1px solid var(--ke-rand);
	border-radius: var(--ke-radius); padding: .15rem .8rem;
	font-size: .78rem; color: inherit;
}

/* Die Kontexte als Raster: an einer Grundschule sind das sieben, die passen
   nebeneinander. Eine Spalte je 12rem, damit lange Namen wie „Sozial- und
   Arbeitsverhalten" nicht umbrechen müssen. */
.sd-kontexte {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(13rem, 1fr));
	gap: .1rem .75rem;
	margin-bottom: .5rem;
}
.sd-kontext {
	display: flex; align-items: center; gap: .45rem;
	min-height: 44px; padding: 0 .35rem;
	border-radius: var(--ke-radius); font-size: .88rem; cursor: pointer;
}
.sd-kontext:hover { background: var(--color-background-hover, rgba(0, 0, 0, .04)); }
.sd-kontext:has(input:checked) { font-weight: 600; }
.sd-satzaktionen { margin-top: .5rem; }

.sd-loeschzeile {
	display: flex; align-items: center; gap: .5rem;
	flex-wrap: wrap; margin-top: .9rem;
}
.sd-loeschen {
	background: none; border: 1px solid var(--ke-fehler);
	border-radius: var(--ke-radius); color: var(--ke-fehler);
	padding: .3rem .7rem; font-size: .82rem;
}

.sd-auftraege { list-style: none; padding: 0; margin: .75rem 0 0; font-size: .9rem; }
.sd-auftraege li { padding: .3rem 0; border-bottom: 1px solid var(--ke-rand); }
.sd-fehler {
	border: 1px solid var(--ke-fehler);
	background: var(--color-error-hover, #fbeaea);
	padding: .6rem .8rem; border-radius: var(--ke-radius); font-size: .85rem;
}
</style>

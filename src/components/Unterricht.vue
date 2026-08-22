<template>
	<div id="kidseye-unterricht" class="ke" :class="{ 'ke--breit': breit }">
		<!-- Kopfleiste: laufende Stunde und Synchronisationszähler (5.13) -->
		<header class="ke-kopf">
			<button v-if="stunde" class="ke-kontext" @click="stundeBeenden">
				<span class="ke-punkt" aria-hidden="true" />
				{{ stunde.klasse }} · {{ stunde.kontext }}
				<span v-if="stunde.inhaltsfeld" class="ke-if">· {{ stunde.inhaltsfeld.bezeichnung }}</span>
			</button>
			<span v-else class="ke-kontext ke-kontext--leer">kidseye</span>

			<span class="ke-rechts">
				<span v-if="offenZahl > 0" class="ke-sync" :title="syncTitel">⟳ {{ offenZahl }}</span>
				<button class="ke-knopf-klein" @click="sucheOffen = true">Suche</button>
			</span>
		</header>

		<!-- Startdialog (4.2) -->
		<StundeStart
			v-if="ansicht === 'start'"
			:auswahl="auswahl"
			@starten="starten" />

		<!-- Nachfrage bei veraltetem Kontext (4.4) -->
		<div v-else-if="ansicht === 'nachfrage'" class="ke-nachfrage">
			<h2>Läuft noch die alte Stunde?</h2>
			<p>
				Der Kontext <strong>{{ stunde.klasse }} · {{ stunde.kontext }}</strong>
				läuft seit {{ Math.round(stunde.alterMinuten / 60) }} Stunden.
				Bevor etwas im falschen Fach landet, frage ich lieber nach.
			</p>
			<div class="ke-nachfrage-knoepfe">
				<button class="primary" @click="neueStunde">Neue Stunde starten</button>
				<button @click="ansicht = 'erfassen'">Weiter in dieser Stunde</button>
			</div>
		</div>

		<!-- Erfassung: Klassenbild plus Erfassungsbereich -->
		<main v-else-if="ansicht === 'erfassen'" class="ke-haupt">
			<section class="ke-plan" :aria-label="'Klasse ' + (stunde ? stunde.klasse : '')">
				<div v-for="gruppe in gruppen" :key="gruppe.name || '_'" class="ke-gruppe">
					<p v-if="gruppe.name" class="ke-gruppe-name">{{ gruppe.name }}</p>
					<div class="ke-kacheln">
						<button
							v-for="kind in gruppe.kinder"
							:key="kind.id"
							class="ke-kachel"
							:class="kachelKlasse(kind)"
							:aria-pressed="istGewaehlt(kind.id)"
							@click="kachelTippen(kind)"
							@contextmenu.prevent="mehrfachStarten(kind)">
							<span class="ke-name">{{ kind.anzeige }}</span>
							<span class="ke-punkte" aria-hidden="true">{{ punkte(kind) }}</span>
							<span v-if="kind.stand.tageHer === null" class="sr-only">
								noch nie beobachtet
							</span>
							<span v-else-if="kind.stand.tageHer > 14" class="sr-only">
								seit über zwei Wochen nicht beobachtet
							</span>
						</button>
					</div>
				</div>

				<p v-if="mehrfach.length > 0" class="ke-mehrfach">
					{{ mehrfach.length }} Kinder gewählt — eine Notiz erzeugt je Kind einen Eintrag.
					<button class="ke-knopf-klein" @click="mehrfachEnde">aufheben</button>
				</p>
			</section>

			<!-- Erfassungsbereich: ab 700 px feste Spalte, darunter Sheet (5.4) -->
			<section v-if="gewaehlt || mehrfach.length" class="ke-panel">
				<div class="ke-panel-kopf">
					<strong>{{ panelTitel }}</strong>
					<span>{{ stunde.klasse }} · {{ stunde.kontext }}</span>
					<button class="ke-schliessen" aria-label="Schließen" @click="abbrechen">✕</button>
				</div>

				<!-- Ein Tap = gespeichert und zu (5.5) -->
				<div class="ke-marker">
					<button
						v-for="m in marker"
						:key="m.id"
						class="ke-mark"
						:class="{ 'ke-mark--zweck': m.zwecke.length > 0 }"
						@click="markerTippen(m)">
						{{ m.text }}
						<small v-if="m.zwecke.length">merkt für {{ m.zwecke[0].name }} vor</small>
					</button>
				</div>

				<textarea
					ref="notiz"
					v-model="text"
					class="ke-notiz"
					rows="2"
					placeholder="Notiz …"
					@input="entwurfMerken" />

				<!-- Verwendungszwecke: optional, nie vorausgewählt (5.23) -->
				<div v-if="zwecke.length" class="ke-zwecke">
					<button
						v-for="z in zwecke"
						:key="z.id"
						class="ke-zweck"
						:class="{ 'ke-zweck--an': gewaehlteZwecke.includes(z.kennung) }"
						:aria-pressed="gewaehlteZwecke.includes(z.kennung)"
						@click="zweckUmschalten(z.kennung)">
						{{ z.name }}
					</button>
				</div>

				<div class="ke-aktionen">
					<label class="ke-foto">
						<input type="file" accept="image/*" capture="environment" @change="fotoWaehlen">
						<span>Foto</span>
					</label>
					<button class="primary" :disabled="!speicherbar" @click="speichern">Sichern</button>
				</div>

				<div v-if="heute.length" class="ke-heute">
					<p class="ke-heute-titel">Heute</p>
					<p v-for="e in heute" :key="e.id" class="ke-heute-zeile">
						{{ uhrzeit(e.erfasstAm) }} · {{ e.markerText || e.text }}
					</p>
				</div>
			</section>
		</main>

		<!-- Rückgängig statt Bestätigung (5.15) -->
		<div v-if="undo" class="ke-undo" role="status">
			gesichert
			<button @click="rueckgaengig">rückgängig</button>
		</div>

		<div v-if="meldung" class="ke-meldung" role="alert">{{ meldung }}</div>

		<!-- Gastkind-Suche über alle Klassen mit Lehrauftrag (5.16) -->
		<div v-if="sucheOffen" class="ke-suche">
			<div class="ke-suche-feld">
				<input
					v-model="suchbegriff"
					type="search"
					placeholder="Kind suchen …"
					@input="suchen">
				<button @click="sucheSchliessen">✕</button>
			</div>
			<button
				v-for="k in suchtreffer"
				:key="k.id"
				class="ke-suchtreffer"
				@click="ausSucheWaehlen(k)">
				{{ k.anzeige }} <small>{{ k.klasse }}</small>
			</button>
		</div>
	</div>
</template>

<script>
import api from '../api.js'
import StundeStart from './StundeStart.vue'
import { aufbereiten } from '../bild.js'
import {
	einreihen, synchronisieren, anzahlOffen, zuruecknehmen,
	entwurfSpeichern, entwurfLesen, entwurfLoeschen,
} from '../offline.js'

const UNDO_MS = 5000
const HINWEIS_FOTO = 'kidseye.fotohinweis'

export default {
	name: 'Unterricht',
	components: { StundeStart },

	data() {
		return {
			ansicht: 'laden',
			stunde: null,
			auswahl: null,
			kinder: [],
			marker: [],
			zwecke: [],
			gewaehlt: null,
			mehrfach: [],
			text: '',
			gewaehlteZwecke: [],
			heute: [],
			undo: null,
			meldung: null,
			offenZahl: 0,
			breit: false,
			sucheOffen: false,
			suchbegriff: '',
			suchtreffer: [],
			syncTitel: '',
		}
	},

	computed: {
		/** Anordnung folgt dem Klassenbild, optional in benannten Gruppen (D11). */
		gruppen() {
			const nachGruppe = new Map()
			for (const kind of this.kinder) {
				const schluessel = kind.gruppe || ''
				if (!nachGruppe.has(schluessel)) {
					nachGruppe.set(schluessel, [])
				}
				nachGruppe.get(schluessel).push(kind)
			}
			return [...nachGruppe.entries()].map(([name, kinder]) => ({ name, kinder }))
		},

		panelTitel() {
			if (this.mehrfach.length > 0) {
				return this.mehrfach.length + ' Kinder'
			}
			return this.gewaehlt ? this.gewaehlt.anzeige : ''
		},

		speicherbar() {
			return this.text.trim().length > 0
		},
	},

	async mounted() {
		this.breitPruefen()
		window.addEventListener('resize', this.breitPruefen)
		// Entwurf überlebt eine Gerätesperre (5.14)
		document.addEventListener('visibilitychange', this.entwurfMerken)
		await this.laden()
		this.offenZahl = await anzahlOffen()
		this.syncSchleife()
	},

	beforeDestroy() {
		window.removeEventListener('resize', this.breitPruefen)
		document.removeEventListener('visibilitychange', this.entwurfMerken)
		clearInterval(this.syncTimer)
	},

	methods: {
		breitPruefen() {
			this.breit = window.innerWidth >= 700
		},

		async laden() {
			const einstieg = await api.einstieg()
			this.auswahl = einstieg.auswahl
			this.stunde = einstieg.stunde

			if (einstieg.status === 'laeuft') {
				this.ansicht = 'erfassen'
				await this.bildschirmLaden()
			} else if (einstieg.status === 'nachfrage') {
				this.ansicht = 'nachfrage'
			} else {
				this.ansicht = 'start'
			}

			if (einstieg.ablage && !einstieg.ablage.ok) {
				this.melden('Fotos sind derzeit nicht möglich: ' + einstieg.ablage.grund)
			}
		},

		async bildschirmLaden() {
			const daten = await api.bildschirm()
			this.stunde = daten.stunde
			this.kinder = daten.kinder || []
			this.marker = daten.marker || []
			this.zwecke = daten.zwecke || []
		},

		async starten({ klasseId, kontextId, inhaltsfeldId }) {
			this.stunde = await api.stundeStarten(klasseId, kontextId, inhaltsfeldId)
			this.ansicht = 'erfassen'
			await this.bildschirmLaden()
		},

		async neueStunde() {
			await api.stundeBeenden()
			this.auswahl = await api.startAuswahl()
			this.stunde = null
			this.ansicht = 'start'
		},

		async stundeBeenden() {
			await this.neueStunde()
		},

		// ---------------------------------------------------- Kachelzustand

		/** Verblassen nach Beobachtungsalter (5.17, D12). */
		kachelKlasse(kind) {
			const stand = kind.stand || {}
			return {
				'ke-kachel--heute': stand.heute > 0,
				'ke-kachel--luecke': stand.tageHer === null || stand.tageHer > 14,
				'ke-kachel--gewaehlt': this.istGewaehlt(kind.id),
			}
		},

		punkte(kind) {
			const zahl = Math.min(kind.stand?.heute || 0, 3)
			return '●'.repeat(zahl)
		},

		istGewaehlt(id) {
			return this.gewaehlt?.id === id || this.mehrfach.includes(id)
		},

		// ---------------------------------------------------- Auswahl

		async kachelTippen(kind) {
			if (this.mehrfach.length > 0) {
				const i = this.mehrfach.indexOf(kind.id)
				if (i === -1) {
					this.mehrfach.push(kind.id)
				} else {
					this.mehrfach.splice(i, 1)
				}
				return
			}
			this.gewaehlt = kind
			this.text = (await entwurfLesen(this.entwurfSchluessel(kind.id))) || ''
			this.gewaehlteZwecke = []
			this.heute = await api.heute(kind.id)
		},

		/** Sammelbeobachtung über langes Antippen (5.10). */
		mehrfachStarten(kind) {
			this.gewaehlt = null
			this.mehrfach = [kind.id]
		},

		mehrfachEnde() {
			this.mehrfach = []
		},

		abbrechen() {
			this.gewaehlt = null
			this.mehrfach = []
			this.text = ''
			this.gewaehlteZwecke = []
		},

		zweckUmschalten(kennung) {
			const i = this.gewaehlteZwecke.indexOf(kennung)
			if (i === -1) {
				this.gewaehlteZwecke.push(kennung)
			} else {
				this.gewaehlteZwecke.splice(i, 1)
			}
		},

		// ---------------------------------------------------- Erfassen

		/**
		 * Ein Tap auf einen Marker sichert sofort und schließt — ohne
		 * zweiten Bestätigungsschritt (5.5).
		 */
		async markerTippen(m) {
			await this.sichern({ markerId: m.id })
		},

		async speichern() {
			await this.sichern({ text: this.text.trim() })
		},

		async sichern(zusatz) {
			const ziele = this.mehrfach.length > 0
				? this.mehrfach
				: (this.gewaehlt ? [this.gewaehlt.id] : [])
			if (ziele.length === 0) {
				return
			}

			const eingereiht = []
			for (const schuelerId of ziele) {
				// Zuerst lokal — Speichern wartet nie auf das Netz (D9)
				eingereiht.push(await einreihen({
					schuelerId,
					zwecke: [...this.gewaehlteZwecke],
					...zusatz,
				}))
			}

			if (this.gewaehlt) {
				await entwurfLoeschen(this.entwurfSchluessel(this.gewaehlt.id))
			}

			this.undo = { eintraege: eingereiht }
			clearTimeout(this.undoTimer)
			this.undoTimer = setTimeout(() => { this.undo = null }, UNDO_MS)

			this.abbrechen()
			this.offenZahl = await anzahlOffen()
			this.synchronisieren()
		},

		async rueckgaengig() {
			if (!this.undo) {
				return
			}
			for (const eintrag of this.undo.eintraege) {
				const serverId = await zuruecknehmen(eintrag.clientUuid)
				if (serverId) {
					try {
						await api.zuruecknehmen(serverId)
					} catch (e) {
						this.melden('Der Eintrag war schon übertragen und ließ sich nicht zurücknehmen.')
					}
				}
			}
			this.undo = null
			this.offenZahl = await anzahlOffen()
			await this.bildschirmLaden()
		},

		// ---------------------------------------------------- Foto

		async fotoWaehlen(ereignis) {
			const datei = ereignis.target.files?.[0]
			ereignis.target.value = ''
			if (!datei || !this.gewaehlt) {
				return
			}

			// Einmaliger Hinweis auf unbeteiligte Kinder (5.9)
			if (!window.localStorage.getItem(HINWEIS_FOTO)) {
				window.localStorage.setItem(HINWEIS_FOTO, '1')
				this.melden('Bitte nur die Arbeitsprobe fotografieren, keine unbeteiligten Kinder. '
					+ 'Ortsdaten werden automatisch entfernt.')
			}

			try {
				// Verkleinern und EXIF entfernen, bevor irgendetwas das Gerät verlässt (5.8)
				const bild = await aufbereiten(datei)
				const beobachtung = await api.erfassen({
					schuelerId: this.gewaehlt.id,
					text: this.text.trim() || null,
					zwecke: this.gewaehlteZwecke,
					erfasstAm: new Date().toISOString(),
				})
				await api.fotoAnhaengen(beobachtung.id, bild.daten)
				this.abbrechen()
				await this.bildschirmLaden()
			} catch (fehler) {
				this.melden(fehler.response?.data?.fehler
					|| 'Das Foto konnte nicht abgelegt werden. Die Notiz bleibt erhalten.')
			}
		},

		// ---------------------------------------------------- Synchronisation

		syncSchleife() {
			this.syncTimer = setInterval(() => this.synchronisieren(), 20000)
			window.addEventListener('online', () => this.synchronisieren())
		},

		syncHinweis(ergebnis) {
			if (ergebnis.grund === 'abgemeldet') {
				return 'Nicht angemeldet — die Beobachtungen bleiben auf dem Gerät und werden '
					+ 'nach der nächsten Anmeldung übertragen.'
			}
			if (ergebnis.grund === 'keine-verbindung') {
				return 'Keine Verbindung — die Beobachtungen bleiben auf dem Gerät und werden '
					+ 'übertragen, sobald das Netz wieder da ist.'
			}
			return ergebnis.offen + ' noch nicht übertragen'
		},

		async synchronisieren() {
			const ergebnis = await synchronisieren((eintraege) => api.synchronisieren(eintraege))
			this.offenZahl = ergebnis.offen
			// Der Grund entscheidet, was die Lehrkraft tut: sich neu anmelden
			// oder auf Netz warten. Beides sieht ohne diese Unterscheidung
			// gleich aus (offline.js, istAbgemeldet).
			this.syncTitel = this.syncHinweis(ergebnis)
			if (ergebnis.gesendet > 0) {
				await this.bildschirmLaden()
			}
		},

		// ---------------------------------------------------- Suche

		async suchen() {
			this.suchtreffer = this.suchbegriff.length >= 2
				? await api.kinderSuche(this.suchbegriff)
				: []
		},

		ausSucheWaehlen(kind) {
			this.sucheSchliessen()
			this.kachelTippen({ ...kind, stand: { heute: 0, tageHer: null } })
		},

		sucheSchliessen() {
			this.sucheOffen = false
			this.suchbegriff = ''
			this.suchtreffer = []
		},

		// ---------------------------------------------------- Kleinkram

		entwurfSchluessel(schuelerId) {
			return 'notiz-' + (this.stunde?.id || 0) + '-' + schuelerId
		},

		entwurfMerken() {
			if (this.gewaehlt) {
				entwurfSpeichern(this.entwurfSchluessel(this.gewaehlt.id), this.text)
			}
		},

		uhrzeit(iso) {
			return new Date(iso).toLocaleTimeString('de-DE',
				{ hour: '2-digit', minute: '2-digit' })
		},

		melden(text) {
			this.meldung = text
			clearTimeout(this.meldungTimer)
			this.meldungTimer = setTimeout(() => { this.meldung = null }, 8000)
		},
	},
}
</script>

<style scoped>
/* Ein einziger Breakpoint bei 700 px (D10):
   darunter einspaltig mit einfahrendem Bereich, darüber zwei Spalten. */

.ke {
	display: flex;
	flex-direction: column;
	min-height: 100vh;
	min-height: 100dvh;
	background: var(--color-main-background);
	padding-bottom: env(safe-area-inset-bottom);
}

.ke-kopf {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: .5rem;
	padding: .5rem .75rem;
	padding-top: calc(.5rem + env(safe-area-inset-top));
	border-bottom: 1px solid var(--color-border);
	font-size: .85rem;
}
.ke-kontext {
	background: none;
	border: 0;
	font: inherit;
	color: inherit;
	padding: .25rem;
	cursor: pointer;
}
.ke-kontext--leer { opacity: .6; cursor: default; }
.ke-punkt {
	display: inline-block;
	width: .5em; height: .5em;
	border-radius: 50%;
	background: var(--color-success, #2f6b4f);
	margin-right: .4em;
}
.ke-if { opacity: .7; }
.ke-rechts { display: flex; align-items: center; gap: .5rem; }
.ke-sync { font-variant-numeric: tabular-nums; opacity: .7; }
.ke-knopf-klein {
	background: none; border: 1px solid var(--color-border);
	border-radius: var(--border-radius); padding: .2rem .5rem;
	font-size: .8rem; cursor: pointer;
}

.ke-haupt { display: flex; flex-direction: column; flex: 1; min-height: 0; }
.ke--breit .ke-haupt { flex-direction: row; }
.ke--breit .ke-plan { flex: 1.35; overflow-y: auto; }
.ke--breit .ke-panel {
	flex: 1;
	border-left: 1px solid var(--color-border);
	border-top: 0;
	position: static;
	max-height: none;
}

.ke-plan { padding: .75rem; }
.ke-gruppe + .ke-gruppe { margin-top: .6rem; }
.ke-gruppe-name {
	margin: 0 0 .3rem;
	font-size: .65rem;
	letter-spacing: .12em;
	text-transform: uppercase;
	opacity: .55;
}
.ke-kacheln {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: .3rem;
}

/* Touchziel deutlich über dem Mindestmaß von 44 px:
   ~84 px auf einem 375-px-Handy, ~115 px auf dem iPad. */
.ke-kachel {
	min-height: 56px;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: .1rem;
	padding: .4rem .2rem;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: .75rem;
	line-height: 1.2;
	cursor: pointer;
}
.ke-kachel--heute { border-color: var(--color-primary-element); }
/* Fällt ins Auge, ohne zu schreien (D12) */
.ke-kachel--luecke {
	border-style: dashed;
	opacity: .6;
}
.ke-kachel--gewaehlt {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 1px;
}
.ke-name { font-weight: 500; }
.ke-punkte {
	font-size: .6rem;
	letter-spacing: .08em;
	color: var(--color-primary-element);
	min-height: .8em;
}

.ke-mehrfach {
	margin: .6rem 0 0;
	font-size: .8rem;
	display: flex;
	align-items: center;
	gap: .5rem;
	flex-wrap: wrap;
}

.ke-panel {
	border-top: 1px solid var(--color-border);
	padding: .75rem;
	display: flex;
	flex-direction: column;
	gap: .5rem;
	background: var(--color-main-background);
	max-height: 62vh;
	overflow-y: auto;
}
.ke-panel-kopf {
	display: flex;
	align-items: baseline;
	gap: .5rem;
	padding-bottom: .4rem;
	border-bottom: 1px solid var(--color-border);
	font-size: .85rem;
}
.ke-panel-kopf span { opacity: .6; font-size: .75rem; }
.ke-schliessen {
	margin-left: auto;
	background: none; border: 0; cursor: pointer;
	font-size: 1rem; padding: .2rem .4rem;
}

.ke-marker { display: grid; gap: .3rem; }
.ke--breit .ke-marker { grid-template-columns: 1fr; }
.ke-mark {
	text-align: left;
	padding: .55rem .6rem;
	min-height: 44px;
	border: 1px solid var(--color-primary-element);
	border-radius: var(--border-radius);
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-text-dark, inherit);
	font-size: .85rem;
	cursor: pointer;
}
.ke-mark--zweck { border-style: dashed; }
.ke-mark small { display: block; font-size: .65rem; opacity: .7; }

.ke-notiz {
	width: 100%;
	resize: vertical;
	font: inherit;
	padding: .5rem;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.ke-zwecke { display: flex; flex-wrap: wrap; gap: .25rem; }
.ke-zweck {
	padding: .3rem .5rem;
	/* Umschaltknopf, keine Anzeige: 44 px wie jede andere Antippfläche
	   dieses Bildschirms. Stand als einzige Regel hier auf 32 px. */
	min-height: 44px;
	font-size: .72rem;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: none;
	color: inherit;
	cursor: pointer;
}
.ke-zweck--an {
	border-color: var(--color-warning, #966a16);
	background: var(--color-warning-hover, #f7f0df);
	color: #4a3a10;
}

.ke-aktionen { display: flex; gap: .5rem; align-items: center; }
.ke-foto {
	flex: 1;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-height: 44px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	cursor: pointer;
}
.ke-foto input { display: none; }
.ke-aktionen .primary { min-height: 44px; padding-inline: 1rem; }

.ke-heute {
	border-top: 1px solid var(--color-border);
	padding-top: .4rem;
	font-size: .72rem;
	opacity: .75;
}
.ke-heute-titel { margin: 0 0 .2rem; font-weight: 600; }
.ke-heute-zeile { margin: 0; font-variant-numeric: tabular-nums; }

.ke-undo, .ke-meldung {
	position: fixed;
	left: 50%;
	transform: translateX(-50%);
	bottom: calc(1rem + env(safe-area-inset-bottom));
	background: var(--color-main-text);
	color: var(--color-main-background);
	padding: .5rem .9rem;
	border-radius: var(--border-radius-pill, 999px);
	font-size: .8rem;
	display: flex;
	gap: .6rem;
	align-items: center;
	max-width: min(92vw, 30rem);
	z-index: 20;
}
.ke-meldung { bottom: calc(4rem + env(safe-area-inset-bottom)); text-align: left; }
.ke-undo button {
	background: none; border: 0; color: inherit;
	text-decoration: underline; cursor: pointer; font: inherit;
}

.ke-nachfrage { padding: 1.5rem 1rem; max-width: 34rem; }
.ke-nachfrage h2 { margin-top: 0; }
.ke-nachfrage-knoepfe { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: 1rem; }
.ke-nachfrage-knoepfe button { min-height: 44px; padding-inline: 1rem; }

.ke-suche {
	position: fixed;
	inset: 0;
	background: var(--color-main-background);
	padding: 1rem;
	padding-top: calc(1rem + env(safe-area-inset-top));
	overflow-y: auto;
	z-index: 30;
}
.ke-suche-feld { display: flex; gap: .5rem; margin-bottom: .75rem; }
.ke-suche-feld input { flex: 1; min-height: 44px; }
.ke-suchtreffer {
	display: block;
	width: 100%;
	text-align: left;
	min-height: 44px;
	padding: .5rem;
	border: 0;
	border-bottom: 1px solid var(--color-border);
	background: none;
	color: inherit;
	font: inherit;
	cursor: pointer;
}
.ke-suchtreffer small { opacity: .6; margin-left: .5rem; }

.sr-only {
	position: absolute; width: 1px; height: 1px;
	padding: 0; margin: -1px; overflow: hidden;
	clip: rect(0 0 0 0); white-space: nowrap; border: 0;
}

@media (prefers-reduced-motion: reduce) {
	* { transition: none !important; animation: none !important; }
}
</style>

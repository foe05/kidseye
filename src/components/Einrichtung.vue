<template>
	<!--
		Einrichtung (Kapitel 3.4, 5.21, 5.22, 7.6, 7.8).

		Zeigt, was noch fehlt, damit kidseye benutzbar ist — und beantwortet
		die zwei Fragen, die sonst als Fehler wirken: warum das Symbol auf dem
		Home-Bildschirm mit Browserleiste startet und warum es beim ersten Mal
		eine eigene Anmeldung verlangt.
	-->
	<div class="ein">
		<h2>Einrichtung</h2>

		<template v-if="stand">
			<!--
				Die Punkte kommen aus dem DiagnoseService — derselben Quelle, aus
				der auch `occ kidseye:pruefen` liest. Deshalb steht hier keine
				Liste von Hand: ein neuer Prüfpunkt erscheint ohne Änderung an
				dieser Stelle (design.md E2).
			-->
			<ul class="ein-liste">
				<li v-for="punkt in stand.punkte" :key="punkt.kennung" :class="zustand(punkt)">
					{{ punkt.titel }}
					<small>{{ punkt.meldung }}</small>
					<small v-if="punkt.abhilfe" class="ein-abhilfe">{{ punkt.abhilfe }}</small>
				</li>
				<li :class="ok(stand.kontexte > 0)">
					Unterrichtskontexte <small>{{ stand.kontexte }}</small>
				</li>
				<li :class="ok(stand.zwecke > 0)">
					Verwendungszwecke <small>{{ stand.zwecke }}</small>
				</li>
			</ul>

			<section class="ein-block">
				<h3>Auf dem Home-Bildschirm ablegen</h3>
				<ol class="ein-schritte">
					<li>Den Unterrichtsmodus im Browser öffnen.</li>
					<li>Auf dem iPad: Teilen-Menü → „Zum Home-Bildschirm".</li>
					<li>
						<strong>Beim ersten Start meldet sich Nextcloud erneut an.</strong>
						Das ist kein Fehler: eine über den Home-Bildschirm installierte
						Anwendung hat unter iOS einen eigenen Speicherbereich, getrennt von
						Safari. Nach dieser einen Anmeldung bleibt die Sitzung dort bestehen.
					</li>
				</ol>
			</section>

			<section class="ein-block">
				<h3>Aufbewahrungsfristen</h3>
				<p class="ein-warnung">{{ stand.fristen.hinweis }}</p>
				<p v-for="(monate, stufe) in stand.fristen.monate" :key="stufe" class="ein-frist">
					<strong>{{ stufe }}</strong>
					<template v-if="monate > 0">{{ monate }} Monate</template>
					<template v-else>keine automatische Frist</template>
				</p>
				<p class="ein-klein">
					Läuft eine Frist ab, wird der Eintrag nur gekennzeichnet.
					Gelöscht wird erst nach ausdrücklicher Bestätigung.
				</p>
			</section>

			<section class="ein-block">
				<h3>Schüler importieren</h3>
				<p class="ein-klein">
					CSV mit den Spalten <code>vorname;nachname;klasse;geburtsjahr</code>.
					Vor dem Schreiben kommt eine Vorschau mit Dubletten und Fehlern.
				</p>
				<textarea v-model="csv" rows="5" class="ein-csv" placeholder="vorname;nachname;klasse" />
				<div class="ein-aktionen">
					<button :disabled="!csv.trim()" @click="vorschau">Vorschau</button>
					<button
						class="primary"
						:disabled="!vorschauDaten || vorschauDaten.neu === 0"
						@click="uebernehmen">
						{{ vorschauDaten ? vorschauDaten.neu : 0 }} übernehmen
					</button>
				</div>

				<p v-if="vorschauDaten && vorschauDaten.meldung" class="ein-warnung">
					{{ vorschauDaten.meldung }}
				</p>
				<table v-else-if="vorschauDaten" class="ein-tabelle">
					<tr v-for="z in vorschauDaten.zeilen" :key="z.zeile" :class="'ein-' + z.status">
						<td>{{ z.zeile }}</td>
						<td>{{ z.vorname }} {{ z.nachname }}</td>
						<td>{{ z.klasse }}</td>
						<td>{{ z.status }}</td>
						<td>{{ z.hinweis }}</td>
					</tr>
				</table>
				<p v-if="importErgebnis" class="ein-ok">
					{{ importErgebnis.angelegt }} angelegt,
					{{ importErgebnis.uebersprungen }} übersprungen.
				</p>
			</section>
		</template>
		<p v-else>Wird geladen …</p>
	</div>
</template>

<script>
import api from '../api.js'

export default {
	name: 'Einrichtung',

	data() {
		return {
			stand: null,
			csv: '',
			vorschauDaten: null,
			importErgebnis: null,
		}
	},

	async mounted() {
		this.stand = await api.einrichtung()
	},

	methods: {
		ok(bedingung) {
			return bedingung ? 'ein-ja' : 'ein-nein'
		},

		// „nicht prüfbar" ist kein Mangel: die Ablage lässt sich ohne
		// angemeldeten Nutzer nicht öffnen, und das steht dem Betrieb nicht
		// entgegen.
		zustand(punkt) {
			if (punkt.zustand === 'nicht_pruefbar') {
				return 'ein-offen'
			}
			return this.ok(punkt.zustand === 'erfuellt')
		},

		async vorschau() {
			this.importErgebnis = null
			this.vorschauDaten = await api.importVorschau(this.csv)
		},

		async uebernehmen() {
			this.importErgebnis = await api.importUebernehmen(this.csv)
			this.vorschauDaten = null
			this.csv = ''
			this.stand = await api.einrichtung()
		},
	},
}
</script>

<style scoped>
.ein { padding: 1rem; max-width: 46rem; }
.ein-abhilfe { display: block; font-family: monospace; opacity: .75; }
.ein-offen { opacity: .75; }
.ein h2 { margin-top: 0; }
.ein h3 { font-size: 1rem; margin: 0 0 .5rem; }
.ein-liste { list-style: none; padding: 0; margin: 0 0 1.5rem; }
.ein-liste li {
	padding: .4rem 0 .4rem 1.5rem; position: relative;
	border-bottom: 1px solid var(--color-border);
}
.ein-liste li::before { position: absolute; left: 0; }
.ein-ja::before { content: '✓'; color: var(--color-success, #2f6b4f); }
.ein-nein::before { content: '!'; color: var(--color-error, #8b2a2a); font-weight: 700; }
.ein-liste small { display: block; opacity: .7; font-size: .78rem; line-height: 1.5; }
.ein-block {
	margin-top: 1.5rem; padding-top: 1rem;
	border-top: 1px solid var(--color-border);
}
.ein-schritte { margin: 0; padding-left: 1.2rem; line-height: 1.6; font-size: .88rem; }
.ein-schritte li + li { margin-top: .4rem; }
.ein-warnung {
	font-size: .82rem; line-height: 1.6;
	border-left: 2px solid var(--color-warning, #966a16);
	padding-left: .75rem; margin: 0 0 .75rem;
}
.ein-frist { margin: .2rem 0; font-size: .85rem; }
.ein-frist strong { display: inline-block; min-width: 8rem; }
.ein-klein { font-size: .78rem; opacity: .7; line-height: 1.5; }
.ein-csv { width: 100%; font-family: monospace; font-size: .8rem; margin: .5rem 0; }
.ein-aktionen { display: flex; gap: .5rem; }
.ein-tabelle { width: 100%; border-collapse: collapse; font-size: .78rem; margin-top: .75rem; }
.ein-tabelle td { border-bottom: 1px solid var(--color-border); padding: .25rem .4rem; }
.ein-dublette { opacity: .6; }
.ein-fehler { color: var(--color-error, #8b2a2a); }
.ein-ok { color: var(--color-success, #2f6b4f); font-size: .85rem; }
</style>

<template>
	<!--
		Auswertung (Kapitel 8): Zeitleiste, Heatmap, Lücken-Radar, Berichte.

		Durchgehalten wird: auf überfachlichen Kompetenzen gibt es
		ausschließlich Belegzahlen, niemals eine Einstufung oder Rangfolge.
	-->
	<div class="aw">
		<h2>Auswertung</h2>

		<div class="ke-filterzeile">
			<label class="ke-feld">
				<span>Klasse</span>
				<select v-model.number="klasseId" @change="klasseGewechselt">
					<option v-for="k in klassen" :key="k.id" :value="k.id">{{ k.name }}</option>
				</select>
			</label>
			<label class="ke-feld">
				<span>Ansicht</span>
				<select v-model="modus" @change="laden">
					<option value="luecken">Lücken-Radar</option>
					<option value="heatmap">Kompetenz-Übersicht</option>
					<option value="kind">Einzelnes Kind</option>
				</select>
			</label>
			<label v-if="modus === 'kind'" class="ke-feld">
				<span>Kind</span>
				<select v-model.number="schuelerId" @change="laden">
					<option v-for="k in kinder" :key="k.id" :value="k.id">{{ k.anzeige }}</option>
				</select>
			</label>
			<label v-if="modus === 'kind'" class="ke-feld">
				<span>Mappe</span>
				<select v-model="zweck" @change="laden">
					<option :value="null">— alle Beobachtungen —</option>
					<option v-for="z in zwecke" :key="z.kennung" :value="z.kennung">{{ z.name }}</option>
				</select>
			</label>
		</div>

		<!-- Lücken-Radar (6.6) -->
		<section v-if="modus === 'luecken' && luecken">
			<p class="aw-hinweis">
				Beobachtung konzentriert sich von selbst auf auffällige Kinder. Hier stehen
				die, die seit über {{ luecken.tage }} Tagen nicht vorkamen — aufgeschlüsselt
				danach, in welchem Kontext die Lücke am größten ist.
			</p>
			<p v-if="luecken.kinder.length === 0" class="aw-leer">
				Keine Lücken. Alle Kinder wurden in den letzten {{ luecken.tage }} Tagen beobachtet.
			</p>
			<article v-for="k in luecken.kinder" :key="k.schuelerId" class="aw-luecke">
				<strong>{{ k.anzeige }}</strong>
				<span v-if="k.nie" class="aw-warn">noch nie beobachtet</span>
				<span v-else>seit {{ k.tageHer }} Tagen nicht</span>
				<span v-if="k.laengsteLuecke" class="aw-detail">
					längste Lücke: {{ k.laengsteLuecke.kontext }}
					<template v-if="k.laengsteLuecke.tageHer === null">(nie)</template>
					<template v-else>({{ k.laengsteLuecke.tageHer }} Tage)</template>
				</span>
			</article>
		</section>

		<!-- Heatmap (8.2) -->
		<section v-else-if="modus === 'heatmap' && heatmap">
			<p class="aw-hinweis">{{ heatmap.hinweis }}</p>
			<div class="aw-tabelle-rahmen">
				<table class="aw-tabelle">
					<thead>
						<tr>
							<th>Kind</th>
							<th v-for="s in heatmap.spalten" :key="s.id">{{ s.bezeichnung }}</th>
							<th>Summe</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="z in heatmap.zeilen" :key="z.schuelerId">
							<th scope="row">{{ z.anzeige }}</th>
							<td
								v-for="w in z.werte"
								:key="w.knotenId"
								:class="{ 'aw-null': w.anzahl === 0 }">
								{{ w.anzahl || '·' }}
							</td>
							<td class="aw-summe">{{ z.summe }}</td>
						</tr>
					</tbody>
				</table>
			</div>
		</section>

		<!-- Zeitleiste eines Kindes (8.1) -->
		<section v-else-if="modus === 'kind' && zeitleiste">
			<p v-if="zeitleiste.versionen && zeitleiste.versionen.wechsel" class="aw-hinweis">
				{{ zeitleiste.versionen.hinweis }}
			</p>
			<p class="aw-zahl">
				{{ zeitleiste.gesamt }} Beobachtungen ab {{ zeitleiste.von }}
			</p>
			<article v-for="e in zeitleiste.eintraege" :key="e.id" class="aw-eintrag">
				<p class="aw-meta">
					{{ datum(e.erfasstAm) }}
					<span v-if="e.kontext"> · {{ e.kontext }}</span>
					· <span class="aw-stufe">{{ e.sichtbarkeit }}</span>
				</p>
				<p class="aw-text">{{ e.text || e.markerText }}</p>
				<p v-if="e.knoten.length" class="aw-knoten">
					{{ e.knoten.map(k => k.bezeichnung).join(' · ') }}
				</p>
				<p v-for="n in e.nachtraege" :key="n.id" class="aw-nachtrag">
					Nachtrag {{ datum(n.erstelltAm) }}: {{ n.text }}
				</p>
			</article>

			<div class="aw-aktionen">
				<a class="button" :href="druckUrl" target="_blank" rel="noopener">
					Bericht als PDF
				</a>
				<a class="button" :href="auskunftUrl" target="_blank" rel="noopener">
					Auskunft nach Art. 15 DSGVO (PDF)
				</a>
			</div>
			<p class="aw-fussnote">
				Der Bericht enthält ausschließlich erfasste Beobachtungen und Zählwerte.
				Die pädagogische Einschätzung trifft die Lehrkraft.
			</p>
		</section>
	</div>
</template>

<script>
import api from '../api.js'

export default {
	name: 'Auswertung',

	data() {
		return {
			klassen: [],
			kinder: [],
			zwecke: [],
			klasseId: null,
			schuelerId: null,
			zweck: null,
			modus: 'luecken',
			luecken: null,
			heatmap: null,
			zeitleiste: null,
		}
	},

	computed: {
		druckUrl() {
			return this.schuelerId
				? api.berichtDruckUrl(this.schuelerId, { zweck: this.zweck, mitArbeitsproben: 1 })
				: '#'
		},
		auskunftUrl() {
			return this.schuelerId ? api.auskunftDruckUrl(this.schuelerId) : '#'
		},
	},

	async mounted() {
		this.klassen = await api.klassen()
		this.zwecke = await api.zwecke()
		this.klasseId = this.klassen[0]?.id ?? null
		if (this.klasseId) {
			await this.klasseGewechselt()
		}
	},

	methods: {
		async klasseGewechselt() {
			this.kinder = await api.kinder(this.klasseId)
			this.schuelerId = this.kinder[0]?.id ?? null
			await this.laden()
		},

		async laden() {
			if (!this.klasseId) {
				return
			}
			if (this.modus === 'luecken') {
				this.luecken = await api.luecken(this.klasseId, 14)
			} else if (this.modus === 'heatmap') {
				this.heatmap = await api.heatmap(this.klasseId, { ebene: 'ueberfachlich' })
			} else if (this.schuelerId) {
				this.zeitleiste = await api.zeitleiste(this.schuelerId, { zweck: this.zweck })
			}
		},

		datum(iso) {
			return new Date(iso).toLocaleString('de-DE',
				{ day: '2-digit', month: '2-digit', year: '2-digit',
					hour: '2-digit', minute: '2-digit' })
		},
	},
}
</script>

<style scoped>
.aw { padding: 1rem; max-width: 60rem; }
.aw h2 { margin-top: 0; }
/* Filterzeile und Felder kommen aus css/kidseye.css (.ke-filterzeile,
   .ke-feld). Das frühere `min-width: 10rem` ließ ein Feld mit langem
   Eintragstext mitwachsen und die Zeile ungleichmäßig umbrechen (E7). */
.aw-hinweis {
	font-size: .82rem; line-height: 1.6; opacity: .8;
	border-left: 2px solid var(--color-border); padding-left: .75rem; margin: 0 0 1rem;
}
.aw-leer { opacity: .7; }
.aw-zahl { font-size: .8rem; opacity: .7; }

.aw-luecke {
	display: flex; gap: .75rem; align-items: baseline; flex-wrap: wrap;
	padding: .5rem 0; border-bottom: 1px solid var(--color-border); font-size: .88rem;
}
.aw-warn { color: var(--color-error, #8b2a2a); }
.aw-detail { opacity: .65; font-size: .8rem; }

/* Die Tabelle scrollt in ihrem eigenen Bereich; die Seite nie waagerecht (8.7) */
.aw-tabelle-rahmen { overflow-x: auto; }
.aw-tabelle { border-collapse: collapse; font-size: .8rem; min-width: 100%; }
.aw-tabelle th, .aw-tabelle td {
	border: 1px solid var(--color-border); padding: .3rem .5rem;
	text-align: center; font-variant-numeric: tabular-nums;
}
.aw-tabelle thead th {
	font-size: .68rem; font-weight: 600; text-align: left;
	vertical-align: bottom; max-width: 8rem;
}
.aw-tabelle tbody th { text-align: left; font-weight: 400; white-space: nowrap; }
.aw-null { opacity: .3; }
.aw-summe { font-weight: 600; }

.aw-eintrag { padding: .5rem 0; border-bottom: 1px solid var(--color-border); }
.aw-meta { margin: 0; font-size: .72rem; opacity: .65; }
.aw-stufe { text-transform: uppercase; letter-spacing: .06em; }
.aw-text { margin: .15rem 0 0; line-height: 1.5; }
.aw-knoten { margin: .2rem 0 0; font-size: .75rem; color: var(--color-primary-element); }
.aw-nachtrag {
	margin: .3rem 0 0 .75rem; font-size: .78rem;
	border-left: 2px solid var(--color-border); padding-left: .5rem; opacity: .85;
}
.aw-aktionen { display: flex; gap: .5rem; margin-top: 1.25rem; flex-wrap: wrap; }
.aw-fussnote { font-size: .75rem; opacity: .65; margin-top: .75rem; line-height: 1.5; }
</style>

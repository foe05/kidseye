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

		<!--
			Kompetenz-Übersicht (8.2).

			Was diese Ansicht beantwortet, ist nicht „wer kann was" — sondern
			„worüber habe ich etwas festgehalten und worüber nicht".
			Überfachliche Kompetenzen tragen ausdrücklich keine Skala; die
			Zahlen sind Belegzahlen. Eine leere Spalte heißt deshalb nie, dass
			die Klasse dort nichts kann, sondern dass dort nichts erfasst
			wurde. Genau das ist die Auskunft, für die es die Ansicht gibt —
			und sie stand vorher nirgends.
		-->
		<section v-else-if="modus === 'heatmap' && heatmap">
			<p class="aw-hinweis">
				Wie breit ist die Beobachtung gestreut? Jede Zelle zählt, wie oft zu
				diesem Kind in dieser Kompetenz etwas festgehalten wurde.
				<strong>{{ heatmap.hinweis }}</strong>
				Eine helle Spalte sagt nichts über die Klasse — sie sagt etwas über
				die eigene Beobachtungsroutine.
			</p>

			<!-- Die zwei Sätze, die man sonst aus der Tabelle zusammensuchen muss -->
			<div v-if="deutung" class="aw-deutung">
				<p>
					<strong>{{ deutung.abgedeckt }} von {{ deutung.moeglich }}</strong>
					Feldern sind belegt ({{ deutung.quote }} %), insgesamt
					{{ heatmap.gesamt }} Beobachtungen auf {{ heatmap.kinder }} Kinder.
				</p>
				<p v-if="deutung.duennste">
					Am dünnsten belegt: <strong>{{ deutung.duennste.bezeichnung }}</strong> —
					<template v-if="deutung.duennste.kinderMit === 0">
						bei keinem Kind erfasst.
					</template>
					<template v-else>
						bei {{ deutung.duennste.kinderMit }} von {{ heatmap.kinder }} Kindern erfasst.
					</template>
				</p>
				<p v-if="deutung.ohneEintrag > 0" class="aw-deutung-warn">
					{{ deutung.ohneEintrag }}
					<template v-if="deutung.ohneEintrag === 1">Kind hat</template>
					<template v-else>Kinder haben</template>
					in diesem Zeitraum überhaupt keine Beobachtung.
				</p>
			</div>

			<!-- Legende: was die Farbe bedeutet, in Zahlen und nicht in Worten -->
			<div class="aw-legende">
				<span class="aw-legende-titel">Beobachtungen je Feld</span>
				<span v-for="s in stufen" :key="s.stufe" class="aw-legende-eintrag">
					<span class="aw-legende-feld" :class="'aw-heat-' + s.stufe" aria-hidden="true" />
					{{ s.text }}
				</span>
			</div>

			<div class="aw-tabelle-rahmen">
				<table class="aw-tabelle">
					<caption class="sr-only">
						Belegzahlen je Kind und überfachlicher Kompetenz.
						Keine Bewertung und keine Einstufung.
					</caption>
					<thead>
						<!-- Bereichsband: „Rücksichtnahme und Solidarität" sagt für sich
						     wenig, unter „Sozialkompetenz" viel. -->
						<tr class="aw-bereichszeile">
							<td class="aw-ecke" />
							<th
								v-for="b in bereiche"
								:key="b.name"
								:colspan="b.anzahl"
								scope="colgroup"
								class="aw-bereich">
								{{ b.name }}
							</th>
							<td class="aw-ecke" />
						</tr>
						<tr>
							<th class="aw-kopf-kind" scope="col">Kind</th>
							<!-- Senkrecht gesetzt: die Breite eines gedrehten Kopfes ist
							     seine Zeilenhöhe, nicht seine Textlänge. Vorher wuchsen
							     die Spalten mit dem längsten Namen und liefen über. -->
							<th
								v-for="s in heatmap.spalten"
								:key="s.id"
								scope="col"
								class="aw-kopf-dim"
								:title="s.beschreibung || s.bezeichnung">
								<span class="aw-kopf-dreh">{{ s.bezeichnung }}</span>
							</th>
							<th class="aw-kopf-summe" scope="col">
								<span class="aw-kopf-dreh">belegt / gesamt</span>
							</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="z in heatmap.zeilen" :key="z.schuelerId">
							<th scope="row" class="aw-kind">{{ z.anzeige }}</th>
							<td
								v-for="(w, i) in z.werte"
								:key="w.knotenId"
								class="aw-zelle"
								:class="'aw-heat-' + stufeVon(w.anzahl)"
								:title="zellenText(z, heatmap.spalten[i], w)">
								<span v-if="w.anzahl > 0">{{ w.anzahl }}</span>
								<span v-else class="sr-only">keine Beobachtung</span>
							</td>
							<td class="aw-summe">
								{{ z.belegt }}<span class="aw-summe-klein">/{{ heatmap.spalten.length }}</span>
								<small>{{ z.summe }}</small>
							</td>
						</tr>
					</tbody>
					<tfoot>
						<tr>
							<th scope="row" class="aw-kind">bei wie vielen Kindern</th>
							<td
								v-for="s in heatmap.spalten"
								:key="s.id"
								class="aw-fuss"
								:class="{ 'aw-fuss--leer': s.kinderMit === 0 }">
								{{ s.kinderMit }}
							</td>
							<td class="aw-summe">{{ heatmap.gesamt }}</td>
						</tr>
					</tfoot>
				</table>
			</div>

			<p v-if="heatmap.zeilen.length === 0" class="aw-leer">
				Für diese Klasse liegen noch keine Beobachtungen vor. Mit
				<code>occ kidseye:beispieldaten</code> lässt sich die Ansicht an
				erzeugten Daten ansehen.
			</p>
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
		/*
		 * Feste Schwellen statt Anteilen am Höchstwert.
		 *
		 * Ein Anteil hätte eine bewegliche Bedeutung: dieselbe Farbe hieße in
		 * einer Klasse „drei Beobachtungen" und in der nächsten „zwölf". Feste
		 * Stufen lassen sich in der Legende ausschreiben, und genau das steht
		 * dort.
		 */
		stufen() {
			return [
				{ stufe: 0, text: 'keine' },
				{ stufe: 1, text: '1' },
				{ stufe: 2, text: '2–3' },
				{ stufe: 3, text: '4–6' },
				{ stufe: 4, text: '7–10' },
				{ stufe: 5, text: 'ab 11' },
			]
		},

		/** Spaltenköpfe zu Bereichsbändern zusammenfassen. */
		bereiche() {
			const gruppen = []
			for (const spalte of this.heatmap?.spalten || []) {
				const name = spalte.bereich || '—'
				const letzte = gruppen[gruppen.length - 1]
				if (letzte && letzte.name === name) {
					letzte.anzahl++
				} else {
					gruppen.push({ name, anzahl: 1 })
				}
			}
			return gruppen
		},

		/**
		 * Die zwei bis drei Sätze, die man sonst aus der Tabelle
		 * zusammensuchen muss. Die Tabelle zeigt alles; sie sagt nichts.
		 */
		deutung() {
			const h = this.heatmap
			if (!h || !h.zeilen.length || !h.spalten.length) {
				return null
			}
			const moeglich = h.zeilen.length * h.spalten.length
			const abgedeckt = h.zeilen.reduce((summe, z) => summe + z.belegt, 0)

			// Die am dünnsten belegte Spalte — bei Gleichstand die erste.
			const duennste = [...h.spalten].sort((a, b) => a.kinderMit - b.kinderMit)[0]

			return {
				moeglich,
				abgedeckt,
				quote: Math.round((abgedeckt / moeglich) * 100),
				duennste,
				ohneEintrag: h.zeilen.filter((z) => z.summe === 0).length,
			}
		},

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

		/** Anzahl auf eine der sechs Stufen abbilden. Siehe `stufen`. */
		stufeVon(anzahl) {
			if (!anzahl) {
				return 0
			}
			if (anzahl === 1) {
				return 1
			}
			if (anzahl <= 3) {
				return 2
			}
			if (anzahl <= 6) {
				return 3
			}
			if (anzahl <= 10) {
				return 4
			}
			return 5
		},

		/** Der Text, der beim Zeigen auf eine Zelle erscheint. */
		zellenText(zeile, spalte, wert) {
			if (!spalte) {
				return ''
			}
			const kopf = zeile.anzeige + ' · ' + spalte.bezeichnung
			const zahl = wert.anzahl === 0
				? 'keine Beobachtung erfasst'
				: wert.anzahl + (wert.anzahl === 1 ? ' Beobachtung' : ' Beobachtungen')
			return spalte.beschreibung
				? kopf + '\n' + zahl + '\n\n' + spalte.beschreibung
				: kopf + '\n' + zahl
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
/* Fünfzehn Dimensionen brauchen mehr Platz als die frühere Zeitleiste. */
.aw { padding: 1rem; max-width: 76rem; }
.aw h2 { margin-top: 0; }
/* Filterzeile und Felder kommen aus css/kidseye.css (.ke-filterzeile,
   .ke-feld). Das frühere `min-width: 10rem` ließ ein Feld mit langem
   Eintragstext mitwachsen und die Zeile ungleichmäßig umbrechen (E7). */
.aw-hinweis {
	font-size: .82rem; line-height: 1.6; color: var(--ke-leise);
	border-left: 2px solid var(--ke-rand); padding-left: .75rem; margin: 0 0 1rem;
}
.aw-leer { color: var(--ke-leise); }
.aw-zahl { font-size: .8rem; color: var(--ke-leise); }

.aw-luecke {
	display: flex; gap: .75rem; align-items: baseline; flex-wrap: wrap;
	padding: .5rem 0; border-bottom: 1px solid var(--ke-rand); font-size: .88rem;
}
.aw-warn { color: var(--ke-fehler); }
.aw-detail { color: var(--ke-leise); font-size: .8rem; }

/* --- Kompetenz-Übersicht ------------------------------------------------ */

.aw-deutung {
	margin: 0 0 1rem; padding: .6rem .8rem;
	border: 1px solid var(--ke-rand); border-radius: var(--ke-radius);
	font-size: .85rem; line-height: 1.55;
}
.aw-deutung p { margin: 0; }
.aw-deutung p + p { margin-top: .2rem; }
.aw-deutung-warn { color: var(--ke-fehler); }

.aw-legende {
	display: flex; align-items: center; gap: .75rem;
	flex-wrap: wrap; margin-bottom: .5rem; font-size: .75rem;
}
.aw-legende-titel { color: var(--ke-leise); }
.aw-legende-eintrag { display: inline-flex; align-items: center; gap: .3rem; }
.aw-legende-feld {
	width: 1.1rem; height: 1.1rem; border-radius: 2px;
	border: 1px solid var(--ke-rand);
}

/* Die Tabelle scrollt in ihrem eigenen Bereich; die Seite nie waagerecht (8.7) */
.aw-tabelle-rahmen { overflow-x: auto; }
/*
 * `table-layout: fixed` ist hier die eigentliche Reparatur. Vorher galt die
 * automatische Breitenverteilung: jede Spalte wuchs mit ihrem längsten
 * Spaltenkopf, und „Rücksichtnahme und Solidarität" sprengte damit die Zelle.
 * `max-width` an einer Zelle half nicht — im automatischen Layout ist es nur
 * ein Vorschlag, den der Inhalt überstimmt.
 */
.aw-tabelle {
	border-collapse: separate; border-spacing: 0;
	table-layout: fixed; font-size: .8rem;
}
.aw-tabelle th, .aw-tabelle td {
	border-right: 1px solid var(--ke-rand);
	border-bottom: 1px solid var(--ke-rand);
	padding: .25rem .3rem;
	text-align: center; font-variant-numeric: tabular-nums;
}
.aw-tabelle thead th, .aw-tabelle thead td { border-top: 1px solid var(--ke-rand); }
.aw-tabelle th:first-child, .aw-tabelle td:first-child {
	border-left: 1px solid var(--ke-rand);
}

/* Die Namensspalte bleibt beim Rollen stehen — ohne sie ist eine Zelle in der
   Mitte keinem Kind mehr zuzuordnen. */
.aw-kind, .aw-kopf-kind, .aw-ecke {
	position: sticky; left: 0; z-index: 2;
	background: var(--ke-grund);
	width: 9rem; min-width: 9rem;
	text-align: left;
}
.aw-kind {
	font-weight: 400; white-space: nowrap;
	overflow: hidden; text-overflow: ellipsis;
}
.aw-kopf-kind { font-size: .7rem; color: var(--ke-leise); vertical-align: bottom; padding-bottom: .4rem; }
.aw-ecke { border-right-color: transparent; }

.aw-bereichszeile .aw-bereich {
	font-size: .68rem; font-weight: 600; letter-spacing: .04em;
	text-transform: uppercase; color: var(--ke-leise);
	padding: .2rem .3rem;
	border-bottom: 2px solid var(--ke-rand);
}

/* Gedrehter Spaltenkopf: seine Breite ist die Zeilenhöhe des Textes, nicht
   dessen Länge. Damit ist die Spaltenbreite von der Bezeichnung entkoppelt —
   genau das war die Ursache des Überlaufs. */
.aw-kopf-dim, .aw-kopf-summe {
	width: 2.1rem; min-width: 2.1rem;
	/* Bemessen am längsten Namen des Rahmens — „Gesellschaftliche
	   Verantwortung", 31 Zeichen. Wird der Kopf niedriger, schneidet er ab;
	   ein abgeschnittener Kopf ist kaum besser als ein überlaufender. */
	height: 12.5rem; padding: .3rem 0;
	vertical-align: bottom; font-weight: 600; font-size: .7rem;
}
.aw-kopf-dreh {
	display: block;
	writing-mode: vertical-rl; transform: rotate(180deg);
	white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
	max-height: 12rem; margin-inline: auto;
}
.aw-kopf-summe { width: 3.4rem; min-width: 3.4rem; }

.aw-zelle { height: 1.9rem; font-size: .75rem; }
/* Die Zelle ist die Trefffläche; beim Zeigen hebt sie sich ab. */
.aw-zelle:hover { outline: 2px solid var(--ke-schrift); outline-offset: -2px; }

/*
 * Die Stufen der Belegdichte. Werte und Schriftfarben stehen in
 * css/kidseye.css und sind dort einzeln gegen 4,5:1 gerechnet.
 */
.aw-heat-0 { background: var(--ke-heat-0); color: var(--ke-heat-tinte-0); }
.aw-heat-1 { background: var(--ke-heat-1); color: var(--ke-heat-tinte-hell); }
.aw-heat-2 { background: var(--ke-heat-2); color: var(--ke-heat-tinte-hell); }
.aw-heat-3 { background: var(--ke-heat-3); color: var(--ke-heat-tinte-hell); }
.aw-heat-4 { background: var(--ke-heat-4); color: var(--ke-heat-tinte-dunkel); }
.aw-heat-5 { background: var(--ke-heat-5); color: var(--ke-heat-tinte-dunkel); }

.aw-summe { font-weight: 600; white-space: nowrap; }
.aw-summe-klein { font-weight: 400; color: var(--ke-leise); }
.aw-summe small { display: block; font-weight: 400; font-size: .65rem; color: var(--ke-leise); }

.aw-tabelle tfoot td, .aw-tabelle tfoot th {
	font-size: .7rem; color: var(--ke-leise);
	border-top: 2px solid var(--ke-rand);
}
.aw-tabelle tfoot .aw-kind { font-size: .68rem; color: var(--ke-leise); }
/* Eine Spalte, in der bei keinem Kind etwas steht: der Befund, für den es
   diese Fußzeile gibt. */
.aw-fuss--leer { color: var(--ke-fehler); font-weight: 600; }

.aw-eintrag { padding: .5rem 0; border-bottom: 1px solid var(--ke-rand); }
.aw-meta { margin: 0; font-size: .72rem; color: var(--ke-leise); }
.aw-stufe { text-transform: uppercase; letter-spacing: .06em; }
.aw-text { margin: .15rem 0 0; line-height: 1.5; }
.aw-knoten { margin: .2rem 0 0; font-size: .75rem; color: var(--color-primary-element); }
.aw-nachtrag {
	margin: .3rem 0 0 .75rem; font-size: .78rem;
	border-left: 2px solid var(--ke-rand); padding-left: .5rem; color: var(--ke-leise);
}
.sr-only {
	position: absolute; width: 1px; height: 1px;
	padding: 0; margin: -1px; overflow: hidden;
	clip: rect(0 0 0 0); white-space: nowrap; border: 0;
}

.aw-aktionen { display: flex; gap: .5rem; margin-top: 1.25rem; flex-wrap: wrap; }
.aw-fussnote { font-size: .75rem; color: var(--ke-leise); margin-top: .75rem; line-height: 1.5; }
</style>

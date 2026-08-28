<template>
	<!--
		Wochendurchgang (Kapitel 6).

		Hier steht bewusst NICHT alles: Beobachtungen, die nur aus einem
		Schnellmarker im Stundenkontext entstanden sind, sind bereits
		vollständig und erscheinen nie. Aus rund 100 Einträgen pro Woche
		werden so rund 30 — etwa zehn Minuten mit Ertrag statt einem
		Pflichtprogramm ohne.
	-->
	<div class="inbox">
		<header class="inbox-kopf">
			<h2>Nacharbeiten</h2>
			<p v-if="!laden" class="inbox-zahl">
				{{ gesamt }} {{ gesamt === 1 ? 'Eintrag' : 'Einträge' }} offen
			</p>
		</header>

		<p v-if="laden">Wird geladen …</p>

		<p v-else-if="gesamt === 0" class="inbox-leer">
			Nichts zu tun. Beobachtungen, die per Marker erfasst wurden, sind bereits
			vollständig zugeordnet und tauchen hier nicht auf.
		</p>

		<template v-else>
			<div v-if="gewaehlt.length" class="inbox-leiste">
				<span>{{ gewaehlt.length }} gewählt</span>
				<button @click="erledigen">erledigen</button>
				<button :disabled="!knotenGewaehlt.length" @click="zuordnen">
					zuordnen &amp; erledigen
				</button>
				<button @click="gewaehlt = []">aufheben</button>
			</div>

			<section v-for="(ids, woche) in nachWoche" :key="woche" class="inbox-woche">
				<h3>{{ woche }}</h3>

				<article
					v-for="eintrag in eintraegeVon(ids)"
					:key="eintrag.id"
					class="inbox-eintrag"
					:class="{ 'inbox-eintrag--offen': offenId === eintrag.id }">
					<label class="inbox-wahl">
						<input
							type="checkbox"
							:checked="gewaehlt.includes(eintrag.id)"
							@change="umschalten(eintrag.id)">
					</label>

					<div class="inbox-inhalt" @click="oeffnen(eintrag)">
						<p class="inbox-meta">
							{{ datum(eintrag.erfasstAm) }} · {{ eintrag.kind }}
							<span v-if="eintrag.kontext"> · {{ eintrag.kontext }}</span>
							<span v-if="eintrag.dateien.length" class="inbox-marke">
								{{ eintrag.dateien.length }} Arbeitsprobe(n)
							</span>
						</p>
						<p class="inbox-text">{{ eintrag.text || eintrag.markerText }}</p>
						<p v-if="eintrag.knoten.length" class="inbox-knoten">
							{{ eintrag.knoten.map(k => k.bezeichnung).join(' · ') }}
						</p>
					</div>

					<div v-if="offenId === eintrag.id" class="inbox-zuordnung">
						<p v-if="vorschlaege.fachlich.length === 0" class="inbox-hinweis">
							Diese Beobachtung stammt aus einem fachneutralen Kontext.
							Es gibt hier keine fachliche Achse — nur überfachliche Kompetenzen.
						</p>

						<template v-if="vorschlaege.fachlich.length">
							<p class="inbox-titel">Fachlich</p>
							<button
								v-for="k in vorschlaege.fachlich"
								:key="k.id"
								class="inbox-knopf"
								:class="{ 'inbox-knopf--an': knotenGewaehlt.includes(k.id) }"
								@click="knotenUmschalten(k.id)">
								{{ k.bezeichnung }}
							</button>
						</template>

						<p class="inbox-titel">Überfachlich</p>
						<button
							v-for="k in vorschlaege.ueberfachlich"
							:key="k.id"
							class="inbox-knopf"
							:class="{ 'inbox-knopf--an': knotenGewaehlt.includes(k.id) }"
							@click="knotenUmschalten(k.id)">
							{{ k.bezeichnung }}
							<small v-if="!k.bewertbar">Beleg, keine Einstufung</small>
						</button>

						<div class="inbox-aktionen">
							<button class="primary" @click="einzelnZuordnen(eintrag)">
								Zuordnen und erledigen
							</button>
							<!-- Erledigen ohne Zuordnung ist ausdrücklich erlaubt (6.4) -->
							<button @click="einzelnErledigen(eintrag)">Ohne Zuordnung erledigen</button>
						</div>
					</div>
				</article>
			</section>
		</template>
	</div>
</template>

<script>
import api from '../api.js'

export default {
	name: 'Inbox',

	data() {
		return {
			laden: true,
			eintraege: [],
			nachWoche: {},
			gesamt: 0,
			gewaehlt: [],
			offenId: null,
			vorschlaege: { fachlich: [], ueberfachlich: [] },
			knotenGewaehlt: [],
		}
	},

	mounted() {
		this.laden_()
	},

	methods: {
		async laden_() {
			this.laden = true
			const daten = await api.inbox()
			this.eintraege = daten.eintraege
			this.nachWoche = daten.nachWoche
			this.gesamt = daten.gesamt
			this.laden = false
		},

		eintraegeVon(ids) {
			return this.eintraege.filter((e) => ids.includes(e.id))
		},

		umschalten(id) {
			const i = this.gewaehlt.indexOf(id)
			if (i === -1) {
				this.gewaehlt.push(id)
			} else {
				this.gewaehlt.splice(i, 1)
			}
		},

		async oeffnen(eintrag) {
			if (this.offenId === eintrag.id) {
				this.offenId = null
				return
			}
			this.offenId = eintrag.id
			this.knotenGewaehlt = eintrag.knoten.map((k) => k.id)
			this.vorschlaege = await api.inboxVorschlaege(eintrag.id)
		},

		knotenUmschalten(id) {
			const i = this.knotenGewaehlt.indexOf(id)
			if (i === -1) {
				this.knotenGewaehlt.push(id)
			} else {
				this.knotenGewaehlt.splice(i, 1)
			}
		},

		async einzelnZuordnen(eintrag) {
			await api.inboxZuordnen([eintrag.id], this.knotenGewaehlt, true)
			this.offenId = null
			await this.laden_()
		},

		async einzelnErledigen(eintrag) {
			await api.inboxErledigen([eintrag.id])
			this.offenId = null
			await this.laden_()
		},

		/** Sammelbearbeitung (6.3) — der eigentliche Tempohebel im Durchgang. */
		async zuordnen() {
			await api.inboxZuordnen(this.gewaehlt, this.knotenGewaehlt, true)
			this.gewaehlt = []
			await this.laden_()
		},

		async erledigen() {
			await api.inboxErledigen(this.gewaehlt)
			this.gewaehlt = []
			await this.laden_()
		},

		datum(iso) {
			return new Date(iso).toLocaleString('de-DE',
				{ day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
		},
	},
}
</script>

<style scoped>
.inbox { padding: 1rem; max-width: 48rem; }
.inbox-kopf { display: flex; align-items: baseline; gap: 1rem; }
.inbox-kopf h2 { margin: 0; }
.inbox-zahl { color: var(--ke-leise); font-size: .85rem; margin: 0; }
.inbox-leer {
	margin-top: 1rem; color: var(--ke-leise); line-height: 1.6;
	border-left: 2px solid var(--color-border); padding-left: .75rem;
}
.inbox-leiste {
	position: sticky; top: 0; z-index: 5;
	display: flex; gap: .5rem; align-items: center; flex-wrap: wrap;
	padding: .5rem 0; margin-top: .5rem;
	background: var(--color-main-background);
	border-bottom: 1px solid var(--color-border);
	font-size: .85rem;
}
.inbox-woche { margin-top: 1.5rem; }
.inbox-woche h3 {
	font-size: .72rem; letter-spacing: .1em; text-transform: uppercase;
	color: var(--ke-leise); margin: 0 0 .4rem;
}
.inbox-eintrag {
	display: grid;
	grid-template-columns: 2rem 1fr;
	gap: .5rem;
	padding: .6rem 0;
	border-bottom: 1px solid var(--color-border);
}
.inbox-eintrag--offen { background: var(--color-background-hover); }
.inbox-inhalt { cursor: pointer; }
.inbox-meta { margin: 0; font-size: .72rem; color: var(--ke-leise); }
.inbox-marke {
	margin-left: .4rem; padding: 0 .3rem;
	border: 1px solid var(--color-border); border-radius: 3px;
}
.inbox-text { margin: .15rem 0 0; line-height: 1.5; }
.inbox-knoten { margin: .2rem 0 0; font-size: .75rem; color: var(--color-primary-element); }
.inbox-zuordnung { grid-column: 1 / -1; padding: .5rem 0 .25rem; }
.inbox-titel {
	margin: .6rem 0 .3rem; font-size: .7rem;
	letter-spacing: .08em; text-transform: uppercase; color: var(--ke-leise);
}
/* Vorschlagsknopf zum Zuordnen. Stand auf 34 px; die Höhe kommt jetzt aus
   css/kidseye.css, inline-flex zentriert den Text darin senkrecht. */
.inbox-knopf {
	display: inline-flex; align-items: center; margin: 0 .25rem .25rem 0;
	padding: .3rem .55rem;
	border: 1px solid var(--color-border); border-radius: var(--border-radius);
	background: none; color: inherit; font-size: .78rem; cursor: pointer;
	text-align: left;
}
.inbox-knopf--an {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element-light);
}
.inbox-knopf small { display: block; font-size: .62rem; color: var(--ke-leise); }
.inbox-hinweis {
	margin: .4rem 0; font-size: .8rem; color: var(--ke-leise); line-height: 1.5;
	border-left: 2px solid var(--color-border); padding-left: .6rem;
}
.inbox-aktionen { display: flex; gap: .5rem; margin-top: .75rem; flex-wrap: wrap; }
</style>

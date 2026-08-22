<template>
	<!--
		Klassenbild (Kapitel 3.5, D11).

		Ein frei anordenbares Kachelraster, ausdrücklich KEINE Raumgeometrie:
		keine Tischformen, keine Koordinaten, keine Tafelposition. Es ist ein
		Merkbild, kein Abbild — deshalb bleibt es auch gültig, wenn die
		Sitzordnung wechselt.
	-->
	<div class="kb">
		<h2>Klassenbild</h2>

		<label class="ke-feld kb-feld">
			<span>Klasse</span>
			<select v-model.number="klasseId" @change="laden">
				<option v-for="k in klassen" :key="k.id" :value="k.id">{{ k.name }}</option>
			</select>
		</label>

		<p class="kb-hinweis">
			Ziehen ordnet die Kacheln um. Eine Gruppe fasst Kinder sichtbar zusammen
			(etwa eine Tischgruppe) — beides ist optional. Ohne jede Pflege steht das
			Bild alphabetisch und ist sofort benutzbar.
		</p>

		<div class="kb-raster">
			<div
				v-for="(kind, i) in kinder"
				:key="kind.id"
				class="kb-kachel"
				draggable="true"
				@dragstart="zieheVon = i"
				@dragover.prevent
				@drop="ablegen(i)">
				<span class="kb-name">{{ kind.anzeige }}</span>
				<input
					v-model="kind.gruppe"
					class="kb-gruppe"
					type="text"
					placeholder="Gruppe"
					:aria-label="'Gruppe von ' + kind.anzeige">
			</div>
		</div>

		<div class="kb-aktionen">
			<button class="primary" @click="speichern">Speichern</button>
			<button @click="zuruecksetzen">Alphabetisch zurücksetzen</button>
			<span v-if="gespeichert" class="kb-ok">gespeichert</span>
		</div>
	</div>
</template>

<script>
import api from '../api.js'

export default {
	name: 'Klassenbild',

	data() {
		return {
			klassen: [],
			klasseId: null,
			kinder: [],
			zieheVon: null,
			gespeichert: false,
		}
	},

	async mounted() {
		this.klassen = await api.klassen()
		this.klasseId = this.klassen[0]?.id ?? null
		if (this.klasseId) {
			await this.laden()
		}
	},

	methods: {
		async laden() {
			this.kinder = await api.kinder(this.klasseId)
			this.gespeichert = false
		},

		ablegen(nach) {
			if (this.zieheVon === null || this.zieheVon === nach) {
				return
			}
			const [bewegt] = this.kinder.splice(this.zieheVon, 1)
			this.kinder.splice(nach, 0, bewegt)
			this.zieheVon = null
		},

		async speichern() {
			await api.klassenbildSpeichern(this.klasseId, this.kinder.map((k, i) => ({
				schuelerId: k.id,
				position: i + 1,
				gruppe: (k.gruppe || '').trim() || null,
			})))
			this.gespeichert = true
			await this.laden()
			this.gespeichert = true
		},

		async zuruecksetzen() {
			this.kinder = await api.klassenbildZuruecksetzen(this.klasseId)
		},
	},
}
</script>

<style scoped>
.kb { padding: 1rem; max-width: 44rem; }
.kb h2 { margin-top: 0; }
/* .ke-feld ordnet Beschriftung und Feld an; hier bleibt nur die Breite. */
.kb-feld { max-width: 16rem; }
.kb-hinweis {
	margin: 1rem 0; font-size: .82rem; line-height: 1.6; opacity: .8;
	border-left: 2px solid var(--color-border); padding-left: .75rem;
}
.kb-raster {
	display: grid; grid-template-columns: repeat(4, 1fr); gap: .4rem;
}
@media (max-width: 34rem) { .kb-raster { grid-template-columns: repeat(3, 1fr); } }
.kb-kachel {
	border: 1px solid var(--color-border); border-radius: var(--border-radius);
	padding: .4rem; display: flex; flex-direction: column; gap: .25rem;
	cursor: grab; background: var(--color-main-background);
}
.kb-name { font-size: .8rem; }
/*
 * Ausnahme vom Mindestmaß 44 px, namentlich geführt in
 * tests/js/formular.spec.js.
 *
 * .kb-gruppe ist ein Eingabefeld, keine Anzeige — aber ein nachrangiges:
 * es sitzt zu viert nebeneinander in einer Kachel des Klassenbilds, wird
 * einmal beim Einrichten gefüllt und danach nicht mehr angefasst. Auf 44 px
 * gebracht verdoppelte es die Kachelhöhe und drängte den Namen aus dem Bild,
 * um den es dort geht. Die Gruppe lässt sich auch unter „Klassen & Kinder"
 * pflegen, wo das Feld die volle Höhe hat.
 */
.kb-gruppe { font-size: .68rem; min-height: 28px; width: 100%; }
.kb-aktionen { display: flex; gap: .5rem; align-items: center; margin-top: 1rem; }
.kb-ok { font-size: .8rem; color: var(--color-success, #2f6b4f); }
</style>

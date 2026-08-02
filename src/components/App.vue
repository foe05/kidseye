<template>
	<!--
		Nextcloud-Oberfläche: Wochendurchgang, Auswertung, Verwaltung (D10).
		Der Erfassungsbildschirm läuft getrennt davon im Vollbild.
	-->
	<div class="app">
		<nav class="app-nav">
			<button
				v-for="reiter in reiter"
				:key="reiter.id"
				:class="{ 'app-nav--an': ansicht === reiter.id }"
				@click="ansicht = reiter.id">
				{{ reiter.name }}
			</button>
			<a class="app-nav-start" :href="unterrichtUrl">Unterrichtsmodus ↗</a>
		</nav>

		<Inbox v-if="ansicht === 'inbox'" />
		<MarkerVerwaltung v-else-if="ansicht === 'marker'" />
		<Auswertung v-else-if="ansicht === 'auswertung'" />
		<Klassenbild v-else-if="ansicht === 'klassenbild'" />
		<Stammdaten v-else-if="ansicht === 'stammdaten'" />
		<Einrichtung v-else-if="ansicht === 'einrichtung'" />
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import Inbox from './Inbox.vue'
import MarkerVerwaltung from './MarkerVerwaltung.vue'
import Auswertung from './Auswertung.vue'
import Klassenbild from './Klassenbild.vue'
import Stammdaten from './Stammdaten.vue'
import Einrichtung from './Einrichtung.vue'

export default {
	name: 'App',
	components: { Inbox, MarkerVerwaltung, Auswertung, Klassenbild, Stammdaten, Einrichtung },

	data() {
		return {
			ansicht: 'inbox',
			unterrichtUrl: generateUrl('/apps/kidseye/unterricht'),
			reiter: [
				{ id: 'inbox', name: 'Nacharbeiten' },
				{ id: 'auswertung', name: 'Auswertung' },
				{ id: 'klassenbild', name: 'Klassenbild' },
				{ id: 'stammdaten', name: 'Klassen & Kinder' },
				{ id: 'marker', name: 'Marker' },
				{ id: 'einrichtung', name: 'Einrichtung' },
			],
		}
	},
}
</script>

<style scoped>
.app { width: 100%; }
.app-nav {
	display: flex; gap: .25rem; align-items: center; flex-wrap: wrap;
	padding: .5rem 1rem; border-bottom: 1px solid var(--color-border);
}
.app-nav button {
	background: none; border: 0; border-radius: var(--border-radius);
	padding: .4rem .7rem; min-height: 40px; cursor: pointer;
	color: inherit; font: inherit;
}
.app-nav--an {
	background: var(--color-primary-element-light);
	font-weight: 600;
}
.app-nav-start { margin-left: auto; font-size: .85rem; }
</style>

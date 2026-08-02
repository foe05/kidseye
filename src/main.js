import Vue from 'vue'
import App from './components/App.vue'

// Einstiegspunkt der Nextcloud-Oberfläche: Wochendurchgang, Auswertung
// und Verwaltung. Der Erfassungsbildschirm läuft getrennt im Vollbild (D10).
Vue.mixin({ methods: { t, n } })

export default new Vue({
	el: '#kidseye-main',
	render: (h) => h(App),
})

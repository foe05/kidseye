import Vue from 'vue'
import Unterricht from './components/Unterricht.vue'

// Einstiegspunkt des Unterrichtsmodus: Vollbild ohne Nextcloud-Kopfleiste,
// Startziel des Home-Bildschirm-Symbols (D14).
Vue.mixin({ methods: { t, n } })

export default new Vue({
	el: '#kidseye-unterricht',
	render: (h) => h(Unterricht),
})

const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

// Zwei Einstiegspunkte (D10):
//  - main:       Verwaltung, Inbox und Auswertung in der Nextcloud-Oberfläche
//  - unterricht: Erfassungsbildschirm im Vollbild, Startziel des
//                Home-Bildschirm-Symbols
webpackConfig.entry = {
	'kidseye-main': path.join(__dirname, 'src', 'main.js'),
	'kidseye-unterricht': path.join(__dirname, 'src', 'unterricht.js'),
}

module.exports = webpackConfig

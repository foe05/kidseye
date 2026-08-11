const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

// Zwei Einstiegspunkte (D10):
//  - main:       Verwaltung, Inbox und Auswertung in der Nextcloud-Oberfläche
//  - unterricht: Erfassungsbildschirm im Vollbild, Startziel des
//                Home-Bildschirm-Symbols
//
// Die Keys bleiben ohne App-Präfix: @nextcloud/webpack-vue-config baut den
// Dateinamen als `${appName}-[name].js`, also kidseye-main.js — genau das,
// was Util::addScript(APP_ID, 'kidseye-main') im PageController erwartet.
webpackConfig.entry = {
	main: path.join(__dirname, 'src', 'main.js'),
	unterricht: path.join(__dirname, 'src', 'unterricht.js'),
}

module.exports = webpackConfig

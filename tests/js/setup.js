// IndexedDB steht in jsdom nicht zur Verfügung — fake-indexeddb liefert eine
// vollständige Umsetzung, sodass die Warteschlange echt getestet wird und
// nicht gegen eine Attrappe.
import 'fake-indexeddb/auto'

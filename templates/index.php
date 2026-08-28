<?php
declare(strict_types=1);
?>
<!--
	Die Verwaltungsoberfläche muss in #app-content hängen, nicht frei in
	#content. Nextcloud gibt erst dieser Hülle drei Dinge mit, die hier alle
	drei gebraucht werden:

	  - den hellen Arbeitsgrund (--color-main-background). Ohne sie steht die
	    App auf dem Hintergrundbild der Instanz;
	  - overflow-y: auto, also den Rollbalken. #content selbst steht auf
	    overflow: hidden — was darüber hinausragt, ist ohne #app-content nicht
	    erreichbar, nicht nur unschön;
	  - die abgerundete Fläche mit ihren Abständen.

	#app-content-wrapper ist die Ebene, auf der Nextcloud die Breite begrenzt.

	Die ID für Vue sitzt bewusst nicht hier, sondern am Wurzelelement von
	App.vue: Vue 2 ersetzt das Mount-Element beim Einhängen. Stünde sie nur
	hier, wäre sie nach dem Einhängen weg — und css/kidseye.css träfe auf
	nichts mehr.
-->
<div id="app-content">
	<div id="app-content-wrapper">
		<div id="kidseye-main"></div>
	</div>
</div>

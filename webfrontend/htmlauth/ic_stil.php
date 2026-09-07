<?php
/**
 * Intercom - der gemeinsame Stil aller Seiten
 *
 * Bis 2.1.2 stand dieser Block ausschliesslich in index.php. Live-, Bild-
 * und Videoarchiv bekamen ihn nie zu sehen: dort stand die Ueberschrift
 * schwarz und in Browservorgabe, waehrend sie auf der Startseite gruen und
 * kleiner war. Derselbe Stil gehoert an EINE Stelle.
 *
 * DIE KLASSENNAMEN HEISSEN sm-, NICHT ic-.
 * Bis 2.1.13 trug dieses Plugin ein eigenes Kuerzel. Der Hausstandard legt
 * sm- fuer JEDES Plugin fest - LoxBerry zeigt immer nur eine Plugin-Seite,
 * zwei Stylesheets treffen sich also gar nicht, und der Isolationsgewinn war
 * eingebildet. Der Preis war real: die Hauspruefung sucht nach sm-, hat das
 * eigene Kuerzel wegen der zusammengesetzten Selektoren (.icw .ic-...) nicht
 * gesehen und die Zeile deshalb GRUEN gemeldet - eine Pruefung, die nichts
 * geprueft hat. Und die mitgelieferte Hilfe benutzte laengst sm-mono, eine
 * Klasse, die es hier gar nicht gab.
 *
 * Eingebunden wird die Datei NACH LBWeb::lbheader(), weil sie Stil in den
 * Seitenkoerper schreibt.
 */
if (!defined('IC_STIL_AUSGEGEBEN')) {
    define('IC_STIL_AUSGEGEBEN', 1);
?>
<style>
.smw, .smw * { text-shadow: none !important; }
.smw { max-width: 1100px; margin-top: -55px; }
.smw h1 { color: #6dac20; font-size: 1.5em; margin: 0 0 4px; }
.smw h2 { color: #6dac20; margin: 18px 0 6px; font-size: 1.15em; }
.smw h3.sm-h3 { color: #4f7d17; font-size: 1.0em; font-weight: 700; margin: 16px 0 2px; }
.smw p, .smw li { line-height: 1.5; }

/* Reiterleiste: echte Verweise, damit jeder Reiter verlinkbar bleibt und die
   Seite auch ohne JavaScript bedienbar ist. Welcher Reiter offen ist,
   entscheidet der Server (sm-active steht schon im ausgelieferten HTML);
   das Skript schaltet danach nur noch ohne Neuladen um. */
.smw .sm-reiter { display: flex; flex-wrap: wrap; gap: 4px; border-bottom: 3px solid #6dac20; margin: 14px 0 0; }
.smw .sm-reiter a { padding: 9px 16px; background: #eee; border-radius: 8px 8px 0 0; cursor: pointer;
    font-weight: 600; color: #444 !important; text-decoration: none; display: inline-block; }
.smw .sm-reiter a.sm-active { background: #6dac20; color: #fff !important; }
.smw .sm-seite { display: none; padding: 14px 2px; }
.smw .sm-seite.sm-active { display: block; }

.smw label { display: block; font-weight: 600; margin: 12px 0 3px; }
.smw input[type=text], .smw input[type=number], .smw input[type=password], .smw select {
    width: 100%; max-width: 520px; padding: 7px 9px; border: 1px solid #bbb; border-radius: 6px; font-size: 1em;
    background: #fff; }
/* EIN AUSWAHLFELD MUSS ALS AUSWAHLFELD ERKENNBAR SEIN.
   Befund vom Anwender am 19.08.2026: das Feld "Weg" sah aus wie ein Textfeld -
   kein Pfeil am Ende. Wer nicht hineinklickt, erfaehrt nie, dass drei Wege
   dahinterstehen. Der Pfeil kommt sonst vom Thema (jQuery Mobile setzt fuer
   Auswahlfelder appearance: none), und darauf kann sich eine Plugin-Oberflaeche
   nicht verlassen. Deshalb: appearance selbst abschalten und den Pfeil selbst
   zeichnen. Dann sieht das Feld ueberall gleich aus - und es kann nie zwei
   Pfeile geben. */
.smw select, .smw select.sm-auswahl {
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
    border: 2px solid #6dac20 !important;
    background-color: #fff !important;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='9' viewBox='0 0 14 9'%3E%3Cpath d='M1 1l6 6 6-6' fill='none' stroke='%236dac20' stroke-width='2'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 12px center !important;
    background-size: 14px 9px !important;
    padding-right: 40px !important;
    cursor: pointer;
}
.smw select:hover, .smw select:focus { border-color: #4f7d17 !important; }
/* Der Hinweis unter dem Feld sagt dasselbe noch einmal in Worten - fuer den
   Fall, dass ein Thema auch Hintergrundbilder unterdrueckt. */
.smw .sm-auswahlhinweis { font-size: 0.82em; color: #4f7d17; margin: 2px 0 0; }
.smw .sm-klein { font-size: 0.88em; color: #666; margin: 3px 0 0; max-width: 780px; }
.smw .sm-mono { font-family: monospace; background: #f4f4f4; padding: 1px 5px; border-radius: 4px;
    word-break: break-all; }

.smw .sm-btn { background: #6dac20; color: #fff !important; border: 0; border-radius: 8px; padding: 9px 18px;
    cursor: pointer; text-decoration: none; font-size: 0.95em; }
.smw .sm-btn.sm-b-lesen   { background: #6dac20 !important; color: #fff !important; }
.smw .sm-btn.sm-b-technik { background: #546e7a !important; color: #fff !important; }
.smw .sm-btn.sm-b-aktion  { background: #e0620d !important; color: #fff !important; }
/* Fuer JEDE Knopfgruppe eine eigene Hover- und Focus-Farbe: fehlen sie, kommt
   der Hover-Zustand vom Rahmen (jQuery Mobile) und ist unlesbar. */
.smw .sm-btn.sm-b-lesen:hover,   .smw .sm-btn.sm-b-lesen:focus   { background: #5c9219 !important; color: #fff !important; }
.smw .sm-btn.sm-b-technik:hover, .smw .sm-btn.sm-b-technik:focus { background: #445a63 !important; color: #fff !important; }
.smw .sm-btn.sm-b-aktion:hover,  .smw .sm-btn.sm-b-aktion:focus  { background: #b94f0a !important; color: #fff !important; }
.smw .sm-btn { text-shadow: none !important; box-shadow: none !important; }

.smw .sm-hinweis { border-left: 5px solid #6dac20; background: #f4faee; padding: 10px 14px; margin: 12px 0; border-radius: 0 8px 8px 0; }
.smw .sm-warn { border-left-color: #e0620d; background: #fff5ee; }
.smw .sm-step { border: 1px solid #ddd; border-radius: 10px; padding: 12px 14px; margin: 10px 0; }

/* Eine Tabelle, die breiter ist als das Fenster, braucht ihre eigene
   Bildlaufleiste - sonst schiebt sie die ganze Seite nach rechts. */
.smw .sm-tabrahmen { overflow-x: auto; max-width: 100%; }
.smw table { border-collapse: collapse; width: 100%; margin: 8px 0; }
.smw th, .smw td { border: 1px solid #ddd; padding: 6px 9px; text-align: left; font-size: 0.93em;
    vertical-align: top; }
.smw th { background: #f2f2f2; }
.smw pre { background: #f6f6f6; border: 1px solid #ddd; border-radius: 8px; padding: 10px;
    max-height: 460px; overflow: auto; font-size: 0.85em; }

.smw .sm-knopfreihe { display: flex; flex-wrap: wrap; gap: 10px; margin: 10px 0 4px; align-items: stretch; }
.smw .sm-knopfreihe form { margin: 0; display: flex; }
.smw .sm-knopfreihe .sm-btn { flex: 0 0 auto; min-width: 250px; text-align: center;
    display: inline-flex; align-items: center; justify-content: center; line-height: 1.25; }
.smw .sm-legende { display: flex; flex-wrap: wrap; gap: 14px; margin: 10px 0 2px; font-size: 0.86em; color: #555; }
.smw .sm-legende span { display: inline-flex; align-items: center; gap: 6px; }
.smw .sm-punkt { width: 13px; height: 13px; border-radius: 3px; display: inline-block; }
.smw .sm-punkt.sm-b-lesen   { background: #6dac20; }
.smw .sm-punkt.sm-b-technik { background: #546e7a; }
.smw .sm-punkt.sm-b-aktion  { background: #e0620d; }

/* Selbstpruefung */
.smw .sm-pz { border-collapse: collapse; width: 100%; }
.smw .sm-pz td { border: 1px solid #e4e4e4; padding: 5px 8px; font-size: 0.92em; }
.smw .sm-pz td.sm-marke { width: 26px; text-align: center; font-weight: 700; }
.smw .sm-ok     { color: #4f7d17; }
.smw .sm-fehl   { color: #c0392b; }
.smw .sm-unklar { color: #b8860b; }
.smw .sm-hinw   { color: #777; }
.smw .sm-rat { color: #666; font-size: 0.9em; }

.smw .sm-gal { display: flex; flex-wrap: wrap; gap: 10px; }
.smw .sm-gal figure { margin: 0; width: 220px; }
.smw .sm-gal img { width: 100%; border-radius: 8px; border: 1px solid #ddd; }
.smw .sm-gal figcaption { font-size: 0.8em; color: #666; word-break: break-all; }
.smw .sm-del { color: #c0392b; text-decoration: underline; cursor: pointer; }

.smw .sm-zeile { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
.smw .sm-zeile > div { flex: 1 1 160px; }
.smw .sm-zeile label { margin-top: 0; }

/* Die Reiterleiste von LoxBerry ("Start | Live | Bilder Archiv | Video
   Archiv") klebte unmittelbar unter der Kopfzeile. Der Selektor #vuenavbar
   ist am laufenden LoxBerry nachgemessen (Kette a.vuenavbarelement ->
   div.vuenavbarcontainer -> div#vuenavbar) und die Wirkung dort geprueft.
   93 px = 139 px des Nutzerwunsches minus ein Drittel. */
#vuenavbar { margin-top: 93px; }
</style>
<?php
}

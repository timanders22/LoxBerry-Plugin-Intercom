<?php
/**
 * Intercom - der gemeinsame Stil aller vier Seiten
 *
 * Bis 2.1.2 stand dieser Block ausschliesslich in index.php. Live-, Bild-
 * und Videoarchiv bekamen ihn nie zu sehen: dort stand die Ueberschrift
 * schwarz und in Browservorgabe, waehrend sie auf der Startseite gruen und
 * kleiner war. Derselbe Stil gehoert an EINE Stelle, sonst laeuft die
 * Oberflaeche wieder auseinander - dieselbe Regel wie bei der Reiterliste.
 *
 * Eingebunden wird die Datei NACH LBWeb::lbheader(), weil sie Stil in den
 * Seitenkoerper schreibt.
 */
if (!defined('IC_STIL_AUSGEGEBEN')) {
    define('IC_STIL_AUSGEGEBEN', 1);
?>
<style>
.icw, .icw * { text-shadow: none !important; }
.icw { max-width: 1100px; margin-top: -55px; }
.icw h1 { color: #6dac20; font-size: 1.5em; margin: 0 0 4px; }
.icw h2 { color: #6dac20; margin: 18px 0 6px; font-size: 1.15em; }
.icw p, .icw li { line-height: 1.5; }
.icw .ic-reiter { display: flex; flex-wrap: wrap; gap: 4px; border-bottom: 3px solid #6dac20; margin: 14px 0 0; }
.icw .ic-reiter div { padding: 9px 16px; background: #eee; border-radius: 8px 8px 0 0; cursor: pointer; font-weight: 600; color: #444; }
.icw .ic-reiter div.aktiv { background: #6dac20; color: #fff; }
.icw .ic-seite { display: none; padding: 14px 2px; }
.icw .ic-seite.aktiv { display: block; }
.icw label { display: block; font-weight: 600; margin: 12px 0 3px; }
.icw input[type=text], .icw input[type=number], .icw input[type=password] {
    width: 100%; max-width: 520px; padding: 7px 9px; border: 1px solid #bbb; border-radius: 6px; font-size: 1em; }
.icw .ic-klein { font-size: 0.88em; color: #666; margin: 3px 0 0; max-width: 720px; }
.icw .ic-mono { font-family: monospace; background: #f4f4f4; padding: 1px 5px; border-radius: 4px; }
.icw .ic-btn { background: #6dac20; color: #fff !important; border: 0; border-radius: 8px; padding: 9px 18px;
    cursor: pointer; text-decoration: none; font-size: 0.95em; }
.icw .ic-hinweis { border-left: 5px solid #6dac20; background: #f4faee; padding: 10px 14px; margin: 12px 0; border-radius: 0 8px 8px 0; }
.icw .ic-warn { border-left-color: #e0620d; background: #fff5ee; }
.icw .ic-schritt { border: 1px solid #ddd; border-radius: 10px; padding: 12px 14px; margin: 10px 0; }
.icw table { border-collapse: collapse; width: 100%; max-width: 900px; margin: 8px 0; }
.icw th, .icw td { border: 1px solid #ddd; padding: 6px 9px; text-align: left; font-size: 0.93em; }
.icw th { background: #f2f2f2; }
.icw pre { background: #f6f6f6; border: 1px solid #ddd; border-radius: 8px; padding: 10px;
    max-height: 460px; overflow: auto; font-size: 0.85em; }

/* Hausstandard: Kachel-Raster im Reiter Test */
.icw .ic-h3 { color: #4f7d17; font-size: 1.0em; font-weight: 700; margin: 16px 0 2px; }
.icw .ic-knopfreihe { display: flex; flex-wrap: wrap; gap: 10px; margin: 10px 0 4px; align-items: stretch; }
.icw .ic-knopfreihe form { margin: 0; display: flex; }
.icw .ic-knopfreihe .ic-btn { flex: 0 0 auto; min-width: 250px; text-align: center;
    display: inline-flex; align-items: center; justify-content: center; line-height: 1.25; }
.icw .ic-legende { display: flex; flex-wrap: wrap; gap: 14px; margin: 10px 0 2px; font-size: 0.86em; color: #555; }
.icw .ic-legende span { display: inline-flex; align-items: center; gap: 6px; }
.icw .ic-punkt { width: 13px; height: 13px; border-radius: 3px; display: inline-block; }
.icw .ic-btn.ic-b-lesen   { background: #6dac20; }
.icw .ic-btn.ic-b-technik { background: #546e7a; }
.icw .ic-btn.ic-b-aktion  { background: #e0620d; }
.icw .ic-punkt.ic-b-lesen   { background: #6dac20; }
.icw .ic-punkt.ic-b-technik { background: #546e7a; }
.icw .ic-punkt.ic-b-aktion  { background: #e0620d; }
.icw .ic-gal { display: flex; flex-wrap: wrap; gap: 10px; }
.icw .ic-gal figure { margin: 0; width: 220px; }
.icw .ic-gal img { width: 100%; border-radius: 8px; border: 1px solid #ddd; }
.icw .ic-gal figcaption { font-size: 0.8em; color: #666; word-break: break-all; }

/* Die Reiterleiste von LoxBerry ("Start | Live | Bilder Archiv | Video
   Archiv") klebte unmittelbar unter der Kopfzeile. Der Selektor #vuenavbar
   ist am laufenden LoxBerry nachgemessen (Kette a.vuenavbarelement ->
   div.vuenavbarcontainer -> div#vuenavbar) und die Wirkung dort geprueft:
   die Leiste ruecke um genau diesen Betrag nach unten. */
/* 2.1.6: Nutzerwunsch 11.08.2026 - sechs Leerzeilen Luft VOR der Menueleiste
 * (139px = 14px + 6 x 20,8px Zeilenhoehe, am Geraet gemessen), dafuer die
 * Luecke zwischen Menueleiste und der Ueberschrift Intercom von 63px auf
 * rund 8px zusammengezogen (margin-top -55px an .icw). */
#vuenavbar { margin-top: 139px; }
</style>
<?php
}

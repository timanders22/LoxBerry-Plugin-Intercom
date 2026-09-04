<?php

// Navigationsleiste. Die Einstellungen sind seit v1.5.0 ein Reiter auf der
// Startseite und tauchen hier nicht mehr als eigener Punkt auf.
//
// BERICHTIGT 04.09.2026 (2.2.6): mit isset(). Bis 2.2.5 standen hier vier
// ungeschuetzte Zugriffe auf $L - als einzige Stelle des Plugins; ic_txt(),
// ic_roh() und ic_pz_zeile() pruefen alle. Gemessen mit
// Werkzeuge/installationslage_rendern.py in der Lage ohne LBP*-Variablen:
// vier Meldungen "Undefined index: COMMON.NAVSTART" (7.4) bzw. "Undefined
// array key" (8.4) - mitten in der ausgelieferten Seite und, weil menu.php
// weit vor dem Seitenkopf eingebunden wird, VOR jeder Kopfzeile.
$ic_m = function ($schluessel, $ersatz) {
    global $L;
    return (is_array($L) && isset($L[$schluessel]) && $L[$schluessel] !== '')
        ? $L[$schluessel] : $ersatz;
};

$navbar[1]['Name'] = $ic_m('COMMON.NAVSTART', 'Start');
$navbar[1]['URL'] = 'index.php';

$navbar[2]['Name'] = $ic_m('COMMON.LIVE', 'Live');
$navbar[2]['URL'] = 'live.php';

$navbar[3]['Name'] = $ic_m('COMMON.BACKUP', 'Archiv');
$navbar[3]['URL'] = 'archive.php';

$navbar[4]['Name'] = $ic_m('COMMON.BACKUPVIDEO', 'Videoarchiv');
$navbar[4]['URL'] = 'videoarchive.php';

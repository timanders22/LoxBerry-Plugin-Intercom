<?php
/**
 * bin/befehle_zuordnung.php - Zuordnungstabelle der Befehlsbruecke, Linie Intercom.
 *
 * Gruppe 7 / MQTT (2.2.23; Bauvorschrift wz_bild/GRUPPE7_BAUVORSCHRIFT.md, Entwurf Musterprojekt/entwurf_gruppe7_ic.txt
 * Abschnitt 3.3, ENTSCHEIDUNGEN_GRUPPE7_INTERCOM.md; Muster vb_g6_cc_bau/bin/befehle_zuordnung.php). Die Bruecke selbst
 * (bin/befehlsbruecke.php und .sh) ist byte-gleich die Stammfassung Werkzeuge/gemeinsam/ - linieneigen ist nur diese Datei.
 *
 * Themen: <praefix>/befehl/<nr>/foto | foto_ohne_meldung | video. <nr> ist die NUMMER der Station (E1: die Zeile in der
 * Tabelle im Reiter Einstellungen, wie station=<nr> in der HTTP-Vorlage); die Grenze 1-50 prueft tun() ueber ic_station().
 *
 * Regeln (Entwurf gemeinsam 2.5): die Datei gibt ein Feld zurueck, setzt keine Konfiguration und schreibt nichts. 'tun'
 * ruft ic_bb_tun() in ic_lib.php: sie prueft synchron (Upgrade-Marke, Station, ffmpeg, laufende Aufnahme, hoechstens
 * IC_AUFTRAEGE_MAX Auftraege) und startet einen kurzen Auftragsprozess (bin/ic_auftrag.sh -> bin/ic_auftrag.php), der
 * DIESELBE Funktion wie der HTTP-Endpunkt aufruft: ic_foto_ausloesen() bzw. ic_video_starten(). Die Bruecke wartet nie
 * auf Kamera, Objekterkennung, Meldungen oder ffmpeg (E4). Quittung "uebergeben": angenommen heisst gestartet; das
 * Ergebnis steht danach in <praefix> (success, station), <praefix>/fehler und <praefix>/video.
 *
 * Haken (Hausherr 22.10.2026, Punkt 1; E2): "Befehle ueber MQTT annehmen" (befehle_mqtt_ein, ab Werk aus) schaltet die
 * Bruecke. Eingreifend ist nur "foto" - ein Foto MIT Klingel-Meldung (Ansage, Signal); ein untergeschobenes UDP-Paket
 * taeuschte sonst ein Klingeln vor. Es braucht zusaetzlich "Klingel-Meldungen ueber MQTT ausloesen"
 * (befehle_mqtt_melden, ab Werk aus); ohne ihn entsteht KEIN Foto (Entwurf 3.2). Foto ohne Meldung und Video sind frei.
 *
 * Sperrzeiten 0 s (Hausherr 22.10.2026, Punkt 2; E3): Intercom bremst selbst - Meldungen eine je 60 s und Station,
 * Aufnahmen nur eine zugleich.
 */
if (!function_exists('ic_bb_tun')) {
    $ic_bb_kand = array();
    if (basename(dirname(__DIR__)) === 'plugins') {
        $ic_bb_kand[] = dirname(dirname(dirname(__DIR__))) . '/webfrontend/htmlauth/plugins/' . basename(__DIR__) . '/ic_lib.php';
    }
    $ic_bb_kand[] = dirname(__DIR__) . '/webfrontend/htmlauth/ic_lib.php';
    foreach ($ic_bb_kand as $ic_bb_k) {
        if (is_file($ic_bb_k)) { require_once $ic_bb_k; break; }
    }
}
$ic_bb_foto = 'Intercom bremst Meldungen selbst (eine je 60 s und Station, ic_klingel_bremse_frei). Ein zweites Klingeln '
    . 'binnen einer Minute braucht ein zweites Foto (Hausherr 22.10.2026, Punkt 2).';
$ic_bb_video = 'Intercom lehnt eine zweite Aufnahme selbst ab, solange eine laeuft (Marke bis Dauer + 60 s oder bis zur '
    . 'Rueckmeldung, ic_videolauf_lesen). Eine Brueckensperre von 60 s verschluckte dagegen das Video eines zweiten '
    . 'Klingelns NACH dem Ende der ersten 10-s-Aufnahme (Entwurf 3.3, E3).';
return array(
    'fassung' => 1,
    'linie'   => 'intercom',
    'plugin'  => 'Intercom',
    'geladen' => function_exists('ic_bb_tun') && function_exists('ic_config')
                 && function_exists('ic_bb_einstellungen') && function_exists('ic_bb_protokoll'),
    'zweiter_haken' => 'befehle_mqtt_melden',
    'teile' => array('station' => '[1-9][0-9]?'),
    'einstellungen' => function () {
        return ic_bb_einstellungen();
    },
    'protokoll' => function ($stufe, $text) {
        ic_bb_protokoll($stufe, $text);
    },
    'befehle' => array(
        '{station}/foto' => array('art' => 'puls', 'eingreifend' => true, 'sperre_s' => 0, 'begruendung' => $ic_bb_foto,
            'titel' => 'Intercom Foto (MQTT)', 'kachel' => 'BEFEHL.K_FOTO', 'quittung' => 'uebergeben',
            'tun' => function ($w, $t) {
                return ic_bb_tun('foto', (string) $t['station'], '1');
            }),
        '{station}/foto_ohne_meldung' => array('art' => 'puls', 'eingreifend' => false, 'sperre_s' => 0,
            'begruendung' => $ic_bb_foto,
            'titel' => 'Intercom Foto ohne Meldung (MQTT)', 'kachel' => 'BEFEHL.K_FOTO_OHNE', 'quittung' => 'uebergeben',
            'tun' => function ($w, $t) {
                return ic_bb_tun('hand', (string) $t['station'], '1');
            }),
        '{station}/video' => array('art' => 'ganz', 'min' => 1, 'max' => 300, 'eingreifend' => false, 'sperre_s' => 0,
            'begruendung' => $ic_bb_video,
            'titel' => 'Intercom Video (MQTT)', 'kachel' => 'BEFEHL.K_VIDEO', 'quittung' => 'uebergeben',
            'tun' => function ($w, $t) {
                return ic_bb_tun('video', (string) $t['station'], (string) $w);
            }),
    ),
);

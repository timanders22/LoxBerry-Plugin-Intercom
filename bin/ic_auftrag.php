<?php
/**
 * Intercom - Auftragsprozess der Befehlsbruecke (Gruppe 7 / MQTT, 2.2.23; Entwurf 3.3, E4)
 *
 *   php ic_auftrag.php foto|hand|video <station> <wert>
 *
 * Gestartet NUR von bin/ic_auftrag.sh (geerbte Deskriptoren geschlossen, im Hintergrund). Ruft DIESELBE Funktion wie
 * der HTTP-Endpunkt - kein HTTP auf sich selbst:
 *   foto   ic_foto_ausloesen(Station, 'klingel')  (Foto mit den eingeschalteten Klingel-Meldungen)
 *   hand   ic_foto_ausloesen(Station, 'hand')     (Foto ohne Meldung)
 *   video  ic_video_starten(Station, <wert> s)
 * Kein Token: der Prozess laeuft lokal als loxberry; Pruefstelle ist die Bruecke (Haken, zweiter Haken, Art und
 * Bereich). Eine Arbeitermarke data/plugins/<ordner>/auftraege/<pid>.json zaehlt die laufenden Auftraege (hoechstens
 * IC_AUFTRAEGE_MAX); sie geht am Ende weg (register_shutdown_function). Rueckgabe 0 = gelungen, 1 = nicht, 2 = Aufruf.
 *
 * Geladen werden die LoxBerry-Bibliotheken (Zugangsdaten des Miniservers fuer die Tuerstation, LBSystem; der Port des
 * Gateways) und ic_lib.php - nicht config.php. ABWEICHUNG vom Entwurf 3.3 ("ic_lib.php direkt"): ohne
 * loxberry_system.php fehlten die Miniserver-Zugangsdaten (ic_zugangsdaten()), und jede Station ohne eigene
 * Zugangsdaten antwortete 401. Der Minutenlauf (timelapse.php) laedt sie in der Kommandozeile genauso.
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 'stderr');

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    echo "Kein Endpunkt.\n";
    exit(2);
}
$ic_a_art = isset($argv[1]) ? (string) $argv[1] : '';
$ic_a_nr = isset($argv[2]) ? (string) $argv[2] : '';
$ic_a_wert = isset($argv[3]) ? (string) $argv[3] : '';
if (!in_array($ic_a_art, array('foto', 'hand', 'video'), true) || !preg_match('/^[1-9][0-9]?\z/', $ic_a_nr)
    || !preg_match('/^[0-9]{1,3}\z/', $ic_a_wert)) {
    fwrite(STDERR, date('Y-m-d H:i:s') . " ic_auftrag.php: Aufruf falsch.\n");
    exit(2);
}

foreach (array('loxberry_system.php', 'loxberry_io.php') as $ic_a_lb) {
    if (stream_resolve_include_path($ic_a_lb) !== false) { require_once $ic_a_lb; }
}
$ic_a_kand = array();
if (basename(dirname(__DIR__)) === 'plugins') {
    $ic_a_kand[] = dirname(dirname(dirname(__DIR__))) . '/webfrontend/htmlauth/plugins/' . basename(__DIR__) . '/ic_lib.php';
}
$ic_a_kand[] = dirname(__DIR__) . '/webfrontend/htmlauth/ic_lib.php';
$ic_a_lib = '';
foreach ($ic_a_kand as $ic_a_k) {
    if (is_file($ic_a_k)) { $ic_a_lib = $ic_a_k; break; }
}
if ($ic_a_lib === '') {
    fwrite(STDERR, date('Y-m-d H:i:s') . " ic_auftrag.php: ic_lib.php nicht gefunden.\n");
    exit(1);
}
require_once $ic_a_lib;

/* Arbeitermarke - VOR der Arbeit, damit die Bruecke sie zaehlt. */
$ic_a_ordner = ic_auftrag_ordner();
if (!@is_dir($ic_a_ordner)) { @mkdir($ic_a_ordner, 0750, true); }
$ic_a_marke = $ic_a_ordner . '/' . getmypid() . '.json';
@file_put_contents($ic_a_marke, json_encode(array('pid' => getmypid(), 'art' => $ic_a_art, 'station' => $ic_a_nr,
                                                  'start' => time())));
register_shutdown_function(function () use ($ic_a_marke) {
    @unlink($ic_a_marke);
});

if (ic_upgrade_marke_liegt()) {
    ic_log('MQTT-Befehl (' . $ic_a_art . ', Station ' . $ic_a_nr . '): Aktualisierung laeuft - nichts ausgeloest.');
    exit(1);
}
$ic_a_station = ic_station($ic_a_nr);
if ($ic_a_station === null) {
    ic_log('MQTT-Befehl (' . $ic_a_art . '): Station ' . $ic_a_nr . ' gibt es nicht - nichts ausgeloest.');
    exit(1);
}
if ($ic_a_art === 'video') {
    $ic_a_s = (int) $ic_a_wert;
    if ($ic_a_s < 1 || $ic_a_s > 300) {
        ic_log('MQTT-Befehl video: Dauer ' . $ic_a_wert . ' s unzulaessig (1 bis 300) - nichts aufgenommen.');
        exit(1);
    }
    $ic_a_r = ic_video_starten($ic_a_station, $ic_a_s);
    if (!$ic_a_r['ok']) {
        ic_log('MQTT-Befehl video (Station "' . $ic_a_station['name'] . '"): ' . $ic_a_r['grund']);
    }
    exit($ic_a_r['ok'] ? 0 : 1);
}
$ic_a_r = ic_foto_ausloesen($ic_a_station, $ic_a_art === 'foto' ? 'klingel' : 'hand', false, null);
if (!$ic_a_r['ok']) {
    ic_log('MQTT-Befehl ' . ($ic_a_art === 'foto' ? 'foto' : 'foto_ohne_meldung') . ' (Station "'
        . $ic_a_station['name'] . '"): kein Bild - ' . $ic_a_r['grund']);
}
exit($ic_a_r['ok'] ? 0 : 1);

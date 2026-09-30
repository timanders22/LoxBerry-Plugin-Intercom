<?php
/**
 * Intercom - den Kamerastrom weiterreichen
 *
 * Aufruf:
 *   /plugins/<ordner>/mjpgproxy.php?token=<TOKEN>
 *   /plugins/<ordner>/mjpgproxy.php?token=<TOKEN>&station=2
 *
 * Der LoxBerry meldet sich bei der Tuerstation an; wer den Strom hier abruft,
 * braucht deren Zugangsdaten nicht. Genau deshalb verlangt diese Datei das
 * Zugriffstoken.
 */

require_once __DIR__ . '/ic_start.php';

/*
 * Zugriffspruefung - bis 1.5.0 gab es hier gar keine.
 *
 * Diese Datei reicht den Kamerastrom der Tuerstation weiter. Sie liegt im
 * unangemeldeten Bereich, also konnte JEDES Geraet im Netz die Kamera vor
 * der Haustuer mitsehen - dauerhaft und ohne Spur.
 *
 * Seit 2.2.13 (C4) eine Ausnahme: ffmpeg aus getvideo.php liest den Strom
 * ueber ?intern=1 OHNE Token - nur von 127.0.0.1, nur waehrend der laufenden
 * Aufzeichnung und nur ein einziges Mal (ic_intern_erlaubt()).
 */
if (!(isset($_GET['intern']) && ic_intern_erlaubt('proxy'))) {
    ic_token_pruefen();
    if (isset($_GET['selftest'])) {
        ic_selftest_antwort('mjpgproxy.php');
    }
}

$ic_stationsangabe = isset($_GET['station']) && is_string($_GET['station'])
                   ? substr($_GET['station'], 0, 32) : '';
$station = ic_station($ic_stationsangabe);
if ($station === null) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo ic_stationen() ? "Unbekannte Station.\n"
                        : "Es ist keine Tuerstation eingerichtet.\n";
    exit;
}

list($ic_user, $ic_pass) = ic_zugangsdaten($station);

// Die Zugangsdaten gehen als KOPFZEILE hinaus, nicht in der Adresse. Bis
// 2.1.13 wurde "http://benutzer:passwort@adresse/..." zusammengesetzt;
// gemessen mit parse_url() zerlegt ein Passwort mit '/' oder '#' die Adresse
// vollstaendig - es gibt dann nicht einmal mehr einen Rechnernamen.
$mjpeg_url = 'http://' . $station['ip'] . '/mjpg/video.mjpg';
$kopf = array('Accept-language: en');
if ($ic_user !== '') {
    $kopf[] = 'Authorization: Basic ' . base64_encode($ic_user . ':' . $ic_pass);
}
/* BERICHTIGT 2.2.13 (C5): keiner Umleitung folgen. Der Stream-Wrapper schickte
 * die Kopfzeile Authorization bis 2.2.12 auch an das Umleitungsziel -
 * gemessen: die Zugangsdaten der Station kamen bei einem fremden Rechner an. */
$opts = array('http' => array(
    'method'  => 'GET',
    'timeout' => 10,
    'follow_location' => 0,
    'header'  => implode("\r\n", $kopf),
));
$context = stream_context_create($opts);

/*
 * Keine Zeitgrenze fuer diesen Prozess, keine Pufferung.
 *
 * apache_setenv() gibt es NUR im SAPI apache2handler. Bis 2.1.13 stand der
 * Aufruf hier ungeschuetzt mit einem vorangestellten @ - und das @
 * unterdrueckt die Anzeige, nicht den Fehler: eine Funktion, die es nicht
 * gibt, ist ein Error und beendet den Lauf. Gemessen unter 7.4.33 und 8.4.24:
 * Rueckgabewert 255, keine Ausgabe, HTTP 500 mit leerem Rumpf. Auf einem
 * LoxBerry mit php-fpm statt mod_php waeren damit Live-Bild UND
 * Videoaufzeichnung ausgefallen, ohne dass irgendwo etwas dazu stuende.
 */
set_time_limit(0);
if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', 1); }
@ini_set('zlib.output_compression', 0);
@ini_set('output_buffering', 'off');
while (ob_get_level() > 0) { @ob_end_flush(); }
ignore_user_abort(false);

$fp = @fopen($mjpeg_url, 'r', false, $context);
if ($fp) {
    /* C5: nur eine Antwort 200 wird weitergereicht - eine Umleitung (die hier
     * nicht verfolgt wird) oder ein anderer Code endet im Ersatzbild. */
    $ic_status = ic_status_aus_kopf(ic_strom_kopfzeilen($fp));
    if ($ic_status !== 0 && $ic_status !== 200) {
        ic_log_gebremst('proxy_status_' . $station['name'], 'Der Kamerastrom von "'
            . $station['name'] . '" antwortete mit HTTP ' . $ic_status
            . ' - es wurde das Ersatzbild ausgeliefert.');
        fclose($fp);
        $fp = false;
    }
}
if ($fp) {
    // Fix v1.4.0: echten Content-Type der Kamera inkl. Boundary weiterreichen.
    // Die alte Fassung sendete fest boundary=athene, die Kamera nutzt aber
    // eine eigene Boundary - Browser warten dann endlos und zeigen kein Bild.
    $contenttype = 'multipart/x-mixed-replace';
    // C7 (seit 2.2.13): die Kopfzeilen ueber stream_get_meta_data() - die
    // lokale Wrapper-Variable ist unter PHP 8.5 veraltet (Bauart A).
    foreach (ic_strom_kopfzeilen($fp) as $h) {
        if (is_string($h) && stripos($h, 'Content-Type:') === 0) {
            $contenttype = trim(substr($h, 13));
        }
    }
    header('Cache-Control: no-cache, private');
    header('Pragma: no-cache');
    header('Content-Type: ' . $contenttype);

    // Weiterreichen mit flush statt fpassthru, damit die Rahmen sofort beim
    // Browser ankommen und ein Abbruch des Lesers das Skript beendet.
    stream_set_timeout($fp, 15);
    while (!feof($fp) && !connection_aborted()) {
        $chunk = fread($fp, 8192);
        if ($chunk === false || $chunk === '') {
            $meta = stream_get_meta_data($fp);
            if (!empty($meta['timed_out'])) { break; }
            continue;
        }
        echo $chunk;
        flush();
    }
    fclose($fp);
} else {
    // Die Station antwortet nicht - Ersatzbild ausliefern und das EINMAL
    // je Stunde ins Protokoll schreiben. Bis 2.1.13 blieb dieser Fall
    // vollstaendig stumm.
    ic_log_gebremst('proxy_' . $station['name'],
        'Der Kamerastrom von "' . $station['name'] . '" (' . $station['ip']
        . ') war nicht erreichbar - es wurde das Ersatzbild ausgeliefert.');
    $d = @file_get_contents(__DIR__ . '/offline.jpg');
    if ($d === false) { $d = ''; }

    header('Content-Type: image/jpeg');
    header('Content-Length: ' . strlen($d));
    header('Cache-Control: no-cache, private');
    header('Pragma: no-cache');

    echo $d;
}

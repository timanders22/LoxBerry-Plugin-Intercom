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
 *
 * Grenzen (NEU, Intercom-a1, Entscheidung 16): ein Abruf endet nach
 * hoechstens 600 s; hoechstens 3 Leser gleichzeitig. Der naechste bekommt
 * HTTP 503 mit Retry-After: 30 und dem Grund im Rumpf und in der Kopfzeile
 * X-Intercom-Grund (LESER_VOLL bzw. SPERRE); ins Protokoll einmal je Stunde.
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
$ic_intern = isset($_GET['intern']) && ic_intern_erlaubt('proxy');
if (!$ic_intern) {
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

/*
 * Leserplatz (NEU, Intercom-a1). Erst NACH Token- und Stationspruefung: ein
 * abgewiesener Aufruf ohne Token legt nichts an. 503 statt 429: die Grenze
 * betrifft die Last aller Leser zusammen, nicht das Verhalten eines Abrufers.
 * Die Aufzeichnung (intern) belegt keinen Platz.
 */
list($ic_lv_s, $ic_lv_max) = ic_livebild_grenzen();
$ic_platz = null;
if (!$ic_intern) {
    list($ic_platz, $ic_platznr, $ic_lv_grund) = ic_livebild_platz();
    if ($ic_platz === false) {
        // Der Pfad steht nur im Protokoll, nie in der Antwort.
        $ic_lv_ort = '';
        if ($ic_lv_grund === 'voll') {
            $ic_lv_text = 'Livebild abgewiesen: es sind schon ' . $ic_lv_max
                . ' Leser gleichzeitig verbunden (hoechstens ' . $ic_lv_max . ').';
        } else {
            $ic_lv_text = 'Livebild abgewiesen: die Sperrdatei fuer die Leserplaetze '
                . 'liess sich nicht oeffnen.';
            $ic_lv_ort = ' Ordner: ' . dirname(ic_sperre_datei('livebild1')) . '.';
        }
        ic_log_gebremst('livebild_' . $ic_lv_grund, $ic_lv_text . $ic_lv_ort . ' Station "'
            . $station['name'] . '". Weitere Abweisungen innerhalb einer Stunde '
            . 'stehen nicht im Protokoll.');
        header('HTTP/1.1 503 Service Unavailable');
        header('Retry-After: 30');
        header('Cache-Control: no-cache, private');
        header('X-Intercom-Grund: ' . ($ic_lv_grund === 'voll' ? 'LESER_VOLL' : 'SPERRE'));
        header('Content-Type: text/plain; charset=utf-8');
        echo $ic_lv_text, ' Bitte in 30 s erneut versuchen.', chr(10);
        exit;
    }
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
    header('X-Intercom-Livebild-Max-S: ' . $ic_lv_s);

    // Weiterreichen mit flush statt fpassthru, damit die Rahmen sofort beim
    // Browser ankommen und ein Abbruch des Lesers das Skript beendet.
    stream_set_timeout($fp, 15);
    // Intercom-a1: hoechstens $ic_lv_s Sekunden je Abruf, dann endet der Strom.
    $ic_lv_ende = time() + $ic_lv_s;
    $ic_lv_abgelaufen = false;
    while (!feof($fp) && !connection_aborted()) {
        if (time() >= $ic_lv_ende) { $ic_lv_abgelaufen = true; break; }
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
    if ($ic_lv_abgelaufen) {
        ic_log_gebremst('livebild_frist', 'Ein Livebild-Abruf von "' . $station['name']
            . '" wurde nach ' . $ic_lv_s . ' s beendet (Grenze je Abruf; neu laden '
            . 'beginnt einen neuen Abruf). Weitere innerhalb einer Stunde stehen nicht im Protokoll.');
    }
    ic_sperre_frei($ic_platz);
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

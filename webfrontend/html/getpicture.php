<?php
/**
 * Intercom - ein Bild von der Tuerstation holen
 *
 * Aufruf (vom Miniserver):
 *   /plugins/<ordner>/getpicture.php?token=<TOKEN>
 *   /plugins/<ordner>/getpicture.php?token=<TOKEN>&trigger=klingel
 *   /plugins/<ordner>/getpicture.php?token=<TOKEN>&station=2
 *   /plugins/<ordner>/getpicture.php?token=<TOKEN>&selftest=1   (loest nichts aus)
 */

require_once __DIR__ . '/ic_start.php';

// Diese Datei loest etwas aus (Archivbild, MQTT, Webhooks) und liest den
// Kamerastrom mit. Bis 1.5.0 ging das ohne jede Pruefung - sie liegt im
// unangemeldeten Bereich.
ic_token_pruefen();

// Der Selbsttest steht VOR allem, was wirkt: er soll nichts ausloesen.
if (isset($_GET['selftest'])) {
    ic_selftest_antwort('getpicture.php');
}

header('Content-Type: application/json; charset=utf-8');

/* ---------------- Station ---------------- */
$ic_stationsangabe = isset($_GET['station']) && is_string($_GET['station'])
                   ? substr($_GET['station'], 0, 32) : '';
$station = ic_station($ic_stationsangabe);
if ($station === null) {
    // Abweisen, nicht zurechtbiegen: waere hier die erste Station genommen
    // worden, haette ein Tippfehler in Loxone stillschweigend die falsche
    // Tuer fotografiert.
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(array('success' => false, 'error' => ic_stationen()
        ? 'Unbekannte Station: ' . $ic_stationsangabe
        : 'Es ist keine Tuerstation eingerichtet. Bitte im Reiter Einstellungen eintragen.'));
    exit;
}

/* ---------------- Ausloeser ---------------- */
// is_string VOR allem anderen: ?trigger[]=x ergibt ein Feld, und substr()
// bricht damit unter PHP 8 mit einem TypeError ab - mitten im Klingelweg,
// nach dem Kamerazugriff und vor Archiv, MQTT und Protokoll.
$trigger = '';
if (isset($_GET['trigger']) && is_string($_GET['trigger'])) {
    $trigger = preg_replace('/[^A-Za-z0-9_\-]/', '', substr($_GET['trigger'], 0, 32));
}

$arr = ic_config();
$nur_vorschau = isset($_GET['hook']);   // ?hook=false: die Oberflaeche schaut nur nach

/* ---------------- Bild holen ---------------- */
$r = ic_bild_holen($station);
if (!$r['ok']) {
    // Gebremst: bei einer ausgeschalteten Station kaeme sonst bei jedem
    // Klingeln eine Zeile, und log/ ist eine Ramdisk.
    ic_log_gebremst('station_' . $station['name'],
        'Die Tuerstation "' . $station['name'] . '" lieferte kein Bild: ' . $r['fehler']);
    header('HTTP/1.1 502 Bad Gateway');
    echo json_encode(array('success' => false, 'station' => $station['name'],
                           'weg' => $r['weg'], 'error' => $r['fehler']));
    exit;
}
$frame = $r['bild'];

if (!$nur_vorschau) { ic_archiv_sicherstellen(); }

/* ---------------- Das jeweils letzte Bild ---------------- */
/*
 * Es liegt an zwei Orten:
 *
 *   data/plugins/<ordner>/lastpicture.jpg   immer, NICHT aus dem Netz erreichbar;
 *                                           ausgeliefert wird es von bild.php,
 *                                           und das verlangt das Token.
 *   webfrontend/html/plugins/<ordner>/lastpicture.jpg
 *                                           nur, solange "bild_oeffentlich"
 *                                           eingeschaltet ist.
 *
 * Warum die offene Kopie ab Werk bleibt: sie ist seit jeher dokumentiert, und
 * bestehende Anlagen binden sie in Loxone oder in einer Mail ein. Ein
 * Vorgabewert, der das abschaltet, wuerde bei jedem Anwender beim ersten
 * Aufruf nach dem Update ein Bild verschwinden lassen, ohne dass er etwas
 * angeklickt hat. Der Reiter Einstellungen sagt, was die offene Kopie
 * bedeutet, und schaltet sie mit einem Haken ab.
 */
$ic_intern = ic_paths()['data'] . '/lastpicture.jpg';
ic_datei_ersetzen($ic_intern, $frame, 0640);
$ic_offen = !isset($arr['bild_oeffentlich']) || $arr['bild_oeffentlich'] !== '0';
$lastpicture = __DIR__ . '/lastpicture.jpg';
if ($ic_offen) {
    ic_datei_ersetzen($lastpicture, $frame);
} elseif (@is_file($lastpicture)) {
    @unlink($lastpicture);
}

// Zeitstempel: erst nach dem unteilbaren Schreiben, und nur wenn php-gd da ist.
$mit_stempel = isset($arr['timestamp_image']) && $arr['timestamp_image'] === 'on';
if ($mit_stempel) {
    ic_zeitstempel_ins_bild($ic_intern);
    if ($ic_offen) { ic_zeitstempel_ins_bild($lastpicture); }
}

/* ---------------- Archiv ---------------- */
$archiviert = false;
$archivhinweis = '';
$archivname = '';
if (!$nur_vorschau) {
    $o = ic_archivordner();
    // Der Name traegt Datum, Uhrzeit und den Ausloeser - und KEINE
    // Doppelpunkte: auf FAT32, exFAT und NTFS sind sie im Dateinamen
    // unzulaessig, und genau dorthin zeigt der einstellbare Speicherort.
    $archivname = ic_archivname(($station['name'] !== '' && count(ic_stationen()) > 1
                                 ? preg_replace('/[^A-Za-z0-9_\-]/', '', $station['name']) . '-' : '')
                                . $trigger);
    $ziel = $o['bild'] . $archivname;
    if (ic_datei_ersetzen($ziel, $frame)) {
        $archiviert = true;
        if ($mit_stempel) { ic_zeitstempel_ins_bild($ziel); }
    } else {
        $archivhinweis = 'Archivbild konnte nicht geschrieben werden: ' . $ziel;
        ic_log_gebremst('archiv', 'Ein Archivbild liess sich nicht schreiben: ' . $ziel
            . ' - Rechte und freien Platz pruefen.');
    }
} else {
    $archivhinweis = 'Nicht archiviert: Aufruf mit ?hook-Parameter (nur Vorschau).';
}

/* ---------------- KI-Objekterkennung ---------------- */
// Die Erkennung selbst steht in ic_lib.php: sie wird auch von der Aufnahme im
// Takt gebraucht, und zwei Kopien derselben Auswertung laufen beim naechsten
// Umbau auseinander.
$ai = ic_ki_erkennen($ic_intern);

/* ---------------- Antwort ---------------- */
$basis = 'http://' . ic_host() . '/plugins/' . ic_plugin_ordner() . '/';
$token = isset($arr['aktionstoken']) ? (string) $arr['aktionstoken'] : '';
$bildurl = $ic_offen ? $basis . 'lastpicture.jpg'
                     : $basis . 'bild.php?token=' . rawurlencode($token);
$json = json_encode(array(
    'success'      => true,
    'timestamp'    => date('d.m.Y-H:i:s'),
    'station'      => $station['name'],
    'weg'          => $r['weg'],
    'dauer'        => round($r['dauer'], 2),
    'trigger'      => $trigger,
    'ai'           => $ai,
    'archived'     => $archiviert,
    'archive_file' => $archivname,
    'archive_info' => $archivhinweis,
    'image'        => $bildurl,
));
echo $json;

/*
 * Ab hier wartet der Miniserver nicht mehr mit.
 *
 * Der Klingelweg summierte bis 2.1.13 im ungünstigen Fall acht Sekunden
 * Strom, fuenf Sekunden Anzeigegeraet und dreimal fuenf Sekunden Webhook -
 * und die Verbindung blieb bis zum Skriptende offen. Die Antwort steht
 * jetzt, sobald das Bild da ist; alles Weitere laeuft danach.
 */
if (!$nur_vorschau) {
    @ignore_user_abort(true);
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    } else {
        while (ob_get_level() > 0) { @ob_end_flush(); }
        @flush();
    }
}

ic_log('Bild von "' . $station['name'] . '" geholt (' . $r['weg'] . ', '
    . ic_byte(strlen($frame)) . ')'
    . ($trigger !== '' ? ', Ausloeser ' . $trigger : '')
    . ($archiviert ? ', archiviert als ' . $archivname : ''));

// Bei ?hook ist Schluss - die Oberflaeche wollte nur nachsehen.
if ($nur_vorschau) { exit; }

/* ---------------- Bild an ein Anzeigegeraet ---------------- */
// App "Notifications for Android TV", Port 7676
if (!empty($arr['tv_enable']) && $arr['tv_enable'] === 'on'
    && !empty($arr['tv_ip']) && function_exists('curl_init')) {
    $tvport = (isset($arr['tv_port']) && is_numeric($arr['tv_port'])) ? (int) $arr['tv_port'] : 7676;
    $tvmsg = $trigger !== '' ? 'Ausloeser: ' . $trigger : 'Jemand hat geklingelt';
    if (count(ic_stationen()) > 1) { $tvmsg .= ' (' . $station['name'] . ')'; }
    $ch = curl_init('http://' . $arr['tv_ip'] . ':' . $tvport . '/');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array(
        'type' => '0', 'title' => 'Loxone Intercom', 'msg' => $tvmsg,
        'duration' => '10', 'fontsize' => '0', 'position' => '0',
        'bkgcolor' => '#009688', 'transparency' => '0', 'offset' => '0',
        'app' => 'intercom', 'force' => 'true', 'interrupt' => '0',
        'filename' => new CURLFile($ic_intern, 'image/jpeg', 'lastpicture.jpg'),
    ));
    @curl_exec($ch);
    curl_close($ch);
}

/* ---------------- MQTT ueber das LoxBerry-Gateway (UDP) ---------------- */
//
// Die Bibliothek Bluerhinos\phpMQTT ist seit Jahren unveraendert, baut eine
// eigene TCP-Verbindung samt Anmeldung auf und war bis 1.5.0 der einzige Weg -
// faellt sie unter PHP 8 aus, meldet das Plugin gar nichts mehr. Das Gateway
// gehoert seit LoxBerry 3 zum System und nimmt eine Zeile auf einem UDP-Port
// entgegen. Ein Paket, keine Verbindung, keine fremde Bibliothek.
//
// Das Themen-Praefix kommt aus dem Ordnernamen, nicht fest aus dem Quelltext.
if (ic_mqtt_an()) {
    ic_mqtt_senden('', $json);
    if ($trigger !== '') {
        ic_mqtt_senden('trigger/' . $trigger, $json);
    }
    ic_ki_melden($ai);
    ic_mqtt_herzschlag();
}

/* ---------------- Webhooks ---------------- */
$jsonarr = json_decode($json, true);

// 1 und 3: POST mit JSON
foreach (array(1, 3) as $nr) {
    if (empty($arr['webhook' . $nr]) || !function_exists('curl_init')) { continue; }
    $ch = curl_init($arr['webhook' . $nr]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Zeitgrenzen: ohne sie wartet cURL unbegrenzt.
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    @curl_exec($ch);
    curl_close($ch);
}

// 2 und 4: Adresse mit Platzhalter <imgurl>
foreach (array(2, 4) as $nr) {
    if (empty($arr['webhook' . $nr])) { continue; }
    $url = str_replace('<imgurl>', urlencode($jsonarr['image']), $arr['webhook' . $nr]);
    // MIT Zeitgrenze. Bis 1.5.0 stand hier file_get_contents($url) ohne
    // Zusammenhang - PHP nahm dann default_socket_timeout, ueblicherweise
    // 60 Sekunden. Ein abgeschalteter Node-RED genuegte, damit der Aufruf
    // eine Minute stillstand, und der Miniserver wartete mit.
    ic_http_holen($url, 5);
}

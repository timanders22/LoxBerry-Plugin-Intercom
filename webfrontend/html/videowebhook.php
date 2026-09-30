<?php
/**
 * Intercom - Rueckmeldung nach abgeschlossener Videoaufzeichnung
 *
 * Wird von getvideo.php aufgerufen, sobald ffmpeg fertig ist. Meldet die
 * fertige Datei per MQTT und ueber die Webhooks weiter.
 *
 * Aufruf: /plugins/<ordner>/videowebhook.php?token=<TOKEN>&file=<Dateiname>
 */

require_once __DIR__ . '/ic_start.php';

/* C4 (seit 2.2.13): die Rueckmeldung aus getvideo.php kommt OHNE Token -
 * nur von 127.0.0.1, nur fuer die Datei der laufenden Aufzeichnung. Jeder
 * andere Aufruf braucht das Token wie bisher. */
$ic_datei_arg = (isset($_GET['file']) && is_string($_GET['file'])) ? basename($_GET['file']) : '';
$ic_intern = isset($_GET['intern']) && ic_intern_erlaubt('hook', $ic_datei_arg);
if (!$ic_intern) {
    ic_token_pruefen();
    if (isset($_GET['selftest'])) {
        ic_selftest_antwort('videowebhook.php');
    }
}

header('Content-Type: application/json; charset=utf-8');

/*
 * Der Dateiname wird GEPRUEFT, nicht uebernommen.
 *
 * Bis 1.5.0 stand hier schlicht $file = $_REQUEST['file']; und der Wert
 * ging unveraendert in die JSON-Antwort, in die MQTT-Meldung und in die
 * Adresse des zweiten Webhooks. Damit liess sich alles einschleusen, was
 * der Empfaenger dieser Meldung fuer eine Dateiadresse hielt - eine
 * fremde Adresse etwa, auf die dann geklickt wird.
 *
 * Drei Stufen:
 *   1. basename() entfernt jeden Pfadanteil (../../ und /etc/passwd),
 *   2. ein Muster laesst nur die Zeichen zu, die getvideo.php selbst
 *      vergibt: Buchstaben, Ziffern, Bindestrich, Unterstrich, Punkt,
 *   3. und zuletzt muss die Datei im Archiv WIRKLICH EXISTIEREN. Damit
 *      ist ausgeschlossen, dass eine Meldung ueber etwas verschickt wird,
 *      das es gar nicht gibt.
 */
$roh = isset($_GET['file']) ? $_GET['file'] : (isset($_POST['file']) ? $_POST['file'] : '');
$file = is_string($roh) ? basename($roh) : '';
if ($file === '' || !preg_match('/^[A-Za-z0-9._-]{1,128}$/', $file)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(array('success' => false, 'error' => 'Ungueltiger Dateiname.'));
    exit;
}
$o = ic_archivordner();

/* C3 (seit 2.2.13): der Rueckgabewert von ffmpeg aus dem Hintergrundlauf. */
$ic_rc = null;
$ic_rcdatei = ic_paths()['datadir'] . '/.video_rc_' . $file;
if (@is_file($ic_rcdatei)) {
    $ic_t = trim((string) @file_get_contents($ic_rcdatei));
    if (preg_match('/^[0-9]{1,3}$/', $ic_t)) { $ic_rc = (int) $ic_t; }
    @unlink($ic_rcdatei);
}
if ($ic_intern) { ic_videolauf_ende($file); }

if (!is_file($o['video'] . $file)) {
    /* BERICHTIGT 2.2.13 (C3): bis 2.2.12 antwortete dieser Zweig ohne eine
     * Protokollzeile - eine gescheiterte Aufnahme blieb spurlos. */
    ic_log_gebremst('video_fehlt', 'Die Videoaufzeichnung ist gescheitert: ' . $file
        . ' liegt nicht im Archiv' . ($ic_rc !== null ? ' (ffmpeg endete mit Rueckgabe ' . $ic_rc . ')' : '')
        . '.');
    header('HTTP/1.1 404 Not Found');
    echo json_encode(array('success' => false,
        'error' => 'Die Aufzeichnung ist gescheitert - die Datei liegt nicht im Archiv.',
        'name' => $file, 'ffmpeg_rc' => $ic_rc));
    exit;
}
if ($ic_rc !== null && $ic_rc !== 0) {
    ic_log('Videoaufzeichnung ' . $file . ': ffmpeg endete mit Rueckgabe ' . $ic_rc
        . ' - die Datei ist womoeglich unvollstaendig.');
}

$arr = ic_config();
$groesse = (int) @filesize($o['video'] . $file);

$www_folder = str_replace(ic_paths()['home'] . '/webfrontend', '', $o['video']);
$dateiurl = 'http://' . ic_host() . $www_folder . rawurlencode($file);

$json = json_encode(array(
    'success'   => true,
    'timestamp' => date('d.m.Y-H:i:s'),
    'file'      => $dateiurl,
    'name'      => $file,
    'size'      => $groesse,
    'ffmpeg_rc' => $ic_rc,
));
echo $json;
$jsonarr = json_decode($json, true);

ic_log('Videoaufzeichnung abgeschlossen: ' . $file . ' (' . ic_byte($groesse) . ')');

/* ---------------- MQTT ---------------- */
// Ueber das LoxBerry-Gateway (UDP) statt ueber Bluerhinos\phpMQTT - siehe
// die ausfuehrliche Begruendung in getpicture.php. Das Praefix kommt aus dem
// Ordnernamen.
if (ic_mqtt_an()) {
    ic_mqtt_senden('video', $json);
    ic_mqtt_herzschlag();
}

/* ---------------- Webhook 1 (POST mit JSON) ---------------- */
if (!empty($arr['videowebhook1']) && !function_exists('curl_init')) {
    /* BERICHTIGT 2.2.6: bis 2.2.5 sprang der Zweig kommentarlos ab. */
    ic_log_gebremst('curl_videowebhook', 'Der Video-Webhook ist eingetragen, aber die '
        . 'PHP-Erweiterung curl fehlt - es wird nichts aufgerufen. '
        . 'Abhilfe: sudo apt install php-curl');
}
if (!empty($arr['videowebhook1']) && function_exists('curl_init')) {
    $ch = curl_init($arr['videowebhook1']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Zeitgrenzen: ohne sie wartet cURL unbegrenzt.
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    $ic_antwort = @curl_exec($ch);
    $ic_wcode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ic_wfehler = curl_error($ch);
    if (PHP_VERSION_ID < 80000) { curl_close($ch); }
    // M5 (seit 2.2.13): ausgewertet und gebremst protokolliert, nicht wiederholt.
    ic_webhook_pruefen('Video-Webhook 1', $ic_antwort !== false, $ic_wcode, $ic_wfehler);
}

/* ---------------- Webhook 2 (Adresse mit Platzhalter) ---------------- */
if (!empty($arr['videowebhook2'])) {
    $url = str_replace('<fileurl>', urlencode($jsonarr['file']), $arr['videowebhook2']);
    // MIT Zeitgrenze - bis 1.5.0 stand hier file_get_contents($url) ohne
    // Zusammenhang, und ein nicht antwortender Empfaenger hielt den
    // Aufruf bis zu einer Minute fest.
    list($ic_winhalt, $ic_wcode, $ic_wfehler) = ic_http_holen_voll($url, 5);
    ic_webhook_pruefen('Video-Webhook 2', $ic_winhalt !== false, $ic_wcode, $ic_wfehler);
}

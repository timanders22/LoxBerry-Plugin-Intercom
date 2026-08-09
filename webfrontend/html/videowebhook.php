<?php
/**
 * intercom22lox - Rueckmeldung nach abgeschlossener Videoaufzeichnung
 *
 * Wird von getvideo.php aufgerufen, sobald ffmpeg fertig ist. Meldet die
 * fertige Datei per MQTT und ueber die Webhooks weiter.
 *
 * Aufruf: /plugins/<ordner>/videowebhook.php?token=<TOKEN>&file=<Dateiname>
 */

require_once dirname(dirname(__DIR__)) . '/htmlauth/plugins/'
           . basename(__DIR__) . '/config.php';

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

ic_token_pruefen();

header('Content-type:application/json;charset=utf-8');

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
 *      vergibt: Ziffern, Bindestrich, Unterstrich, Punkt,
 *   3. und zuletzt muss die Datei im Archiv WIRKLICH EXISTIEREN. Damit
 *      ist ausgeschlossen, dass eine Meldung ueber etwas verschickt wird,
 *      das es gar nicht gibt.
 */
$file = isset($_REQUEST['file']) ? basename((string) $_REQUEST['file']) : '';
if ($file === '' || !preg_match('/^[A-Za-z0-9._-]{1,128}$/', $file)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(array('success' => false, 'error' => 'Ungueltiger Dateiname.'));
    exit;
}
if (!is_file($folder_video_archive . $file)) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(array('success' => false, 'error' => 'Datei nicht im Archiv.'));
    exit;
}

$arr = ic_config();

$www_folder = str_replace(ic_paths()['home'] . '/webfrontend', '', $folder_video_archive);
$dateiurl = 'http://' . ic_host() . $www_folder . rawurlencode($file);

$json = json_encode(array(
    'success'   => true,
    'timestamp' => date('d.m.Y-H:i:s'),
    'file'      => $dateiurl,
));
echo $json;
$jsonarr = json_decode($json, true);

/* ---------------- MQTT ---------------- */
// Ueber das LoxBerry-Gateway (UDP) statt ueber Bluerhinos\phpMQTT - siehe
// die ausfuehrliche Begruendung in getpicture.php.
if (isset($arr['mqtt_enable']) && $arr['mqtt_enable'] == '1') {
    ic_mqtt_senden('intercom22loxvideo', $json);
}

/* ---------------- Webhook 1 (POST mit JSON) ---------------- */
if (!empty($arr['videowebhook1']) && function_exists('curl_init')) {
    $ch = curl_init($arr['videowebhook1']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Zeitgrenzen: ohne sie wartet cURL unbegrenzt.
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    @curl_exec($ch);
    curl_close($ch);
}

/* ---------------- Webhook 2 (Adresse mit Platzhalter) ---------------- */
if (!empty($arr['videowebhook2'])) {
    $url = str_replace('<fileurl>', urlencode($jsonarr['file']), $arr['videowebhook2']);
    // MIT Zeitgrenze - bis 1.5.0 stand hier file_get_contents($url) ohne
    // Zusammenhang, und ein nicht antwortender Empfaenger hielt den
    // Aufruf bis zu einer Minute fest.
    ic_http_holen($url, 5);
}

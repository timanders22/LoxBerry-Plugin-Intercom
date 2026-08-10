<?php
/**
 * Intercom - automatische Archiv-Bereinigung.
 *
 * Wird taeglich per Cron aufgerufen. Loescht in den Archiven (Bilder, Videos,
 * Timelapse) Dateien, die aelter als "cleanup_days" Tage sind, und behaelt
 * hoechstens "cleanup_count" Dateien je Archiv (die neuesten bleiben).
 * Beide Werte sind optional - leer/0 = keine Begrenzung.
 */

/* Die Bibliothek liegt im ANGEMELDETEN Bereich, diese Datei nicht. Der Weg
 * dorthin sieht in den beiden Zustaenden verschieden aus:
 *
 *   installiert  <home>/webfrontend/html/plugins/<ordner>/diese-datei.php
 *                <home>/webfrontend/htmlauth/plugins/<ordner>/config.php
 *   Archiv       <wurzel>/webfrontend/html/diese-datei.php
 *                <wurzel>/webfrontend/htmlauth/config.php
 *
 * Bis 2.1.1 stand hier ein fester Ausdruck mit ZWEI dirname(). Installiert
 * ergab der <home>/webfrontend/html/htmlauth/plugins/<ordner>/config.php -
 * ein Verzeichnis, das es nicht gibt. require_once auf eine fehlende Datei
 * ist ein Fatal Error, und weil display_errors erst danach abgeschaltet
 * wurde, kam nicht einmal eine lesbare Meldung zurueck, sondern HTTP 500.
 *
 * Deshalb eine Kandidatenliste statt einer Rechnung: genommen wird die
 * Datei, die wirklich da ist. */
$ic_config_gefunden = false;
foreach (array(
    dirname(dirname(dirname(__DIR__))) . '/htmlauth/plugins/' . basename(__DIR__) . '/config.php',
    dirname(dirname(__DIR__)) . '/htmlauth/plugins/' . basename(__DIR__) . '/config.php',
    dirname(__DIR__) . '/htmlauth/config.php',
) as $ic_kandidat) {
    if (is_file($ic_kandidat)) {
        require_once $ic_kandidat;
        $ic_config_gefunden = true;
        break;
    }
}
if (!$ic_config_gefunden) {
    /* Sagen, was fehlt, statt mit 500 zu enden. Diese Datei wird von Loxone
     * und von der Tuerstation aufgerufen - dort sieht niemand ein
     * Apache-Protokoll. */
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo "FEHLER: config.php des Plugins wurde nicht gefunden.\n";
    echo "Gesucht ausgehend von: " . __DIR__ . "\n";
    echo "Bitte das Plugin neu installieren.\n";
    exit;
}

/*
 * Nur ueber die Kommandozeile (Cron), nicht ueber HTTP.
 *
 * Diese Datei liegt unter webfrontend/html/ und waere damit fuer jeden im
 * Netz aufrufbar. Sie loest Arbeit aus (Aufnahme bzw. Loeschen im Archiv)
 * und hat im Web nichts verloren. php-cli setzt PHP_SAPI auf "cli";
 * ueber den Apache steht dort etwas anderes.
 */
if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Dieses Skript laeuft nur ueber den Cron, nicht ueber HTTP.\n";
    exit;
}


if (!file_exists(LBPCONFIGDIR.'/data.json')) { exit; }
$arr = json_decode(file_get_contents(LBPCONFIGDIR.'/data.json'), true);
if (!is_array($arr)) { exit; }

$days  = (isset($arr['cleanup_days'])  && is_numeric($arr['cleanup_days']))  ? (int)$arr['cleanup_days']  : 0;
$count = (isset($arr['cleanup_count']) && is_numeric($arr['cleanup_count'])) ? (int)$arr['cleanup_count'] : 0;
if ($days <= 0 && $count <= 0) { exit; }

$deleted = 0;

function intercom_cleanup_dir($dir, $patterns, $days, $count, &$deleted) {
    $files = array();
    foreach ($patterns as $pat) {
        $found = glob($dir.$pat);
        if (is_array($found)) { $files = array_merge($files, $found); }
    }
    if (!$files) { return; }
    // Nach Aenderungszeit sortieren (neueste zuerst)
    usort($files, function ($a, $b) { return filemtime($b) - filemtime($a); });

    $now = time();
    foreach ($files as $i => $f) {
        $tooOld = ($days > 0 && ($now - filemtime($f)) > $days * 86400);
        $tooMany = ($count > 0 && $i >= $count);
        if ($tooOld || $tooMany) {
            if (@unlink($f)) { $deleted++; }
        }
    }
}

intercom_cleanup_dir($folder_img_archive,   array('*.jpg'),           $days, $count, $deleted);
intercom_cleanup_dir($folder_video_archive, array('*.avi', '*.jpg'),  $days, $count > 0 ? $count * 2 : 0, $deleted); // Video + Thumbnail
intercom_cleanup_dir($folder_timelapse,     array('*.jpg'),           $days, $count, $deleted);

echo "OK: $deleted Datei(en) bereinigt\n";

<?php
/**
 * Intercom - automatische Archiv-Bereinigung.
 *
 * Wird taeglich per Cron aufgerufen. Loescht in den Archiven (Bilder, Videos,
 * Timelapse) Dateien, die aelter als "cleanup_days" Tage sind, und behaelt
 * hoechstens "cleanup_count" Dateien je Archiv (die neuesten bleiben).
 * Beide Werte sind optional - leer/0 = keine Begrenzung.
 */

require_once dirname(dirname(__DIR__)) . '/htmlauth/plugins/'
           . basename(__DIR__) . '/config.php';

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

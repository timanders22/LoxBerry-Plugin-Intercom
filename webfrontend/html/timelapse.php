<?php
/* ---- Sperre gegen Parallellaeufe (Muster fer_sperre, FerienFeiertage) ----
 *
 * Der Bildabruf von der Kamera wartet auf ein Netz. Dauert der Lauf laenger als der Cron-Takt,
 * startet der naechste, waehrend dieser noch laeuft: doppelte Abrufe,
 * doppelte Meldungen, im schlimmsten Fall zwei Schreibvorgaenge auf dieselbe
 * Datei. Die Sperre ist nicht blockierend - wer nicht drankommt, geht
 * kommentarlos wieder (der naechste Takt kommt ohnehin gleich).
 */
$ic_sperrdatei = sys_get_temp_dir() . '/ic_cron.lock';
$ic_sperre = @fopen($ic_sperrdatei, 'c');
if ($ic_sperre === false || !flock($ic_sperre, LOCK_EX | LOCK_NB)) {
    exit(0);
}

/**
 * Intercom - Timelapse: jeden Tag ein Foto zu einer festen Uhrzeit.
 *
 * Wird minuetlich per Cron aufgerufen und beendet sich sofort, wenn die
 * Funktion deaktiviert ist oder die konfigurierte Uhrzeit (HH:MM) nicht der
 * aktuellen Minute entspricht. Die Fotos landen im Ordner "timelapse" des
 * Archiv-Speicherorts (Dateiname = Datum) und lassen sich spaeter z.B. mit
 * ffmpeg zu einem Zeitraffer-Video zusammensetzen:
 *   ffmpeg -pattern_type glob -i 'timelapse/*.jpg' -r 10 zeitraffer.mp4
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


$miniserver_config = LBSystem::get_miniservers();

if (!file_exists(LBPCONFIGDIR.'/data.json')) { exit; }
$arr = json_decode(file_get_contents(LBPCONFIGDIR.'/data.json'), true);
if (!is_array($arr)) { exit; }

if (!isset($arr['timelapse_enable']) || $arr['timelapse_enable'] != "on") { exit; }

$tltime = isset($arr['timelapse_time']) ? trim($arr['timelapse_time']) : '';
if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $tltime, $m)) { exit; }
$soll = sprintf('%02d:%02d', $m[1], $m[2]);
if (date('H:i') !== $soll) { exit; }

// Heute schon aufgenommen? (Schutz bei mehrfachen Cron-Laeufen in derselben Minute)
$outfile = $folder_timelapse . date("Y.m.d") . "-timelapse.jpg";
if (file_exists($outfile)) { exit; }

// Einzelbild aus dem MJPEG-Stream der Intercom holen (gleiche Logik wie getpicture.php)
$camurl = "http://" . $miniserver_config[1]["Admin_RAW"] . ":" . $miniserver_config[1]["Pass_RAW"] . "@" . $arr["intercomip"] . "/mjpg/video.mjpg";
$boundary = "\n--";
$f = @fopen($camurl, "r");
if (!$f) { exit; }
$r = "";
$guard = 0;
while (substr_count($r, "Content-Length") != 2 && $guard++ < 4000) { $r .= fread($f, 512); }
fclose($f);
$start = strpos($r, "\xff");
if ($start === false) { exit; }
$end = strpos($r, $boundary, $start) - 1;
$frame = substr($r, $start, $end - $start);
if ($frame === '' ) { exit; }

file_put_contents($outfile, $frame);

// Optionaler Zeitstempel (wie bei den Archivbildern)
if (isset($arr['timestamp_image']) && $arr['timestamp_image'] == "on" && function_exists('imagecreatefromjpeg')) {
    $img = @imagecreatefromjpeg($outfile);
    if ($img) {
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        $text = date('d.m.Y H:i:s');
        imagefilledrectangle($img, 9, 29, strlen($text) * imagefontwidth(5) + 11, 45, $black);
        imagestring($img, 5, 10, 30, $text, $white);
        imagejpeg($img, $outfile);
        imagedestroy($img);
    }
}
echo "OK: " . basename($outfile) . "\n";

// Optional: Zeitraffer-Video automatisch neu rendern (im Hintergrund).
// Aus allen Timelapse-Fotos entsteht taeglich ein aktualisiertes MP4.
if (isset($arr['timelapse_video']) && $arr['timelapse_video'] == "on") {
    $video = $folder_timelapse . "zeitraffer.mp4";
    $cmd = 'ffmpeg -y -pattern_type glob -framerate 10 -i ' . escapeshellarg($folder_timelapse . '*-timelapse.jpg')
         . ' -vf "scale=trunc(iw/2)*2:trunc(ih/2)*2" -c:v libx264 -pix_fmt yuv420p ' . escapeshellarg($video);
    shell_exec(sprintf('%s > /dev/null 2>&1 &', $cmd));
    echo "Zeitraffer-Video wird aktualisiert: " . basename($video) . "\n";
}

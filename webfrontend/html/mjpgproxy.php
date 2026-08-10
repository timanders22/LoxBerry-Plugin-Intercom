<?php
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

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

/*
 * Zugriffspruefung - bis 1.5.0 gab es hier gar keine.
 *
 * Diese Datei reicht den Kamerastrom der Tuerstation weiter. Sie liegt im
 * unangemeldeten Bereich, also konnte JEDES Geraet im Netz die Kamera vor
 * der Haustuer mitsehen - dauerhaft und ohne Spur. Die Adresse, die das
 * Plugin dabei intern aufbaut, enthaelt ausserdem die Zugangsdaten des
 * Miniservers; sie wird zwar nicht ausgegeben, aber ein offener Strom ist
 * schon fuer sich genommen genug.
 *
 * Das Bild im Reiter der Oberflaeche und der ffmpeg-Aufruf aus
 * getvideo.php reichen das Token in der Adresse mit.
 */
ic_token_pruefen();

$miniserver_config = LBSystem::get_miniservers();
$arr = ic_config();

// config:
$mjpeg_url="http://". $miniserver_config[1]["Admin_RAW"] .":". $miniserver_config[1]["Pass_RAW"] ."@". $arr["intercomip"]. "/mjpg/video.mjpg";

// preparing http options (10s connect timeout so the page does not hang forever):
$opts = array(
	'http'=>array(
		'method'=>"GET",
		'timeout'=>10,
		'header'=>"Accept-language: en\r\n" .
		"Cookie: foo=bar\r\n"
	  )
);
$context = stream_context_create($opts);

// set no time limit and disable compression/buffering:
set_time_limit(0);
@apache_setenv('no-gzip', 1);
@ini_set('zlib.output_compression', 0);
@ini_set('output_buffering', 'off');
while (ob_get_level() > 0) { @ob_end_flush(); }
ignore_user_abort(false);

/* Sends an http request with the options shown above */
$fp = @fopen($mjpeg_url, 'r', false, $context);
if ($fp) {
	// Fix v1.4.0: echten Content-Type der Kamera inkl. Boundary weiterreichen.
	// Die alte Version sendete fest boundary=athene, die Kamera nutzt aber eine
	// eigene Boundary - Browser warten dann endlos und zeigen kein Bild.
	$contenttype = 'multipart/x-mixed-replace';
	if (isset($http_response_header) && is_array($http_response_header)) {
		foreach ($http_response_header as $h) {
			if (stripos($h, 'Content-Type:') === 0) {
				$contenttype = trim(substr($h, 13));
				break;
			}
		}
	}
	header("Cache-Control: no-cache");
	header("Cache-Control: private");
	header("Pragma: no-cache");
	header("Content-type: ".$contenttype);

	// pass data (manual loop with flush instead of fpassthru, so frames
	// reach the browser immediately and a client disconnect ends the script)
	stream_set_timeout($fp, 15);
	while (!feof($fp) && !connection_aborted()) {
		$chunk = fread($fp, 8192);
		if ($chunk === false || $chunk === '') {
			$meta = stream_get_meta_data($fp);
			if (!empty($meta['timed_out'])) break;
			continue;
		}
		echo $chunk;
		flush();
	}
	fclose($fp);
} else {
	// error: webcam probably offline
	// send alternative picture:
	$d = @file_get_contents(__DIR__ . "/offline.jpg");
	if ($d === false) { $d = ""; }

	Header("Content-Type: image/jpeg");
	Header("Content-Length: ".strlen($d));
	header("Cache-Control: no-cache");
	header("Cache-Control: private");
	header("Pragma: no-cache");

	echo $d;
}

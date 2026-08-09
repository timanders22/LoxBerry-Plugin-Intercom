<?php
/**
 * Intercom - Videoaufzeichnung ausloesen
 *
 * Aufruf (vom Miniserver):
 *   /plugins/<ordner>/getvideo.php?token=<TOKEN>&s=20
 *
 *
 * ===================================================================
 * ZUR VORGESCHICHTE DIESER DATEI - bitte nicht wieder aufheben
 * ===================================================================
 *
 * Bis 1.5.0 stand hier sinngemaess:
 *
 *   $hookstr = ' ; wget http://'.$_SERVER['HTTP_HOST'].'/plugins/...';
 *   $command = '(ffmpeg -f mjpeg -t '.$seconds.' -r 20 -i "http://'
 *            . $_SERVER['HTTP_HOST'] . '/plugins/.../mjpgproxy.php" ...';
 *   shell_exec(sprintf('%s > /dev/null 2>&1 &', $command));
 *
 * $_SERVER['HTTP_HOST'] ist der Inhalt der Host-Kopfzeile. Die bestimmt
 * ausschliesslich der Aufrufer - jeder Aufrufer. Sie landete unmaskiert
 * in einer Zeichenkette, die an die Shell ging. Eine Anfrage mit einer
 * passend gewaehlten Host-Kopfzeile brachte den LoxBerry damit dazu,
 * einen beliebigen Befehl auszufuehren, mit den Rechten des Webservers.
 *
 * Diese Datei liegt unter webfrontend/html/ - also im UNANGEMELDETEN
 * Bereich. Es brauchte also weder Zugangsdaten noch ein Token. Wer den
 * LoxBerry ueber HTTP erreichte, hatte eine Befehlszeile darauf.
 *
 * Drei Dinge sind deshalb geaendert:
 *
 *   1. Fuer Aufrufe an den eigenen Rechner wird 127.0.0.1 benutzt
 *      (ic_eigene_basis()). HTTP_HOST wird dafuer gar nicht mehr
 *      gebraucht - fuer einen Aufruf an sich selbst ist es ohnehin
 *      ueberfluessig.
 *   2. JEDER Wert, der an die Shell geht, laeuft durch escapeshellarg().
 *      Auch die, von denen man "weiss", dass sie nur Ziffern enthalten:
 *      dieses Wissen ist genau das, was beim naechsten Umbau verloren
 *      geht.
 *   3. Der Aufruf verlangt das Zugriffstoken.
 *
 * Der Befund des Pruefers zielte auf $_REQUEST['s'] - dort ist die Lage
 * milder, als sie klingt: is_numeric() laesst zwar 1e3 oder " 12" durch,
 * aber keine Shell-Sonderzeichen. Aus $s liess sich also keine
 * Befehlsausfuehrung bauen, wohl aber eine sehr lange Aufzeichnung
 * (1e3 = 1000 Sekunden). Deshalb ist $s jetzt eine ganze Zahl mit
 * Ober- und Untergrenze.
 */

require_once dirname(dirname(__DIR__)) . '/htmlauth/plugins/'
           . basename(__DIR__) . '/config.php';

ini_set('max_execution_time', 120);
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

ic_token_pruefen();

/* ---------------- Dauer ---------------- */
// Ganze Zahl, mit Grenzen. 1 bis 300 Sekunden: darunter entsteht keine
// brauchbare Aufnahme, darueber laeuft ffmpeg laenger als die Zeitgrenze
// dieses Skripts und fuellt bei einem Daueraufruf die Karte.
$seconds = 20;
if (isset($_REQUEST['s']) && is_numeric($_REQUEST['s'])) {
    $seconds = (int) $_REQUEST['s'];
}
if ($seconds < 1)   { $seconds = 1; }
if ($seconds > 300) { $seconds = 300; }

$arr = ic_config();

/* ---------------- Dateinamen ---------------- */
// Der Name besteht ausschliesslich aus Datum, der geprueften Zahl und
// festem Text - er kann also nichts Fremdes enthalten. Maskiert wird er
// trotzdem, siehe oben Punkt 2.
$videofile = $folder_video_archive . date('Y_m_d-H_i_s') . '-' . $seconds . 's-intercom.avi';
$video_tn_file = preg_replace('/\.avi$/', '.jpg', $videofile);
$videofilenameonly = basename($videofile);

/* ---------------- Zeitstempel-Filter ---------------- */
$vf = array();
if (isset($arr['timestamp_video']) && $arr['timestamp_video'] === 'on') {
    // Als eigenes Argument an ffmpeg, nicht als Textbaustein in einer
    // Befehlszeile. Damit entfaellt die Kette aus Rueckstrichen, die
    // frueher noetig war, um die Doppelpunkte durch zwei Ebenen von
    // Maskierung zu bringen - und mit ihr die Fehlerquelle.
    $vf = array('-vf', "drawtext=text='%{localtime\\:%d.%m.%Y %H\\:%M\\:%S}'"
                     . ':x=10:y=10:fontsize=24:fontcolor=white:box=1:boxcolor=black');
}

/* ---------------- Befehl zusammensetzen ---------------- */
$basis = ic_eigene_basis();
$token = isset($arr['aktionstoken']) ? (string) $arr['aktionstoken'] : '';

/** Jedes Argument einzeln maskieren und mit Leerzeichen verbinden. */
function ic_befehl(array $teile)
{
    $out = array();
    foreach ($teile as $t) {
        // Feste Schalter (beginnen mit -) unveraendert, alles andere
        // maskiert. So bleibt der Befehl lesbar und trotzdem dicht.
        $out[] = (strlen($t) > 0 && $t[0] === '-' && strpos($t, ' ') === false)
               ? $t : escapeshellarg($t);
    }
    return implode(' ', $out);
}

$aufnahme = ic_befehl(array_merge(
    array('ffmpeg', '-f', 'mjpeg', '-t', (string) $seconds, '-r', '20',
          '-i', $basis . '/mjpgproxy.php?token=' . rawurlencode($token)),
    $vf,
    array('-r', '5', $videofile)
));

$vorschau = ic_befehl(array('ffmpeg', '-i', $videofile, '-ss', '00:00:02',
                            '-vframes', '1', '-q:v', '2', $video_tn_file));

$hook = ic_befehl(array('wget', '-q', '-O', '/dev/null', '--timeout=10',
    $basis . '/videowebhook.php?token=' . rawurlencode($token)
    . '&file=' . rawurlencode($videofilenameonly)));

$command = '(' . $aufnahme . ' ; ' . $vorschau . ' ; ' . $hook . ')';

// Im Hintergrund starten. Der gesamte Befehl steht in einfachen
// Anfuehrungszeichen der Maskierung - hier wird nichts mehr angefuegt,
// was von aussen kommt.
shell_exec($command . ' > /dev/null 2>&1 &');

/* ---------------- Antwort ---------------- */
header('Content-type:application/json;charset=utf-8');

// Die Adresse fuer den Anwender darf HTTP_HOST benutzen - sie geht in
// eine JSON-Antwort, nicht in eine Befehlszeile. ic_host() beschraenkt
// sie zusaetzlich auf unbedenkliche Zeichen.
$wwwpfad = str_replace(ic_paths()['home'] . '/webfrontend', '', $videofile);
$videourl = 'http://' . ic_host() . $wwwpfad;

echo json_encode(array(
    'success'   => true,
    'timestamp' => date('d.m.Y-H:i:s'),
    'videofile' => $videourl,
    'length'    => $seconds,
));

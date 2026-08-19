<?php
/**
 * Intercom - Videoaufzeichnung ausloesen
 *
 * Aufruf (vom Miniserver):
 *   /plugins/<ordner>/getvideo.php?token=<TOKEN>&s=20
 *   /plugins/<ordner>/getvideo.php?token=<TOKEN>&s=20&station=2
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
 * NEU IN 2.2.0: der Endpunkt behauptet keinen Erfolg mehr, den er nicht
 * kennt. Bis 2.1.13 stand success:true fest im Text - auch wenn ffmpeg
 * gar nicht installiert war oder das Archiv nicht beschreibbar.
 */

require_once __DIR__ . '/ic_start.php';

ini_set('max_execution_time', 120);

ic_token_pruefen();

if (isset($_GET['selftest'])) {
    ic_selftest_antwort('getvideo.php');
}

header('Content-Type: application/json; charset=utf-8');

/* ---------------- Station ---------------- */
$ic_stationsangabe = isset($_GET['station']) && is_string($_GET['station'])
                   ? substr($_GET['station'], 0, 32) : '';
$station = ic_station($ic_stationsangabe);
if ($station === null) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(array('success' => false, 'error' => ic_stationen()
        ? 'Unbekannte Station: ' . $ic_stationsangabe
        : 'Es ist keine Tuerstation eingerichtet.'));
    exit;
}

/* ---------------- Dauer ---------------- */
// Ganze Zahl, mit Grenzen. 1 bis 300 Sekunden: darunter entsteht keine
// brauchbare Aufnahme, darueber laeuft ffmpeg laenger als die Zeitgrenze
// dieses Skripts und fuellt bei einem Daueraufruf die Karte.
//
// Der Befund des Pruefers zielte 2026 auf $_REQUEST['s'] - dort ist die Lage
// milder, als sie klingt: is_numeric() laesst zwar 1e3 oder " 12" durch, aber
// keine Shell-Sonderzeichen. Aus $s liess sich also keine Befehlsausfuehrung
// bauen, wohl aber eine 1000-Sekunden-Aufnahme.
$seconds = 20;
if (isset($_GET['s']) && is_numeric($_GET['s'])) {
    $seconds = (int) $_GET['s'];
}
if ($seconds < 1)   { $seconds = 1; }
if ($seconds > 300) { $seconds = 300; }

$arr = ic_config();

/* ---------------- Voraussetzungen PRUEFEN, nicht annehmen ---------------- */
$ffmpeg = ic_programm('ffmpeg');
if (!$ffmpeg) {
    ic_log_gebremst('ffmpeg', 'Eine Videoaufzeichnung wurde angefordert, aber ffmpeg '
        . 'ist nicht installiert. Abhilfe: sudo apt-get install -y ffmpeg');
    header('HTTP/1.1 501 Not Implemented');
    echo json_encode(array('success' => false,
        'error' => 'ffmpeg ist nicht installiert - ohne ffmpeg gibt es keine Videoaufzeichnung.'));
    exit;
}
if (!ic_archiv_sicherstellen()) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(array('success' => false,
        'error' => 'Das Archivverzeichnis liess sich nicht anlegen: ' . ic_paths()['legacy']));
    exit;
}
$o = ic_archivordner();
if (!@is_writable($o['video'])) {
    ic_log_gebremst('videoordner', 'Der Videoordner ist nicht beschreibbar: ' . $o['video']);
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(array('success' => false,
        'error' => 'Der Videoordner ist nicht beschreibbar: ' . $o['video']));
    exit;
}

/* ---------------- Sperre ---------------- */
// Jeder Aufruf startet ein bis zu 300 Sekunden laufendes ffmpeg im
// Hintergrund; zehn Aufrufe starteten bis 2.1.13 zehn davon. Die Sperre ist
// nicht blockierend: wer nicht drankommt, bekommt eine klare Absage statt
// einer zweiten Aufnahme derselben Szene.
$ic_sperre = ic_sperre('video');
if ($ic_sperre === false) {
    header('HTTP/1.1 429 Too Many Requests');
    echo json_encode(array('success' => false,
        'error' => 'Es laeuft bereits eine Aufzeichnung.'));
    exit;
}

/* ---------------- Dateinamen ---------------- */
// Der Name besteht ausschliesslich aus Datum, der geprueften Zahl und
// festem Text - er kann also nichts Fremdes enthalten. Maskiert wird er
// trotzdem, siehe oben Punkt 2.
$stationsteil = count(ic_stationen()) > 1
    ? preg_replace('/[^A-Za-z0-9_\-]/', '', $station['name']) . '-' : '';
$videofile = $o['video'] . date('Y_m_d-H_i_s') . '-' . $stationsteil . $seconds . 's-intercom.avi';
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
/* Weitergegeben wird die NUMMER, nicht der Name.
 *
 * ic_station() fasst eine Angabe aus ein oder zwei Ziffern als Nummer auf.
 * Wer seine dritte Station "2" nennt, haette mit dem Namen die zweite
 * aufgezeichnet - ein Fehler, den niemand bemerkt, weil beide Stationen ein
 * gueltiges Bild liefern. Die Nummer ist eindeutig; sie steht auch in der
 * Loxone-Vorlage. */
$ic_nummer = 0;
foreach (ic_stationen() as $ic_i => $ic_s) {
    if ($ic_s['ip'] === $station['ip'] && $ic_s['name'] === $station['name']) {
        $ic_nummer = $ic_i + 1;
        break;
    }
}
$stationsparameter = (count(ic_stationen()) > 1 && $ic_nummer > 0)
    ? '&station=' . $ic_nummer : '';

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
    array($ffmpeg, '-f', 'mjpeg', '-t', (string) $seconds, '-r', '20',
          '-i', $basis . '/mjpgproxy.php?token=' . rawurlencode($token) . $stationsparameter),
    $vf,
    array('-r', '5', $videofile)
));

$vorschau = ic_befehl(array($ffmpeg, '-i', $videofile, '-ss', '00:00:02',
                            '-vframes', '1', '-q:v', '2', $video_tn_file));

// Die Rueckmeldung laeuft ueber wget, wenn es da ist - sonst ueber php-cli.
// Bis 2.1.13 wurde wget aufgerufen, ohne dass es in dpkg/apt stand: fehlte
// es, lief die Aufnahme, aber der Video-Webhook loeste nie aus - und weil
// der ganze Befehl nach /dev/null ging, fiel es nirgends auf.
$hookurl = $basis . '/videowebhook.php?token=' . rawurlencode($token)
         . '&file=' . rawurlencode($videofilenameonly);
$wget = ic_programm('wget');
if ($wget) {
    $hook = ic_befehl(array($wget, '-q', '-O', '/dev/null', '--timeout=10', $hookurl));
} else {
    $php = ic_programm('php');
    $hook = $php
        ? ic_befehl(array($php, '-r',
            'echo @file_get_contents(' . var_export($hookurl, true) . ');'))
        : '';
    if ($hook === '') {
        ic_log_gebremst('hookweg', 'Weder wget noch php stehen zur Verfuegung - die '
            . 'Rueckmeldung nach der Videoaufzeichnung entfaellt.');
    }
}

$command = '(' . $aufnahme . ' ; ' . $vorschau . ($hook !== '' ? ' ; ' . $hook : '') . ')';

// Im Hintergrund starten. Der gesamte Befehl steht in einfachen
// Anfuehrungszeichen der Maskierung - hier wird nichts mehr angefuegt,
// was von aussen kommt.
//
// Die Sperre wird bewusst NICHT bis zum Ende der Aufnahme gehalten: dieser
// Prozess endet gleich, der Hintergrundlauf nicht. Sie verhindert deshalb
// gleichzeitige AUSLOESER, nicht gleichzeitige ffmpeg-Laeufe.
shell_exec($command . ' > /dev/null 2>&1 &');

ic_log('Videoaufzeichnung gestartet: ' . $videofilenameonly . ' (' . $seconds . ' s, Station "'
    . $station['name'] . '")');
ic_merker_setzen('video', $videofilenameonly);

/* ---------------- Antwort ---------------- */
// Die Adresse fuer den Anwender darf HTTP_HOST benutzen - sie geht in
// eine JSON-Antwort, nicht in eine Befehlszeile. ic_host() beschraenkt
// sie zusaetzlich auf unbedenkliche Zeichen.
$wwwpfad = str_replace(ic_paths()['home'] . '/webfrontend', '', $videofile);
$videourl = 'http://' . ic_host() . $wwwpfad;

echo json_encode(array(
    'success'   => true,
    'started'   => true,
    'timestamp' => date('d.m.Y-H:i:s'),
    'station'   => $station['name'],
    'videofile' => $videourl,
    'file'      => $videofilenameonly,
    'length'    => $seconds,
    // Die Aufnahme LAEUFT - fertig ist sie erst in $seconds Sekunden. Bis
    // 2.1.13 stand hier nur success:true, und das las sich wie "liegt bereit".
    'ready_in'  => $seconds,
    'hinweis'   => 'Die Aufnahme laeuft. Die Datei ist erst nach '
                 . $seconds . ' Sekunden vollstaendig.',
));

flock($ic_sperre, LOCK_UN);
fclose($ic_sperre);

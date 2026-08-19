<?php
/**
 * Intercom - gemeinsame Grundlage (neu ab 1.6.0, erweitert in 2.2.0)
 *
 * Enthaelt die Dinge, die jedes Skript braucht: Pfade ohne fest
 * eingetragenen Ordnernamen, Zugriffstoken, unteilbares Schreiben,
 * abgesicherte HTTP-Aufrufe, den Bildabruf von der Tuerstation, die
 * Selbstpruefung und das MQTT-Gateway ueber UDP.
 *
 * Was hier steht, steht genau einmal: die Selbstpruefung des Reiters Test
 * und die des Endpunkts (?selftest=1) holen ihre Zeilen aus DERSELBEN
 * Funktion, und die Loxone-Vorlage baut ihre Adressen aus denselben
 * Bausteinen wie der Reiter "Einbindung in Loxone".
 */

/* ==================================================================
 * Pfade
 * ================================================================== */

/**
 * Der eigene Plugin-Ordner - ERMITTELT, nicht geraten.
 *
 * Bis 1.5.0 stand in jedem Skript die Zeile
 *     require_once "../../../htmlauth/plugins/<Ordner>/config.php";
 * mit dem Ordnernamen fest im Text. Ist der Ordner bei der
 * Installation schon belegt, haengt LoxBerry einen Zaehler an
 * (intercom_01) - und dann zeigt jeder dieser Verweise auf die
 * VORGAENGERINSTALLATION oder ins Leere.
 *
 * Der Ordnername steckt im Ablageort dieser Datei - von dort wird er
 * genommen.
 */

/* Den LoxBerry-Wurzelordner ohne festen Systempfad bestimmen.
 *
 * Vom eigenen Ablageort aufwaerts, bis ein Verzeichnis gefunden ist, das
 * config/plugins UND webfrontend enthaelt. Das trifft die uebliche
 * Installation genauso wie eine an einem anderen Ort - und es trifft auch
 * den Fall, dass das Plugin noch als entpacktes Archiv daliegt (dann findet
 * es nichts und gibt einen Leerstring zurueck, was der Aufrufer ohnehin
 * abfangen muss).
 */
if (!function_exists('lb_wurzel_ermitteln')) {
    function lb_wurzel_ermitteln()
    {
        $d = __DIR__;
        for ($i = 0; $i < 8; $i++) {
            if (is_dir($d . '/config/plugins') && is_dir($d . '/webfrontend')) {
                return $d;
            }
            $eltern = dirname($d);
            if ($eltern === $d) { break; }
            $d = $eltern;
        }
        return '';
    }
}

function ic_plugin_ordner()
{
    // .../webfrontend/htmlauth/plugins/<ordner>/ic_lib.php
    return basename(dirname(__FILE__));
}

function ic_paths()
{
    static $p = null;
    if ($p !== null) {
        return $p;
    }
    $home = getenv('LBHOMEDIR');
    if (!$home || !is_dir($home)) {
        foreach (array(lb_wurzel_ermitteln(), '/home/loxberry/loxberry') as $k) {
            if (is_dir($k)) { $home = $k; break; }
        }
    }
    if (!$home) { $home = lb_wurzel_ermitteln(); }
    $ordner = ic_plugin_ordner();
    $p = array(
        'home'    => $home,
        'plugin'  => $ordner,
        'config'  => $home . '/config/plugins/' . $ordner,
        'data'    => $home . '/data/plugins/' . $ordner,
        'log'     => $home . '/log/plugins/' . $ordner,
        'html'    => $home . '/webfrontend/html/plugins/' . $ordner,
        'htmlauth' => $home . '/webfrontend/htmlauth/plugins/' . $ordner,
        'legacy'  => $home . '/webfrontend/legacy/' . $ordner . '_data/',
    );
    return $p;
}

/**
 * Der Titel fuer die Kopfzeile - aus der plugin.cfg, nicht fest im Quelltext.
 *
 * KANDIDATENLISTE STATT EINER RECHNUNG (2.2.0)
 * --------------------------------------------
 * Bis 2.1.13 standen hier zwei feste Ausdruecke:
 *     dirname(dirname(__DIR__)) . '/plugin.cfg'
 *     dirname(__DIR__) . '/plugin.cfg'
 * Installiert liegt diese Datei unter
 *     <home>/webfrontend/htmlauth/plugins/<ordner>/ic_lib.php
 * und die beiden ergeben damit <home>/webfrontend/htmlauth/plugin.cfg und
 * <home>/webfrontend/htmlauth/plugins/plugin.cfg - zwei gemeinsame
 * LoxBerry-Verzeichnisse, in denen keine Plugin-Datei liegt. Getroffen wurde
 * nur der Archivfall. Der Befund von 2.1.12 (der INI-Zerleger) war damit
 * behoben, der Pfad davor nicht: die Funktion fiel weiterhin still auf ihren
 * Vorgabewert zurueck, und weil der zufaellig derselbe Titel ist, fiel es
 * niemandem auf.
 *
 * Deshalb eine Liste - und dazu ic_titel_quelle(), damit der Reiter Test
 * anzeigen kann, WELCHE Datei getroffen hat oder dass es die Vorgabe war.
 * Wo der Ablageort nicht belegt ist, wird er nicht behauptet.
 */
function ic_titel_kandidaten()
{
    $p = ic_paths();
    $o = ic_plugin_ordner();
    return array(
        $p['config'] . '/plugin.cfg',
        $p['data'] . '/plugin.cfg',
        $p['home'] . '/templates/plugins/' . $o . '/plugin.cfg',
        $p['htmlauth'] . '/plugin.cfg',
        $p['html'] . '/plugin.cfg',
        // Entpacktes Archiv: .../webfrontend/htmlauth/ic_lib.php
        dirname(dirname(__DIR__)) . '/plugin.cfg',
        dirname(__DIR__) . '/plugin.cfg',
        __DIR__ . '/plugin.cfg',
    );
}

function ic_titel_quelle()
{
    static $q = null;
    if ($q !== null) { return $q; }
    $q = '';
    foreach (ic_titel_kandidaten() as $k) {
        if (@is_file($k)) { $q = $k; break; }
    }
    return $q;
}

/** Ein Wert aus der plugin.cfg - oder der Vorgabewert. */
function ic_plugincfg($sektion, $schluessel, $vorgabe = '')
{
    static $d = null;
    if ($d === null) {
        $d = array();
        $cfg = ic_titel_quelle();
        if ($cfg !== '') {
            /* Die #-Kommentarzeilen muessen raus, sonst liest hier niemand
             * etwas. Die plugin.cfg kommentiert mit '#'; PHPs INI-Zerleger
             * kennt als Kommentarzeichen aber nur ';' - '#' wurde mit PHP 7
             * entfernt. Er liest die Kommentare als Zuweisungen und bricht an
             * der ersten Zeile mit einem Sonderzeichen ab. parse_ini_file()
             * gibt dann false zurueck, gemessen unter 7.4.33 und 8.4.24.
             *
             * Entfernt werden nur Zeilen, deren erstes sichtbares Zeichen '#'
             * ist. Ein '#' INNERHALB eines Wertes bleibt erhalten.
             */
            $roh = @file_get_contents($cfg);
            if ($roh !== false) {
                $x = @parse_ini_string(preg_replace('/^[ \t]*#.*$/m', '', $roh),
                                       true, INI_SCANNER_RAW);
                if (is_array($x)) { $d = $x; }
            }
        }
    }
    if (isset($d[$sektion][$schluessel]) && trim($d[$sektion][$schluessel]) !== '') {
        return trim($d[$sektion][$schluessel], " \t\"'");
    }
    return $vorgabe;
}

function ic_titel()   { return ic_plugincfg('PLUGIN', 'TITLE', 'Intercom'); }
function ic_fassung() { return ic_plugincfg('PLUGIN', 'VERSION', ''); }

/* ==================================================================
 * Konfiguration
 * ================================================================== */

/** Die Konfiguration als Array - nie null, nie false. */
function ic_config()
{
    $datei = ic_paths()['config'] . '/data.json';
    $arr = is_readable($datei)
        ? json_decode((string) @file_get_contents($datei), true) : null;
    return is_array($arr) ? $arr : array();
}

/** Der Ort der Zweitschrift - NEBEN dem Konfigordner, nicht darin. */
function ic_zweitschrift()
{
    $p = ic_paths();
    return dirname($p['config']) . '/' . basename($p['config']) . '.backup.json';
}

/**
 * Die Konfiguration schreiben - unteilbar, mit Zweitschrift.
 *
 * Gibt array(ok, meldung) zurueck. json_encode liefert bei ungueltigem UTF-8
 * FALSE, und file_put_contents($pfad, false) schreibt 0 Bytes und gibt 0
 * zurueck, nicht false. Deshalb wird die Kodierung vorher geprueft.
 *
 * Die Zweitschrift liegt AUSSERHALB des Ordners, den der Installer beim
 * Update ueberschreibt, und wird von postinstall.sh zurueckgespielt, wenn die
 * eigentliche Datei leer ist. 0600, weil hier Zugangsdaten und das
 * Zugriffstoken stehen.
 */
function ic_config_speichern(array $neu)
{
    $js = json_encode($neu, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES);
    if ($js === false) {
        return array(false, 'json_encode: ' . json_last_error_msg());
    }
    $datei = ic_paths()['config'] . '/data.json';
    if (!ic_datei_ersetzen($datei, $js, 0600)) {
        return array(false, $datei);
    }
    if (!ic_datei_ersetzen(ic_zweitschrift(), $js, 0600)) {
        ic_log('WARNUNG: Die Zweitschrift liess sich nicht schreiben: ' . ic_zweitschrift());
    }
    return array(true, '');
}

/* ==================================================================
 * Ausgabe maskieren
 * ================================================================== */

/** Fuer HTML. */
function ic_e($wert) { return htmlspecialchars((string) $wert, ENT_QUOTES, 'UTF-8'); }

/** Fuer XML-Attribute der Loxone-Vorlage. */
function ic_x($wert)
{
    return htmlspecialchars((string) $wert, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/* ==================================================================
 * Zugriffstoken
 * ================================================================== */

/**
 * Ein neues Zugriffstoken.
 *
 * KEIN Rueckfall auf mt_rand oder uniqid: dieses Token ist das Einzige,
 * was die Endpunkte im unangemeldeten Bereich schuetzt. Ein erratbares
 * waere schlimmer als gar keines - dann weist der Endpunkt wenigstens
 * konsequent alles ab.
 */
function ic_token_neu($laenge = 24)
{
    $zeichen = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $t = '';
    try {
        for ($i = 0; $i < $laenge; $i++) {
            $t .= $zeichen[random_int(0, strlen($zeichen) - 1)];
        }
    } catch (Exception $e) {
        throw new RuntimeException('Kein sicherer Zufall verfuegbar: ' . $e->getMessage());
    } catch (Error $e) {
        throw new RuntimeException('random_int steht nicht zur Verfuegung.');
    }
    return $t;
}

/** Die Adresse des Aufrufers, auf unbedenkliche Zeichen beschraenkt. */
function ic_absender()
{
    $a = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    $a = preg_replace('/[^0-9A-Fa-f:.]/', '', $a);
    return $a !== '' ? $a : 'unbekannt';
}

/**
 * Zugriffspruefung fuer die Endpunkte unter webfrontend/html/.
 *
 * Dieser Bereich liegt BEWUSST im unangemeldeten Teil - der Miniserver
 * muss ihn ohne Zugangsdaten erreichen koennen. Bis 1.5.0 gab es dort
 * aber ueberhaupt keine Pruefung: jedes Geraet im Netz konnte eine
 * Videoaufzeichnung ausloesen, das Archiv fuellen und den Kamerastrom
 * mitlesen.
 *
 * Bricht mit HTTP 403 ab, wenn das Token fehlt oder falsch ist - und
 * schreibt seit 2.2.0 eine gebremste Protokollzeile dazu. Bis dahin
 * hinterliess ein Abtasten der Endpunkte keine Spur; genau das "ohne Spur"
 * war der Grund, aus dem das Token eingefuehrt wurde.
 *
 * Gelesen wird aus $_GET und $_POST, NICHT aus $_REQUEST: was $_REQUEST
 * enthaelt, haengt von request_order ab. Steht die Einstellung leer, gilt
 * variables_order - und deren Vorgabe EGPCS nimmt auch Cookies auf. Gemessen
 * an den eingebauten Vorgabewerten von 7.4.33 und 8.4.24: request_order ist
 * leer, variables_order ist EGPCS. Ein Cookie namens "token" haette die
 * Pruefung also fuettern koennen.
 */
function ic_token_pruefen($erlaube_leer = false)
{
    $arr = ic_config();
    $soll = isset($arr['aktionstoken']) ? (string) $arr['aktionstoken'] : '';
    if ($soll === '') {
        if ($erlaube_leer) { return; }
        ic_log_gebremst('token_leer', 'Ein Aufruf wurde abgewiesen: es ist noch kein '
            . 'Zugriffstoken eingerichtet.');
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: text/plain; charset=utf-8');
        echo "FEHLER: Es ist noch kein Zugriffstoken eingerichtet.\n"
           . "Bitte einmal die Plugin-Oberflaeche oeffnen und speichern -\n"
           . "dort wird eines erzeugt und die fertigen Adressen angezeigt.\n";
        exit;
    }
    $ist = isset($_GET['token']) ? $_GET['token']
         : (isset($_POST['token']) ? $_POST['token'] : '');
    $ist = is_string($ist) ? $ist : '';
    // hash_equals vergleicht in gleichbleibender Zeit; ein einfaches ===
    // liesse sich ueber die Antwortzeit Zeichen fuer Zeichen erraten. Das
    // leere Soll ist oben schon abgefangen - hash_equals('','') waere wahr.
    if ($ist === '' || !hash_equals($soll, $ist)) {
        ic_log_gebremst('token_falsch', 'Ein Aufruf wurde mit fehlendem oder falschem '
            . 'Token abgewiesen (Absender ' . ic_absender() . ').');
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: text/plain; charset=utf-8');
        echo "FEHLER: Ungueltiges oder fehlendes Token.\n";
        exit;
    }
}

/**
 * Das Merkmal, das jedes Formular der Oberflaeche mitfuehrt.
 *
 * Der angemeldete Bereich schuetzt gegen Fremde, nicht gegen fremd
 * ausgeloeste Aufrufe: ein Bild auf einer beliebigen Seite mit der Quelle
 * archive.php?submit=1 hat bis 2.1.13 das gesamte Bildarchiv geloescht,
 * waehrend der Anwender angemeldet war. Das Merkmal haengt am Zugriffstoken
 * und wechselt deshalb mit ihm.
 */
function ic_merkmal()
{
    $arr = ic_config();
    $t = isset($arr['aktionstoken']) ? (string) $arr['aktionstoken'] : '';
    if ($t === '') { return ''; }
    return substr(hash('sha256', 'intercom-formular|' . $t), 0, 32);
}

/** Traegt der Aufruf das Merkmal? Fehlt es, wird abgewiesen - nicht geraten. */
function ic_merkmal_gueltig()
{
    $soll = ic_merkmal();
    if ($soll === '') { return false; }
    $ist = isset($_POST['merkmal']) ? $_POST['merkmal']
         : (isset($_GET['merkmal']) ? $_GET['merkmal'] : '');
    if (!is_string($ist) || $ist === '') { return false; }
    return hash_equals($soll, $ist);
}

/* ==================================================================
 * Dateien
 * ================================================================== */

/**
 * Eine Datei unteilbar ersetzen.
 *
 * Bis 1.5.0 schrieb getpicture.php mit
 *     file_put_contents("lastpicture.jpg", $frame);
 * unmittelbar in die Zieldatei. Treffen zwei Ausloeser gleichzeitig ein -
 * Klingel und Bewegungsmelder sind genau dafuer gebaut -, schreiben beide
 * Prozesse ineinander, und wer die Datei in diesem Augenblick liest,
 * bekommt ein halbes JPEG. Der Zwischenname enthaelt Prozessnummer und
 * Zufall, damit sich auch die Zwischendateien nicht in die Quere kommen.
 *
 * ZWEI AENDERUNGEN IN 2.2.0
 *   1. Rechte VOR Inhalt: die Datei wird leer angelegt, bekommt ihre Rechte
 *      und wird erst dann gefuellt. Andernfalls steht sie fuer die Dauer des
 *      Schreibens mit den Vorgaben der umask da - bei data.json mit dem
 *      Zugriffstoken darin.
 *   2. Geprueft wird gegen strlen($inhalt), nicht gegen === false. Eine
 *      KURZE Schreibung (Karte voll) meldet sich sonst nicht als Fehler,
 *      und bei einem Bildarchiv ist die volle Karte der wahrscheinlichste
 *      Fall ueberhaupt.
 */
function ic_datei_ersetzen($pfad, $inhalt, $modus = 0644)
{
    if ($inhalt === false || $inhalt === null || $inhalt === '') {
        return false;
    }
    $ordner = dirname($pfad);
    if (!is_dir($ordner)) { @mkdir($ordner, 0775, true); }
    $tmp = $pfad . '.' . getmypid() . '.' . mt_rand(1000, 9999) . '.tmp';
    if (@file_put_contents($tmp, '') === false) {
        return false;
    }
    @chmod($tmp, $modus);
    $n = @file_put_contents($tmp, $inhalt);
    if ($n === false || $n !== strlen($inhalt)) {
        @unlink($tmp);
        return false;
    }
    if (!@rename($tmp, $pfad)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

/**
 * Eine nicht blockierende Sperre gegen Parallellaeufe.
 *
 * Der Name traegt den PLUGIN-ORDNER. Bis 2.1.13 hiess die Datei fest
 * ic_cron.lock; bei einer Zweitinstallation (intercom_01) haetten sich beide
 * Installationen gegenseitig ausgesperrt - und weil der Zeitraffer nur in
 * einer einzigen Minute je Tag arbeitet, haette die unterlegene ihr Tagesbild
 * kommentarlos verloren.
 *
 * Der Rueckgabewert muss festgehalten werden: faellt die Variable aus dem
 * Gueltigkeitsbereich, gibt PHP die Datei frei und die Sperre ist wirkungslos.
 */
function ic_sperre($name)
{
    $p = ic_paths();
    $ordner = @is_dir($p['data']) ? $p['data'] : sys_get_temp_dir();
    if (!@is_dir($ordner)) { @mkdir($ordner, 0775, true); }
    $datei = $ordner . '/.sperre_' . preg_replace('/[^a-z0-9_]/i', '', $name);
    $fh = @fopen($datei, 'c');
    if ($fh === false) { return false; }
    if (!@flock($fh, LOCK_EX | LOCK_NB)) {
        @fclose($fh);
        return false;
    }
    return $fh;
}

/* ==================================================================
 * HTTP
 * ================================================================== */

/**
 * Eine Adresse abrufen - mit harter Zeitgrenze.
 *
 * Bis 1.5.0 wurde fuer die zweite Webhook-Art schlicht
 * file_get_contents($url) benutzt, ohne Zusammenhang und ohne Zeitgrenze.
 * PHP nimmt dann default_socket_timeout, ueblicherweise 60 Sekunden.
 * Antwortet die Gegenstelle des Anwenders nicht - ein abgeschalteter
 * Node-RED genuegt -, haengt der Aufruf so lange, und der Miniserver
 * wartet mit.
 *
 * $auth ist array(benutzer, passwort) oder null. Die Zugangsdaten gehen als
 * Kopfzeile hinaus, NICHT in der Adresse - siehe ic_bild_holen().
 *
 * Gibt array(inhalt, code, fehler) zurueck; $inhalt ist false bei Fehlschlag.
 */
function ic_http_holen_voll($url, $zeitgrenze = 5, $auth = null, $kopfzeilen = array())
{
    $url = (string) $url;
    if ($url === '') { return array(false, 0, 'keine Adresse'); }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $opt = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $zeitgrenze,
            CURLOPT_CONNECTTIMEOUT => min(3, $zeitgrenze),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'LoxBerry Intercom',
        );
        if ($kopfzeilen) { $opt[CURLOPT_HTTPHEADER] = $kopfzeilen; }
        if (is_array($auth) && $auth[0] !== '') {
            $opt[CURLOPT_USERPWD] = $auth[0] . ':' . $auth[1];
            $opt[CURLOPT_HTTPAUTH] = CURLAUTH_ANY;
        }
        curl_setopt_array($ch, $opt);
        $antwort = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $fehler = curl_error($ch);
        curl_close($ch);
        return array($antwort, $code, $fehler);
    }
    $kopf = $kopfzeilen;
    if (is_array($auth) && $auth[0] !== '') {
        $kopf[] = 'Authorization: Basic ' . base64_encode($auth[0] . ':' . $auth[1]);
    }
    $ctx = stream_context_create(array('http' => array(
        'method' => 'GET',
        'timeout' => $zeitgrenze,
        'ignore_errors' => true,
        'user_agent' => 'LoxBerry Intercom',
        'header' => implode("\r\n", $kopf),
    )));
    $inhalt = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header) && is_array($http_response_header)
        && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }
    return array($inhalt, $code, $inhalt === false ? 'Abruf gescheitert' : '');
}

/** Kurzform, wie bis 2.1.13: nur der Inhalt. */
function ic_http_holen($url, $zeitgrenze = 5)
{
    list($inhalt) = ic_http_holen_voll($url, $zeitgrenze);
    return $inhalt;
}

/* ==================================================================
 * Tuerstationen
 * ================================================================== */

/**
 * Die eingerichteten Tuerstationen - als durchnummerierte Liste.
 *
 * Bis 2.1.13 gab es genau ein Feld "intercomip" und drei Stellen mit
 * $miniserver_config[1] fest im Text. Wer zwei Stationen hat - oder auch nur
 * zwei Klingeltaster an einer -, konnte sie weder getrennt abfragen noch
 * unterscheiden.
 *
 * DER AKTUALISIERUNGSFALL IST DER REGELFALL: eine bestehende data.json kennt
 * "stationen" nicht. Dann wird die Liste aus "intercomip" gebildet, und alles
 * verhaelt sich wie bisher. Umgekehrt bleibt "intercomip" beim Speichern mit
 * der ersten Station gleichlautend, damit ein Rueckschritt auf 2.1.13 nichts
 * zerreisst.
 */
function ic_stationen()
{
    $cfg = ic_config();
    $aus = array();
    if (isset($cfg['stationen']) && is_array($cfg['stationen'])) {
        foreach ($cfg['stationen'] as $s) {
            if (!is_array($s)) { continue; }
            $ip = isset($s['ip']) ? trim((string) $s['ip']) : '';
            if ($ip === '') { continue; }
            $aus[] = array(
                'name'   => (isset($s['name']) && trim((string) $s['name']) !== '')
                            ? trim((string) $s['name']) : $ip,
                'ip'     => $ip,
                'user'   => isset($s['user']) ? (string) $s['user'] : '',
                'pass'   => isset($s['pass']) ? (string) $s['pass'] : '',
                'ms'     => isset($s['ms']) ? max(1, (int) $s['ms']) : 1,
                'standbild' => isset($s['standbild']) ? (string) $s['standbild'] : '',
            );
        }
    }
    if (!$aus) {
        $ip = isset($cfg['intercomip']) ? trim((string) $cfg['intercomip']) : '';
        if ($ip !== '') {
            $aus[] = array('name' => $ip, 'ip' => $ip, 'user' => '', 'pass' => '',
                           'ms' => 1, 'standbild' => '');
        }
    }
    return $aus;
}

/**
 * Eine Station heraussuchen - nach Nummer (ab 1) oder nach Namen.
 *
 * Was nicht ins Muster passt, wird abgewiesen und nicht zurechtgebogen:
 * eine unbekannte Angabe ergibt null, und der Aufrufer meldet das.
 */
function ic_station($angabe = '')
{
    $liste = ic_stationen();
    if (!$liste) { return null; }
    $angabe = is_string($angabe) ? trim($angabe) : '';
    if ($angabe === '') { return $liste[0]; }
    if (preg_match('/^[0-9]{1,2}$/', $angabe)) {
        $i = (int) $angabe - 1;
        return isset($liste[$i]) ? $liste[$i] : null;
    }
    foreach ($liste as $s) {
        if (strcasecmp($s['name'], $angabe) === 0) { return $s; }
    }
    return null;
}

/**
 * Die Zugangsdaten fuer eine Station.
 *
 * Vorrang haben eigene Angaben an der Station; sonst die des Miniservers.
 * Zurueck kommt array(benutzer, passwort, quelle).
 */
function ic_zugangsdaten(array $station)
{
    if ($station['user'] !== '') {
        return array($station['user'], $station['pass'], 'Station');
    }
    $nr = isset($station['ms']) ? max(1, (int) $station['ms']) : 1;
    if (class_exists('LBSystem', false) && method_exists('LBSystem', 'get_miniservers')) {
        $ms = LBSystem::get_miniservers();
        if (is_array($ms) && isset($ms[$nr]) && is_array($ms[$nr])) {
            $u = isset($ms[$nr]['Admin_RAW']) ? (string) $ms[$nr]['Admin_RAW'] : '';
            $pw = isset($ms[$nr]['Pass_RAW']) ? (string) $ms[$nr]['Pass_RAW'] : '';
            if ($u !== '') { return array($u, $pw, 'Miniserver ' . $nr); }
        }
        return array('', '', 'Miniserver ' . $nr . ' nicht gefunden');
    }
    return array('', '', 'LBSystem nicht verfuegbar');
}

/**
 * Ein JPEG aus einem MJPEG-Bruchstueck schneiden.
 *
 * DER TEUERSTE BEFUND DER 2.1er-REIHE steckte in der alten Fassung:
 *
 *     $start = strpos($r, "\xff");
 *     $end   = strpos($r, "\n--", $start) - 1;
 *     $frame = substr($r, $start, $end - $start);
 *     if ($frame === "" || strlen($frame) < 100) { ... abweisen ... }
 *
 * Fehlt die Grenzmarke - Zeitgrenze, abgebrochene Verbindung, andere
 * Kopfzeilen -, liefert strpos FALSE, $end wird -1, und substr() mit
 * negativer Laenge schneidet nicht ab, sondern gibt fast den ganzen Puffer
 * zurueck. Gemessen unter 7.4.33 und 8.4.24: 2000 Byte Puffer ergaben ein
 * "Bild" von 1989 Byte - die Laengenpruefung greift also NICHT. Das
 * Bruchstueck wurde als lastpicture.jpg veroeffentlicht, ins Archiv gelegt
 * und per MQTT und Webhook als gueltiges Bild gemeldet.
 *
 * Gesucht wird jetzt nach den beiden Marken, die ein JPEG selbst traegt:
 * FFD8 am Anfang, FFD9 am Ende. Fehlt eine davon, gibt es kein Bild - und
 * das wird gemeldet, nicht zurechtgebogen.
 */
function ic_jpeg_schneiden($roh)
{
    $roh = (string) $roh;
    $start = strpos($roh, "\xFF\xD8\xFF");
    if ($start === false) {
        return array(false, 'kein JPEG-Anfang (FFD8) im Datenstrom');
    }
    $ende = strpos($roh, "\xFF\xD9", $start + 3);
    if ($ende === false) {
        return array(false, 'kein vollstaendiger Rahmen: das Ende (FFD9) fehlt');
    }
    $bild = substr($roh, $start, $ende - $start + 2);
    if (strlen($bild) < 1000) {
        return array(false, 'Rahmen zu klein (' . strlen($bild) . ' Byte)');
    }
    return array($bild, '');
}

/**
 * Ein Standbild von der Tuerstation holen.
 *
 * ZWEI WEGE, und der Anwender bestimmt, welcher gilt:
 *
 *   'strom'  (Vorgabe)  der MJPEG-Strom /mjpg/video.mjpg, wie seit jeher
 *   'standbild'         eine Standbild-Adresse, ueblicherweise /jpg/image.jpg
 *   'auto'              erst Standbild, bei Fehlschlag der Strom
 *
 * Warum die Vorgabe der alte Weg bleibt: eine neue Funktion, die ab Werk an
 * ist, erreicht beim ersten Aufruf nach dem Update JEDE bestehende Anlage.
 * Ob eine bestimmte Firmware das Standbild ausliefert, ist nur am Geraet zu
 * messen - der Reiter Test misst es und sagt das Ergebnis; umgestellt wird
 * von Hand.
 *
 * Der Hinweis auf /jpg/image.jpg stammt aus einer Loxone-Projektdatei: Loxone
 * Config traegt am Baustein IntercomDevice neben IntVideoUrl auch
 * IntAlertImage ein, und das zeigt genau dorthin.
 *
 * DIE ZUGANGSDATEN GEHEN ALS KOPFZEILE HINAUS, NICHT IN DER ADRESSE.
 * Bis 2.1.13 wurde "http://benutzer:passwort@adresse/..." zusammengesetzt.
 * Gemessen mit parse_url(): ein Passwort mit '/' oder '#' zerlegt die
 * Adresse vollstaendig - es gibt dann nicht einmal mehr einen Rechnernamen.
 *
 * Rueckgabe: array('ok', 'bild', 'weg', 'fehler', 'code', 'dauer')
 */
function ic_bild_holen(array $station, $weg = null, $zeitgrenze = 8)
{
    $cfg = ic_config();
    if ($weg === null) {
        $weg = isset($cfg['bildweg']) ? (string) $cfg['bildweg'] : 'strom';
    }
    if (!in_array($weg, array('strom', 'standbild', 'auto'), true)) { $weg = 'strom'; }

    if ($station['ip'] === '') {
        return array('ok' => false, 'bild' => '', 'weg' => $weg,
                     'fehler' => 'Fuer diese Station ist keine Adresse eingetragen.',
                     'code' => 0, 'dauer' => 0.0);
    }
    $t0 = microtime(true);
    $vorlauf = '';

    if ($weg === 'standbild' || $weg === 'auto') {
        $r = ic_standbild_holen($station, $zeitgrenze);
        if ($r['ok'] || $weg === 'standbild') {
            $r['dauer'] = microtime(true) - $t0;
            return $r;
        }
        $vorlauf = $r['fehler'];
    }

    $r = ic_strombild_holen($station, $zeitgrenze);
    if (!$r['ok'] && $vorlauf !== '') {
        $r['fehler'] = 'Standbild: ' . $vorlauf . ' | Strom: ' . $r['fehler'];
    }
    $r['dauer'] = microtime(true) - $t0;
    return $r;
}

/** Der Standbildweg - eine einzelne Adresse, ein fertiges JPEG. */
function ic_standbild_holen(array $station, $zeitgrenze = 8)
{
    $cfg = ic_config();
    $pfad = $station['standbild'] !== '' ? $station['standbild']
          : ((isset($cfg['standbild_pfad']) && trim((string) $cfg['standbild_pfad']) !== '')
             ? trim((string) $cfg['standbild_pfad']) : '/jpg/image.jpg');
    if (substr($pfad, 0, 1) !== '/') { $pfad = '/' . $pfad; }
    list($u, $pw) = ic_zugangsdaten($station);
    $url = 'http://' . $station['ip'] . $pfad;
    list($inhalt, $code, $fehler) = ic_http_holen_voll($url, $zeitgrenze, array($u, $pw));
    $aus = array('ok' => false, 'bild' => '', 'weg' => 'standbild ' . $pfad,
                 'fehler' => '', 'code' => $code, 'dauer' => 0.0);
    if ($inhalt === false || $inhalt === '') {
        $aus['fehler'] = $fehler !== '' ? $fehler : 'keine Antwort';
        return $aus;
    }
    if ($code !== 0 && $code !== 200) {
        $aus['fehler'] = 'HTTP ' . $code
                       . ($code === 401 ? ' - Benutzername oder Passwort stimmen nicht' : '');
        return $aus;
    }
    if (strncmp($inhalt, "\xFF\xD8\xFF", 3) !== 0) {
        $aus['fehler'] = 'die Antwort ist kein JPEG (' . strlen($inhalt) . ' Byte)';
        return $aus;
    }
    $aus['ok'] = true;
    $aus['bild'] = $inhalt;
    return $aus;
}

/** Der bisherige Weg: einen Rahmen aus dem MJPEG-Strom lesen. */
function ic_strombild_holen(array $station, $zeitgrenze = 8)
{
    list($u, $pw) = ic_zugangsdaten($station);
    $url = 'http://' . $station['ip'] . '/mjpg/video.mjpg';
    $aus = array('ok' => false, 'bild' => '', 'weg' => 'strom /mjpg/video.mjpg',
                 'fehler' => '', 'code' => 0, 'dauer' => 0.0);
    $kopf = array('Accept: image/jpeg, multipart/x-mixed-replace, */*');
    if ($u !== '') {
        $kopf[] = 'Authorization: Basic ' . base64_encode($u . ':' . $pw);
    }
    // Zeitgrenze auf den Strom legen, BEVOR gelesen wird: ohne sie wartet
    // fread beliebig lange, wenn die Tuerstation die Verbindung offen haelt,
    // aber nichts mehr schickt. In timelapse.php fehlte genau das bis 2.1.13.
    $ctx = stream_context_create(array('http' => array(
        'method' => 'GET',
        'timeout' => $zeitgrenze,
        'ignore_errors' => true,
        'header' => implode("\r\n", $kopf),
    )));
    $f = @fopen($url, 'r', false, $ctx);
    if (!$f) {
        $aus['fehler'] = 'die Station war nicht erreichbar';
        return $aus;
    }
    if (isset($http_response_header) && is_array($http_response_header)
        && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        $aus['code'] = (int) $m[1];
        if ($aus['code'] === 401) {
            fclose($f);
            $aus['fehler'] = 'HTTP 401 - Benutzername oder Passwort stimmen nicht';
            return $aus;
        }
    }
    stream_set_timeout($f, $zeitgrenze);
    /* WAECHTER gegen die Endlosschleife.
     *
     * Bis 1.5.0 stand hier eine Schleife ohne jede Abbruchbedingung. Sie hat
     * eine harte Obergrenze, prueft auf Zeitablauf und Dateiende - und bricht
     * ab, sobald ein vollstaendiges JPEG im Puffer steht.
     */
    $r = '';
    $guard = 0;
    while ($guard++ < 4000) {
        $teil = fread($f, 4096);
        if ($teil === false || $teil === '') {
            $meta = stream_get_meta_data($f);
            if (!empty($meta['timed_out']) || feof($f)) { break; }
            continue;
        }
        $r .= $teil;
        if (strlen($r) > 2000 && strpos($r, "\xFF\xD8\xFF") !== false
            && strpos($r, "\xFF\xD9", 3) !== false) {
            break;
        }
        if (strlen($r) > 4194304) { break; }
    }
    fclose($f);
    list($bild, $fehler) = ic_jpeg_schneiden($r);
    if ($bild === false) {
        $aus['fehler'] = $fehler . ' (' . strlen($r) . ' Byte gelesen)';
        return $aus;
    }
    $aus['ok'] = true;
    $aus['bild'] = $bild;
    return $aus;
}

/* ==================================================================
 * MQTT ueber das LoxBerry-Gateway (UDP)
 * ================================================================== */

/**
 * Das Themen-Praefix - aus dem ORDNERNAMEN, nicht fest.
 *
 * Bis 2.1.13 stand ueberall woertlich "intercom". Bei einer Zweitinstallation
 * (intercom_01) haetten beide Installationen auf dasselbe Thema gesendet, und
 * im Broker haette abwechselnd die eine und die andere Tuerstation gestanden.
 */
function ic_mqtt_praefix()
{
    $cfg = ic_config();
    $p = isset($cfg['mqtt_praefix']) ? trim((string) $cfg['mqtt_praefix']) : '';
    if ($p === '') { $p = ic_plugin_ordner(); }
    return ic_mqtt_thema($p);
}

/** Den UDP-Eingangsport des MQTT-Gateways ermitteln. */
function ic_mqtt_udpport()
{
    static $port = null;
    if ($port !== null) { return $port; }
    $port = 0;
    if (function_exists('mqtt_connectiondetails')) {
        $creds = mqtt_connectiondetails();
        if (is_array($creds) && !empty($creds['udpinport'])) {
            $port = (int) $creds['udpinport'];
        }
    }
    if (!$port) {
        $gen = @json_decode((string) @file_get_contents(
            ic_paths()['home'] . '/config/system/general.json'), true);
        // is_array() vor dem verschachtelten Zugriff: waere der Wert eine
        // Zeichenkette mit Inhalt, verrechnete PHP den Schluessel zu
        // Position 0, isset() waere wahr, und der Port ergaebe sich aus
        // dem ersten Buchstaben.
        foreach (array(array('Mqtt', 'Udpinport'), array('mqtt', 'udpinport')) as $paar) {
            list($a, $b) = $paar;
            if (isset($gen[$a]) && is_array($gen[$a]) && isset($gen[$a][$b])) {
                $port = (int) $gen[$a][$b];
                if ($port) { break; }
            }
        }
    }
    if ($port < 1 || $port > 65535) { $port = 0; }
    return $port;
}

/**
 * Steht das MQTT-Gateway auf Autostart?
 *
 * Der Schluessel heisst Gatewayautostart. Ein "Mqtt.Autostart" gibt es nicht -
 * wer danach fragt, warnt immer, und das ist im Bestand schon fuenfmal
 * passiert. Rueckgabe: true, false oder null (nicht feststellbar).
 */
function ic_mqtt_autostart()
{
    $gen = @json_decode((string) @file_get_contents(
        ic_paths()['home'] . '/config/system/general.json'), true);
    foreach (array(array('Mqtt', 'Gatewayautostart'),
                   array('mqtt', 'gatewayautostart')) as $paar) {
        list($a, $b) = $paar;
        if (isset($gen[$a]) && is_array($gen[$a]) && isset($gen[$a][$b])) {
            $w = $gen[$a][$b];
            return ($w === true || $w === 1 || $w === '1' || $w === 'true');
        }
    }
    return null;
}

/** Ein Thema fuer das MQTT-Gateway saeubern. */
function ic_mqtt_thema($thema)
{
    // Das Gateway trennt die UDP-Zeile an Leerzeichen: Verb, Thema, Rest.
    // Ein Leerzeichen IM Thema verschiebt alles dahinter. # und + sind im
    // MQTT-Thema Platzhalter und dort unzulaessig - der Ausloesername
    // kommt aber aus der Adresse und ist damit fremdbestimmt.
    $t = preg_replace('#[^A-Za-z0-9_/\-]#', '_', (string) $thema);
    return trim(preg_replace('#/+#', '/', $t), '/');
}

/** Eine Nutzlast fuer das MQTT-Gateway saeubern. */
function ic_mqtt_nutzlast($wert)
{
    // Zeilenumbrueche muessen weg: das Gateway liest zeilenweise. Ein
    // Umbruch mitten in der Nutzlast macht aus einer Nachricht zwei - die
    // zweite beginnt nicht mit dem Verb und wird verworfen.
    $w = str_replace(array("\r\n", "\r", "\n", "\t"), ' ', (string) $wert);
    return trim(preg_replace('/ {2,}/', ' ', $w));
}

/**
 * Eine Nachricht ueber das LoxBerry-MQTT-Gateway senden.
 *
 * Ohne die Bibliothek Bluerhinos\phpMQTT. Die ist seit Jahren
 * unveraendert, bringt einen eigenen TCP-Verbindungsaufbau samt
 * Anmeldung mit und war bis 1.5.0 der einzige Weg - faellt sie unter
 * PHP 8 aus, meldet das Plugin nichts mehr. Das Gateway gehoert seit
 * LoxBerry 3 zum System und nimmt Zeilen der Form "retain <Thema> <Wert>"
 * auf einem UDP-Port entgegen. Ein Paket, keine Verbindung, keine fremde
 * Bibliothek - und nichts, was den Aufruf aufhalten kann.
 *
 * Uebergeben wird nur der Teil HINTER dem Praefix ('' fuer das Sammelthema).
 */
function ic_mqtt_senden($unterthema, $wert, $retain = true)
{
    $port = ic_mqtt_udpport();
    if (!$port) {
        ic_log_gebremst('mqtt_port', 'MQTT: in der general.json steht kein UDP-Eingangsport '
            . 'des Gateways - es wird nichts veroeffentlicht.');
        return false;
    }
    if (!function_exists('socket_create')) {
        ic_log_gebremst('mqtt_sockets', 'MQTT: die PHP-Erweiterung sockets fehlt. '
            . 'Abhilfe: sudo apt install php-sockets');
        return false;
    }
    $s = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if (!$s) {
        ic_log_gebremst('mqtt_socket', 'MQTT: Socket liess sich nicht anlegen.');
        return false;
    }
    $thema = ic_mqtt_praefix();
    $unterthema = ic_mqtt_thema($unterthema);
    if ($unterthema !== '') { $thema .= '/' . $unterthema; }
    $msg = ($retain ? 'retain ' : 'publish ') . $thema . ' ' . ic_mqtt_nutzlast($wert);
    $ok = @socket_sendto($s, $msg, strlen($msg), 0, '127.0.0.1', $port);
    socket_close($s);
    if ($ok === false) {
        ic_log_gebremst('mqtt_senden', 'MQTT: das Senden an 127.0.0.1:' . $port
            . ' ist gescheitert.');
    }
    return $ok !== false;
}

/**
 * Der Herzschlag.
 *
 * Ohne ihn ist ein totes Plugin nicht von einem ruhigen zu unterscheiden:
 * ein virtueller Eingang behaelt seinen letzten Wert, und in der App sieht
 * dann alles normal aus. Gesendet wird die Loxone-Zeit des letzten Abrufs
 * (Sekunden seit 01.01.2009) - damit laesst sich in Loxone unmittelbar ein
 * Alter rechnen.
 */
function ic_mqtt_herzschlag()
{
    if (!ic_mqtt_an()) { return false; }
    ic_mqtt_senden('ok', (string) (time() - 1230768000));

    /* Die Archivzahl kostet vier Verzeichnisdurchlaeufe. Der Herzschlag laeuft
     * minuetlich; auf einer Anlage mit zehntausenden Archivdateien waere das
     * dauerhafte Last, die es bis 2.1.13 nicht gab. Gezaehlt wird deshalb
     * hoechstens alle fuenfzehn Minuten - fuer eine Zahl, die sich zwischen
     * zwei Klingelvorgaengen nicht aendert, ist das reichlich. */
    $mk = ic_merker_lesen('bilderzahl');
    if ($mk === null || (time() - $mk['zeit']) >= 900) {
        $z = ic_archiv_zahlen();
        ic_mqtt_senden('bilder', (string) $z['bilder']);
        ic_merker_setzen('bilderzahl', (string) $z['bilder']);
    }
    return true;
}

/**
 * Ist MQTT eingeschaltet?
 *
 * An EINER Stelle beantwortet. Bis hierher stand an vier Stellen
 * mqtt_enable === '1' und an drei !empty(...) - zwei Pruefungen auf denselben
 * Schalter laufen auseinander, sobald ein anderer Wert darin steht: dann
 * saendet der Herzschlag, waehrend die Bildmeldungen ausbleiben, und die
 * Selbstpruefung meldet MQTT als eingeschaltet.
 */
function ic_mqtt_an()
{
    $cfg = ic_config();
    $w = isset($cfg['mqtt_enable']) ? (string) $cfg['mqtt_enable'] : '0';
    return ($w === '1' || $w === 'on' || $w === 'true');
}

/** Alle Themen, die dieses Plugin veroeffentlicht - EINE Quelle. */
function ic_mqtt_themen()
{
    $p = ic_mqtt_praefix();
    return array(
        array($p,                   'MQTT.T_BILD'),
        array($p . '/video',        'MQTT.T_VIDEO'),
        array($p . '/trigger/NAME', 'MQTT.T_TRIGGER'),
        array($p . '/ai',           'MQTT.T_AI'),
        array($p . '/ai_count',     'MQTT.T_AI_COUNT'),
        array($p . '/timelapse',    'MQTT.T_TIMELAPSE'),
        array($p . '/ok',           'MQTT.T_OK'),
        array($p . '/bilder',       'MQTT.T_BILDER'),
    );
}

/* ==================================================================
 * Protokoll
 *
 * Bis 1.6.0 hat die Oberflaeche eine Logdatei ANGEZEIGT, die niemand
 * geschrieben hat - der Reiter blieb dauerhaft leer, ohne dass irgendwo ein
 * Fehler sichtbar wurde.
 *
 * ACHTUNG: <home>/log/ liegt auf dem LoxBerry auf einer RAMDISK. Diese Datei
 * ueberlebt keinen Neustart, und eine unbegrenzt wachsende Datei frisst
 * Arbeitsspeicher - deshalb die Rotation.
 * ================================================================== */

function ic_logdatei()
{
    $p = ic_paths();
    return $p['log'] . '/' . $p['plugin'] . '.log';
}

function ic_log($text)
{
    $p = ic_paths();
    if (!@is_dir($p['log'])) { @mkdir($p['log'], 0775, true); }
    $datei = ic_logdatei();
    if (@is_file($datei) && @filesize($datei) > 262144) {
        $rest = array_slice(@file($datei, FILE_IGNORE_NEW_LINES) ?: array(), -300);
        @file_put_contents($datei, implode("\n", $rest) . "\n");
    }
    return @file_put_contents($datei,
        '[' . date('Y-m-d H:i:s') . '] ' . $text . "\n", FILE_APPEND) !== false;
}

/**
 * Dieselbe Meldung hoechstens einmal je Zeitfenster.
 *
 * Die Tuerstation wird bei jedem Klingeln abgefragt. Ohne Bremse schriebe eine
 * Dauerstoerung - etwa eine ausgeschaltete Station - die Ramdisk voll.
 */
function ic_log_gebremst($schluessel, $text, $sekunden = 3600)
{
    $p = ic_paths();
    if (!@is_dir($p['data'])) { @mkdir($p['data'], 0775, true); }
    $f = $p['data'] . '/.meld_' . preg_replace('/[^a-z0-9_]/i', '', $schluessel);
    $letzte = @is_file($f) ? (int) @file_get_contents($f) : 0;
    if (time() - $letzte >= $sekunden) {
        @file_put_contents($f, (string) time());
        ic_log($text);
    }
    return true;
}

/**
 * Einen Merker mit Zeitstempel setzen bzw. lesen.
 *
 * Damit beantwortet der Reiter Test "wann lief der Zeitraffer zuletzt?" und
 * "wann wurde zuletzt aufgeraeumt?" - Fragen, auf die es bis 2.1.13 keine
 * Antwort gab, weil beide Cron-Laeufe ihre Ausgabe nach /dev/null schrieben
 * und keiner von beiden je eine Protokollzeile hinterliess.
 */
function ic_merker_setzen($name, $text = '')
{
    $p = ic_paths();
    if (!@is_dir($p['data'])) { @mkdir($p['data'], 0775, true); }
    $f = $p['data'] . '/.merker_' . preg_replace('/[^a-z0-9_]/i', '', $name);
    return @file_put_contents($f, time() . "\t" . str_replace("\n", ' ', (string) $text)) !== false;
}

function ic_merker_lesen($name)
{
    $p = ic_paths();
    $f = $p['data'] . '/.merker_' . preg_replace('/[^a-z0-9_]/i', '', $name);
    if (!@is_file($f)) { return null; }
    $roh = (string) @file_get_contents($f);
    $teile = explode("\t", $roh, 2);
    return array('zeit' => (int) $teile[0],
                 'text' => isset($teile[1]) ? $teile[1] : '');
}

/* ==================================================================
 * Archiv
 * ================================================================== */

/** Die Ordner des Archivs - aus EINER Quelle, damit sie nicht auseinanderlaufen. */
function ic_archivordner()
{
    $l = ic_paths()['legacy'];
    return array(
        'bild'      => $l . 'img_archive/',
        'video'     => $l . 'video_archive/',
        'timelapse' => $l . 'timelapse/',
    );
}

/**
 * Was liegt im Archiv?
 *
 * Bis 2.1.13 zaehlte die Startseite mit glob('*') im Videoordner - und damit
 * die Vorschaubilder mit. Nachgebaut mit 25 Aufnahmen: angezeigt wurden
 * 50 Videos. Eine Zahl mit falschem Namen ist so schlecht wie eine falsche
 * Zahl, denn sie wird nach ihrem Namen weiterverwendet.
 */
function ic_archiv_zahlen()
{
    $o = ic_archivordner();
    $z = function ($muster) {
        $f = glob($muster);
        if (!is_array($f)) { return array(0, 0); }
        $b = 0;
        foreach ($f as $d) { $b += (int) @filesize($d); }
        return array(count($f), $b);
    };
    list($nb, $bb) = $z($o['bild'] . '*.jpg');
    list($nv, $bv) = $z($o['video'] . '*.avi');
    list($nt, $bt) = $z($o['timelapse'] . '*.jpg');
    list($nvv, $bvv) = $z($o['video'] . '*.jpg');
    return array(
        'bilder' => $nb, 'bilder_byte' => $bb,
        'videos' => $nv, 'videos_byte' => $bv + $bvv,
        'timelapse' => $nt, 'timelapse_byte' => $bt,
        'summe_byte' => $bb + $bv + $bvv + $bt,
    );
}

/** Freier Platz auf dem Medium, auf dem das Archiv liegt. */
function ic_platz()
{
    $l = rtrim(ic_paths()['legacy'], '/');
    if (!@is_dir($l)) { return array(0, 0); }
    $frei = @disk_free_space($l);
    $ganz = @disk_total_space($l);
    return array($frei === false ? 0 : (float) $frei,
                 $ganz === false ? 0 : (float) $ganz);
}

/** Eine Byte-Zahl lesbar machen. */
function ic_byte($b)
{
    $b = (float) $b;
    $e = array('B', 'kB', 'MB', 'GB', 'TB');
    $i = 0;
    while ($b >= 1024 && $i < count($e) - 1) { $b /= 1024; $i++; }
    return ($i === 0 ? (string) (int) $b : number_format($b, 1, ',', '.')) . ' ' . $e[$i];
}

/** Die drei Grenzen der Aufbewahrung stehen an genau EINER Stelle. */
function ic_aufbewahrung()
{
    $cfg = ic_config();
    $n = function ($w) { return is_numeric($w) ? max(0, (int) $w) : 0; };
    return array(
        'tage' => $n(isset($cfg['cleanup_days']) ? $cfg['cleanup_days'] : 0),
        'zahl' => $n(isset($cfg['cleanup_count']) ? $cfg['cleanup_count'] : 0),
        'mb'   => $n(isset($cfg['cleanup_mb']) ? $cfg['cleanup_mb'] : 0),
    );
}

/* ==================================================================
 * Fremde Programme
 * ================================================================== */

/** Liegt ein Programm im Pfad? Die Antwort wird zwischengespeichert. */
function ic_programm($name)
{
    static $bekannt = array();
    if (array_key_exists($name, $bekannt)) { return $bekannt[$name]; }
    $bekannt[$name] = false;
    if (!preg_match('/^[a-z0-9_-]+$/i', $name)) { return false; }
    if (function_exists('shell_exec')) {
        $p = @shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null');
        $p = is_string($p) ? trim($p) : '';
        if ($p !== '') { $bekannt[$name] = $p; }
    }
    return $bekannt[$name];
}

/* ==================================================================
 * Adressen fuer Loxone und fuer den Anwender
 * ================================================================== */

/**
 * Der eigene Rechnername fuer Adressen, die AN DEN ANWENDER gehen.
 *
 * NICHT fuer Shell-Befehle verwenden - dafuer gibt es ic_eigene_basis().
 * $_SERVER['HTTP_HOST'] ist der Inhalt der Host-Kopfzeile und damit
 * vollstaendig vom Aufrufer bestimmt. Hier wird er wenigstens auf
 * unbedenkliche Zeichen beschraenkt.
 */
function ic_host()
{
    $h = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
    $h = preg_replace('/[^A-Za-z0-9\.\-:\[\]]/', '', $h);
    if ($h === '') {
        $h = gethostname() ?: 'loxberry';
    }
    return $h;
}

/**
 * Die Basisadresse fuer Aufrufe, die das Plugin an SICH SELBST richtet.
 *
 * Immer 127.0.0.1 - niemals HTTP_HOST. Genau darin lag bis 1.5.0 die
 * schwerste Luecke des Plugins: getvideo.php setzte HTTP_HOST unmaskiert
 * in eine Shell-Befehlszeile ein. Wer eine Anfrage mit einer selbst
 * gewaehlten Host-Kopfzeile schickte, brachte den LoxBerry dazu, jeden
 * beliebigen Befehl auszufuehren - ohne Anmeldung, aus dem gesamten Netz.
 */
function ic_eigene_basis()
{
    return 'http://127.0.0.1/plugins/' . ic_plugin_ordner();
}

/**
 * Die Adressen, die in Loxone eingetragen werden - EINE Quelle.
 *
 * Aus dieser Liste speisen sich der Reiter "Einbindung in Loxone", die
 * Loxone-Vorlage und der Reiter Test. Bis 2.1.13 standen dieselben Adressen
 * an drei Stellen im Quelltext; wer eine aendert, aendert sonst zwei.
 */
function ic_adressen($host, $token, $trigger = 'klingel', $station = '')
{
    $b = 'http://' . $host . '/plugins/' . ic_plugin_ordner() . '/';
    $t = '?token=' . rawurlencode($token !== '' ? $token : 'TOKEN');
    $s = $station !== '' ? '&station=' . rawurlencode($station) : '';
    return array(
        'bild'      => $b . 'getpicture.php' . $t . $s,
        'bild_trig' => $b . 'getpicture.php' . $t . $s . '&trigger=' . rawurlencode($trigger),
        'video'     => $b . 'getvideo.php' . $t . $s . '&s=10',
        'strom'     => $b . 'mjpgproxy.php' . $t . $s,
        'letztes'   => $b . 'bild.php' . $t,
        'selftest'  => $b . 'getpicture.php' . $t . '&selftest=1',
    );
}

/* ==================================================================
 * Loxone-Vorlage (XML-Export fuer Loxone Config)
 *
 * Nachbau nach dem Hausmuster: Attributreihenfolge, CRLF als Zeilenende und
 * der Tabulator vor den Kindelementen entsprechen den Ausfuhren aus Loxone
 * Config. templateType 3 kennzeichnet einen virtuellen Ausgang, 2 einen
 * HTTP-Eingang.
 *
 * Loxone Config LEGT BEIM IMPORT NEU AN und ueberschreibt nichts - zweimal
 * importiert heisst doppelte Objekte. Dieser Satz steht auch im Reiter.
 * ================================================================== */

function ic_xml_virtual_out(array $kopf, array $cmds)
{
    $crlf = "\r\n";
    $o = '<?xml version="1.0" encoding="utf-8"?>' . $crlf;
    $o .= '<VirtualOut ';
    $o .= 'HintText="' . ic_x(isset($kopf['hint']) ? $kopf['hint'] : '') . '" ';
    $o .= 'Title="' . ic_x($kopf['title']) . '" ';
    $o .= 'Comment="' . ic_x(isset($kopf['comment']) ? $kopf['comment'] : '') . '" ';
    $o .= 'Address="' . ic_x(isset($kopf['address']) ? $kopf['address'] : '') . '" ';
    $o .= 'CmdInit="" ';
    $o .= 'CloseAfterSend="true" ';
    $o .= 'CmdSep=""';
    $o .= '>' . $crlf;
    $o .= "\t" . '<Info templateType="3" minVersion="17010727"/>' . $crlf;
    foreach ($cmds as $c) {
        $o .= "\t" . '<VirtualOutCmd ';
        $o .= 'Title="' . ic_x($c['title']) . '" ';
        $o .= 'Comment="' . ic_x(isset($c['comment']) ? $c['comment'] : '') . '" ';
        $o .= 'CmdOnMethod="' . ic_x(isset($c['method']) ? $c['method'] : 'GET') . '" ';
        $o .= 'CmdOn="' . ic_x(isset($c['on']) ? $c['on'] : '') . '" ';
        $o .= 'CmdOffMethod="' . ic_x(isset($c['method']) ? $c['method'] : 'GET') . '" ';
        $o .= 'CmdOff="' . ic_x(isset($c['off']) ? $c['off'] : '') . '" ';
        $o .= 'Analog="' . (!empty($c['analog']) ? 'true' : 'false') . '" ';
        $o .= 'Repeat="0" ';
        $o .= 'RepeatRate="0" ';
        $o .= 'HintText=""';
        $o .= '/>' . $crlf;
    }
    $o .= '</VirtualOut>' . $crlf;
    return $o;
}

/**
 * Der virtuelle Eingang fuer die MQTT-Themen.
 *
 * Gateway-Eingaenge sind nackte VirtualIn - dafuer kennt Loxone Config kein
 * Vorlagenformat, das Gateway legt sie beim ersten Empfang selbst an. Der
 * Hauskunstgriff: VirtualInHttp mit einer Dummy-Adresse und einer sehr langen
 * Abholzeit erzeugen; Loxone legt die richtig benannten Eingaenge an, die
 * Werte kommen danach vom Gateway. Titel = Thema mit Unterstrichen,
 * Check=" ".
 *
 * Textthemen bleiben aussen vor - das nachgebaute Format ist nur fuer
 * Zahlenwerte belegt. Das steht auch im Hinweistext des Reiters.
 */
function ic_xml_virtual_in(array $kopf, array $cmds)
{
    $crlf = "\r\n";
    $o = '<?xml version="1.0" encoding="utf-8"?>' . $crlf;
    $o .= '<VirtualInHttp ';
    $o .= 'HintText="' . ic_x(isset($kopf['hint']) ? $kopf['hint'] : '') . '" ';
    $o .= 'Title="' . ic_x($kopf['title']) . '" ';
    $o .= 'Comment="' . ic_x(isset($kopf['comment']) ? $kopf['comment'] : '') . '" ';
    $o .= 'Address="' . ic_x(isset($kopf['address']) ? $kopf['address'] : '') . '" ';
    $o .= 'PollingTime="' . (int) (isset($kopf['polling']) ? $kopf['polling'] : 604800) . '"';
    $o .= '>' . $crlf;
    $o .= "\t" . '<Info templateType="2" minVersion="17010727"/>' . $crlf;
    foreach ($cmds as $c) {
        $o .= "\t" . '<VirtualInHttpCmd ';
        $o .= 'Title="' . ic_x($c['title']) . '" ';
        $o .= 'Comment="' . ic_x(isset($c['comment']) ? $c['comment'] : '') . '" ';
        $o .= 'Check="' . ic_x(isset($c['check']) ? $c['check'] : ' ') . '" ';
        $o .= 'Signed="true" ';
        $o .= 'Analog="' . (!empty($c['analog']) ? 'true' : 'false') . '" ';
        $o .= 'SourceValLow="0" DestValLow="0" ';
        $o .= 'SourceValHigh="1" DestValHigh="1" ';
        $o .= 'DefVal="0" ';
        $o .= 'MinVal="' . ic_x(isset($c['min']) ? $c['min'] : '0') . '" ';
        $o .= 'MaxVal="' . ic_x(isset($c['max']) ? $c['max'] : '0') . '" ';
        $o .= 'Unit="' . ic_x(isset($c['unit']) ? $c['unit'] : '<v.1>') . '" ';
        $o .= 'HintText=""';
        $o .= '/>' . $crlf;
    }
    $o .= '</VirtualInHttp>' . $crlf;
    return $o;
}

/**
 * Eine erzeugte Vorlage ausliefern.
 *
 * Die Anfuehrungszeichen um den Dateinamen sind Pflicht: ohne sie bricht
 * jeder Name, der ein Leerzeichen enthaelt.
 */
function ic_vorlage_ausliefern($name, $inhalt)
{
    header('Content-Type: application/x-download');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . strlen($inhalt));
    echo $inhalt;
}

/**
 * Die Vorlage der Steuerbefehle - ein virtueller Ausgang, ein Befehl je
 * Station und Aufgabe.
 */
function ic_vorlage_ausgang($host, $token)
{
    $st = ic_stationen();
    if (!$st) {
        $st = array(array('name' => 'Intercom', 'ip' => '', 'user' => '', 'pass' => '',
                          'ms' => 1, 'standbild' => ''));
    }
    $cmds = array();
    foreach ($st as $i => $s) {
        $nr = (string) ($i + 1);
        $a = ic_adressen($host, $token, 'klingel', $nr);
        $cmds[] = array(
            'title' => 'Foto ' . $s['name'],
            'comment' => 'Holt ein Standbild und legt es ins Archiv.',
            'on' => $a['bild_trig'],
        );
        $cmds[] = array(
            'title' => 'Video ' . $s['name'],
            'comment' => 'Nimmt 10 Sekunden auf; erlaubt sind 1 bis 300 ueber &s=.',
            'on' => $a['video'],
        );
    }
    return ic_xml_virtual_out(array(
        'title'   => 'Intercom (LoxBerry-Plugin)',
        'comment' => 'Erzeugt vom Plugin Intercom. Loxone Config legt beim Import NEU an.',
        'address' => '/dev/tcp/' . $host . '/80',
        'hint'    => 'Adresse pruefen: der Miniserver muss den LoxBerry unter diesem Namen erreichen.',
    ), $cmds);
}

/** Die Vorlage der Rueckmeldungen (MQTT-Gateway-Eingaenge). */
function ic_vorlage_eingang($host)
{
    $p = ic_mqtt_praefix();
    $cmds = array(
        array('title' => $p . '_ok',
              'comment' => 'Herzschlag: Loxone-Zeit des letzten Abrufs (Sekunden seit 01.01.2009).',
              'analog' => true, 'min' => '0', 'max' => '2147483647', 'unit' => '<v.1>'),
        array('title' => $p . '_bilder',
              'comment' => 'Zahl der Bilder im Archiv.',
              'analog' => true, 'min' => '0', 'max' => '1000000', 'unit' => '<v.1>'),
    );
    return ic_xml_virtual_in(array(
        'title'   => 'Intercom Rueckmeldungen (LoxBerry-Plugin)',
        'comment' => 'Die Werte kommen vom MQTT-Gateway, nicht von dieser Adresse.',
        'address' => 'http://localhost',
        'polling' => 604800,
        'hint'    => 'Nur Zahlenwerte. Texte (Bildadresse, erkannte Objekte) legt das '
                   . 'Gateway beim ersten Empfang selbst an.',
    ), $cmds);
}

/* ==================================================================
 * Selbstpruefung - EINE Quelle fuer den Reiter Test und fuer ?selftest=1
 *
 * Vier Regeln, an denen andere Plugins teuer gescheitert sind:
 *
 *   1. Jede Zeile, die ueber eine MENGE urteilt, prueft zuerst, ob die Menge
 *      leer ist. "Alle 0 von 0 in Ordnung" ist kein Haken.
 *   2. Eine Zusammenfassung darf nicht besser aussehen als ihr schlechtester
 *      Punkt. Ein Hinweis ist fuer "geht mich nichts an" da, nicht fuer
 *      "ich weiss es nicht" - dafuer gibt es 'unklar'.
 *   3. Die Ursache steht VOR der Wirkung: ob die Station antwortet, steht vor
 *      der Frage, ob Bilder da sind.
 *   4. Was das Netz befragt, laeuft NICHT bei jedem Seitenaufbau, sondern nur
 *      auf Knopfdruck.
 *
 * Eine Zeile traegt Schluessel, keine fertigen Saetze: 'frage' und 'antwort'
 * sind Sprachschluessel, wenn sie mit TEST. beginnen, sonst Klartext (Pfade,
 * Fehlermeldungen des Geraets). 'fargs'/'aargs' sind die Einsetzungen.
 * ================================================================== */

function ic_pz($lage, $frage, $antwort, $rat = '', $fargs = array(), $aargs = array())
{
    return array('lage' => $lage, 'frage' => $frage, 'antwort' => $antwort,
                 'rat' => $rat, 'fargs' => $fargs, 'aargs' => $aargs);
}

/**
 * Die Selbstpruefung.
 *
 * $mit_netz = false laesst alles weg, was hinausgeht - das ist der Zustand
 * beim gewoehnlichen Aufruf der Seite.
 */
function ic_selbsttest($mit_netz = false)
{
    $cfg = ic_config();
    $z = array();

    /* -------- Grundlagen -------- */
    $token = isset($cfg['aktionstoken']) ? (string) $cfg['aktionstoken'] : '';
    $z[] = $token !== ''
        ? ic_pz('ok', 'TEST.F_TOKEN', 'TEST.A_TOKEN_JA', '', array(), array(strlen($token)))
        : ic_pz('fehl', 'TEST.F_TOKEN', 'TEST.A_TOKEN_NEIN', 'TEST.R_TOKEN');

    $quelle = ic_titel_quelle();
    $z[] = $quelle !== ''
        ? ic_pz('ok', 'TEST.F_CFG', 'TEST.A_CFG_JA', '', array(), array($quelle, ic_fassung()))
        : ic_pz('hinweis', 'TEST.F_CFG', 'TEST.A_CFG_NEIN', 'TEST.R_CFG');

    /* -------- Stationen: erst die Menge, dann das Urteil -------- */
    $st = ic_stationen();
    if (!$st) {
        $z[] = ic_pz('fehl', 'TEST.F_STATIONEN', 'TEST.A_STATIONEN_KEINE', 'TEST.R_STATIONEN');
    } else {
        $namen = array();
        foreach ($st as $s) { $namen[] = $s['name'] . ' (' . $s['ip'] . ')'; }
        $z[] = ic_pz('ok', 'TEST.F_STATIONEN', 'TEST.A_STATIONEN', '',
                     array(), array(count($st), implode(', ', $namen)));
        foreach ($st as $s) {
            list($u, $pw, $q) = ic_zugangsdaten($s);
            $z[] = $u !== ''
                ? ic_pz('ok', 'TEST.F_ZUGANG', 'TEST.A_ZUGANG_JA', '',
                        array($s['name']), array($u, $q))
                : ic_pz('fehl', 'TEST.F_ZUGANG', 'TEST.A_ZUGANG_NEIN', 'TEST.R_ZUGANG',
                        array($s['name']), array($q));
        }
    }

    /* -------- Netz: nur auf Knopfdruck -------- */
    if ($mit_netz && $st) {
        foreach ($st as $s) {
            $r = ic_bild_holen($s, 'strom');
            $z[] = $r['ok']
                ? ic_pz('ok', 'TEST.F_STROM', 'TEST.A_STROM_JA', '', array($s['name']),
                        array(ic_byte(strlen($r['bild'])), number_format($r['dauer'], 1, ',', '.')))
                : ic_pz('fehl', 'TEST.F_STROM', $r['fehler'], 'TEST.R_STROM', array($s['name']));
            $r2 = ic_standbild_holen($s);
            $weg = isset($cfg['bildweg']) ? (string) $cfg['bildweg'] : 'strom';
            $z[] = $r2['ok']
                ? ic_pz('ok', 'TEST.F_STANDBILD', 'TEST.A_STANDBILD_JA',
                        $weg === 'strom' ? 'TEST.R_STANDBILD' : '',
                        array($s['name']), array(ic_byte(strlen($r2['bild']))))
                : ic_pz('hinweis', 'TEST.F_STANDBILD', $r2['fehler'],
                        'TEST.R_STANDBILD_NEIN', array($s['name']));
        }
    } elseif ($st) {
        $z[] = ic_pz('unklar', 'TEST.F_NETZ', 'TEST.A_NETZ_UNGEPRUEFT', 'TEST.R_NETZ');
    }

    /* -------- Der eigene Endpunkt, wirklich aufgerufen -------- */
    if ($mit_netz) {
        $url = ic_eigene_basis() . '/getpicture.php?selftest=1&token=' . rawurlencode($token);
        list($inhalt, $code) = ic_http_holen_voll($url, 6);
        $d = is_string($inhalt) ? json_decode($inhalt, true) : null;
        $z[] = (is_array($d) && !empty($d['selftest']))
            ? ic_pz('ok', 'TEST.F_ENDPUNKT', 'TEST.A_ENDPUNKT_JA', '', array(), array($code))
            : ic_pz('fehl', 'TEST.F_ENDPUNKT', 'TEST.A_ENDPUNKT_NEIN', 'TEST.R_ENDPUNKT',
                    array(), array($code));
    } else {
        $z[] = ic_pz('unklar', 'TEST.F_ENDPUNKT', 'TEST.A_NETZ_UNGEPRUEFT', 'TEST.R_NETZ');
    }

    /* -------- Fremde Programme -------- */
    foreach (array('ffmpeg' => 'TEST.R_FFMPEG', 'wget' => 'TEST.R_WGET') as $prg => $rat) {
        $pfad = ic_programm($prg);
        $z[] = $pfad
            ? ic_pz('ok', 'TEST.F_PROGRAMM', $pfad, '', array($prg))
            : ic_pz('fehl', 'TEST.F_PROGRAMM', 'TEST.A_PROGRAMM_NEIN', $rat, array($prg));
    }
    $z[] = function_exists('imagecreatefromjpeg')
        ? ic_pz('ok', 'TEST.F_GD', 'TEST.A_JA')
        : ic_pz((empty($cfg['timestamp_image']) && empty($cfg['timestamp_video']))
                ? 'hinweis' : 'fehl', 'TEST.F_GD', 'TEST.A_NEIN', 'TEST.R_GD');
    $z[] = function_exists('socket_create')
        ? ic_pz('ok', 'TEST.F_SOCKETS', 'TEST.A_JA')
        : ic_pz(empty($cfg['mqtt_enable']) ? 'hinweis' : 'fehl',
                'TEST.F_SOCKETS', 'TEST.A_NEIN', 'TEST.R_SOCKETS');

    /* -------- MQTT -------- */
    if (!empty($cfg['mqtt_enable'])) {
        $port = ic_mqtt_udpport();
        $z[] = $port
            ? ic_pz('ok', 'TEST.F_MQTTPORT', (string) $port)
            : ic_pz('fehl', 'TEST.F_MQTTPORT', 'TEST.A_MQTTPORT_NEIN', 'TEST.R_MQTTPORT');
        $auto = ic_mqtt_autostart();
        if ($auto === true) {
            $z[] = ic_pz('ok', 'TEST.F_MQTTAUTO', 'TEST.A_JA');
        } elseif ($auto === false) {
            $z[] = ic_pz('fehl', 'TEST.F_MQTTAUTO', 'TEST.A_NEIN', 'TEST.R_MQTTAUTO');
        } else {
            $z[] = ic_pz('unklar', 'TEST.F_MQTTAUTO', 'TEST.A_UNKLAR');
        }
        $z[] = ic_pz('ok', 'TEST.F_MQTTABO', ic_mqtt_praefix() . '/#');
    } else {
        $z[] = ic_pz('hinweis', 'TEST.F_MQTT', 'TEST.A_MQTT_AUS');
    }

    /* -------- Archiv -------- */
    $schreibbar = true;
    foreach (ic_archivordner() as $d) {
        if (!@is_dir($d) || !@is_writable($d)) { $schreibbar = false; }
    }
    $z[] = $schreibbar
        ? ic_pz('ok', 'TEST.F_ARCHIVORDNER', rtrim(ic_paths()['legacy'], '/'))
        : ic_pz('fehl', 'TEST.F_ARCHIVORDNER', rtrim(ic_paths()['legacy'], '/'),
                'TEST.R_ARCHIVORDNER');

    $zahlen = ic_archiv_zahlen();
    if ($zahlen['bilder'] + $zahlen['videos'] + $zahlen['timelapse'] === 0) {
        // Die leere Menge bekommt KEIN Haekchen. Eine wahre Aussage ueber
        // nichts ist keine Auskunft - und sie steht genau dort, wo jemand
        // hinsieht, WEIL etwas fehlt.
        $z[] = ic_pz('unklar', 'TEST.F_ARCHIV', 'TEST.A_ARCHIV_LEER', 'TEST.R_ARCHIV_LEER');
    } else {
        $z[] = ic_pz('ok', 'TEST.F_ARCHIV', 'TEST.A_ARCHIV', '', array(),
                     array($zahlen['bilder'], $zahlen['videos'], $zahlen['timelapse'],
                           ic_byte($zahlen['summe_byte'])));
    }
    list($frei, $ganz) = ic_platz();
    if ($ganz > 0) {
        $z[] = ($frei / $ganz > 0.1)
            ? ic_pz('ok', 'TEST.F_PLATZ', 'TEST.A_PLATZ', '', array(),
                    array(ic_byte($frei), ic_byte($ganz)))
            : ic_pz('fehl', 'TEST.F_PLATZ', 'TEST.A_PLATZ', 'TEST.R_PLATZ', array(),
                    array(ic_byte($frei), ic_byte($ganz)));
    } else {
        $z[] = ic_pz('unklar', 'TEST.F_PLATZ', 'TEST.A_UNKLAR');
    }
    $g = ic_aufbewahrung();
    $z[] = ($g['tage'] > 0 || $g['zahl'] > 0 || $g['mb'] > 0)
        ? ic_pz('ok', 'TEST.F_GRENZE', 'TEST.A_GRENZE', '', array(),
                array($g['tage'], $g['zahl'], $g['mb']))
        : ic_pz('fehl', 'TEST.F_GRENZE', 'TEST.A_GRENZE_KEINE', 'TEST.R_GRENZE');

    /* -------- Die beiden Cron-Laeufe -------- */
    foreach (array('timelapse' => 'TEST.F_LAUF_TL', 'cleanup' => 'TEST.F_LAUF_CU') as $m => $frage) {
        $mk = ic_merker_lesen($m);
        if ($mk === null) {
            $z[] = ic_pz('unklar', $frage, 'TEST.A_LAUF_NIE', 'TEST.R_LAUF');
        } else {
            $z[] = ic_pz((time() - $mk['zeit']) < 172800 ? 'ok' : 'hinweis', $frage,
                         'TEST.A_LAUF', '', array(),
                         array(date('d.m.Y H:i', $mk['zeit']), $mk['text']));
        }
    }
    $z[] = @is_file(ic_logdatei())
        ? ic_pz('ok', 'TEST.F_LOG', ic_logdatei())
        : ic_pz('hinweis', 'TEST.F_LOG', 'TEST.A_LOG_NEIN');

    /* -------- Speicherort -------- */
    $sp = isset($cfg['storage_path']) ? trim((string) $cfg['storage_path']) : '';
    if ($sp === '') {
        $z[] = ic_pz('ok', 'TEST.F_SPEICHER', 'TEST.A_SPEICHER_SD');
    } else {
        $link = rtrim(ic_paths()['legacy'], '/');
        $ziel = @is_link($link) ? (string) @readlink($link) : '';
        $soll = rtrim($sp, '/') . '/' . ic_plugin_ordner() . '_data';
        $z[] = ($ziel === $soll)
            ? ic_pz('ok', 'TEST.F_SPEICHER', $ziel)
            : ic_pz('fehl', 'TEST.F_SPEICHER', 'TEST.A_SPEICHER_FALSCH', 'TEST.R_SPEICHER',
                    array(), array($ziel !== '' ? $ziel : '-', $soll));
    }

    /* -------- Die eigene Oberflaeche gegen sich selbst -------- */
    $z[] = ic_pruefe_reiter();
    $z[] = ic_pruefe_vorlage();

    return $z;
}

/**
 * Reiterleiste, Bereiche und Positivliste gegeneinander zaehlen.
 *
 * Gelesen wird aus DERSELBEN Datei, die die Oberflaeche ausliefert - sonst
 * gaebe es eine zweite Stelle, die man mitpflegen muss. Die gesuchten Formen
 * stehen in dieser Datei absichtlich nur in den Suchmustern und nirgends im
 * Klartext eines Kommentars: ein Beispiel in der gesuchten Schreibweise
 * wuerde mitgezaehlt.
 */
function ic_pruefe_reiter()
{
    $t = @file_get_contents(__DIR__ . '/index.php');
    if ($t === false) {
        return ic_pz('unklar', 'TEST.F_REITER', 'TEST.A_UNKLAR');
    }
    $leiste = preg_match_all('/data\-ziel="tab\-([a-z]+)"/', $t, $m1) ? $m1[1] : array();
    $bereiche = preg_match_all('/class="sm\-seite[^"]*" id="tab\-([a-z]+)"/', $t, $m2) ? $m2[1] : array();
    $liste = array();
    if (preg_match('/\$ic_tabliste\s*=\s*array\((.*?)\);/s', $t, $m3)
        && preg_match_all("/'tab\\-([a-z]+)'/", $m3[1], $m4)) {
        $liste = $m4[1];
    }
    // Doppelte Eintraege koennen entstehen, wenn eine Stelle in zwei
    // ausgeschriebenen Zweigen steht - gezaehlt wird der Reiter, nicht die
    // Fundstelle.
    $leiste = array_values(array_unique($leiste));
    $bereiche = array_values(array_unique($bereiche));
    $liste = array_values(array_unique($liste));
    sort($leiste); sort($bereiche); sort($liste);
    return ($leiste === $bereiche && $bereiche === $liste && count($liste) > 0)
        ? ic_pz('ok', 'TEST.F_REITER', 'TEST.A_REITER', '', array(), array(count($liste)))
        : ic_pz('fehl', 'TEST.F_REITER', 'TEST.A_REITER_UNGLEICH', 'TEST.R_REITER',
                array(), array(count($leiste), count($bereiche), count($liste)));
}

/** Die erzeugte Vorlage durch den XML-Leser schicken - wohlgeformt oder nicht. */
function ic_pruefe_vorlage()
{
    $cfg = ic_config();
    $token = isset($cfg['aktionstoken']) ? (string) $cfg['aktionstoken'] : 'TOKEN';
    $xml = ic_vorlage_ausgang(ic_host(), $token) . ic_vorlage_eingang(ic_host());
    $vorher = libxml_use_internal_errors(true);
    $ok1 = simplexml_load_string(ic_vorlage_ausgang(ic_host(), $token)) !== false;
    $ok2 = simplexml_load_string(ic_vorlage_eingang(ic_host())) !== false;
    $fehler = '';
    if (!$ok1 || !$ok2) {
        $e = libxml_get_errors();
        $fehler = $e ? trim($e[0]->message) : 'unbekannt';
    }
    libxml_clear_errors();
    libxml_use_internal_errors($vorher);
    return ($ok1 && $ok2)
        ? ic_pz('ok', 'TEST.F_VORLAGE', 'TEST.A_VORLAGE', '', array(), array(strlen($xml)))
        : ic_pz('fehl', 'TEST.F_VORLAGE', $fehler, 'TEST.R_VORLAGE');
}

/**
 * Die Zusammenfassung.
 *
 * Sie darf nicht besser aussehen als ihr schlechtester Punkt: 'unklar' zaehlt
 * NICHT als bestanden. "22 von 22 bestanden", waehrend nichts funktionierte,
 * ist die teuerste Ausprägung dieser Fehlerklasse im Bestand.
 */
function ic_selbsttest_bilanz(array $zeilen)
{
    $n = array('ok' => 0, 'fehl' => 0, 'hinweis' => 0, 'unklar' => 0);
    foreach ($zeilen as $z) { $n[$z['lage']]++; }
    $gewertet = $n['ok'] + $n['fehl'] + $n['unklar'];
    return array('ok' => $n['ok'], 'fehl' => $n['fehl'], 'hinweis' => $n['hinweis'],
                 'unklar' => $n['unklar'], 'gewertet' => $gewertet,
                 'bestanden' => ($n['fehl'] === 0 && $n['unklar'] === 0));
}

/* ==================================================================
 * Archivordner und Speicherort
 *
 * Bis 2.1.13 stand beides in config.php - und die wird von JEDEM Endpunkt
 * ganz oben eingebunden, VOR der Token-Pruefung. Eine Anfrage ohne Token
 * legte damit bis zu vier Verzeichnisse an, und war ein Speicherort
 * eingetragen, liefen sogar zwei Shell-Aufrufe (cp -rn und rm -rf) an.
 * "Der unangemeldete Endpunkt darf nichts anlegen" - deshalb passiert das
 * jetzt nur noch dort, wo es hingehoert: nach der Pruefung bzw. beim
 * Speichern in der Oberflaeche.
 * ================================================================== */

/** Die Archivordner anlegen, falls sie fehlen. Erst NACH der Token-Pruefung. */
function ic_archiv_sicherstellen()
{
    $l = rtrim(ic_paths()['legacy'], '/');
    if (!@file_exists($l)) {
        // 0775 statt 0777: fuer alle beschreibbar muss das Archiv nicht sein.
        @mkdir($l, 0775, true);
    }
    foreach (ic_archivordner() as $d) {
        if (!@file_exists($d)) { @mkdir($d, 0775, true); }
    }
    return @is_dir($l);
}

/**
 * Den eingestellten Speicherort einrichten.
 *
 * Ist ein Pfad hinterlegt und beschreibbar, wird
 * webfrontend/legacy/<ordner>_data als Symlink dorthin gefuehrt - alle
 * Archiv-Adressen funktionieren dadurch unveraendert weiter.
 *
 * Laeuft NUR aus der Oberflaeche (Speichern) und nur mit gueltigem Merkmal.
 * Der Umzug ist ausserdem gegen Parallellaeufe gesperrt: zwei gleichzeitige
 * Aufrufe koennten sonst dieselben Dateien gleichzeitig kopieren und loeschen.
 *
 * Rueckgabe: array(ok, meldung)
 */
function ic_speicherort_anwenden()
{
    $cfg = ic_config();
    $storage = isset($cfg['storage_path']) ? rtrim(trim((string) $cfg['storage_path']), '/') : '';
    $link = rtrim(ic_paths()['legacy'], '/');
    if ($storage === '') {
        ic_archiv_sicherstellen();
        return array(true, '');
    }
    if (!@is_dir($storage) || !@is_writable($storage)) {
        return array(false, $storage);
    }
    $sperre = ic_sperre('speicherort');
    if ($sperre === false) {
        return array(false, 'Ein anderer Vorgang arbeitet gerade am Speicherort.');
    }
    // Aus dem ORDNERNAMEN abgeleitet, nicht fest eingetragen: sonst zeigt
    // eine Zweitinstallation (intercom_01) auf dasselbe Archiv.
    $ziel = $storage . '/' . ic_plugin_ordner() . '_data';
    if (!@file_exists($ziel)) { @mkdir($ziel, 0775, true); }
    $meldung = '';
    if (@is_link($link)) {
        if (@readlink($link) !== $ziel) {
            @unlink($link);
            @symlink($ziel, $link);
        }
    } elseif (@is_dir($link)) {
        // vorhandene Daten einmalig auf den neuen Speicher uebernehmen
        @shell_exec('cp -rn ' . escapeshellarg($link) . '/. ' . escapeshellarg($ziel) . '/ 2>/dev/null');
        @shell_exec('rm -rf ' . escapeshellarg($link));
        @symlink($ziel, $link);
        $meldung = 'verschoben';
    } else {
        @symlink($ziel, $link);
    }
    ic_archiv_sicherstellen();
    flock($sperre, LOCK_UN);
    fclose($sperre);
    return array(@is_dir($link), $meldung);
}

/**
 * Ein Bild mit Zeitstempel versehen.
 *
 * Bis 2.1.13 stand der Aufruf ohne Pruefung da: imagecreatefromjpeg()
 * liefert bei einer beschaedigten Datei false, und imagecolorallocate(false,
 * ...) ist unter PHP 8 ein TypeError - also ein Abbruch mitten im Klingelweg.
 * In timelapse.php war die Pruefung vorhanden, in getpicture.php nicht:
 * dieselbe Aufgabe, zwei Sorgfaltsgrade.
 */
function ic_zeitstempel_ins_bild($datei, $text = null)
{
    if (!function_exists('imagecreatefromjpeg')) { return false; }
    if (!@is_file($datei)) { return false; }
    $text = $text === null ? date('d.m.Y H:i:s') : $text;
    $img = @imagecreatefromjpeg($datei);
    if (!$img) {
        ic_log_gebremst('gd', 'Der Zeitstempel liess sich nicht setzen: die Datei '
            . basename($datei) . ' ist kein lesbares JPEG.');
        return false;
    }
    $weiss = imagecolorallocate($img, 255, 255, 255);
    $schwarz = imagecolorallocate($img, 0, 0, 0);
    imagefilledrectangle($img, 9, 29, strlen($text) * imagefontwidth(5) + 11, 45, $schwarz);
    imagestring($img, 5, 10, 30, $text, $weiss);
    $ok = @imagejpeg($img, $datei);
    imagedestroy($img);
    return $ok;
}

/**
 * Ein Dateiname fuer das Archiv - ohne Doppelpunkte.
 *
 * Bis 2.1.13 stand im Bildnamen date("Y.m.d-H:i:s"). Auf FAT32, exFAT und
 * NTFS ist ':' im Dateinamen unzulaessig - und genau dort landet das Archiv,
 * sobald jemand den Speicherort auf einen USB-Stick legt, wofuer das Feld
 * ausdruecklich gedacht ist. Gemessen auf diesem Rechner: file_put_contents
 * scheitert mit "Failed to open stream: No such file or directory".
 * getvideo.php macht es an derselben Stelle seit jeher richtig.
 */
function ic_archivname($zusatz = '', $endung = 'jpg')
{
    $z = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $zusatz);
    return date('Y_m_d-H_i_s') . ($z !== '' ? '-' . $z : '') . '-intercom.' . $endung;
}

/**
 * Blaettern - Rechnung an EINER Stelle.
 *
 * Bis 2.1.13 stand in beiden Galerien
 *     $last_page = (int)($total / $per_page);
 *     $offset    = ($per_page + 1) * ($page - 1);
 * Zwei Fehler in zwei Zeilen: der Versatz springt je Seite um EINS zu weit,
 * und die letzte, angebrochene Seite gibt es gar nicht. Nachgerechnet mit
 * 40 Bildern und 18 je Seite: vier Bilder (Index 18, 37, 38, 39) waren ueber
 * die Oberflaeche nicht erreichbar, und bei 10 Bildern stand in der Kopfzeile
 * "Seite 1/0".
 *
 * Rueckgabe: array(seite, letzte, versatz, ende)
 */
function ic_blaettern($gesamt, $je_seite, $wunsch)
{
    $je_seite = max(1, (int) $je_seite);
    $gesamt = max(0, (int) $gesamt);
    $letzte = max(1, (int) ceil($gesamt / $je_seite));
    $seite = (is_numeric($wunsch) && (int) $wunsch >= 1) ? (int) $wunsch : 1;
    if ($seite > $letzte) { $seite = $letzte; }
    $versatz = ($seite - 1) * $je_seite;
    $ende = min($gesamt, $versatz + $je_seite);
    return array($seite, $letzte, $versatz, $ende);
}

/* ==================================================================
 * Befristete Bildlinks
 *
 * Fuer Mails und Meldungen. Bis 2.1.13 haben Anwender dort die Adresse von
 * getpicture.php eingetragen - also die AUSLOESEADRESSE samt Token: wer im
 * Mail darauf klickte, machte eine neue Aufnahme, statt das Bild vom
 * Klingeln zu sehen, und das Token stand dauerhaft in der Mail.
 *
 * Ein Link traegt einen eigenen Code, laeuft nach einer einstellbaren Zeit ab
 * und hat eine Obergrenze an Abrufen. Das Zugriffstoken kommt darin nicht vor.
 * ================================================================== */

function ic_bildlink_datei()
{
    return ic_paths()['data'] . '/bildlinks.json';
}

function ic_bildlink_liste()
{
    $d = @file_get_contents(ic_bildlink_datei());
    $a = $d === false ? null : json_decode($d, true);
    if (!is_array($a)) { return array(); }
    // Abgelaufene gleich mit ausraeumen - sonst waechst die Datei ewig.
    $jetzt = time();
    $rest = array();
    foreach ($a as $code => $e) {
        if (is_array($e) && isset($e['bis']) && (int) $e['bis'] > $jetzt
            && (int) $e['rest'] > 0) {
            $rest[$code] = $e;
        }
    }
    return $rest;
}

function ic_bildlink_erzeugen($stunden = 24, $abrufe = 5)
{
    $stunden = max(1, min(720, (int) $stunden));
    $abrufe = max(1, min(1000, (int) $abrufe));
    $code = ic_token_neu(20);
    $liste = ic_bildlink_liste();
    $liste[$code] = array('bis' => time() + $stunden * 3600, 'rest' => $abrufe,
                          'erzeugt' => time());
    $js = json_encode($liste, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($js === false || !ic_datei_ersetzen(ic_bildlink_datei(), $js, 0600)) {
        return '';
    }
    return $code;
}

/* ==================================================================
 * Zeitraffer, Intervallaufnahme und Aufraeumen
 *
 * Die Arbeit steht HIER, nicht in den Cron-Skripten: der Reiter Test soll
 * dieselbe Arbeit ausloesen koennen wie der Cron. Bis 2.1.13 zeigten zwei
 * Knoepfe im Reiter Test auf timelapse.php und cleanup.php - beide weisen
 * HTTP-Aufrufe ab, beide Knoepfe endeten also ausnahmslos auf einer
 * Fehlerseite (ueber HTTP gemessen: 403).
 * ================================================================== */

/**
 * Ein Zeitrafferbild aufnehmen.
 *
 * $erzwingen = true nimmt sofort auf, ohne auf Uhrzeit und Tagesbild zu
 * achten - das ist der Knopf im Reiter Test.
 *
 * Rueckgabe: array(ok, meldung, datei)
 */
function ic_timelapse_lauf($erzwingen = false)
{
    $cfg = ic_config();
    if (!$erzwingen) {
        /* AUSGESCHALTET IST KEIN FEHLER - und schon gar keiner, den man jede
         * Minute meldet.
         *
         * Der Zeitraffer ist ab Werk aus, der Cron laeuft minuetlich, und seit
         * 2.2.0 geht dessen Ausgabe an den Systemlogger statt nach /dev/null.
         * Eine Rueckgabe mit Meldung ergaebe damit auf JEDER Standardanlage
         * 1440 Zeilen am Tag, jede mit dem Wort FEHLER, fuer einen voellig
         * gewoehnlichen Zustand - und in einem Protokoll, das jede Minute
         * dasselbe ruft, findet niemand mehr die Zeile, die zaehlt. */
        if (empty($cfg['timelapse_enable']) || $cfg['timelapse_enable'] !== 'on') {
            return array(false, '', '');
        }
        $t = isset($cfg['timelapse_time']) ? trim((string) $cfg['timelapse_time']) : '';
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $t, $m)) {
            // Das ist ein echter Fehler - aber einer, der bis zur naechsten
            // Aenderung bestehen bleibt. Also hoechstens einmal je Stunde.
            ic_log_gebremst('tlzeit', 'Zeitraffer: die eingestellte Uhrzeit "' . $t
                . '" ist keine gueltige Angabe (HH:MM) - es wird nichts aufgenommen.');
            return array(false, '', '');
        }
        if (date('H:i') !== sprintf('%02d:%02d', $m[1], $m[2])) {
            return array(false, '', '');   // nicht die Minute - kein Fehler, keine Meldung
        }
    }
    $st = ic_stationen();
    if (!$st) {
        return array(false, 'Es ist keine Tuerstation eingerichtet.', '');
    }
    ic_archiv_sicherstellen();
    $o = ic_archivordner();
    $ziel = $o['timelapse'] . date('Y_m_d') . '-timelapse.jpg';
    if (!$erzwingen && @is_file($ziel)) {
        return array(false, '', '');       // heute schon aufgenommen
    }
    if ($erzwingen && @is_file($ziel)) {
        $ziel = $o['timelapse'] . date('Y_m_d-H_i_s') . '-timelapse.jpg';
    }
    $r = ic_bild_holen($st[0]);
    if (!$r['ok']) {
        ic_log('Zeitraffer: kein Bild von "' . $st[0]['name'] . '" - ' . $r['fehler']);
        ic_merker_setzen('timelapse', 'kein Bild: ' . $r['fehler']);
        return array(false, $r['fehler'], '');
    }
    if (!ic_datei_ersetzen($ziel, $r['bild'])) {
        ic_log('Zeitraffer: das Bild liess sich nicht schreiben: ' . $ziel);
        ic_merker_setzen('timelapse', 'nicht schreibbar');
        return array(false, 'Das Bild liess sich nicht schreiben: ' . $ziel, '');
    }
    if (!empty($cfg['timestamp_image']) && $cfg['timestamp_image'] === 'on') {
        ic_zeitstempel_ins_bild($ziel);
    }
    ic_log('Zeitrafferbild aufgenommen: ' . basename($ziel) . ' (' . ic_byte(strlen($r['bild'])) . ')');
    ic_merker_setzen('timelapse', basename($ziel));
    if (!empty($cfg['mqtt_enable']) && $cfg['mqtt_enable'] === '1') {
        ic_mqtt_senden('timelapse', json_encode(array(
            'timestamp' => date('d.m.Y-H:i:s'), 'file' => basename($ziel))));
    }

    /* Zeitraffer-Video im Hintergrund neu erzeugen. */
    if (!empty($cfg['timelapse_video']) && $cfg['timelapse_video'] === 'on') {
        $ffmpeg = ic_programm('ffmpeg');
        if (!$ffmpeg) {
            ic_log_gebremst('tlvideo', 'Das Zeitraffer-Video wurde angefordert, aber '
                . 'ffmpeg ist nicht installiert.');
        } else {
            $video = $o['timelapse'] . 'zeitraffer.mp4';
            // escapeshellarg auf das Muster ist hier RICHTIG: -pattern_type glob
            // laesst ffmpeg selbst aufloesen; eine Aufloesung durch die Shell
            // waere der Fehler.
            $cmd = escapeshellarg($ffmpeg) . ' -y -pattern_type glob -framerate 10 -i '
                 . escapeshellarg($o['timelapse'] . '*-timelapse.jpg')
                 . ' -vf ' . escapeshellarg('scale=trunc(iw/2)*2:trunc(ih/2)*2')
                 . ' -c:v libx264 -pix_fmt yuv420p ' . escapeshellarg($video);
            shell_exec($cmd . ' > /dev/null 2>&1 &');
            ic_log('Zeitraffer-Video wird neu erzeugt: ' . basename($video));
        }
    }
    return array(true, basename($ziel), $ziel);
}

/**
 * Objekterkennung an einem Bild.
 *
 * Steht hier und nicht in getpicture.php, weil sie an ZWEI Stellen gebraucht
 * wird: beim Klingeln und bei der Aufnahme im Takt. Der Hinweistext der
 * Takteinstellung sagt "mit Objekterkennung, falls sie eingeschaltet ist" -
 * und ein Satz, der eine Eigenschaft zusichert, ist kein Beleg dafuer. Also
 * bekommt der Takt dieselbe Funktion wie die Klingel.
 *
 * DeepStack / CodeProject.AI-kompatibel. Rueckgabe: leeres Feld, wenn
 * ausgeschaltet, nicht erreichbar oder ohne Fund; sonst
 * array('objects' => [...], 'count' => n).
 */
function ic_ki_erkennen($bilddatei)
{
    $cfg = ic_config();
    if (empty($cfg['ai_enable']) || $cfg['ai_enable'] !== 'on'
        || empty($cfg['ai_url']) || !function_exists('curl_init')
        || !@is_file($bilddatei)) {
        return array();
    }
    $minconf = (isset($cfg['ai_minconf']) && is_numeric($cfg['ai_minconf']))
             ? ((float) $cfg['ai_minconf']) / 100 : 0.5;
    $ch = curl_init($cfg['ai_url']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array(
        'image' => new CURLFile($bilddatei, 'image/jpeg', basename($bilddatei)),
        'min_confidence' => $minconf,
    ));
    $antwort = @curl_exec($ch);
    $fehler = curl_error($ch);
    curl_close($ch);
    if ($antwort === false || $antwort === '') {
        ic_log_gebremst('ki', 'Die Objekterkennung war nicht erreichbar: '
            . ($fehler !== '' ? $fehler : 'keine Antwort'));
        return array();
    }
    $daten = @json_decode((string) $antwort, true);
    if (!is_array($daten) || !isset($daten['predictions']) || !is_array($daten['predictions'])) {
        ic_log_gebremst('ki_form', 'Die Objekterkennung antwortete in einer Form, die '
            . 'dieses Plugin nicht kennt - erwartet wird ein Feld predictions.');
        return array();
    }
    $labels = array();
    foreach ($daten['predictions'] as $vorhersage) {
        if (isset($vorhersage['label'])) {
            $labels[] = $vorhersage['label'] . (isset($vorhersage['confidence'])
                ? ' (' . round($vorhersage['confidence'] * 100) . '%)' : '');
        }
    }
    return array('objects' => $labels, 'count' => count($labels));
}

/** Ein erkanntes Ergebnis veroeffentlichen - an EINER Stelle. */
function ic_ki_melden(array $ai)
{
    if (!$ai || !ic_mqtt_an()) { return false; }
    ic_mqtt_senden('ai', json_encode($ai));
    ic_mqtt_senden('ai_count', (string) $ai['count']);
    return true;
}

/**
 * Aufnahme in festem Takt.
 *
 * Der letzte offene Punkt der Wunschliste im README ("Bild alle X Sekunden").
 * Der Takt steht in Minuten, weil der Cron minuetlich laeuft - eine kuerzere
 * Angabe waere eine Zahl, die nichts bewirkt.
 *
 * Ab Werk AUS: eine Aufnahme alle paar Minuten fuellt das Archiv, und das
 * soll niemand nach einem Update ungefragt vorfinden.
 */
function ic_intervall_lauf()
{
    $cfg = ic_config();
    $min = isset($cfg['intervall_min']) && is_numeric($cfg['intervall_min'])
         ? (int) $cfg['intervall_min'] : 0;
    if ($min < 1) { return array(false, '', ''); }
    $mk = ic_merker_lesen('intervall');
    if ($mk !== null && (time() - $mk['zeit']) < $min * 60 - 30) {
        return array(false, '', '');
    }
    $st = ic_stationen();
    if (!$st) { return array(false, 'Es ist keine Tuerstation eingerichtet.', ''); }
    ic_archiv_sicherstellen();
    $o = ic_archivordner();
    $r = ic_bild_holen($st[0]);
    if (!$r['ok']) {
        ic_log_gebremst('intervall', 'Intervallaufnahme: kein Bild - ' . $r['fehler']);
        ic_merker_setzen('intervall', 'kein Bild');
        return array(false, $r['fehler'], '');
    }
    $ziel = $o['bild'] . ic_archivname('intervall');
    if (!ic_datei_ersetzen($ziel, $r['bild'])) {
        return array(false, 'nicht schreibbar', '');
    }
    if (!empty($cfg['timestamp_image']) && $cfg['timestamp_image'] === 'on') {
        ic_zeitstempel_ins_bild($ziel);
    }
    // Dieselbe Erkennung wie beim Klingeln - der Hinweistext sagt sie zu.
    $ai = ic_ki_erkennen($ziel);
    if ($ai) {
        ic_ki_melden($ai);
        ic_log('Intervallaufnahme ' . basename($ziel) . ': ' . $ai['count']
            . ' Objekt(e) erkannt (' . implode(', ', $ai['objects']) . ')');
    }
    ic_merker_setzen('intervall', basename($ziel)
        . ($ai ? ', ' . $ai['count'] . ' Objekt(e)' : ''));
    return array(true, basename($ziel), $ziel);
}

/**
 * Das Archiv aufraeumen - nach Alter, Anzahl UND Platz.
 *
 * Die dritte Grenze ist neu: Anzahl ist ein schlechter Stellvertreter fuer
 * den Platz auf der Karte, weil ein Video und ein Bild sich um
 * Groessenordnungen unterscheiden.
 *
 * $probe = true loescht nichts und sagt nur, was geschaehe. Ein Trockenlauf,
 * der die Sprache des Ernstfalls spricht, waere eine stille Falschaussage -
 * deshalb steht in der Meldung ausdruecklich, dass nichts geloescht wurde.
 *
 * Rueckgabe: array(zahl, byte, zeilen)
 */
function ic_aufraeumen($probe = false)
{
    $g = ic_aufbewahrung();
    $o = ic_archivordner();
    $zeilen = array();
    $zahl = 0;
    $byte = 0.0;
    if ($g['tage'] <= 0 && $g['zahl'] <= 0 && $g['mb'] <= 0) {
        return array(0, 0, array('Keine Grenze eingestellt - es wird nichts geloescht.'));
    }

    $gruppen = array(
        'Bilder'    => array($o['bild'], array('*.jpg'), $g['zahl']),
        'Videos'    => array($o['video'], array('*.avi', '*.jpg'),
                             $g['zahl'] > 0 ? $g['zahl'] * 2 : 0),
        'Zeitraffer' => array($o['timelapse'], array('*.jpg'), $g['zahl']),
    );
    $jetzt = time();
    foreach ($gruppen as $name => $gr) {
        list($ordner, $muster, $hoechst) = $gr;
        $dateien = array();
        foreach ($muster as $m) {
            $f = glob($ordner . $m);
            if (is_array($f)) { $dateien = array_merge($dateien, $f); }
        }
        // Das Zeitraffer-Video ist ein Erzeugnis, kein Archivstueck.
        $dateien = array_values(array_filter($dateien, function ($d) {
            return basename($d) !== 'zeitraffer.mp4';
        }));
        if (!$dateien) { continue; }
        usort($dateien, function ($a, $b) {
            $ma = (int) @filemtime($a); $mb = (int) @filemtime($b);
            return $mb - $ma;   // neueste zuerst
        });
        foreach ($dateien as $i => $d) {
            $alt = ($g['tage'] > 0 && ($jetzt - (int) @filemtime($d)) > $g['tage'] * 86400);
            $zuviel = ($hoechst > 0 && $i >= $hoechst);
            if (!$alt && !$zuviel) { continue; }
            $gr_byte = (float) @filesize($d);
            if ($probe || @unlink($d)) {
                $zahl++;
                $byte += $gr_byte;
                if (count($zeilen) < 20) {
                    $zeilen[] = $name . ': ' . basename($d) . ' (' . ic_byte($gr_byte) . ')'
                              . ($alt ? ' - zu alt' : ' - ueberzaehlig');
                }
            }
        }
    }

    /* Die Platzgrenze zuletzt: sie greift auf das, was danach noch daliegt. */
    if ($g['mb'] > 0) {
        $grenze = $g['mb'] * 1048576;
        $alle = array();
        foreach (array($o['bild'] . '*.jpg', $o['video'] . '*.avi', $o['video'] . '*.jpg',
                       $o['timelapse'] . '*.jpg') as $m) {
            $f = glob($m);
            if (is_array($f)) { $alle = array_merge($alle, $f); }
        }
        $summe = 0.0;
        foreach ($alle as $d) { $summe += (float) @filesize($d); }
        if ($summe > $grenze) {
            usort($alle, function ($a, $b) {
                return (int) @filemtime($a) - (int) @filemtime($b);   // aelteste zuerst
            });
            foreach ($alle as $d) {
                if ($summe <= $grenze) { break; }
                $gr_byte = (float) @filesize($d);
                if ($probe || @unlink($d)) {
                    $summe -= $gr_byte;
                    $zahl++;
                    $byte += $gr_byte;
                    if (count($zeilen) < 30) {
                        $zeilen[] = 'Platz: ' . basename($d) . ' (' . ic_byte($gr_byte) . ')';
                    }
                }
            }
        }
    }

    if (!$probe) {
        ic_log('Archiv aufgeraeumt: ' . $zahl . ' Datei(en), ' . ic_byte($byte) . ' frei geworden.');
        ic_merker_setzen('cleanup', $zahl . ' Datei(en), ' . ic_byte($byte));
    }
    return array($zahl, $byte, $zeilen);
}

/** Gilt der Code? Der Abruf wird dabei mitgezaehlt. */
function ic_bildlink_pruefen($code)
{
    if (!is_string($code) || !preg_match('/^[A-Za-z0-9]{10,40}$/', $code)) { return false; }
    $liste = ic_bildlink_liste();
    $treffer = '';
    // In gleichbleibender Zeit vergleichen, wie beim Zugriffstoken.
    foreach ($liste as $c => $e) {
        if (hash_equals($c, $code)) { $treffer = $c; }
    }
    if ($treffer === '') { return false; }
    $liste[$treffer]['rest'] = (int) $liste[$treffer]['rest'] - 1;
    $js = json_encode($liste, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($js !== false) { ic_datei_ersetzen(ic_bildlink_datei(), $js, 0600); }
    return true;
}

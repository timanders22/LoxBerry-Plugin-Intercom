<?php
/**
 * intercom22lox - gemeinsame Grundlage (neu ab 1.6.0)
 *
 * Enthaelt die Dinge, die jedes Skript braucht: Pfade ohne fest
 * eingetragenen Ordnernamen, Zugriffstoken, unteilbares Schreiben,
 * abgesicherte HTTP-Aufrufe und das MQTT-Gateway ueber UDP.
 */

/* ==================================================================
 * Pfade
 * ================================================================== */

/**
 * Der eigene Plugin-Ordner - ERMITTELT, nicht geraten.
 *
 * Bis 1.5.0 stand in jedem Skript die Zeile
 *     require_once "../../../htmlauth/plugins/intercom22lox/config.php";
 * mit dem Ordnernamen fest im Text. Ist der Ordner bei der
 * Installation schon belegt, haengt LoxBerry einen Zaehler an
 * (intercom22lox_01) - und dann zeigt jeder dieser Verweise auf die
 * VORGAENGERINSTALLATION oder ins Leere. Im ersten Fall arbeiten zwei
 * Installationen auf derselben Konfiguration, im zweiten bricht jedes
 * Skript mit einem toedlichen Fehler ab.
 *
 * Der Ordnername steckt im Ablageort dieser Datei - von dort wird er
 * genommen.
 */
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
        foreach (array('/opt/loxberry', '/home/loxberry/loxberry') as $k) {
            if (is_dir($k)) { $home = $k; break; }
        }
    }
    if (!$home) { $home = '/opt/loxberry'; }
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

/** Die Konfiguration als Array - nie null, nie false. */
function ic_config()
{
    $datei = ic_paths()['config'] . '/data.json';
    $arr = is_readable($datei)
        ? json_decode((string) @file_get_contents($datei), true) : null;
    return is_array($arr) ? $arr : array();
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

/**
 * Zugriffspruefung fuer die Endpunkte unter webfrontend/html/.
 *
 * Dieser Bereich liegt BEWUSST im unangemeldeten Teil - der Miniserver
 * muss ihn ohne Zugangsdaten erreichen koennen. Bis 1.5.0 gab es dort
 * aber ueberhaupt keine Pruefung: jedes Geraet im Netz konnte eine
 * Videoaufzeichnung ausloesen, das Archiv fuellen und den Kamerastrom
 * mitlesen.
 *
 * Bricht mit HTTP 403 ab, wenn das Token fehlt oder falsch ist.
 */
function ic_token_pruefen($erlaube_leer = false)
{
    $arr = ic_config();
    $soll = isset($arr['aktionstoken']) ? (string) $arr['aktionstoken'] : '';
    if ($soll === '') {
        if ($erlaube_leer) { return; }
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: text/plain; charset=utf-8');
        echo "FEHLER: Es ist noch kein Zugriffstoken eingerichtet.\n"
           . "Bitte einmal die Plugin-Oberflaeche oeffnen und speichern -\n"
           . "dort wird eines erzeugt und die fertigen Adressen angezeigt.\n";
        exit;
    }
    $ist = isset($_REQUEST['token']) ? (string) $_REQUEST['token'] : '';
    // hash_equals vergleicht in gleichbleibender Zeit; ein einfaches ===
    // liesse sich ueber die Antwortzeit Zeichen fuer Zeichen erraten.
    if ($ist === '' || !hash_equals($soll, $ist)) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: text/plain; charset=utf-8');
        echo "FEHLER: Ungueltiges oder fehlendes Token.\n";
        exit;
    }
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
 */
function ic_datei_ersetzen($pfad, $inhalt, $modus = 0644)
{
    if ($inhalt === false || $inhalt === null || $inhalt === '') {
        return false;
    }
    $ordner = dirname($pfad);
    if (!is_dir($ordner)) { @mkdir($ordner, 0775, true); }
    $tmp = $pfad . '.' . getmypid() . '.' . mt_rand(1000, 9999) . '.tmp';
    if (@file_put_contents($tmp, $inhalt) === false) {
        return false;
    }
    @chmod($tmp, $modus);
    if (!@rename($tmp, $pfad)) {
        @unlink($tmp);
        return false;
    }
    return true;
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
 * wartet mit. Bei einer Klingel ist das die gesamte Zeit, in der niemand
 * das Bild sieht.
 */
function ic_http_holen($url, $zeitgrenze = 5)
{
    $url = (string) $url;
    if ($url === '') { return false; }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $zeitgrenze,
            CURLOPT_CONNECTTIMEOUT => min(3, $zeitgrenze),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'LoxBerry intercom22lox',
        ));
        $antwort = curl_exec($ch);
        curl_close($ch);
        return $antwort;
    }
    $ctx = stream_context_create(array('http' => array(
        'method' => 'GET',
        'timeout' => $zeitgrenze,
        'ignore_errors' => true,
        'user_agent' => 'LoxBerry intercom22lox',
    )));
    return @file_get_contents($url, false, $ctx);
}

/* ==================================================================
 * MQTT ueber das LoxBerry-Gateway (UDP)
 * ================================================================== */

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
 * PHP 8 aus, meldet das Plugin nichts mehr. Das Gateway ist seit
 * LoxBerry 3 Bestandteil des Systems und nimmt Zeilen der Form
 * "retain <Thema> <Wert>" auf einem UDP-Port entgegen. Ein Paket, keine
 * Verbindung, keine fremde Bibliothek.
 */
function ic_mqtt_senden($thema, $wert, $retain = true)
{
    $port = ic_mqtt_udpport();
    if (!$port || !function_exists('socket_create')) { return false; }
    $s = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if (!$s) { return false; }
    $msg = ($retain ? 'retain ' : 'publish ') . ic_mqtt_thema($thema)
         . ' ' . ic_mqtt_nutzlast($wert);
    $ok = @socket_sendto($s, $msg, strlen($msg), 0, '127.0.0.1', $port);
    socket_close($s);
    return $ok !== false;
}

/* ==================================================================
 * Sonstiges
 * ================================================================== */

/**
 * Der eigene Rechnername fuer Adressen, die AN DEN ANWENDER gehen.
 *
 * NICHT fuer Shell-Befehle verwenden - dafuer gibt es ic_eigene_basis().
 * $_SERVER['HTTP_HOST'] ist der Inhalt der Host-Kopfzeile und damit
 * vollstaendig vom Aufrufer bestimmt. Hier wird er wenigstens auf
 * unbedenkliche Zeichen beschraenkt, damit er nicht als HTML oder als
 * Teil einer anderen Adresse missbraucht werden kann.
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
 *
 * Fuer einen Aufruf an den eigenen Rechner ist HTTP_HOST ausserdem
 * schlicht ueberfluessig: 127.0.0.1 ist immer richtig und haengt von
 * nichts ab, was von aussen kommt.
 */
function ic_eigene_basis()
{
    return 'http://127.0.0.1/plugins/' . ic_plugin_ordner();
}

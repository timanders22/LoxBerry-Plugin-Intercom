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
        'datadir'    => $home . '/data/plugins/' . $ordner,
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
        $p['datadir'] . '/plugin.cfg',
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

/**
 * Der Eintrag dieses Plugins in der plugindatabase.json des LoxBerry.
 *
 * NEU 2.2.9, und zwar aus einem gemessenen Grund: KEINER der acht Kandidaten
 * oben existiert auf einer Installation. plugininstall.pl liest die
 * plugin.cfg aus dem Auspackordner und loescht sie danach - installiert wird
 * sie nirgendwohin (Installationsprotokoll vom 07.09.2026, Zeile 411:
 * "removed .../LoxBerry-Plugin-Intercom-2.2.8/plugin.cfg"). Getroffen hat die
 * Liste immer nur den Archivfall, also den Arbeitsordner.
 *
 * Die Folge war nicht bloss eine gelbe Zeile im Reiter Test: ic_fassung()
 * gab eine LEERE Zeichenkette zurueck. Am Geraet gemessen antwortete
 * ?selftest=1 mit "version":"", und der Kopf einer Sicherungsdatei trug
 * "_fassung": "". Ein Plugin, das seine eigene Fassung nicht nennen kann,
 * macht jede Fehlersuche darueber unmoeglich.
 *
 * LoxBerry fuehrt Titel und Fassung in data/system/plugindatabase.json (644,
 * lesbar). Gesucht wird ueber "folder" - der Ordnername ist das, was auch
 * ic_paths() benutzt; ueber den md5-Schluessel zu gehen waere geraten.
 */
function ic_plugindb()
{
    static $e = null;
    if ($e !== null) { return $e; }
    $e = array();
    $roh = @file_get_contents(ic_paths()['home'] . '/data/system/plugindatabase.json');
    if ($roh === false || $roh === '') { return $e; }
    $d = @json_decode($roh, true);
    if (!is_array($d)) { return $e; }
    $liste = (isset($d['plugins']) && is_array($d['plugins'])) ? $d['plugins'] : $d;
    if (!is_array($liste)) { return $e; }
    $o = ic_plugin_ordner();
    foreach ($liste as $x) {
        if (is_array($x) && isset($x['folder']) && (string) $x['folder'] === $o) {
            $e = $x;
            break;
        }
    }
    return $e;
}

/**
 * Woher Titel und Fassung wirklich kommen.
 *
 * Drei Ausgaenge, nicht zwei - der Reiter Test soll den Unterschied zwischen
 * "aus einer Datei", "aus der Datenbank des LoxBerry" und "gar nicht"
 * anzeigen koennen. Ein Haken, der beide ersten Faelle zusammenwirft, sagt
 * weniger als er koennte.
 */
function ic_fassungsquelle()
{
    $q = ic_titel_quelle();
    if ($q !== '') { return array('datei', $q); }
    $e = ic_plugindb();
    if (isset($e['version']) && (string) $e['version'] !== '') {
        return array('db', ic_paths()['home'] . '/data/system/plugindatabase.json');
    }
    return array('', '');
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
    /* NEU 2.2.9: bevor der Vorgabewert kommt, wird die plugindatabase.json
     * gefragt. Nur fuer die Felder, die dort WIRKLICH stehen - geraten wird
     * nichts; alles Uebrige faellt weiter auf den Vorgabewert. */
    $ausdb = array(
        'VERSION'      => 'version',
        'TITLE'        => 'title',
        'NAME'         => 'name',
        'FOLDER'       => 'folder',
        'AUTHOR_NAME'  => 'author_name',
        'AUTHOR_EMAIL' => 'author_email',
        'INTERFACE'    => 'interface',
    );
    if ($sektion === 'PLUGIN' && isset($ausdb[$schluessel])) {
        $e = ic_plugindb();
        $f = $ausdb[$schluessel];
        if (isset($e[$f]) && (string) $e[$f] !== '') {
            return (string) $e[$f];
        }
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

/* ------------------------------------------------------------------
 * Woran eine Konfiguration ihren Inhalt erkennt
 * ------------------------------------------------------------------
 *
 * Das Merkwort dieser Linie sind zwei Dinge: das Zugriffstoken und die
 * Tuerstationen (seit 2.2.0 die Liste "stationen", davor das eine Feld
 * "intercomip"). Ohne beides ist eine Konfiguration wertlos - mit einem
 * davon nicht. Entschieden wird nach Inhalt, nicht nach Form: "{}", eine
 * leere Datei, abgeschnittenes JSON und eine fehlende Datei sind hier alle
 * dasselbe, naemlich "ohne Inhalt".
 */
function ic_hat_token(array $c)
{
    return isset($c['aktionstoken']) && is_string($c['aktionstoken'])
        && $c['aktionstoken'] !== '';
}

function ic_hat_stationen(array $c)
{
    if (isset($c['stationen']) && is_array($c['stationen']) && count($c['stationen']) > 0) {
        return true;
    }
    return isset($c['intercomip']) && is_string($c['intercomip'])
        && trim($c['intercomip']) !== '';
}

function ic_config_hat_inhalt(array $c)
{
    return ic_hat_token($c) || ic_hat_stationen($c);
}

/* ------------------------------------------------------------------
 * Die Marke "Aktualisierung laeuft"
 * ------------------------------------------------------------------
 *
 * Zwischen dem Kopieren der neuen Dateien und postinstall.sh liegt beim
 * Upgrade rund eine Minute (Regeln/06, am Geraet 08.09.2026 gemessen). In
 * dieser Luecke ist data.json die leere Vorgabe aus dem Archiv; die echten
 * Einstellungen liegen nur in der Zweitschrift. Bis 2.2.10 erzeugte die
 * Oberflaeche, in dieser Zeit geoeffnet, ein neues Token und schrieb es in
 * data.json UND in die Zweitschrift - postinstall.sh fand danach "Einstellungen
 * vorhanden", Stationen und altes Token waren weg (in WSL nachgestellt,
 * Pruefung-Upgradeluecke-2026-09-17, Fall F).
 *
 * preupgrade.sh legt die Marke als Erstes an, postinstall.sh entfernt sie
 * nach der Rueckholung. Sie liegt NEBEN dem Datenordner, weil
 * purge_installation den Ordner selbst loescht.
 *
 * ZWEI LESARTEN, seit 2.2.13 getrennt (Entscheidung 1 vom 29.09.2026 und
 * Nr. 8, Frage 17 vom 30.09.2026):
 *   ic_upgrade_laeuft()      nur die SPERRE DER OBERFLAECHE. Aelter als eine
 *                            Stunde oder unlesbar gilt sie hier nicht: eine
 *                            abgebrochene Installation darf die Seite nicht
 *                            fuer immer stilllegen.
 *   ic_upgrade_marke_liegt() Heilung, Bereinigung und Zeitraffer - OHNE
 *                            Altersgrenze. Bis 2.2.12 fragten auch diese nach
 *                            der Stunde; ein Update mit mehr als einer Stunde
 *                            Luecke loeschte darin Archivbilder nach der
 *                            Vorgabe von 90 Tagen (Installer-Pruefer, Fall U4).
 *                            Wer frisch anfangen will, deinstalliert vorher.
 */
function ic_upgrade_marke()
{
    $p = ic_paths();
    return dirname($p['datadir']) . '/' . basename($p['datadir']) . '.upgrade_laeuft';
}

function ic_upgrade_laeuft()
{
    $f = ic_upgrade_marke();
    $roh = @is_file($f) ? @file_get_contents($f) : false;
    if ($roh === false) { return false; }
    $roh = trim((string) $roh);
    if (!preg_match('/^[0-9]{1,12}$/', $roh)) { return false; }
    $alter = time() - (int) $roh;
    // Ein paar Minuten "Zukunft" sind eine nachgestellte Uhr, keine Luege.
    return $alter > -300 && $alter < 3600;
}

/** Liegt die Marke ueberhaupt - gleich wie alt? (seit 2.2.13, siehe oben) */
function ic_upgrade_marke_liegt()
{
    clearstatcache(true, ic_upgrade_marke());
    return @is_file(ic_upgrade_marke());
}

/** Seit wann liegt sie? 0 = unlesbar. */
function ic_upgrade_marke_zeit()
{
    $roh = trim((string) @file_get_contents(ic_upgrade_marke()));
    return preg_match('/^[0-9]{1,12}$/', $roh) ? (int) $roh : 0;
}

/**
 * Eine Konfiguration ohne Inhalt aus der Zweitschrift wiederherstellen.
 *
 * Nur fuer die Oberflaeche - der unangemeldete Bereich ruft das nie
 * (Regeln/05: die Selbstheilung liegt hinter der Anmeldung). Und nicht,
 * waehrend die Marke gilt; dann holt postinstall.sh zurueck.
 *
 * Lesen -> pruefen -> einmal zurueckschreiben -> einmal melden. Einmal, weil
 * die geheilte Datei beim naechsten Aufruf Inhalt hat. Die Zweitschrift wird
 * dabei nicht angefasst, sondern Byte fuer Byte uebernommen. Stand in
 * data.json etwas, das kein leeres Objekt war (abgeschnittenes JSON,
 * Einstellungen ohne Token und Station), bleibt es als data.json.kaputt
 * liegen - mit 0600, es kann Zugangsdaten tragen.
 *
 * Rueckgabe array(lage, pfad): 'ok' (Inhalt da, nichts zu tun), 'ohne'
 * (keine brauchbare Zweitschrift), 'geheilt', 'fehler' (Schreiben scheiterte;
 * pfad nennt die Datei).
 *
 * BERICHTIGT 2.2.13 (I1, I8): bis 2.2.12 stand hier, 'ohne' sei "der Fall der
 * Neuinstallation". Gemessen war das Gegenteil: eine Neuinstallation neben
 * einer liegengebliebenen Zweitschrift ergab 'geheilt' - schon beim ersten
 * Oeffnen, noch vor postinstall.sh (Installer-Pruefer, Fall N4). Seit 2.2.13
 * heilt die Bibliothek OHNE Upgrade-Marke nie aus einer Zweitschrift, die
 * aelter ist als diese Installation. Die Installation erkennt sie am
 * Zeitstempel dieser Datei: der Installer kopiert mit cp -r ohne -p
 * (sbin/plugininstall.pl:1050). Die Marke gilt dabei ohne Altersgrenze.
 * preinstall.sh legt solche Zweitschriften ohnehin nach .alt; die Bibliothek
 * liest .alt nie.
 */
function ic_config_heilen()
{
    $datei = ic_paths()['config'] . '/data.json';
    $roh = @is_file($datei) ? @file_get_contents($datei) : false;
    $cfg = ($roh !== false && trim((string) $roh) !== '')
         ? json_decode((string) $roh, true) : array();
    if (is_array($cfg) && ic_config_hat_inhalt($cfg)) {
        return array('ok', '');
    }
    $zweit = ic_zweitschrift();
    $zroh = @is_file($zweit) ? @file_get_contents($zweit) : false;
    $z = ($zroh !== false && trim((string) $zroh) !== '')
       ? json_decode((string) $zroh, true) : null;
    if (!is_array($z) || !ic_config_hat_inhalt($z)) {
        return array('ohne', '');
    }
    if (!ic_upgrade_marke_liegt()) {
        clearstatcache(true, $zweit);
        $inst = @filemtime(__FILE__);
        $zeit = @filemtime($zweit);
        if ($inst !== false && $zeit !== false && $zeit < $inst) {
            ic_log_gebremst('heilung_alt', 'Die Konfiguration traegt weder Zugriffstoken noch '
                . 'Tuerstation. Die Zweitschrift ' . $zweit . ' ist aelter als diese Installation '
                . 'und wird deshalb nicht eingespielt (Neuinstallation, Entscheidung 1).');
            return array('ohne', '');
        }
    }
    $rest = ($roh === false) ? '' : preg_replace('/\s+/', '', (string) $roh);
    $aufheben = ($rest !== '' && $rest !== '{}' && $rest !== '[]');
    if ($aufheben && !ic_datei_ersetzen($datei . '.kaputt', (string) $roh, 0600)) {
        return array('fehler', $datei . '.kaputt');
    }
    if (!ic_datei_ersetzen($datei, (string) $zroh, 0600)) {
        return array('fehler', $datei);
    }
    ic_log('Die Konfiguration trug weder Zugriffstoken noch Tuerstation; sie wurde '
        . 'aus der Zweitschrift wiederhergestellt: ' . $zweit
        . ($aufheben ? ' (der vorherige Inhalt liegt unter ' . $datei . '.kaputt)' : '') . '.');
    return array('geheilt', $zweit);
}

/**
 * Was die Zweitschrift traegt und die neue Konfiguration nicht.
 *
 * Leer heisst: die Zweitschrift darf erneuert werden. Verglichen wird, ob
 * ein SCHLUESSEL fehlt, nicht ob ein Wert leer ist - "stationen": [] ist
 * ein gewolltes Loeschen und wird nachgezogen; ein fehlender Schluessel
 * heisst, die Konfiguration ist nicht aus dem gespeicherten Stand
 * hervorgegangen. Ein leeres Token dagegen gibt es auf keinem Weg der
 * Oberflaeche und gilt deshalb als fehlend.
 */
function ic_zweitschrift_fehlt(array $neu)
{
    $zweit = ic_zweitschrift();
    $zroh = @is_file($zweit) ? @file_get_contents($zweit) : false;
    $z = ($zroh !== false && trim((string) $zroh) !== '')
       ? json_decode((string) $zroh, true) : null;
    if (!is_array($z)) { return array(); }
    $fehlt = array();
    if (ic_hat_token($z) && !ic_hat_token($neu)) {
        $fehlt[] = 'aktionstoken';
    }
    if (ic_hat_stationen($z) && !array_key_exists('stationen', $neu)
        && !array_key_exists('intercomip', $neu)) {
        $fehlt[] = 'stationen';
    }
    return $fehlt;
}

/** Hat ic_config_speichern() in diesem Aufruf die Zweitschrift geschont? */
function ic_zweitschrift_geschont($fehlt = null)
{
    static $f = array();
    if (is_array($fehlt)) { $f = $fehlt; }
    return $f;
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
 *
 * Die Zweitschrift wird NICHT erneuert, wenn die neue Konfiguration Token
 * oder Stationen nicht traegt, die dort stehen (ic_zweitschrift_fehlt()).
 * data.json wird trotzdem geschrieben - das Speichern selbst wird nicht
 * verhindert, nur der einzige Rueckweg nicht zerstoert; Protokoll und
 * Oberflaeche sagen es.
 */
function ic_config_speichern(array $neu)
{
    $js = json_encode($neu, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES);
    if ($js === false) {
        return array(false, 'json_encode: ' . json_last_error_msg());
    }
    $fehlt = ic_zweitschrift_fehlt($neu);
    $datei = ic_paths()['config'] . '/data.json';
    if (!ic_datei_ersetzen($datei, $js, 0600)) {
        return array(false, $datei);
    }
    if ($fehlt) {
        ic_zweitschrift_geschont($fehlt);
        ic_log('WARNUNG: Die Zweitschrift bleibt unveraendert - die gespeicherte '
            . 'Konfiguration traegt nicht, was dort steht (' . implode(', ', $fehlt)
            . '): ' . ic_zweitschrift());
    } elseif (!ic_datei_ersetzen(ic_zweitschrift(), $js, 0600)) {
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
 * Der Ort einer Sperre - fuer ic_sperre() und ic_sperre_belegt() derselbe.
 *
 * Im Datenordner, solange es ihn gibt. Ohne Datenordner (nach
 * purge_installation, vor postinstall.sh) fiel ic_sperre() bis 2.2.10 auf
 * sys_get_temp_dir() zurueck - mit dem festen Namen .sperre_<name>. Zwei
 * Installationen (intercom, intercom_01) teilten sich dann /tmp/.sperre_cron,
 * und die zweite wurde abgewiesen; ic_sperre_belegt() sah dort gar nicht
 * nach. In WSL gemessen (Pruefung-Intercom-2.2.11, Fall N6). Der Name traegt
 * deshalb den Plugin-Ordner und ein Kennzeichen des LoxBerry-Wurzelordners.
 */
function ic_sperre_datei($name)
{
    $p = ic_paths();
    $n = preg_replace('/[^a-z0-9_]/i', '', $name);
    if (@is_dir($p['datadir'])) {
        return $p['datadir'] . '/.sperre_' . $n;
    }
    return rtrim(sys_get_temp_dir(), '/') . '/.' . preg_replace('/[^a-z0-9_]/i', '', $p['plugin'])
         . '_' . substr(md5((string) $p['home']), 0, 8) . '.sperre_' . $n;
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
    $datei = ic_sperre_datei($name);
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
        if (PHP_VERSION_ID < 80000) { curl_close($ch); }
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
        // Mit Zugangsdaten keiner Umleitung folgen (C5, seit 2.2.13): der
        // Stream-Wrapper schickt die Kopfzeile Authorization sonst auch an
        // das Umleitungsziel - gemessen im Pruefstand des code-Pruefers.
        'follow_location' => (is_array($auth) && $auth[0] !== '') ? 0 : 1,
        'header' => implode("\r\n", $kopf),
    )));
    $inhalt = @file_get_contents($url, false, $ctx);
    /* BERICHTIGT 2.2.13 (C7, Bauart A): PHP 8.5 erklaert die lokal angelegte
     * Antwortkopf-Variable des Stream-Wrappers fuer veraltet und meldet das
     * schon beim Uebersetzen dieser Datei. Hausform:
     * http_get_last_response_headers(), wo es sie gibt (ab 8.4), sonst ueber
     * den Namen. */
    if (function_exists('http_get_last_response_headers')) {
        $antwortkopf = http_get_last_response_headers();
    } else {
        $kn = 'http_response_header';
        $antwortkopf = isset($$kn) ? $$kn : null;
    }
    $code = ic_status_aus_kopf(is_array($antwortkopf) ? $antwortkopf : array());
    return array($inhalt, $code, $inhalt === false ? 'Abruf gescheitert' : '');
}

/** Der Statuscode der LETZTEN Antwort in einer Liste von Kopfzeilen (0 = keiner). */
function ic_status_aus_kopf(array $kopf)
{
    $code = 0;
    foreach ($kopf as $z) {
        if (is_string($z) && preg_match('#^HTTP/\S+\s+(\d{3})#', $z, $m)) {
            $code = (int) $m[1];
        }
    }
    return $code;
}

/** Die Kopfzeilen eines geoeffneten HTTP-Stroms - ueber stream_get_meta_data() (C7). */
function ic_strom_kopfzeilen($f)
{
    $m = is_resource($f) ? @stream_get_meta_data($f) : null;
    return (is_array($m) && isset($m['wrapper_data']) && is_array($m['wrapper_data']))
        ? $m['wrapper_data'] : array();
}

/* ENTFERNT 2.2.13 (M5): die Kurzform ic_http_holen() - sie gab nur den Inhalt
 * zurueck, und genau deshalb wertete bis 2.2.12 niemand den HTTP-Code eines
 * Webhooks aus. Ihre beiden Aufrufer (Webhook 2/4, Video-Webhook 2) nehmen
 * jetzt ic_http_holen_voll() und ic_webhook_pruefen(). */

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
    /* Seit 2.2.13 (O8) ein dritter Wert: derselbe Grund in der Sprache der
     * Oberflaeche. Der zweite bleibt deutsch - er geht ins Protokoll und in
     * die JSON-Antwort an Loxone. */
    $start = strpos($roh, "\xFF\xD8\xFF");
    if ($start === false) {
        $t = 'kein JPEG-Anfang (FFD8) im Datenstrom';
        return array(false, $t, ic_uebersetzt('TEST.E_JPEG_ANFANG', array(), $t));
    }
    $ende = strpos($roh, "\xFF\xD9", $start + 3);
    if ($ende === false) {
        $t = 'kein vollstaendiger Rahmen: das Ende (FFD9) fehlt';
        return array(false, $t, ic_uebersetzt('TEST.E_JPEG_ENDE', array(), $t));
    }
    $bild = substr($roh, $start, $ende - $start + 2);
    if (strlen($bild) < 1000) {
        $t = 'Rahmen zu klein (' . strlen($bild) . ' Byte)';
        return array(false, $t, ic_uebersetzt('TEST.E_RAHMEN_KLEIN', array(strlen($bild)), $t));
    }
    return array($bild, '', '');
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
        $t = 'Fuer diese Station ist keine Adresse eingetragen.';
        return array('ok' => false, 'bild' => '', 'weg' => $weg,
                     'fehler' => $t, 'fehler_ui' => ic_uebersetzt('TEST.E_KEINE_ADRESSE', array(), $t),
                     'code' => 0, 'dauer' => 0.0);
    }
    $t0 = microtime(true);
    $vorlauf = '';

    if ($weg === 'standbild' || $weg === 'auto') {
        $r = ic_standbild_holen($station, $zeitgrenze);
        if ($r['ok'] || $weg === 'standbild') {
            $r['dauer'] = microtime(true) - $t0;
            ic_merker_setzen('stationskontakt', $r['ok'] ? '1' : '0');
            return $r;
        }
        $vorlauf = $r['fehler'];
        $vorlauf_ui = $r['fehler_ui'];
    }

    $r = ic_strombild_holen($station, $zeitgrenze);
    if (!$r['ok'] && $vorlauf !== '') {
        $r['fehler'] = 'Standbild: ' . $vorlauf . ' | Strom: ' . $r['fehler'];
        $r['fehler_ui'] = ic_uebersetzt('TEST.E_BEIDE', array($vorlauf_ui, $r['fehler_ui']),
                                        $r['fehler']);
    }
    $r['dauer'] = microtime(true) - $t0;
    /* NEU 2.2.13 (M3): der letzte Stationskontakt als Merker - daraus bildet
     * der Herzschlag status/ok (Entscheidung 8). */
    ic_merker_setzen('stationskontakt', $r['ok'] ? '1' : '0');
    return $r;
}

/** Der Standbildweg - eine einzelne Adresse, ein fertiges JPEG. */
function ic_standbild_holen(array $station, $zeitgrenze = 8)
{
    // Seit 2.2.15 aus ic_standbild_pfad() - die Stationsprobe (a2) braucht
    // denselben Pfad, und zwei Kopien laufen beim naechsten Umbau auseinander.
    $pfad = ic_standbild_pfad($station);
    list($u, $pw) = ic_zugangsdaten($station);
    $url = 'http://' . $station['ip'] . $pfad;
    list($inhalt, $code, $fehler) = ic_http_holen_voll($url, $zeitgrenze, array($u, $pw));
    $aus = array('ok' => false, 'bild' => '', 'weg' => 'standbild ' . $pfad,
                 'fehler' => '', 'fehler_ui' => '', 'code' => $code, 'dauer' => 0.0);
    if ($inhalt === false || $inhalt === '') {
        $aus['fehler'] = $fehler !== '' ? $fehler : 'keine Antwort';
        $aus['fehler_ui'] = $fehler === 'Abruf gescheitert'
            ? ic_uebersetzt('TEST.E_ABRUF', array(), $fehler)
            : ($fehler !== '' ? $fehler : ic_uebersetzt('TEST.E_KEINE_ANTWORT', array(), 'keine Antwort'));
        return $aus;
    }
    if ($code !== 0 && $code !== 200) {
        $aus['fehler'] = 'HTTP ' . $code
                       . ($code === 401 ? ' - Benutzername oder Passwort stimmen nicht' : '');
        $aus['fehler_ui'] = $code === 401
            ? ic_uebersetzt('TEST.E_HTTP401', array(), $aus['fehler'])
            : ic_uebersetzt('TEST.E_HTTP', array($code), $aus['fehler']);
        return $aus;
    }
    if (strncmp($inhalt, "\xFF\xD8\xFF", 3) !== 0) {
        $aus['fehler'] = 'die Antwort ist kein JPEG (' . strlen($inhalt) . ' Byte)';
        $aus['fehler_ui'] = ic_uebersetzt('TEST.E_KEIN_JPEG', array(strlen($inhalt)), $aus['fehler']);
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
                 'fehler' => '', 'fehler_ui' => '', 'code' => 0, 'dauer' => 0.0);
    $kopf = array('Accept: image/jpeg, multipart/x-mixed-replace, */*');
    if ($u !== '') {
        $kopf[] = 'Authorization: Basic ' . base64_encode($u . ':' . $pw);
    }
    // Zeitgrenze auf den Strom legen, BEVOR gelesen wird: ohne sie wartet
    // fread beliebig lange, wenn die Tuerstation die Verbindung offen haelt,
    // aber nichts mehr schickt. In timelapse.php fehlte genau das bis 2.1.13.
    /* BERICHTIGT 2.2.13 (C5): 'follow_location' => 0. Der Stream-Wrapper
     * folgte bis 2.2.12 einer Umleitung und schickte die Kopfzeile
     * Authorization mit - gemessen mit einer Station, die per 302 auf einen
     * fremden Rechner zeigte: dort kamen die Zugangsdaten der Station an. Eine
     * Intercom leitet nicht um; eine Umleitung ist hier ein Fehler. */
    $ctx = stream_context_create(array('http' => array(
        'method' => 'GET',
        'timeout' => $zeitgrenze,
        'ignore_errors' => true,
        'follow_location' => 0,
        'header' => implode("\r\n", $kopf),
    )));
    $f = @fopen($url, 'r', false, $ctx);
    if (!$f) {
        $aus['fehler'] = 'die Station war nicht erreichbar';
        $aus['fehler_ui'] = ic_uebersetzt('TEST.E_NICHT_ERREICHBAR', array(), $aus['fehler']);
        return $aus;
    }
    // C7: die Kopfzeilen ueber stream_get_meta_data(), nicht ueber die
    // lokale Wrapper-Variable (PHP 8.5).
    $aus['code'] = ic_status_aus_kopf(ic_strom_kopfzeilen($f));
    if ($aus['code'] === 401) {
        fclose($f);
        $aus['fehler'] = 'HTTP 401 - Benutzername oder Passwort stimmen nicht';
        $aus['fehler_ui'] = ic_uebersetzt('TEST.E_HTTP401', array(), $aus['fehler']);
        return $aus;
    }
    if ($aus['code'] >= 300 && $aus['code'] < 400) {
        fclose($f);
        $aus['fehler'] = 'HTTP ' . $aus['code'] . ' - die Station leitet um; einer Umleitung '
                       . 'folgt das Plugin nicht';
        $aus['fehler_ui'] = ic_uebersetzt('TEST.E_UMLEITUNG', array($aus['code']), $aus['fehler']);
        return $aus;
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
    list($bild, $fehler, $fehler_ui) = ic_jpeg_schneiden($r);
    if ($bild === false) {
        $aus['fehler'] = $fehler . ' (' . strlen($r) . ' Byte gelesen)';
        $aus['fehler_ui'] = ic_uebersetzt('TEST.E_GELESEN', array($fehler_ui, strlen($r)),
                                          $aus['fehler']);
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

/**
 * Der Name, unter dem das MQTT-Gateway einen virtuellen Eingang anlegt.
 *
 * NEU 04.09.2026 (2.2.6). Das Gateway ersetzt im Themennamen "/" durch "_"
 * (Regeln/07). Bis 2.2.5 baute die Importvorlage ihre Titel dagegen aus dem
 * ROHEN Praefix: bei einem Praefix "haus/tuer" hiess der Eingang in der
 * Vorlage "haus/tuer_ok", das Gateway legte aber "haus_tuer_ok" an. Der
 * importierte Eingang bekam nie einen Wert, und daneben entstand beim ersten
 * Empfang ein zweiter. ic_mqtt_thema() laesst den Schraegstrich absichtlich
 * stehen - ein mehrstufiges Thema ist zulaessig -, also gehoert die
 * Umsetzung hierher, an EINE Stelle fuer Vorlage und Baustein-Liste.
 */
function ic_gatewayname($thema)
{
    return str_replace('/', '_', ic_mqtt_thema($thema));
}

/**
 * Die Fassung des LoxBerry-MQTT-Gateways - 0 heisst "nicht feststellbar".
 *
 * UMGEZOGEN 04.09.2026 (2.2.6) aus index.php in die Bibliothek: die
 * Selbstpruefung braucht sie, und bis 2.2.5 suchte diese Funktion ihren
 * Pfad ueber getenv('LBHOMEDIR') allein, waehrend ic_paths() eine
 * Kandidatenliste fuehrt. War die Umgebungsvariable im Webkontext nicht
 * gesetzt, lasen Autostart- und Portpruefung die general.json weiterhin,
 * die Fassungspruefung aber nicht - zwei Wahrheiten ueber denselben Pfad.
 */
function ic_gateway_fassung()
{
    $home = ic_paths()['home'];
    if ($home === '' || !@is_dir($home)) { return 0; }
    $d = @json_decode((string) @file_get_contents(
        $home . '/config/system/general.json'), true);
    if (!is_array($d)) { return 0; }
    foreach (array('Mqtt', 'mqtt') as $ab) {
        if (!isset($d[$ab]) || !is_array($d[$ab])) { continue; }
        foreach (array('Gatewayversion', 'gatewayversion') as $sl) {
            if (isset($d[$ab][$sl]) && (string) $d[$ab][$sl] !== '') {
                return (int) $d[$ab][$sl];
            }
        }
    }
    return 0;
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
function ic_mqtt_senden($unterthema, $wert, $retain = null)
{
    /* BERICHTIGT 04.09.2026 (2.2.6): die Vorgabe ist nicht mehr "immer
     * retained", sondern "was die Themenliste sagt". Wer es ausdruecklich
     * anders will, uebergibt true oder false. */
    if ($retain === null) { $retain = ic_mqtt_retain($unterthema); }
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
    /* Das Lebenszeichen nach Hausschema (Regeln/07): status/ok sagt, ob der
     * letzte Kontakt zur Tuerstation geklappt hat, status/ts wann der Lauf
     * war, status/zaehler laeuft 0..999 um. Erst der Zaehler macht "der
     * Dienst steht" von "er arbeitet" unterscheidbar - ein Zeitstempel allein
     * sagt das nicht, wenn niemand die Uhr des Miniservers dagegenhaelt.
     *
     * BERICHTIGT 2.2.13, zwei Sachen an dieser Stelle:
     * 1. (M7) Der Laufmerker wird IMMER geschrieben, nur das Senden haengt an
     *    ic_mqtt_an(). Bis 2.2.12 stand der Ruecksprung davor: ohne MQTT
     *    (Werksvorgabe) meldete ?selftest=1 dauerhaft alter=-1 ("noch nie
     *    gelaufen"), obwohl der Cron jede Minute lief.
     * 2. (M3, Entscheidung 8) status/ok stand fest auf 1 - auch bei einer
     *    Station, die seit Tagen schweigt. Jetzt kommt es aus dem letzten
     *    Stationskontakt (ic_stationskontakt_ok()).
     *
     * <praefix>/ok bleibt unveraendert daneben stehen. Es traegt seit jeher
     * die Loxone-Zeit, und an dem Namen haengen bestehende Anlagen; ein
     * Umbenennen waere ein Schnitt, keine Berichtigung. Die Themenliste
     * kennzeichnet es als ueberholt.
     */
    $zk = ic_merker_lesen('mqttzaehler');
    $zaehler = ($zk === null || !is_numeric($zk['text']))
             ? 0 : (((int) $zk['text']) + 1) % 1000;
    ic_merker_setzen('mqttzaehler', (string) $zaehler);
    if (!ic_mqtt_an()) { return false; }

    ic_mqtt_senden('status/ok', ic_stationskontakt_ok() ? '1' : '0');
    ic_mqtt_senden('status/ts', (string) (time() - 1230768000));
    ic_mqtt_senden('status/zaehler', (string) $zaehler);
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
function ic_mqtt_themen($p = null)
{
    /* Seit 2.2.13 mit wahlweise anderem Praefix: der Praefixwechsel raeumt die
     * behaltenen Themen unter dem ALTEN Praefix ab (M2) und nimmt sie aus
     * dieser Liste, nicht aus einer zweiten. */
    if ($p === null) { $p = ic_mqtt_praefix(); }
    /* Drittes Feld: wird das Thema RETAINED gesendet?
     *
     * Hausstandard seit 03.09.2026 (Regeln/07): Zustaende retained, damit
     * Loxone nach einem Neustart des Miniservers oder des Gateways sofort den
     * Stand hat; Messwerte mit Zeitbezug NICHT retained, damit nach einem
     * Ausfall kein alter Wert als aktuell erscheint; das Lebenszeichen NIE -
     * retained zeigte es immer "lebt".
     *
     * Bis 2.2.5 ging jedes der acht Themen retained hinaus (ic_mqtt_senden()
     * hat retain=true als Vorgabe, und kein Aufruf uebergab etwas anderes;
     * der publish-Zweig war toter Code). Nach einem Neustart hielt der Broker
     * damit eine alte Klingelmeldung samt Bildadresse vor, die aussah, als
     * sei gerade geklingelt worden.
     *
     * Der Sender liest die Klasse aus DIESER Liste (ic_mqtt_retain()), und
     * die Tabelle im Reiter MQTT zeigt sie an - Sender und Anleitung koennen
     * nicht auseinanderlaufen.
     */
    return array(
        array($p,                     'MQTT.T_BILD',      false),
        array($p . '/video',          'MQTT.T_VIDEO',     false),
        array($p . '/trigger/NAME',   'MQTT.T_TRIGGER',   false),
        array($p . '/ai',             'MQTT.T_AI',        false),
        array($p . '/ai_count',       'MQTT.T_AI_COUNT',  true),
        array($p . '/timelapse',      'MQTT.T_TIMELAPSE', false),
        // NEU 2.2.13 (M4): eine misslungene Aufnahme an der Klingel, fluechtig.
        array($p . '/fehler',         'MQTT.T_FEHLER',    false),
        array($p . '/bilder',         'MQTT.T_BILDER',    true),
        array($p . '/status/ok',      'MQTT.T_ST_OK',     false),
        array($p . '/status/ts',      'MQTT.T_ST_TS',     false),
        array($p . '/status/zaehler', 'MQTT.T_ST_ZAEHLER', false),
        array($p . '/ok',             'MQTT.T_OK',        false),
    );
}

/**
 * Wird dieses Unterthema retained gesendet? - aus der Themenliste, nicht
 * aus einem zweiten Verzeichnis. Ein unbekanntes Unterthema geht NICHT
 * retained hinaus; das ist die vorsichtigere der beiden Antworten.
 */
function ic_mqtt_retain($unterthema)
{
    $p = ic_mqtt_praefix();
    $voll = $unterthema === '' ? $p : $p . '/' . ic_mqtt_thema($unterthema);
    foreach (ic_mqtt_themen() as $t) {
        if ($t[0] === $voll) { return (bool) $t[2]; }
        /* trigger/NAME steht in der Liste als Muster. */
        if (substr($t[0], -5) === '/NAME'
            && strpos($voll, substr($t[0], 0, -4)) === 0) {
            return (bool) $t[2];
        }
    }
    return false;
}

/**
 * Hat der letzte Kontakt zur Tuerstation geklappt? (NEU 2.2.13, M3)
 *
 * Quelle ist der Merker, den ic_bild_holen() bei JEDEM Abruf setzt (Klingel,
 * Aufnahme im Takt, Zeitraffer, Pruefung mit Netz) und seit 2.2.15 auch die
 * Stationsprobe im Minutentakt (ic_stationsprobe_lauf(), Intercom-a2).
 *
 * BERICHTIGT 2.2.15 (Intercom-a2): bis 2.2.14 zaehlte ohne Aufnahme im Takt
 * allein der letzte Kontakt, GLEICH WIE ALT - eine Station, die nach dem
 * letzten Klingeln ausfiel, stand in Loxone tagelang auf status/ok 1. Jetzt
 * ist der Takt die Probe (eine Minute): aelter als drei Takte heisst 0.
 */
function ic_stationskontakt_ok()
{
    $mk = ic_merker_lesen('stationskontakt');
    if ($mk === null || trim((string) $mk['text']) !== '1') { return false; }
    return (time() - (int) $mk['zeit']) <= 3 * 60;
}

/**
 * Eine Zeile an den UDP-Eingang des Gateways, mit VOLLEM Thema (NEU 2.2.13).
 *
 * Fuer das Abraeumen unter einem anderen als dem eingestellten Praefix (M1,
 * M2). Eine leere Nutzlast mit "retain" loescht beim Broker den behaltenen
 * Wert. Rueckgabe: ging das Paket hinaus? Ob der Broker es bekam, sagt UDP
 * nicht.
 */
function ic_mqtt_roh_senden($verb, $thema, $wert)
{
    $port = ic_mqtt_udpport();
    if (!$port || !function_exists('socket_create')) { return false; }
    $thema = ic_mqtt_thema($thema);
    if ($thema === '' || !in_array($verb, array('retain', 'publish'), true)) { return false; }
    $s = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if (!$s) { return false; }
    $w = ic_mqtt_nutzlast($wert);
    $msg = $verb . ' ' . $thema . ($w !== '' ? ' ' . $w : '');
    $ok = @socket_sendto($s, $msg, strlen($msg), 0, '127.0.0.1', $port);
    socket_close($s);
    return $ok !== false;
}

/**
 * Die heute behaltenen Themen unter einem Praefix abraeumen (NEU 2.2.13, M2).
 *
 * Beim Praefixwechsel und beim Abschalten: unter dem ALTEN Praefix stuenden
 * sonst bilder und ai_count fuer immer im Broker, und Gateway V1 lieferte
 * nach einem Wechsel zwei Bildzaehler. Dreimal gesendet, weil UDP verliert.
 * Rueckgabe array(gesendet, versucht, themen).
 */
function ic_mqtt_behaltene_abraeumen($praefix, $mal = 3)
{
    $themen = array();
    foreach (ic_mqtt_themen(ic_mqtt_thema($praefix)) as $t) {
        if ($t[2] && substr($t[0], -5) !== '/NAME') { $themen[] = $t[0]; }
    }
    $ok = 0;
    $versucht = 0;
    for ($i = 0; $i < $mal; $i++) {
        foreach ($themen as $t) {
            $versucht++;
            if (ic_mqtt_roh_senden('retain', $t, '')) { $ok++; }
        }
    }
    return array($ok, $versucht, $themen);
}

/* ------------------------------------------------------------------
 * Altlast bis 2.2.5 abraeumen (NEU 2.2.13, M1)
 * ------------------------------------------------------------------
 *
 * Bis 2.2.5 ging jedes Thema RETAINED hinaus (ic_mqtt_senden() hatte
 * retain=true als Vorgabe). Seit 2.2.6 gehen das Klingelthema, trigger/<name>,
 * video, ai, timelapse und ok fluechtig - ein fluechtiges publish ueberschreibt
 * den behaltenen Wert aber nicht. Auf jeder Anlage, die von 2.2.5 oder frueher
 * kommt, steht er deshalb bis heute im Broker; bei abgeschaltetem offenem Bild
 * sogar mit bild.php?token=<Zugriffstoken> (mqtt-Pruefer, Befund 1).
 *
 * Abgeraeumt wird mit leerem retain, und zwar mehrfach ueber den ersten Tag
 * verteilt (UDP verliert 17 bis 70 %): sechs Runden, die erste sofort, dann
 * nach 15 min, 1 h, 4 h, 12 h und 24 h. Eine Runde zaehlt nur, wenn JEDES
 * sendto hinausging; danach nie wieder. Nicht abgeraeumt werden bilder und
 * ai_count - die sind heute behalten und werden weiter gebraucht.
 *
 * Der Merker liegt NEBEN dem Datenordner (purge_installation raeumt den
 * Ordner bei jedem Upgrade ab); die Deinstallation entfernt ihn.
 */
function ic_mqtt_altlast_datei()
{
    $p = ic_paths();
    return dirname($p['datadir']) . '/' . basename($p['datadir']) . '.mqtt_altlast.json';
}

/** Die Themen der Altlast: je Praefix sechs Staemme und die bekannten Ausloeser. */
function ic_mqtt_altlast_themen()
{
    $praefixe = array_values(array_unique(array_filter(array(
        ic_mqtt_praefix(), ic_mqtt_thema(ic_plugin_ordner())))));
    /* Ausloeser: die drei, die Oberflaeche und Vorlage nennen, dazu jeder, der
     * im Namen eines Archivbilds steht (<datum>-<zeit>-[<station>-]<name>-intercom.jpg).
     * Ein Name, zu dem nie etwas behalten wurde, kostet ein leeres Paket. */
    $namen = array('klingel' => true, 'briefkasten' => true, 'test' => true);
    $o = ic_archivordner();
    foreach ((@glob($o['bild'] . '*.jpg') ?: array()) as $d) {
        if (count($namen) >= 100) { break; }
        if (!preg_match('/^\d{4}_\d{2}_\d{2}-\d{2}_\d{2}_\d{2}-(.+)-intercom(?:-\d+)?\.jpg$/',
                        basename($d), $m)) {
            continue;
        }
        $teile = explode('-', $m[1]);
        for ($i = 0; $i < count($teile); $i++) {
            $n = implode('-', array_slice($teile, $i));
            if ($n !== 'intervall' && preg_match('/^[A-Za-z0-9_\-]{1,32}$/', $n)) {
                $namen[$n] = true;
            }
        }
    }
    $themen = array();
    foreach ($praefixe as $p) {
        foreach (array('', '/video', '/ai', '/timelapse', '/ok') as $s) { $themen[] = $p . $s; }
        foreach (array_keys($namen) as $n) { $themen[] = $p . '/trigger/' . $n; }
    }
    return $themen;
}

/** Eine Runde, wenn sie faellig ist. Rueckgabe: '' (nichts zu tun) oder ein Wort. */
function ic_mqtt_altlast_lauf()
{
    if (!ic_mqtt_an()) { return ''; }
    $datei = ic_mqtt_altlast_datei();
    $st = @json_decode((string) @file_get_contents($datei), true);
    if (!is_array($st)) { $st = array(); }
    if (!empty($st['fertig'])) { return ''; }
    $abstaende = array(0, 900, 3600, 14400, 43200, 86400);
    $runden = isset($st['runden']) ? max(0, (int) $st['runden']) : 0;
    $erste = isset($st['erste']) ? (int) $st['erste'] : 0;
    if ($runden > 0 && $runden < count($abstaende) && time() < $erste + $abstaende[$runden]) {
        return '';
    }
    if ($runden < count($abstaende)) {
        $themen = ic_mqtt_altlast_themen();
        $ok = 0;
        foreach ($themen as $t) {
            if (ic_mqtt_roh_senden('retain', $t, '')) { $ok++; }
        }
        if ($ok !== count($themen)) {
            ic_log_gebremst('altlast', 'MQTT: die Altlast aus 2.2.5 und frueher liess sich nicht '
                . 'vollstaendig abraeumen (' . $ok . ' von ' . count($themen) . ' Paketen) - '
                . 'naechster Versuch im naechsten Takt.');
            return 'teil';
        }
        if ($runden === 0) { $st['erste'] = time(); }
        $runden++;
        $st['runden'] = $runden;
        $st['zuletzt'] = time();
        $st['themen'] = count($themen);
        if ($runden === 1) {
            ic_log('MQTT: die behaltenen Themen aus 2.2.5 und frueher werden abgeraeumt ('
                . count($themen) . ' Themen, Runde 1 von ' . count($abstaende) . ').');
        }
    }
    if ($runden >= count($abstaende)) {
        $st['fertig'] = time();
        ic_log('MQTT: die Altlast aus 2.2.5 und frueher ist abgeraeumt (' . $runden
            . ' Runden) - das geschieht nicht wieder.');
    }
    $js = json_encode($st);
    if ($js === false || !ic_datei_ersetzen($datei, $js, 0600)) {
        ic_log_gebremst('altlast_merker', 'MQTT: der Merker ' . $datei . ' liess sich nicht '
            . 'schreiben - das Abraeumen wird wiederholt.');
        return 'merker';
    }
    return 'runde';
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
    clearstatcache(true, $datei);
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
    /* BERICHTIGT 04.09.2026 (2.2.6), zwei Sachen an derselben Stelle:
     *
     * 1. Der Merker lag unter data/ und wurde damit auch von einem
     *    ABGEWIESENEN Aufruf des unangemeldeten Endpunkts angelegt -
     *    gemessen: eine Anfrage ohne Token hinterliess
     *    data/plugins/<ordner>/.meld_token_leer. Der unangemeldete
     *    Endpunkt darf nichts anlegen; die Protokollzeile selbst ist
     *    Pflicht, der Merker gehoert deshalb neben das Protokoll.
     * 2. Damit loest sich zugleich der zweite Fehler: log/ liegt auf der
     *    Ramdisk. Lag der Merker unter data/, ueberlebte er einen
     *    Neustart, das Protokoll nicht - bis zu eine Stunde lang
     *    schwieg die Bremse ueber eine laufende Stoerung, waehrend die
     *    Protokolldatei leer war.
     *
     * Und der Merker wird erst NACH ic_log() gesetzt: scheitert das
     * Schreiben ins Protokoll, ist die Meldung sonst trotzdem
     * verbraucht.
     */
    $f = ic_logordner() . '/.meld_' . preg_replace('/[^a-z0-9_]/i', '', $schluessel);
    $letzte = @is_file($f) ? (int) @file_get_contents($f) : 0;
    if (time() - $letzte >= $sekunden) {
        ic_log($text);
        @file_put_contents($f, (string) time());
    }
    return true;
}

/**
 * Ist eine Sperre gerade belegt? - ANSEHEN, nicht nehmen.
 *
 * NEU 04.09.2026 (2.2.6) fuer cleanup.php: die Bereinigung wartet kurz auf
 * den Zeitrafferlauf, statt sofort aufzugeben. Die Sperre wird dabei
 * geoeffnet, geprueft und sofort wieder freigegeben - genommen wird sie
 * nicht.
 */
function ic_sperre_belegt($name)
{
    $datei = ic_sperre_datei($name);
    if (!@is_file($datei)) { return false; }
    $fh = @fopen($datei, 'c');
    if ($fh === false) { return true; }   // im Zweifel belegt - fail closed
    $frei = @flock($fh, LOCK_EX | LOCK_NB);
    if ($frei) { @flock($fh, LOCK_UN); }
    @fclose($fh);
    return !$frei;
}

/**
 * Der Protokollordner - eine Quelle fuer Protokoll und Bremsmerker.
 */
function ic_logordner()
{
    $o = defined('LBPLOGDIR') && LBPLOGDIR !== '' ? LBPLOGDIR : ic_paths()['log'];
    if (!@is_dir($o)) { @mkdir($o, 0775, true); }
    return rtrim($o, '/');
}

/**
 * Die letzten N Zeilen einer Datei - RUECKWAERTS gelesen, nicht ganz.
 *
 * NEU 04.09.2026 (2.2.6). Bis 2.2.5 las die Oberflaeche mit
 * @file($datei) die ganze Datei in den Speicher und nahm davon die
 * letzten 200 Zeilen. Fuer <ordner>.log ist das durch die Kappung bei
 * 256 kB begrenzt - die Kandidatenliste nimmt aber auch die Altdatei
 * intercom22lox.log auf, und die kappt ic_log() nie (ic_logdatei()
 * bildet nur den neuen Namen). Nach einem Neustart, solange die neue
 * Datei noch fehlt, wurde also eine ungekappte Datei vollstaendig
 * gelesen.
 *
 * Gemessen im Haus an 12.000 Zeilen (610 kB), je 20 Durchlaeufe:
 * file()+array_reverse 0,37 ms und 2048 kB zusaetzlicher Speicher,
 * exec("tail") 2,17 ms, rueckwaerts mit fseek 0,05 ms und 0 kB.
 */
function ic_log_ende($datei, $zeilen = 200, $block = 8192)
{
    if (!@is_file($datei)) { return ''; }
    $fh = @fopen($datei, 'rb');
    if ($fh === false) { return ''; }
    @fseek($fh, 0, SEEK_END);
    $rest = (int) @ftell($fh);
    $puffer = '';
    $gefunden = 0;
    while ($rest > 0 && $gefunden <= $zeilen) {
        $lies = ($rest > $block) ? $block : $rest;
        $rest -= $lies;
        @fseek($fh, $rest, SEEK_SET);
        $stueck = (string) @fread($fh, $lies);
        $puffer = $stueck . $puffer;
        $gefunden = substr_count($puffer, "\n");
    }
    @fclose($fh);
    $z = explode("\n", $puffer);
    if (count($z) > $zeilen) { $z = array_slice($z, -$zeilen); }
    return implode("\n", $z);
}

/**
 * Taugt dieser Wert ueberhaupt fuer eine Konfigurationsdatei?
 *
 * NEU 04.09.2026 (2.2.6), Eingang der Wertpruefung beim Zurueckspielen.
 * Kein Feld, kein Objekt, kein Wahrheitswert, kein null, keine
 * Steuerzeichen, Laengengrenze. 'stationen' ist die einzige Ausnahme
 * und wird eigens geprueft.
 */
function ic_wert_taugt($w)
{
    if (is_array($w) || is_object($w) || is_bool($w) || is_null($w)) { return false; }
    $t = (string) $w;
    if (strlen($t) > 4096) { return false; }
    return preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $t) !== 1;
}

/**
 * Ist dieser Wert fuer DIESE Einstellung zulaessig?
 *
 * NEU 04.09.2026 (2.2.6). Bis 2.2.5 prueste ic_sicherung_lesen() nur den
 * SCHLUESSEL; jeder Wert ging durch. Gemessen ueber HTTP mit einer
 * hochgeladenen Datei: aus dem Aktionstoken wurde die Zahl 12345, aus
 * storage_path ein Feld, aus mqtt_praefix "haus\nEINGESCHLEUST=ja" -
 * ein Zeilenumbruch in einem Thema schleust eine zweite Zeile in jedes
 * UDP-Datagramm an das Gateway.
 *
 * Geprueft wird gegen dieselben Formen, die das Formular schreibt.
 */
function ic_wert_pruefen($schluessel, $wert)
{
    /* Stationen: ein Feld von Feldern mit bekannten Feldnamen - und seit
     * 2.2.13 (C8) mit DERSELBEN Pruefung wie das Formular: Adresse Pflicht und
     * nach dem Muster aus index.php, ms ganzzahlig 1 bis 10. Bis 2.2.12 lief
     * "127.0.0.1:47313/fang?x=" durch, und der Bildabruf schickte die
     * Zugangsdaten der Station an einen fremden Rechner (code-Pruefer, m6 d). */
    if ($schluessel === 'stationen') {
        return ic_stationen_mangel($wert) === array();
    }
    if (!ic_wert_taugt($wert)) { return false; }
    $t = (string) $wert;

    if ($schluessel === 'aktionstoken') {
        /* Weit gefasst und mit Laenge 0 - ein leeres Token in einer
         * Sicherung heisst "kein Token gesichert" und ist kein
         * unzulaessiger Wert. Zugelassen ist, was ohne Kodierung in eine
         * Adresse passt. */
        return preg_match('/^[A-Za-z0-9_.\-]{0,64}$/', $t) === 1;
    }
    if ($schluessel !== 'ms' && array_key_exists($schluessel, ic_zahlregeln())) {
        // Dieselbe Regel wie im Formular (C8/O2): ganze Zahl im Bereich.
        return ic_zahl_gueltig($schluessel, $t);
    }
    if ($schluessel === 'intercomip') {
        return $t === '' || ic_adresse_gueltig($t);
    }
    if ($schluessel === 'bildweg') {
        return in_array($t, array('strom', 'standbild', 'auto'), true);
    }
    if ($schluessel === 'timelapse_time') {
        return $t === '' || preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $t) === 1;
    }
    if (in_array($schluessel, array('timestamp_image', 'timestamp_video',
                                    'timelapse_enable', 'timelapse_video',
                                    'tv_enable', 'ai_enable',
                                    'klingel_signal', 'klingel_sprache'), true)) {
        return $t === '' || $t === 'on';
    }
    /* Klingel-1 (seit 2.2.15): Ordner, Token und Rufnummer der Nachbarlinien -
     * dieselbe Pruefung wie im Formular (ic_klingel_wert_gueltig()). */
    if (in_array($schluessel, ic_klingel_textschluessel(), true)) {
        return ic_klingel_wert_gueltig($schluessel, $t);
    }
    if (in_array($schluessel, array('bild_oeffentlich', 'mqtt_enable',
                                    'archiv_schutz'), true)) {
        return in_array($t, array('', '0', '1', 'on', 'true'), true);
    }
    if ($schluessel === 'mqtt_praefix') {
        /* Kein Schraegstrich am Rand, keine Rauten, keine Pluszeichen -
         * dieselbe Form, die das Formular seit 2.2.13 verlangt (O2). */
        return ic_praefix_gueltig($t);
    }
    /* Alles Uebrige ist ein einzeiliges Textfeld - ic_wert_taugt() hat es
     * bereits geprueft. */
    return true;
}

/* ------------------------------------------------------------------
 * EINE Pruefung fuer Formular und Zurueckspielen (NEU 2.2.13, C8/O2)
 * ------------------------------------------------------------------
 * Regeln/05: "Adressen werden an beiden Enden mit derselben Beurteilung
 * geprueft". Bis 2.2.12 stand das Adressmuster nur in index.php, und die
 * Zahlen wurden im Formular still gerundet, beim Zurueckspielen gar nicht
 * geprueft.
 */

/** Name oder IP-Adresse der Station, wahlweise mit Port - sonst nichts. */
function ic_adresse_gueltig($ip)
{
    return is_string($ip) && preg_match('/^[A-Za-z0-9.\-]+(:[0-9]{1,5})?\z/', $ip) === 1;
}

/** Das MQTT-Praefix: leer oder Stufen aus Buchstaben, Ziffern, _ und -. */
function ic_praefix_gueltig($t)
{
    return is_string($t)
        && ($t === '' || preg_match('#^[A-Za-z0-9_\-]+(/[A-Za-z0-9_\-]+)*\z#', $t) === 1);
}

/** Die Zahlenfelder: array(kleinster, groesster oder null). */
function ic_zahlregeln()
{
    return array(
        'cleanup_days'  => array(0, null),
        'cleanup_count' => array(0, null),
        'cleanup_mb'    => array(0, null),
        'intervall_min' => array(0, null),
        'tv_port'       => array(1, 65535),
        'ai_minconf'    => array(0, 100),
        'ms'            => array(1, 10),
    );
}

/** Der erlaubte Bereich als kurzer Text fuer eine Meldung. */
function ic_zahlbereich($schluessel)
{
    $r = ic_zahlregeln();
    if (!isset($r[$schluessel])) { return ''; }
    list($min, $max) = $r[$schluessel];
    return $max === null ? ('>= ' . $min) : ($min . '-' . $max);
}

/**
 * Ist das eine ganze Zahl im erlaubten Bereich? Leer heisst "nicht gesetzt"
 * und ist zulaessig - ausser bei ms. Was nicht passt, wird ABGEWIESEN, nicht
 * gerundet: bis 2.2.12 wurden aus -5 Tagen "keine Grenze" und aus 2.7 eine 2.
 */
function ic_zahl_gueltig($schluessel, $wert)
{
    $r = ic_zahlregeln();
    if (!isset($r[$schluessel])) { return false; }
    if (is_int($wert)) {
        $t = (string) $wert;
    } elseif (is_string($wert)) {
        $t = $wert;
    } else {
        return false;
    }
    if ($t === '') { return $schluessel !== 'ms'; }
    if (preg_match('/^[0-9]{1,9}\z/', $t) !== 1) { return false; }
    list($min, $max) = $r[$schluessel];
    $n = (int) $t;
    return $n >= $min && ($max === null || $n <= $max);
}

/**
 * Was stimmt an einer Stationsliste nicht? Rueckgabe: Liste von
 * array(Nummer der Station ab 1 oder 0 fuer die Liste, Feld). Leer = in Ordnung.
 */
function ic_stationen_mangel($wert)
{
    if (!is_array($wert) || count($wert) > 50) { return array(array(0, 'stationen')); }
    $erlaubt = array('name', 'ip', 'user', 'pass', 'ms', 'standbild');
    $m = array();
    $nr = 0;
    foreach ($wert as $st) {
        $nr++;
        if (!is_array($st)) { $m[] = array($nr, 'stationen'); continue; }
        foreach ($st as $sk => $sw) {
            if (!in_array((string) $sk, $erlaubt, true)) { $m[] = array($nr, (string) $sk); continue; }
            if ((string) $sk === 'ms') {
                if (!ic_zahl_gueltig('ms', $sw)) { $m[] = array($nr, 'ms'); }
                continue;
            }
            if (!ic_wert_taugt($sw)) { $m[] = array($nr, (string) $sk); }
        }
        $ip = (isset($st['ip']) && is_string($st['ip'])) ? trim($st['ip']) : '';
        if (!ic_adresse_gueltig($ip)) { $m[] = array($nr, 'ip'); }
    }
    return $m;
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
    if (!@is_dir($p['datadir'])) { @mkdir($p['datadir'], 0775, true); }
    $f = $p['datadir'] . '/.merker_' . preg_replace('/[^a-z0-9_]/i', '', $name);
    return @file_put_contents($f, time() . "\t" . str_replace("\n", ' ', (string) $text)) !== false;
}

function ic_merker_lesen($name)
{
    $p = ic_paths();
    $f = $p['datadir'] . '/.merker_' . preg_replace('/[^a-z0-9_]/i', '', $name);
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

/**
 * Das juengste Bild im Bildarchiv - oder '' wenn keines da ist.
 *
 * NEU 2.2.9 fuer den dritten Rueckfall in bild.php. Verglichen wird der
 * Zeitstempel der Datei, nicht der Name: die Namen tragen zwar das Datum,
 * aber ein zurueckgespieltes Archiv oder eine umbenannte Datei wuerde eine
 * Namenssortierung in die Irre fuehren.
 */
function ic_archiv_neuestes_bild()
{
    $o = ic_archivordner();
    $f = @glob($o['bild'] . '*.jpg');
    if (!is_array($f) || !$f) { return ''; }
    $bestes = '';
    $zeit = -1;
    foreach ($f as $d) {
        $t = @filemtime($d);
        if ($t !== false && $t > $zeit) { $zeit = $t; $bestes = $d; }
    }
    return $bestes;
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
    /* BERICHTIGT 04.09.2026 (2.2.6): der Ersatzwert kommt aus ic_vorgaben(),
     * nicht als 0 daneben. Vorher zeigte das Formular 90 Tage und diese
     * Funktion rechnete mit 0 - gemessen an einer frischen Anlage. */
    return array(
        'tage' => $n(isset($cfg['cleanup_days'])
                     ? $cfg['cleanup_days'] : ic_vorgabe('cleanup_days', 0)),
        'zahl' => $n(isset($cfg['cleanup_count'])
                     ? $cfg['cleanup_count'] : ic_vorgabe('cleanup_count', 0)),
        'mb'   => $n(isset($cfg['cleanup_mb'])
                     ? $cfg['cleanup_mb'] : ic_vorgabe('cleanup_mb', 0)),
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
    /* BERICHTIGT 2.2.13 (C6): mit dem Port des LoxBerry-Webservers. Bis 2.2.12
     * stand hier fest Port 80 - auf einer Anlage mit anderem Webport konnte
     * ffmpeg den Strom nicht lesen, und die Zeile "Endpunkt" im Reiter Test
     * wurde rot. Bei 80 ohne Port, wie bisher. */
    $port = ic_webport();
    return 'http://127.0.0.1' . ($port === 80 ? '' : ':' . $port) . '/plugins/' . ic_plugin_ordner();
}

/** Der Port des LoxBerry-Webservers: lbwebserverport(), sonst general.json. */
function ic_webport()
{
    $port = 0;
    if (function_exists('lbwebserverport')) {
        $port = (int) lbwebserverport();
    }
    if ($port < 1) {
        $gen = @json_decode((string) @file_get_contents(
            ic_paths()['home'] . '/config/system/general.json'), true);
        if (is_array($gen) && isset($gen['Webserver']) && is_array($gen['Webserver'])
            && isset($gen['Webserver']['Port'])) {
            $port = (int) $gen['Webserver']['Port'];
        }
    }
    return ($port >= 1 && $port <= 65535) ? $port : 80;
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
        /* Der Comment wird in Loxone Config zum ANZEIGENAMEN - eine
         * Beschriftung, kein Satz. Bis 2.2.5 standen hier 42 und 56
         * Zeichen; die Erklaerung steht jetzt im HintText der Wurzel. */
        /* Seit 2.2.13 (O8) aus der Sprachdatei, mit echten Umlauten; ohne
         * Sprachdatei (Selbsttest am Endpunkt) der deutsche Wortlaut. */
        $cmds[] = array(
            'title' => ic_sprachwert('LOX.V_FOTO', 'Foto') . ' ' . $s['name'],
            'comment' => ic_sprachwert('LOX.V_FOTO_KOMMENTAR', 'Foto ins Archiv'),
            'on' => $a['bild_trig'],
        );
        $cmds[] = array(
            'title' => ic_sprachwert('LOX.V_VIDEO', 'Video') . ' ' . $s['name'],
            'comment' => ic_sprachwert('LOX.V_VIDEO_KOMMENTAR', 'Video 10 s'),
            'on' => $a['video'],
        );
    }
    /* BERICHTIGT 04.09.2026 (2.2.6): eine vollstaendige http-Adresse, kein
     * Geraetepfad.
     *
     * Gemessen an der massgeblichen Ausfuhr aus Loxone Config vom 12.08.2026
     * (VO_Intercom2Loxberry - Foto_Test.xml, genau dieser Anwendungsfall):
     * Config schreibt dort
     *     Address="http://<host>/plugins/intercom/getpicture.php?token=..."
     * und dieselbe vollstaendige URL noch einmal als CmdOn. Ein Geraetepfad
     * /dev/tcp/<host>/80 schickt den Befehlstext roh ueber die Verbindung -
     * eine http-URL als CmdOn ist dort kein gueltiger Aufruf. Der
     * Importknopf legte damit Befehle an, die nichts ausloesen, waehrend die
     * von Hand nach Schritt 2 nachgebaute Fassung arbeitete. */
    $erste = $cmds ? $cmds[0]['on'] : ('http://' . $host . '/');
    return ic_xml_virtual_out(array(
        'title'   => 'Intercom (LoxBerry-Plugin)',
        'comment' => ic_sprachwert('LOX.V_KOMMENTAR',
                         'Erzeugt vom Plugin Intercom. Loxone Config legt beim Import NEU an.'),
        'address' => $erste,
        'hint'    => ic_sprachwert('LOX.V_HINWEIS',
                         'Adresse prüfen: der Miniserver muss den LoxBerry unter diesem '
                       . 'Namen erreichen. Foto holt ein Standbild und legt es ins Archiv; '
                       . 'Video nimmt 10 Sekunden auf - erlaubt sind 1 bis 300 über &s=, '
                       . 'ein Wert außerhalb wird abgewiesen.'),
    ), $cmds);
}

/** Die Vorlage der Rueckmeldungen (MQTT-Gateway-Eingaenge). */
function ic_vorlage_eingang($host)
{
    /* BERICHTIGT 04.09.2026 (2.2.6), vier Sachen an dieser Stelle:
     *
     * 1. Der Titel kommt aus ic_gatewayname(), nicht aus dem rohen Praefix -
     *    das Gateway ersetzt "/" durch "_", die Vorlage tat es nicht.
     * 2. ai_count fehlte, obwohl es ein reiner Zahlenwert ist und der
     *    Hinweistext behauptete, es fehlten nur Texte.
     * 3. Die drei Lebenszeichen-Themen sind dazugekommen.
     * 4. Der Comment wird in Loxone Config zum ANZEIGENAMEN. "Herzschlag:
     *    Loxone-Zeit des letzten Abrufs (Sekunden seit 01.01.2009)." sind
     *    70 Zeichen - das ist ein Satz, kein Kachelname. Die Erklaerung steht
     *    jetzt im HintText, wo sie hingehoert.
     */
    $g = ic_gatewayname(ic_mqtt_praefix());
    $cmds = array(
        array('title' => $g . '_status_ok',
              'comment' => ic_sprachwert('LOX.VI_OK', 'Lebenszeichen'),
              'analog' => true, 'min' => '0', 'max' => '1', 'unit' => '<v.0>'),
        array('title' => $g . '_status_ts',
              'comment' => ic_sprachwert('LOX.VI_TS', 'Herzschlag (Loxone-Zeit)'),
              'analog' => true, 'min' => '0', 'max' => '2000000000', 'unit' => '<v.0> s'),
        array('title' => $g . '_status_zaehler',
              'comment' => ic_sprachwert('LOX.VI_ZAEHLER', 'Laufzähler 0-999'),
              'analog' => true, 'min' => '-1', 'max' => '999', 'unit' => '<v.0>'),
        array('title' => $g . '_bilder',
              'comment' => ic_sprachwert('LOX.VI_BILDER', 'Bilder im Archiv'),
              'analog' => true, 'min' => '0', 'max' => '1000000', 'unit' => '<v.0>'),
        array('title' => $g . '_ai_count',
              'comment' => ic_sprachwert('LOX.VI_KI', 'Erkannte Objekte'),
              'analog' => true, 'min' => '0', 'max' => '100', 'unit' => '<v.0>'),
        array('title' => $g . '_ok',
              'comment' => ic_sprachwert('LOX.VI_OK_ALT', 'Herzschlag (überholt)'),
              'analog' => true, 'min' => '0', 'max' => '2000000000', 'unit' => '<v.0> s'),
    );
    return ic_xml_virtual_in(array(
        'title'   => ic_sprachwert('LOX.VI_TITEL', 'Intercom Rückmeldungen (LoxBerry-Plugin)'),
        'comment' => ic_sprachwert('LOX.VI_KOMMENTAR',
                         'Die Werte kommen vom MQTT-Gateway, nicht von dieser Adresse.'),
        'address' => 'http://localhost',
        'polling' => 604800,
        'hint'    => ic_sprachwert('LOX.VI_HINWEIS',
                         'Nur Zahlenwerte. Texte (Bildadresse, erkannte Objekte, '
                       . 'Zeitrafferdatei) legt das Gateway beim ersten Empfang selbst '
                       . 'an. Die Zeile <praefix>_ok ist überholt und steht nur für '
                       . 'bestehende Anlagen hier; neu ist <praefix>_status_ts. '
                       . 'Ausfallerkennung: <praefix>_status_zaehler ändert sich in '
                       . 'jeder Minute - bleibt er stehen, läuft der Cron nicht.'),
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
function ic_selbsttest($mit_netz = false, $am_endpunkt = false)
{
    /* $am_endpunkt (NEU 2.2.13, C9): der Selbsttest des Endpunkts. Dort misst
     * nie jemand das Netz - die beiden Netzzeilen sind dort ein Hinweis, kein
     * "unklar". Bis 2.2.12 war "bestanden" am Endpunkt auf jeder Anlage
     * dauerhaft false (code-Pruefer, Befund 5). */
    $cfg = ic_config();
    $z = array();

    /* -------- Ist die Konfiguration heil? (NEU 2.2.13, O6) -------- */
    list($ic_klage, $ic_kpfad) = ic_config_lage();
    $ic_geheilt = (isset($GLOBALS['ic_heilung']) && is_array($GLOBALS['ic_heilung'])
                   && $GLOBALS['ic_heilung'][0] === 'geheilt');
    if ($ic_geheilt) {
        $z[] = ic_pz('hinweis', 'TEST.F_KONF', 'TEST.A_KONF_GEHEILT', '', array(),
                     array($GLOBALS['ic_heilung'][1]));
    } elseif ($ic_klage === 'ok') {
        $z[] = ic_pz('ok', 'TEST.F_KONF', 'TEST.A_KONF_OK');
    } else {
        $z[] = ic_pz('fehl', 'TEST.F_KONF', 'TEST.A_KONF_' . strtoupper($ic_klage), 'TEST.R_KONF');
    }
    if (ic_upgrade_marke_liegt()) {
        $ic_mz = ic_upgrade_marke_zeit();
        $z[] = ic_pz('fehl', 'TEST.F_MARKE', 'TEST.A_MARKE', 'TEST.R_MARKE', array(),
                     array($ic_mz > 0 ? date('d.m.Y H:i', $ic_mz) : '?', ic_upgrade_marke()));
    }

    /* -------- Grundlagen -------- */
    $token = isset($cfg['aktionstoken']) ? (string) $cfg['aktionstoken'] : '';
    $z[] = $token !== ''
        ? ic_pz('ok', 'TEST.F_TOKEN', 'TEST.A_TOKEN_JA', '', array(), array(strlen($token)))
        : ic_pz('fehl', 'TEST.F_TOKEN', 'TEST.A_TOKEN_NEIN', 'TEST.R_TOKEN');

    /* BERICHTIGT 2.2.9: bis 2.2.8 stand hier eine Zeile, die auf JEDER
     * Installation gelb war - die plugin.cfg wird nirgendwohin installiert.
     * Ein Fehlalarm bei jedem Lauf ist eine abgeschaltete Pruefung. Gefragt
     * wird jetzt nach der Sache statt nach der Datei: woher kommen Titel und
     * Fassung? */
    list($ic_qart, $ic_qort) = ic_fassungsquelle();
    if ($ic_qart === 'datei') {
        $z[] = ic_pz('ok', 'TEST.F_CFG', 'TEST.A_CFG_JA', '', array(),
                     array($ic_qort, ic_fassung()));
    } elseif ($ic_qart === 'db') {
        $z[] = ic_pz('ok', 'TEST.F_CFG', 'TEST.A_CFG_DB', '', array(),
                     array(ic_fassung()));
    } else {
        $z[] = ic_pz('hinweis', 'TEST.F_CFG', 'TEST.A_CFG_NEIN', 'TEST.R_CFG');
    }

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
                : ic_pz('fehl', 'TEST.F_STROM', $r['fehler_ui'], 'TEST.R_STROM', array($s['name']));
            $r2 = ic_standbild_holen($s);
            $weg = isset($cfg['bildweg']) ? (string) $cfg['bildweg'] : 'strom';
            $z[] = $r2['ok']
                ? ic_pz('ok', 'TEST.F_STANDBILD', 'TEST.A_STANDBILD_JA',
                        $weg === 'strom' ? 'TEST.R_STANDBILD' : '',
                        array($s['name']), array(ic_byte(strlen($r2['bild']))))
                : ic_pz('hinweis', 'TEST.F_STANDBILD', $r2['fehler_ui'],
                        'TEST.R_STANDBILD_NEIN', array($s['name']));
        }
    } elseif ($st) {
        $z[] = $am_endpunkt
            ? ic_pz('hinweis', 'TEST.F_NETZ', 'TEST.A_NETZ_ENDPUNKT')
            : ic_pz('unklar', 'TEST.F_NETZ', 'TEST.A_NETZ_UNGEPRUEFT', 'TEST.R_NETZ');
    }

    /* -------- Der eigene Endpunkt, wirklich aufgerufen -------- */
    if ($mit_netz) {
        $url = ic_eigene_basis() . '/getpicture.php?selftest=1&token=' . rawurlencode($token);
        list($inhalt, $code) = ic_http_holen_voll($url, 6);
        $d = is_string($inhalt) ? json_decode($inhalt, true) : null;
        if (is_array($d) && !empty($d['selftest'])) {
            $z[] = ic_pz('ok', 'TEST.F_ENDPUNKT', 'TEST.A_ENDPUNKT_JA', '', array(), array($code));
        } elseif ((int) $code === 0) {
            /* NEU 2.2.13 (O6): gar keine Antwort ist ein Messfehler dieses
             * Aufrufs (Port, Namensaufloesung), kein Befund ueber Loxone. */
            $z[] = ic_pz('hinweis', 'TEST.F_ENDPUNKT', 'TEST.A_ENDPUNKT_NULL', 'TEST.R_ENDPUNKT_NULL',
                         array(), array(ic_eigene_basis()));
        } else {
            $z[] = ic_pz('fehl', 'TEST.F_ENDPUNKT', 'TEST.A_ENDPUNKT_NEIN', 'TEST.R_ENDPUNKT',
                         array(), array($code));
        }
    } else {
        $z[] = $am_endpunkt
            ? ic_pz('hinweis', 'TEST.F_ENDPUNKT', 'TEST.A_NETZ_ENDPUNKT')
            : ic_pz('unklar', 'TEST.F_ENDPUNKT', 'TEST.A_NETZ_UNGEPRUEFT', 'TEST.R_NETZ');
    }

    /* -------- Webhooks und Zusammenspiel (seit 2.2.15) -------- */
    $z[] = ic_pruefe_webhooks();                      // Intercom-b1
    $z[] = ic_pruefe_klingel('signal', $mit_netz);    // Klingel-1
    $z[] = ic_pruefe_klingel('sprache', $mit_netz);

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
    /* NEU 04.09.2026 (2.2.6): php-curl wurde bis 2.2.5 an vier Stellen
     * gebraucht (Bild ans Anzeigegeraet, Webhook 1 und 3, Video-Webhook,
     * Objekterkennung), stand nicht in dpkg/apt, und jede der vier Stellen
     * sprang kommentarlos ab. Fehlte es, feuerte nichts davon - und nichts
     * sagte es. Jetzt steht es in dpkg/apt, die Stellen schreiben eine
     * gebremste Zeile, und hier steht die Frage. */
    $z[] = function_exists('curl_init')
        ? ic_pz('ok', 'TEST.F_CURL', 'TEST.A_JA')
        : ic_pz((empty($cfg['webhook1']) && empty($cfg['webhook2'])
                 && empty($cfg['webhook3']) && empty($cfg['webhook4'])
                 && empty($cfg['videowebhook1']) && empty($cfg['videowebhook2'])
                 && empty($cfg['tv_enable']) && empty($cfg['ai_enable']))
                ? 'hinweis' : 'fehl', 'TEST.F_CURL', 'TEST.A_NEIN', 'TEST.R_CURL');
    /* BERICHTIGT 2.2.13 (O5): ist der Schutz eingeschaltet und fehlt die
     * Schutzdatei, ist das ein Kreuz. Bis 2.2.12 stand dann nur der graue
     * Hinweis "nein", und die Oberflaeche zeigte den Haken als gesetzt. */
    $ic_schutz_soll = isset($cfg['archiv_schutz'])
        && in_array((string) $cfg['archiv_schutz'], array('1', 'on', 'true'), true);
    if (ic_archiv_geschuetzt()) {
        $z[] = ic_pz('ok', 'TEST.F_ARCHIVSCHUTZ', 'TEST.A_ARCHIVSCHUTZ_AN');
    } elseif ($ic_schutz_soll) {
        $z[] = ic_pz('fehl', 'TEST.F_ARCHIVSCHUTZ', 'TEST.A_ARCHIVSCHUTZ_FEHLT',
                     'TEST.R_ARCHIVSCHUTZ_FEHLT', array(), array(ic_archiv_schutzdatei()));
    } else {
        $z[] = ic_pz('hinweis', 'TEST.F_ARCHIVSCHUTZ', 'TEST.A_ARCHIVSCHUTZ_AUS',
                     'TEST.R_ARCHIVSCHUTZ');
    }
    $z[] = function_exists('socket_create')
        ? ic_pz('ok', 'TEST.F_SOCKETS', 'TEST.A_JA')
        : ic_pz(!ic_mqtt_an() ? 'hinweis' : 'fehl',
                'TEST.F_SOCKETS', 'TEST.A_NEIN', 'TEST.R_SOCKETS');

    /* BERICHTIGT 04.09.2026 (2.2.6): der Schalter wird ausschliesslich ueber
     * ic_mqtt_an() beantwortet. Bis 2.2.5 stand daneben zweimal empty() und
     * einmal === '1'; der Kommentar an ic_mqtt_an() erklaerte die
     * Vereinheitlichung fuer vollzogen, sie war es nicht. Stand 'on' oder
     * 'true' in der Datei - was ic_mqtt_an() zulaesst und was die
     * Rueckspielfunktion bis 2.2.5 durchliess -, sendete der Herzschlag,
     * das Zeitrafferthema aber nicht. */
    /* -------- MQTT -------- */
    if (ic_mqtt_an()) {
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
        /* BERICHTIGT 04.09.2026 (2.2.6): diese Zeile war immer gruen und
         * kannte Gateway V2 nicht. Ein Haken, der nichts gemessen hat, geht
         * in die Bilanz "x von y in Ordnung" ein; und bei V2 sagte er "das
         * Abo gehoert ins Gateway", waehrend der Reiter MQTT zwei Zeilen
         * weiter richtig sagt, dass dort nichts einzutragen ist. */
        $gv = ic_gateway_fassung();
        if ($gv >= 2) {
            $z[] = ic_pz('hinweis', 'TEST.F_MQTTABO', 'TEST.A_MQTTABO_V2');
        } elseif ($gv === 0) {
            $z[] = ic_pz('unklar', 'TEST.F_MQTTABO', 'TEST.A_UNKLAR', 'TEST.R_MQTTABO');
        } else {
            $z[] = ic_pz('hinweis', 'TEST.F_MQTTABO', ic_mqtt_praefix() . '/#',
                         'TEST.R_MQTTABO');
        }
        $z[] = ic_pruefe_themen();
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
        /* NEU 2.2.13 (O6/C9): ein ausgeschalteter Zeitraffer ist grau "aus",
         * kein Dauer-Fragezeichen - ausgeschaltet ist eine Entscheidung. */
        if ($m === 'timelapse'
            && (empty($cfg['timelapse_enable']) || $cfg['timelapse_enable'] !== 'on')) {
            $z[] = ic_pz('hinweis', $frage, 'TEST.A_LAUF_AUS');
            continue;
        }
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

    /* -------- Stehen die Cron-Eintraege? (NEU 2.2.13, O6) -------- */
    $z[] = ic_pruefe_cron();

    /* -------- Die eigene Oberflaeche gegen sich selbst -------- */
    $z[] = ic_pruefe_reiter();
    $z[] = ic_pruefe_formulare();
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

/**
 * Stimmen die gesendeten Themen mit der Themenliste ueberein?
 *
 * NEU 04.09.2026 (2.2.6). Die Deckung war beim Nachmessen vollstaendig - aber
 * nichts hielt sie. Genau daran ist Renault 2.0.6 gescheitert: 20 gesendete
 * Themen standen in keiner Anleitung. Gelesen werden die ic_mqtt_senden()-
 * Aufrufe aus ALLEN drei Dateien, die senden, nicht nur aus dieser.
 */
function ic_pruefe_themen()
{
    $p = ic_paths();
    $dateien = array(
        __DIR__ . '/ic_lib.php',
        dirname(__DIR__) . '/html/getpicture.php',
        dirname(__DIR__) . '/html/videowebhook.php',
        $p['html'] . '/getpicture.php',
        $p['html'] . '/videowebhook.php',
    );
    $gesendet = array();
    $gelesen = 0;
    foreach ($dateien as $d) {
        if (!@is_file($d)) { continue; }
        $t = (string) @file_get_contents($d);
        if ($t === '') { continue; }
        $gelesen++;
        if (preg_match_all("/ic_mqtt_senden\(\s*'([^']*)'/", $t, $m)) {
            foreach ($m[1] as $u) {
                $gesendet[$u === '' ? '' : $u] = true;
            }
        }
    }
    if ($gelesen === 0) {
        return ic_pz('unklar', 'TEST.F_THEMEN', 'TEST.A_UNKLAR');
    }
    $praefix = ic_mqtt_praefix();
    $genannt = array();
    foreach (ic_mqtt_themen() as $t) {
        $genannt[$t[0]] = true;
    }
    $fehlt = array();
    foreach (array_keys($gesendet) as $u) {
        /* 'trigger/' . $trigger steht als Verkettung im Quelltext; der
         * Aufruf liefert dann 'trigger/' - die Liste fuehrt trigger/NAME.
         *
         * BERICHTIGT am Tag des Einbaus (04.09.2026), gemessen an der
         * Selbstpruefung: hier stand rtrim($u, '/') und danach .= 'NAME',
         * also OHNE den Schraegstrich - herausgekommen ist
         * "<praefix>/triggerNAME", das steht in keiner Liste, und die
         * Zeile meldete auf JEDER Anlage einen Fehler. Eine Pruefung, die
         * immer rot ist, ist keine Pruefung. */
        $voll = $u === '' ? $praefix : $praefix . '/' . rtrim($u, '/');
        if (substr($u, -1) === '/') { $voll .= '/NAME'; }
        if (!isset($genannt[$voll])) { $fehlt[] = $voll; }
    }
    if ($fehlt) {
        return ic_pz('fehl', 'TEST.F_THEMEN', implode(', ', $fehlt), 'TEST.R_THEMEN',
                     array(), array());
    }
    return ic_pz('ok', 'TEST.F_THEMEN', 'TEST.A_THEMEN', '', array(),
                 array(count($gesendet), count($genannt)));
}

/** Die erzeugte Vorlage durch den XML-Leser schicken - wohlgeformt oder nicht. */
function ic_pruefe_vorlage()
{
    $cfg = ic_config();
    /* BERICHTIGT 04.09.2026 (2.2.6): php-xml ist nicht garantiert geladen.
     * Bis 2.2.5 stand simplexml_load_string() ohne Wache hier - es war die
     * EINZIGE ungeschuetzte Erweiterungsfunktion des Plugins. Fehlte
     * php-xml, starb ic_selbsttest() mit einem fatalen Fehler, und weil
     * ic_start.php display_errors abschaltet, war das HTTP 500 mit leerem
     * Rumpf - an allen fuenf Endpunkten, ausgerechnet an der Stelle, die
     * dem Anwender sagen soll, ob sein Token stimmt. php-xml steht seit
     * 2.2.6 zusaetzlich in dpkg/apt. */
    if (!function_exists('simplexml_load_string')) {
        return ic_pz('unklar', 'TEST.F_VORLAGE', 'TEST.A_VORLAGE_UNKLAR', 'TEST.R_VORLAGE_XML');
    }
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
    ic_archiv_schutz_anwenden();
    return @is_dir($l);
}

/** Der Pfad der Schutzdatei des Archivs. */
function ic_archiv_schutzdatei()
{
    return rtrim(ic_paths()['legacy'], '/') . '/.htaccess';
}

/**
 * Ist das Archiv ohne Anmeldung erreichbar?
 *
 * NEU 04.09.2026 (2.2.6). Gemessen am Quelltext der LoxBerry-Fassung
 * 4.0.0.15 und der LoxBerry-Fassung 3.0.1.3: die Vhost-Datei traegt
 *     Alias /legacy/ ${LBHOMEDIR}/webfrontend/legacy/
 * mit einem Directory-Block OHNE AuthType und Require und MIT
 * Options +Indexes; unter webfrontend/legacy/ liegt keine .htaccess,
 * anders als unter webfrontend/htmlauth/. Das Bild- und Videoarchiv
 * dieses Plugins liegt genau dort - jedes Geraet im Netz kann es
 * auflisten und herunterladen.
 *
 * Der Haken "Das letzte Bild nur mit Token" schuetzt gemessen nur
 * lastpicture.jpg, nicht das Archiv. Der Hinweistext sagt das seit
 * 2.2.6 auch.
 *
 * Rueckgabe: true = geschuetzt (Schutzdatei liegt), false = offen.
 */
function ic_archiv_geschuetzt()
{
    return @is_file(ic_archiv_schutzdatei());
}

/**
 * Den Archivschutz herstellen oder wegnehmen - nach der Einstellung.
 *
 * AB WERK AUS. Eine Anmeldung vor dem Archiv aendert das Verhalten jeder
 * bestehenden Anlage: die Galerien holen ihre Bilder ueber /legacy/, und
 * ob der Browser die Anmeldung der Plugin-Seite dorthin mitnimmt, ist an
 * einem Geraet zu messen und hier nicht messbar. Deshalb ein Schalter,
 * ab Werk aus, mit einer Zeile im Reiter Test, die den Zustand nennt -
 * melden ist richtig, blockieren nicht.
 */
function ic_archiv_schutz_anwenden()
{
    $cfg = ic_config();
    $an = isset($cfg['archiv_schutz'])
        ? in_array((string) $cfg['archiv_schutz'], array('1', 'on', 'true'), true)
        : false;
    $datei = ic_archiv_schutzdatei();
    if (!$an) {
        if (@is_file($datei)) { @unlink($datei); }
        return false;
    }
    if (@is_file($datei)) { return true; }
    $wurzel = rtrim(ic_paths()['home'], '/');
    /* Wortgleich mit webfrontend/htmlauth/.htaccess des LoxBerry, damit
     * dieselbe Anmeldung gilt und nicht eine zweite entsteht. */
    $inhalt = "AuthType Basic\n"
            . "AuthName \"Loxberry Administration\"\n"
            . "AuthUserFile " . $wurzel . "/config/system/htusers.dat\n"
            . "Require valid-user\n"
            . "Order allow,deny\n"
            . "Allow from localhost\n"
            . "Allow from 127.0.0.1\n"
            . "Allow from 127.0.1.1\n"
            . "Satisfy Any\n"
            . "Options -Indexes\n";
    return ic_datei_ersetzen($datei, $inhalt, 0644);
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
 * Rueckgabe: array(ok, meldung). Meldung ist '' , 'verschoben', ein Pfad
 * (Speicherort fehlt/nicht beschreibbar), 'belegt', 'kopieren_gescheitert'
 * oder 'verweis_gescheitert' - die Oberflaeche macht daraus den Satz.
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
        return array(false, 'belegt');
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
        /* BERICHTIGT 2.2.13 (C1): erst pruefen, dann loeschen.
         *
         * Bis 2.2.12 stand hier cp -rn ohne Blick auf das Ergebnis und danach
         * unbedingt rm -rf. Gemessen (code-Pruefer, m7): Zielordner vorhanden,
         * aber nicht beschreibbar - cp scheiterte, das Archiv wurde trotzdem
         * geloescht, Rueckgabe "verschoben", danach 0 Bilder. Dasselbe bei
         * vollem USB-Stick.
         *
         * Jetzt: Ziel beschreibbar? Kopieren, dann Datei fuer Datei vergleichen
         * (Name und Groesse). Ein ZWEITER Gang holt nach, was Klingel, Takt oder
         * Zeitraffer waehrend des ersten ins alte Archiv geschrieben haben -
         * diese Schreiber fragen die Sperre des Speicherorts nicht. Erst wenn
         * beide Vergleiche vollstaendig sind, wird das alte Archiv entfernt.
         * Scheitert etwas, bleibt es vollstaendig, und es gibt keinen Verweis. */
        if (!@is_dir($ziel) || !@is_writable($ziel)) {
            ic_log('Speicherort: der Zielordner ' . $ziel . ' ist nicht beschreibbar - das Archiv '
                . 'bleibt, wo es ist; es wurde nichts geloescht.');
            flock($sperre, LOCK_UN);
            fclose($sperre);
            return array(false, 'kopieren_gescheitert');
        }
        if (!ic_archiv_kopieren($link, $ziel) || !ic_archiv_kopieren($link, $ziel)) {
            flock($sperre, LOCK_UN);
            fclose($sperre);
            return array(false, 'kopieren_gescheitert');
        }
        $o = array();
        $rc = 1;
        @exec('rm -rf ' . escapeshellarg($link) . ' 2>&1', $o, $rc);
        clearstatcache();
        if (@file_exists($link) || !@symlink($ziel, $link)) {
            ic_log('Speicherort: das Archiv liegt vollstaendig unter ' . $ziel . ', der Verweis '
                . $link . ' liess sich aber nicht anlegen (rm endete mit ' . $rc . ').');
            flock($sperre, LOCK_UN);
            fclose($sperre);
            return array(false, 'verweis_gescheitert');
        }
        ic_log('Speicherort: das Archiv wurde nach ' . $ziel . ' verschoben.');
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
 * Einen Archivnamen EXKLUSIV anlegen (NEU 2.2.13, C10).
 *
 * Der Name ist nur sekundengenau. Gemessen (mqtt-Pruefer, F8): fuenf Paare
 * gleichzeitiger Aufrufe mit gleichem Ausloeser ergaben 5 statt 10
 * Archivbilder, waehrend alle zehn Antworten "archived": true meldeten - der
 * zweite Schreiber ersetzte die Datei des ersten. Jetzt wird der Name mit
 * fopen(..., 'x') belegt; ist er vergeben, kommt eine Nummer dahinter
 * (-intercom-2.jpg). Rueckgabe: der belegte Dateiname oder ''.
 */
function ic_archivdatei_anlegen($ordner, $zusatz = '', $endung = 'jpg')
{
    $basis = ic_archivname($zusatz, $endung);
    $stamm = substr($basis, 0, -strlen('.' . $endung));
    for ($i = 1; $i <= 50; $i++) {
        $name = $i === 1 ? $basis : $stamm . '-' . $i . '.' . $endung;
        $fh = @fopen($ordner . $name, 'x');
        if ($fh !== false) {
            fclose($fh);
            return $name;
        }
        if (!@is_dir($ordner)) { return ''; }
    }
    return '';
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

/* UMGEZOGEN 2.2.13 (I4): NEBEN den Datenordner. Bis 2.2.12 lag die Datei in
 * data/plugins/<ordner>/, und den raeumt der Installer bei jedem Upgrade ab -
 * jeder verschickte Link starb beim naechsten Auto-Update, auch wenn er noch
 * Tage galt, und bild.php meldete "abgelaufen" (Installer-Pruefer, B1).
 * uninstall raeumt die Datei ab, eine Neuinstallation legt sie nach .alt. */
function ic_bildlink_datei()
{
    $p = ic_paths();
    return dirname($p['datadir']) . '/' . basename($p['datadir']) . '.bildlinks.json';
}

/** Der Ordner der Bildkopien je Code (C11), 0700, daneben. */
function ic_bildlink_ordner()
{
    $p = ic_paths();
    return dirname($p['datadir']) . '/' . basename($p['datadir']) . '.bildlinks';
}

/** Eine Sperre um Lesen-Aendern-Schreiben der Linkliste (blockierend, kurz). */
function ic_bildlink_sperre()
{
    $o = ic_bildlink_ordner();
    if (!@is_dir($o)) { @mkdir($o, 0700, true); }
    return ic_sperre_warten($o . '/.sperre', 10);
}

/** Die Liste schreiben und Kopien ohne Eintrag abraeumen - nur unter der Sperre. */
function ic_bildlink_schreiben(array $liste)
{
    $js = json_encode($liste, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($js === false || !ic_datei_ersetzen(ic_bildlink_datei(), $js, 0600)) {
        return false;
    }
    foreach ((@glob(ic_bildlink_ordner() . '/*.jpg') ?: array()) as $f) {
        if (!isset($liste[basename($f, '.jpg')])) { @unlink($f); }
    }
    return true;
}

/** Einmalige Uebernahme aus dem alten Ort (bis 2.2.12). */
function ic_bildlink_uebernehmen()
{
    $neu = ic_bildlink_datei();
    $alt = ic_paths()['datadir'] . '/bildlinks.json';
    if (@is_file($neu) || !@is_file($alt)) { return; }
    $roh = @file_get_contents($alt);
    if ($roh !== false && $roh !== '' && ic_datei_ersetzen($neu, $roh, 0600)) {
        @unlink($alt);
    }
}

/** Die gekappte Stundenzahl - fuer Link UND Meldung dieselbe (O7). */
function ic_bildlink_stunden($w)
{
    return is_numeric($w) ? max(1, min(720, (int) $w)) : 24;
}

function ic_bildlink_liste()
{
    ic_bildlink_uebernehmen();
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

/**
 * Einen befristeten Link anlegen - an DAS BILD gebunden (NEU 2.2.13, C11).
 *
 * BILDLINK_TEXT verspricht "das Bild vom Klingeln". Bis 2.2.12 lieferte der
 * Link das jeweils NEUESTE Bild: gemessen (mqtt-Pruefer, F2) zeigte Link 1
 * nach dem zweiten Klingeln das Bild des zweiten Besuchers. Jetzt liegt je
 * Code eine Kopie (0600) im Ordner neben bildlinks.json; sie geht mit dem
 * Link. $quelle: das Bild; ohne Angabe das, was bild.php gerade als letztes
 * Bild ausliefern wuerde. Ohne Bild gibt es keinen Link.
 */
function ic_bildlink_erzeugen($stunden = 24, $abrufe = 5, $quelle = '')
{
    $stunden = ic_bildlink_stunden($stunden);
    $abrufe = max(1, min(1000, (int) $abrufe));
    if ($quelle === '') { list($quelle) = ic_letztes_bild_pfad(); }
    $bild = ($quelle !== '') ? @file_get_contents($quelle) : false;
    if ($bild === false || $bild === '') { return ''; }
    $sperre = ic_bildlink_sperre();
    if ($sperre === false) { return ''; }
    $code = ic_token_neu(20);
    $kopie = ic_bildlink_ordner() . '/' . $code . '.jpg';
    if (!ic_datei_ersetzen($kopie, $bild, 0600)) {
        ic_sperre_frei($sperre);
        return '';
    }
    $liste = ic_bildlink_liste();
    $liste[$code] = array('bis' => time() + $stunden * 3600, 'rest' => $abrufe,
                          'erzeugt' => time(), 'bild' => $code . '.jpg');
    if (!ic_bildlink_schreiben($liste)) {
        @unlink($kopie);
        ic_sperre_frei($sperre);
        return '';
    }
    ic_sperre_frei($sperre);
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
        return array(false, ic_uebersetzt('TEST.E_KEINE_STATION', array(),
                                          'Es ist keine Tuerstation eingerichtet.'), '');
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
        return array(false, $r['fehler_ui'], '');
    }
    if (!ic_datei_ersetzen($ziel, $r['bild'])) {
        ic_log('Zeitraffer: das Bild liess sich nicht schreiben: ' . $ziel);
        ic_merker_setzen('timelapse', 'nicht schreibbar');
        return array(false, ic_uebersetzt('TEST.E_NICHT_SCHREIBBAR', array($ziel),
                                          'Das Bild liess sich nicht schreiben: ' . $ziel), '');
    }
    if (!empty($cfg['timestamp_image']) && $cfg['timestamp_image'] === 'on') {
        ic_zeitstempel_ins_bild($ziel);
    }
    ic_log('Zeitrafferbild aufgenommen: ' . basename($ziel) . ' (' . ic_byte(strlen($r['bild'])) . ')');
    ic_merker_setzen('timelapse', basename($ziel));
    if (ic_mqtt_an()) {
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
        || empty($cfg['ai_url'])
        || !@is_file($bilddatei)) {
        return array();
    }
    /* BERICHTIGT 04.09.2026 (2.2.6): die Wache bleibt - php-curl ist nicht
     * garantiert geladen -, aber sie schweigt nicht mehr. Bis 2.2.5 sprang
     * die Erkennung ohne curl kommentarlos ab; eingeschaltet und nutzlos
     * ist schlimmer als abgeschaltet. */
    if (!function_exists('curl_init')) {
        ic_log_gebremst('curl_ki', 'Die Objekterkennung ist eingeschaltet, aber die '
            . 'PHP-Erweiterung curl fehlt - es wird nichts erkannt. '
            . 'Abhilfe: sudo apt install php-curl');
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
    if (PHP_VERSION_ID < 80000) { curl_close($ch); }
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
    $name = ic_archivdatei_anlegen($o['bild'], 'intervall');
    $ziel = $o['bild'] . $name;
    if ($name === '' || !ic_datei_ersetzen($ziel, $r['bild'])) {
        if ($name !== '') { @unlink($ziel); }
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
    /* Seit 2.2.13 (O8) in der Sprache der Oberflaeche; ueber den Cron (ohne
     * Sprachdatei) deutsch wie bisher. */
    if ($g['tage'] <= 0 && $g['zahl'] <= 0 && $g['mb'] <= 0) {
        return array(0, 0, array(ic_uebersetzt('UI.CU_KEINE_GRENZE', array(),
            'Keine Grenze eingestellt - es wird nichts geloescht.')));
    }

    $gruppen = array(
        ic_uebersetzt('UI.CU_BILDER', array(), 'Bilder')
            => array($o['bild'], array('*.jpg'), $g['zahl']),
        ic_uebersetzt('UI.CU_VIDEOS', array(), 'Videos')
            => array($o['video'], array('*.avi', '*.jpg'), $g['zahl'] > 0 ? $g['zahl'] * 2 : 0),
        ic_uebersetzt('UI.CU_ZEITRAFFER', array(), 'Zeitraffer')
            => array($o['timelapse'], array('*.jpg'), $g['zahl']),
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
                    $zeilen[] = ic_uebersetzt($alt ? 'UI.CU_ZU_ALT' : 'UI.CU_UEBERZAEHLIG',
                        array($name, basename($d), ic_byte($gr_byte)),
                        $name . ': ' . basename($d) . ' (' . ic_byte($gr_byte) . ')'
                        . ($alt ? ' - zu alt' : ' - ueberzaehlig'));
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
                        $zeilen[] = ic_uebersetzt('UI.CU_PLATZ', array(basename($d), ic_byte($gr_byte)),
                            'Platz: ' . basename($d) . ' (' . ic_byte($gr_byte) . ')');
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

/**
 * Einen Code einloesen - der Abruf wird dabei mitgezaehlt.
 *
 * Rueckgabe: false (ungueltig, abgelaufen, verbraucht), '' (der Link gilt,
 * seine Bildkopie fehlt aber), 'letztes' (Link aus einer frueheren Fassung
 * ohne Bildkopie: wie bisher das letzte Bild) oder der Pfad der Bildkopie.
 * Seit 2.2.13 unter einer Sperre: bis 2.2.12 lasen, aenderten und schrieben
 * getpicture.php und bild.php die Liste ohne Sperre (code-Pruefer, Nicht
 * pruefbar, Wettlauf).
 */
function ic_bildlink_einloesen($code)
{
    if (!is_string($code) || !preg_match('/^[A-Za-z0-9]{10,40}$/', $code)) { return false; }
    $sperre = ic_bildlink_sperre();
    if ($sperre === false) { return false; }
    $liste = ic_bildlink_liste();
    $treffer = '';
    // In gleichbleibender Zeit vergleichen, wie beim Zugriffstoken.
    foreach ($liste as $c => $e) {
        if (hash_equals((string) $c, $code)) { $treffer = (string) $c; }
    }
    if ($treffer === '') {
        ic_sperre_frei($sperre);
        return false;
    }
    $e = $liste[$treffer];
    $liste[$treffer]['rest'] = (int) $e['rest'] - 1;
    ic_bildlink_schreiben($liste);
    ic_sperre_frei($sperre);
    if (!isset($e['bild'])) { return 'letztes'; }
    $pfad = ic_bildlink_ordner() . '/' . basename((string) $e['bild']);
    return @is_file($pfad) ? $pfad : '';
}


/**
 * Die Vorgabewerte - an EINER Stelle, fuer Oberflaeche UND Bibliothek.
 *
 * BERICHTIGT 04.09.2026 (2.2.6). Bis 2.2.5 stand die Liste allein in
 * index.php und fuellte nur die Anzeige; ic_aufbewahrung() hatte eigene
 * Ersatzwerte. Gemessen an einer frischen Anlage (data.json = {}): das
 * Formular zeigte "90 Tage", ic_aufbewahrung() lieferte tage=0, und
 * ic_aufraeumen() meldete "Keine Grenze eingestellt - es wird nichts
 * geloescht". Zwei Wahrheiten ueber dieselbe Einstellung; die Karte lief
 * voll, waehrend das Formular eine Grenze anzeigte.
 */
function ic_vorgaben()
{
    return array(
        'intercomip' => '', 'storage_path' => '', 'timelapse_time' => '12:00',
        'tv_ip' => '', 'tv_port' => '7676', 'ai_url' => '', 'ai_minconf' => '50',
        'cleanup_days' => '90', 'cleanup_count' => '', 'cleanup_mb' => '',
        'intervall_min' => '', 'standbild_pfad' => '/jpg/image.jpg',
        'bildweg' => 'strom', 'bild_oeffentlich' => '1',
        'mqtt_enable' => '0', 'mqtt_praefix' => '',
        'webhook1' => '', 'webhook2' => '', 'webhook3' => '', 'webhook4' => '',
        'videowebhook1' => '', 'videowebhook2' => '',
        'timestamp_image' => '', 'timestamp_video' => '', 'timelapse_enable' => '',
        'timelapse_video' => '', 'tv_enable' => '', 'ai_enable' => '',
        'archiv_schutz' => '0',
        'aktionstoken' => '',
        // Klingel-1 (seit 2.2.15, D): ab Werk AUS - eine eingerichtete
        // Anlage verhaelt sich nach dem Update wie vorher.
        'klingel_signal' => '', 'klingel_signal_ordner' => 'signalbot',
        'klingel_signal_token' => '', 'klingel_signal_an' => '',
        'klingel_sprache' => '', 'klingel_sprache_ordner' => 'sprachsteuerung',
        'klingel_sprache_token' => '',
        // Entscheidung 13: nur bei diesen Ausloesern (Komma-Liste).
        'klingel_ausloeser' => 'klingel',
    );
}

/**
 * Einen Vorgabewert holen.
 *
 * Wer einen Ersatzwert braucht, holt ihn hier - nicht als Literal neben
 * der Lesestelle. Sonst entstehen wieder zwei Wahrheiten.
 */
function ic_vorgabe($schluessel, $ersatz = '')
{
    $v = ic_vorgaben();
    return array_key_exists($schluessel, $v) ? $v[$schluessel] : $ersatz;
}

/**
 * Die Schluessel, die zur Konfiguration gehoeren.
 *
 * Diese Linie hat KEINE Vorgabenfunktion: ic_config() gibt die data.json
 * roh zurueck, und die Ersatzwerte stehen verstreut an den Stellen, die
 * sie aus $_POST uebernehmen. Erfunden wird hier nichts - die Liste ist
 * gemessen an allen Stellen, die $ic_cfg[...] oder $ic_neu[...] anfassen.
 *
 * Wer hier einen Schluessel ergaenzt, muss ihn auch in der Oberflaeche
 * fuehren, sonst laesst die Sicherung etwas durch, das niemand setzt.
 */
function ic_sicherungsschluessel()
{
    return array(
        'ai_enable', 'ai_minconf', 'ai_url', 'aktionstoken',
        'archiv_schutz', 'bild_oeffentlich', 'bildweg',
        'cleanup_count', 'cleanup_days', 'cleanup_mb',
        'intercomip', 'intervall_min',
        'klingel_ausloeser', 'klingel_signal', 'klingel_signal_an', 'klingel_signal_ordner', 'klingel_signal_token',
        'klingel_sprache', 'klingel_sprache_ordner', 'klingel_sprache_token',
        'mqtt_enable', 'mqtt_praefix',
        'standbild_pfad', 'stationen', 'storage_path',
        'timelapse_enable', 'timelapse_time', 'timelapse_video',
        'timestamp_image', 'timestamp_video',
        'tv_enable', 'tv_ip', 'tv_port',
        'videowebhook1', 'videowebhook2',
        'webhook1', 'webhook2', 'webhook3', 'webhook4',
    );
}

/**
 * Eine Sicherungsdatei einlesen - und dabei NICHTS durchgehen lassen.
 *
 * Grundlage ist der AKTUELLE Stand, nicht eine Vorgabenliste: Vorgabewerte
 * gibt es hier nicht zu messen, und geraten werden sie nicht. Ein
 * Schluessel, der in der Sicherung fehlt, behaelt damit seinen jetzigen
 * Wert - das ist der einzige Unterschied zu den uebrigen Linien, und er
 * ist gewollt.
 *
 * Der Rest ist wie ueberall: eine halb gueltige Datei ueberschreibt GAR
 * NICHTS. Eine zur Haelfte uebernommene Konfiguration ist schlimmer als
 * die alte, und man sieht es ihr nicht an.
 *
 * Rueckgabe: array(Konfiguration|null, Beanstandungen[], uebernommene Werte,
 * uebergangene Schluessel[], Token der Datei war leer).
 *
 * Der VIERTE Wert kam am 07.09.2026 dazu. Bis dahin quittierte die
 * Oberflaeche nur "n Werte zurueckgespielt" - eine Zahl, die nach
 * Vollstaendigkeit aussieht, obwohl fehlende Schluessel stillschweigend
 * ihren jetzigen Wert behielten. Beim UMZUG auf einen zweiten LoxBerry -
 * dem ausdruecklichen Zweck einer Sicherung - ist "der jetzige Wert" die
 * frische Konfiguration; der Schluessel blieb also auf dem Anfangswert,
 * ohne ein Wort. Pumpenwacht und Midea2Lox, die dieselbe Grundlage
 * benutzen, nennen die uebergangenen Schluessel seit jeher.
 */
function ic_sicherung_lesen($roh)
{
    $mangel = array();
    $leertoken = false;
    $daten = json_decode((string) $roh, true);
    if (!is_array($daten)) {
        return array(null, array(ic_txt('UI.SICH_KEIN_JSON')), 0);
    }
    $neu = ic_config();
    $bekannt = ic_sicherungsschluessel();
    $anzahl = 0;
    foreach ($daten as $k => $w) {
        /* Der lesbare Kopf wird UEBERGANGEN, nicht beanstandet. Seit 2.2.6
         * schreibt die Sicherung einen: _plugin, _fassung, _erzeugt. Wer
         * eine alte Datei von Hand um eine Zeile ergaenzt, soll sie nicht
         * zurueckgewiesen bekommen. */
        if ((string) $k !== '' && substr((string) $k, 0, 1) === '_') {
            continue;
        }
        if (!in_array($k, $bekannt, true)) {
            $mangel[] = sprintf(ic_txt('UI.SICH_FREMD'),
                                 htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8'));
            continue;
        }
        /* BERICHTIGT 04.09.2026 (2.2.6): auch der WERT wird geprueft, nicht
         * nur der Schluessel. Gemessen ueber HTTP: bis 2.2.5 wurde aus dem
         * Aktionstoken die Zahl 12345, aus storage_path ein Feld und aus
         * mqtt_praefix eine Zeichenkette mit Zeilenumbruch. Fail closed -
         * eine Beanstandung heisst, dass GAR NICHTS geschrieben wird. */
        /* NEU 2.2.13 (C8): je Station und Feld eine Beanstandung - "Station 1:
         * ip" sagt mehr als "stationen". Gesammelt wird weiter ALLES, und bei
         * einer Beanstandung wird nichts geschrieben. */
        if ($k === 'stationen') {
            $sm = ic_stationen_mangel($w);
            if ($sm) {
                foreach ($sm as $sp) {
                    $mangel[] = sprintf(ic_txt('UI.SICH_STATION'), (int) $sp[0],
                                        htmlspecialchars((string) $sp[1], ENT_QUOTES, 'UTF-8'));
                }
                continue;
            }
        }
        if (!ic_wert_pruefen($k, $w)) {
            $mangel[] = sprintf(ic_txt('UI.SICH_WERT'),
                                 htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8'));
            continue;
        }
        /* NEU 17.09.2026: ein LEERES Aktionstoken heisst "kein Token
         * gesichert" und ist kein unzulaessiger Wert (Regeln/05, an
         * VolkswagenID 0.9.12 entschieden) - es darf aber auch das
         * laufende nicht ersetzen. An jedem Zugriffstoken haengt jede
         * Adresse im Miniserver; ein leeres macht sie alle stumm
         * ungueltig, und seit dieser Fassung erzeugt die Oberflaeche
         * kein neues mehr an seiner Stelle. Der Schluessel behaelt
         * deshalb seinen jetzigen Wert - wie jeder, der in der Datei
         * fehlt -, und die Oberflaeche sagt es (UI.SICH_TOKEN_LEER).
         * Beanstandet wird nichts: eine Sicherung ohne Token ist keine
         * halb gueltige Datei. */
        if ((string) $k === 'aktionstoken' && (string) $w === '') {
            $leertoken = true;
            continue;
        }
        $neu[$k] = $w;
        $anzahl++;
    }
    if ($anzahl === 0) {
        $mangel[] = ic_txt('UI.SICH_LEER');
    }
    /* Was NICHT in der Datei stand, behaelt seinen jetzigen Wert - und der
     * Anwender erfaehrt, welche das waren. Der lesbare Kopf zaehlt nicht
     * mit: er gehoert nicht zur Konfiguration. */
    $uebergangen = array();
    foreach ($bekannt as $bk) {
        if (!array_key_exists($bk, $daten)) {
            $uebergangen[] = $bk;
        }
    }
    return array($mangel ? null : $neu, $mangel, $anzahl, $uebergangen, $leertoken);
}

/**
 * Ablegen und einen schlichten Wahrheitswert liefern.
 *
 * ic_config_speichern() gibt array($ok, $was) zurueck. Ein nicht-leeres
 * Array ist in PHP wahr - auch wenn $ok false ist. Wer das Paar direkt in
 * ein if schreibt, meldet jeden Schreibfehler als Erfolg.
 */
function ic_config_ablegen($cfg)
{
    $r = ic_config_speichern($cfg);
    return is_array($r) ? (bool) $r[0] : (bool) $r;
}

/* Aus vier Oberflaechendateien hierher zusammengefuehrt. Sie trugen
 * dieselbe Definition Wort fuer Wort - und ohne function_exists-Schutz;
 * zwei davon gleichzeitig geladen haetten die Seite zerlegt. Solange die
 * Uebersetzung nur in den Oberflaechen stand, liess sich ausserdem keine
 * Bibliotheksfunktion einzeln pruefen, die sie benutzt. */
function ic_txt($schluessel)
{
    global $L;
    return isset($L[$schluessel]) ? ic_e($L[$schluessel]) : $schluessel;
}

/* ==================================================================
 * Seit 2.2.13 (Durchgang 30.09.2026)
 * ================================================================== */

/**
 * Ein Sprachwert UNMASKIERT mit Einsetzungen - oder der Ersatz (O8).
 *
 * Fuer Meldungen, die die Bibliothek erzeugt: in der Oberflaeche ist $L
 * geladen, und der Satz kommt in deren Sprache; ueber den Cron und am
 * Endpunkt gibt es kein $L, dort bleibt der deutsche Ersatz. Wer das Ergebnis
 * in HTML ausgibt, maskiert es selbst.
 */
function ic_uebersetzt($schluessel, array $args, $ersatz)
{
    global $L;
    if (!is_array($L) || !isset($L[$schluessel]) || !is_string($L[$schluessel])) {
        return $ersatz;
    }
    if (!$args) { return $L[$schluessel]; }
    try {
        $t = @vsprintf($L[$schluessel], $args);
    } catch (\Throwable $e) {
        return $ersatz;
    }
    return is_string($t) ? $t : $ersatz;
}

/** Ein Sprachwert unmaskiert ohne Einsetzungen - fuer die Loxone-Vorlagen (O8). */
function ic_sprachwert($schluessel, $ersatz)
{
    return ic_uebersetzt($schluessel, array(), $ersatz);
}

/** Eine blockierende Sperre mit Frist; Rueckgabe Dateizeiger oder false. */
function ic_sperre_warten($datei, $sekunden = 10)
{
    $fh = @fopen($datei, 'c');
    if ($fh === false) { return false; }
    $ende = microtime(true) + $sekunden;
    while (!@flock($fh, LOCK_EX | LOCK_NB)) {
        if (microtime(true) > $ende) {
            @fclose($fh);
            return false;
        }
        usleep(50000);
    }
    return $fh;
}

function ic_sperre_frei($fh)
{
    if (is_resource($fh)) {
        @flock($fh, LOCK_UN);
        @fclose($fh);
    }
}

/** Das Bild, das bild.php gerade als letztes ausliefern wuerde: array(pfad, quelle). */
function ic_letztes_bild_pfad()
{
    $p = ic_paths();
    if (@is_file($p['datadir'] . '/lastpicture.jpg')) {
        return array($p['datadir'] . '/lastpicture.jpg', 'datei');
    }
    if (@is_file($p['html'] . '/lastpicture.jpg')) {
        return array($p['html'] . '/lastpicture.jpg', 'offene-kopie');
    }
    $a = ic_archiv_neuestes_bild();
    return $a !== '' ? array($a, 'archiv') : array('', '');
}

/* ------------------------------------------------------------------
 * C1: Archiv kopieren und nachzaehlen
 * ------------------------------------------------------------------ */

/**
 * Ein Kopiergang: cp -rn, danach JEDE Datei der Quelle im Ziel mit derselben
 * Groesse. Die Zaehlung entscheidet, nicht allein der Rueckgabewert: neuere
 * coreutils melden bei -n auch fuer eine uebersprungene, schon vorhandene
 * Datei einen Fehler. Beides steht im Protokoll.
 */
function ic_archiv_kopieren($quelle, $ziel)
{
    $o = array();
    $rc = 1;
    @exec('cp -rn ' . escapeshellarg($quelle) . '/. ' . escapeshellarg($ziel) . '/ 2>&1', $o, $rc);
    list($n, $fehlt) = ic_archiv_vergleich($quelle, $ziel);
    if ($n < 0 || $fehlt > 0) {
        ic_log('Speicherort: das Kopieren nach ' . $ziel . ' ist gescheitert (cp endete mit ' . $rc
            . ($o ? ': ' . substr(str_replace(array("\r", "\n"), ' ', (string) $o[0]), 0, 160) : '')
            . '; ' . ($n < 0 ? 'die Quelle liess sich nicht lesen' : $fehlt . ' von ' . $n
            . ' Dateien fehlen im Ziel oder weichen ab') . '). Das Archiv bleibt, wo es ist; '
            . 'es wurde nichts geloescht.');
        return false;
    }
    return true;
}

/** Zaehlung Quelle gegen Ziel: array(Dateien der Quelle, davon fehlend/abweichend); -1 = unlesbar. */
function ic_archiv_vergleich($quelle, $ziel)
{
    $n = 0;
    $fehlt = 0;
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($quelle, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile()) { continue; }
            $n++;
            $rel = substr($f->getPathname(), strlen(rtrim($quelle, '/')) + 1);
            $z = rtrim($ziel, '/') . '/' . $rel;
            clearstatcache(true, $z);
            if (!@is_file($z) || @filesize($z) !== $f->getSize()) { $fehlt++; }
        }
    } catch (\Exception $e) {
        return array(-1, 0);
    }
    return array($n, $fehlt);
}

/* ------------------------------------------------------------------
 * C2/C4: laufende Videoaufzeichnung, interner Aufruf ohne Token
 * ------------------------------------------------------------------
 * getvideo.php legt die Marke vor dem Start an: Start, Dauer, PID, Datei und
 * Station. Sie ist die Sperre ueber die LAUFZEIT von ffmpeg (verfaellt nach
 * Dauer + 60 s) und zugleich der Ausweis fuer die beiden internen Aufrufe:
 * ffmpeg liest den Strom ueber mjpgproxy.php?intern=1, die Rueckmeldung ruft
 * videowebhook.php?intern=1&file=... - beide OHNE Zugriffstoken, nur von
 * 127.0.0.1, nur waehrend dieser Aufzeichnung, der Strom nur ein einziges
 * Mal. Bis 2.2.12 stand das Token bis zu 300 s in der Prozessliste, lesbar
 * fuer jeden Benutzer (code-Pruefer, Befund 6).
 */
function ic_videolauf_datei()
{
    return ic_paths()['datadir'] . '/.video_lauf.json';
}

/** Die Marke, solange sie gilt (Start + Dauer + $nachlauf s), sonst null. */
function ic_videolauf_lesen($nachlauf = 60)
{
    $f = ic_videolauf_datei();
    clearstatcache(true, $f);
    if (!@is_file($f)) { return null; }
    $d = json_decode((string) @file_get_contents($f), true);
    if (!is_array($d) || !isset($d['start'], $d['dauer'])) { return null; }
    if (time() > (int) $d['start'] + (int) $d['dauer'] + (int) $nachlauf) { return null; }
    return $d;
}

function ic_videolauf_setzen(array $d)
{
    $js = json_encode($d);
    return $js !== false && ic_datei_ersetzen(ic_videolauf_datei(), $js, 0600);
}

/** Die Rueckmeldung ist da: Marke weg, wenn sie zu dieser Datei gehoert. */
function ic_videolauf_ende($datei)
{
    $d = ic_videolauf_lesen(600);
    if ($d !== null && isset($d['datei']) && (string) $d['datei'] === (string) $datei) {
        @unlink(ic_videolauf_datei());
    }
}

/** Kommt der Aufruf vom eigenen Rechner? */
function ic_von_hier()
{
    $a = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    return $a === '127.0.0.1' || $a === '::1';
}

/**
 * Darf dieser interne Aufruf ohne Token herein? $zweck 'proxy' (einmal, mit
 * passender Station) oder 'hook' (mit passendem Dateinamen).
 */
function ic_intern_erlaubt($zweck, $datei = '')
{
    if (!ic_von_hier()) { return false; }
    $sperre = ic_sperre_warten(ic_videolauf_datei() . '.sperre', 5);
    if ($sperre === false) { return false; }
    $ok = false;
    $d = ic_videolauf_lesen($zweck === 'hook' ? 600 : 60);
    if ($d !== null && $zweck === 'proxy' && empty($d['proxy'])) {
        $st = (isset($_GET['station']) && is_string($_GET['station'])) ? $_GET['station'] : '';
        if ((string) (isset($d['station']) ? $d['station'] : '') === $st) {
            $d['proxy'] = time();
            $ok = ic_videolauf_setzen($d);
        }
    } elseif ($d !== null && $zweck === 'hook' && $datei !== '' && isset($d['datei'])) {
        $ok = hash_equals((string) $d['datei'], (string) $datei);
    }
    ic_sperre_frei($sperre);
    return $ok;
}

/**
 * Ein Webhook kam nicht an: gebremst ins Protokoll (NEU 2.2.13, M5).
 *
 * Genannt wird der NAME des Webhooks, nie seine Adresse - sie kann
 * Zugangsdaten tragen. Adressen in der Fehlermeldung von curl werden
 * unkenntlich gemacht. Wiederholt wird nicht (Dubletten beim Empfaenger;
 * Bauliste, "beim Bau so festgelegt").
 */
function ic_webhook_pruefen($name, $angekommen, $code, $fehler)
{
    $code = (int) $code;
    /* NEU 2.2.15 (Intercom-b1): je Webhook ein Merker "zuletzt angekommen"
     * und EIN Merker "letzter Fehler" (Name, Grund, Zeit) - daraus liest der
     * Reiter Test seine Zeile. Die Adresse steht in keinem von beiden. */
    if ($angekommen && $code >= 200 && $code < 300) {
        ic_merker_setzen('webhook_ok_' . ic_webhook_kennung($name), '1');
        return true;
    }
    $f = preg_replace('#[a-z][a-z0-9+.\-]*://\S+#i', '<Adresse>', (string) $fehler);
    $f = preg_replace('#[^\s/@]+:[^\s/@]*@#', '', $f);
    $ic_grund = ($code > 0 ? 'HTTP ' . $code : 'keine Antwort')
              . ($f !== '' ? ' (' . substr(str_replace("\t", ' ', $f), 0, 120) . ')' : '');
    ic_merker_setzen('webhookfehler', $name . "\t" . $ic_grund);
    ic_log_gebremst('webhook_' . preg_replace('/[^a-z0-9]/i', '', $name), $name . ' kam nicht an: '
        . ($code > 0 ? 'HTTP ' . $code : 'keine Antwort') . ($f !== '' ? ' (' . $f . ')' : '')
        . '. Es wird nicht wiederholt.');
    return false;
}

/* ------------------------------------------------------------------
 * O6: drei Zeilen fuer den Reiter Test
 * ------------------------------------------------------------------ */

/** Wie steht data.json? array(ok|leer|ohne|kaputt, pfad) */
function ic_config_lage()
{
    $datei = ic_paths()['config'] . '/data.json';
    if (!@is_file($datei)) { return array('leer', $datei); }
    $roh = (string) @file_get_contents($datei);
    $rest = preg_replace('/\s+/', '', $roh);
    if ($rest === '' || $rest === '{}' || $rest === '[]') { return array('leer', $datei); }
    $d = json_decode($roh, true);
    if (!is_array($d)) { return array('kaputt', $datei); }
    return ic_config_hat_inhalt($d) ? array('ok', $datei) : array('ohne', $datei);
}

/** Stehen die beiden Cron-Eintraege? */
function ic_pruefe_cron()
{
    $home = ic_paths()['home'];
    if ($home === '' || !@is_dir($home . '/system/cron')) {
        return ic_pz('unklar', 'TEST.F_CRON', 'TEST.A_UNKLAR');
    }
    $o = ic_plugin_ordner();
    $da = array();
    $fehlt = array();
    foreach (array('cron.01min', 'cron.daily') as $c) {
        $f = $home . '/system/cron/' . $c . '/' . $o;
        if (@is_file($f)) { $da[] = $f; } else { $fehlt[] = $f; }
    }
    return $fehlt
        ? ic_pz('fehl', 'TEST.F_CRON', 'TEST.A_CRON_NEIN', 'TEST.R_CRON', array(),
                array(implode(', ', $fehlt)))
        : ic_pz('ok', 'TEST.F_CRON', 'TEST.A_CRON_JA', '', array(), array(implode(', ', $da)));
}

/**
 * Tragen alle Formulare das Merkmal? Gezaehlt in den ausgelieferten Dateien:
 * jedes Formular-Tag gegen jeden Aufruf von ic_formularfelder() in einer
 * Ausgabezeile. Die gesuchten Formen stehen hier nur als Muster.
 */
function ic_pruefe_formulare()
{
    $formen = 0;
    $merkmale = 0;
    $gelesen = 0;
    foreach (array('index.php', 'archive.php', 'videoarchive.php', 'live.php') as $d) {
        $t = @file_get_contents(__DIR__ . '/' . $d);
        if ($t === false) { continue; }
        $gelesen++;
        $formen += preg_match_all('/<form\b/i', $t);
        $merkmale += preg_match_all('/<\?=\s*ic_formularfelder\(/', $t);
    }
    if ($gelesen === 0) {
        return ic_pz('unklar', 'TEST.F_FORMULARE', 'TEST.A_UNKLAR');
    }
    return ($formen > 0 && $formen === $merkmale)
        ? ic_pz('ok', 'TEST.F_FORMULARE', 'TEST.A_FORMULARE', '', array(), array($merkmale, $formen))
        : ic_pz('fehl', 'TEST.F_FORMULARE', 'TEST.A_FORMULARE_NEIN', 'TEST.R_FORMULARE',
                array(), array($merkmale, $formen));
}

/* ------------------------------------------------------------------
 * O1: Einmalmeldung fuer PRG (Regeln/04, Raumklima 0.11.8)
 * ------------------------------------------------------------------
 * Jeder POST endet mit 303 auf index.php?tab=<reiter>; was er zu sagen hat,
 * liegt bis zum naechsten GET in data/plugins/<ordner>/einmalmeldung.json
 * (0600, hoechstens 120 s alt). Gelesen wird NUR beim GET, geloescht VOR der
 * Anzeige. Zugangsdaten stehen nie darin; ein befristeter Bildlink ist darin
 * genauso geschuetzt wie in bildlinks.json.
 */
function ic_einmal_datei()
{
    return ic_paths()['datadir'] . '/einmalmeldung.json';
}

function ic_einmal_schreiben(array $d)
{
    $d['zeit'] = time();
    $js = json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $js !== false && ic_datei_ersetzen(ic_einmal_datei(), $js, 0600);
}

function ic_einmal_lesen()
{
    $f = ic_einmal_datei();
    if (!@is_file($f)) { return array(); }
    $roh = (string) @file_get_contents($f);
    @unlink($f);
    $d = json_decode($roh, true);
    if (!is_array($d) || !isset($d['zeit'])
        || (time() - (int) $d['zeit']) > 120 || (time() - (int) $d['zeit']) < -5) {
        return array();
    }
    return $d;
}

/* ==================================================================
 * Seit 2.2.15 (Verbesserungsbau Welle 1, 30.09.2026)
 * ================================================================== */

/* ------------------------------------------------------------------
 * Intercom-a2: leichte Stationsprobe im Minutentakt
 * ------------------------------------------------------------------
 * status/ok spiegelte ohne Aufnahme im Takt den letzten Stationskontakt,
 * gleich wie alt (Baubericht 2.2.13, Offen). Die Probe fragt die Station
 * einmal je Minute an, OHNE ein Bild zu holen und ohne Archivbild:
 *   bildweg standbild/auto  HEAD auf die Standbild-Adresse
 *   bildweg strom/auto      den Strom oeffnen, nur die Kopfzeilen lesen,
 *                           sofort schliessen
 * Zeitgrenze 3 s, keiner Umleitung folgen (C5), Zugangsdaten als Kopfzeile.
 * Kontakt heisst: 2xx. Ein 401 ist KEIN Kontakt - ein Bild gaebe es damit
 * auch nicht (dieselbe Lesart wie ic_bild_holen()).
 */

/** Die Standbild-Adresse einer Station (Pfad), an EINER Stelle. */
function ic_standbild_pfad(array $station)
{
    $cfg = ic_config();
    $pfad = $station['standbild'] !== '' ? $station['standbild']
          : ((isset($cfg['standbild_pfad']) && trim((string) $cfg['standbild_pfad']) !== '')
             ? trim((string) $cfg['standbild_pfad']) : '/jpg/image.jpg');
    if (substr($pfad, 0, 1) !== '/') { $pfad = '/' . $pfad; }
    return $pfad;
}

/** Eine Station anfragen, ohne ein Bild zu holen. Rueckgabe array(ok, grund). */
function ic_stationsprobe(array $station, $zeitgrenze = 3)
{
    $cfg = ic_config();
    $weg = isset($cfg['bildweg']) ? (string) $cfg['bildweg'] : 'strom';
    list($u, $pw) = ic_zugangsdaten($station);
    $kopf = array();
    if ($u !== '') { $kopf[] = 'Authorization: Basic ' . base64_encode($u . ':' . $pw); }
    $wege = array();
    if ($weg === 'standbild' || $weg === 'auto') { $wege[] = array('HEAD', ic_standbild_pfad($station)); }
    if ($weg !== 'standbild') { $wege[] = array('GET', '/mjpg/video.mjpg'); }
    $grund = '';
    foreach ($wege as $w) {
        $ctx = stream_context_create(array('http' => array(
            'method' => $w[0],
            'timeout' => $zeitgrenze,
            'ignore_errors' => true,
            'follow_location' => 0,
            'header' => implode("\r\n", $kopf),
        )));
        $f = @fopen('http://' . $station['ip'] . $w[1], 'r', false, $ctx);
        if ($f === false) {
            $grund = $w[0] . ' ' . $w[1] . ': nicht erreichbar';
            continue;
        }
        $code = ic_status_aus_kopf(ic_strom_kopfzeilen($f));
        @fclose($f);
        if ($code >= 200 && $code < 300) { return array(true, ''); }
        $grund = $w[0] . ' ' . $w[1] . ': HTTP ' . $code;
    }
    return array(false, $grund);
}

/**
 * Die Probe im Cron-Takt (timelapse.php). Nur mit MQTT - sie speist allein
 * status/ok. Nicht, wenn in diesem Takt schon ein Abruf lief (Klingel,
 * Aufnahme im Takt, Zeitraffer): dessen Merker ist dann juenger als 50 s.
 * Mehrere Stationen: Kontakt nur, wenn ALLE antworten.
 * Rueckgabe: true/false (Ergebnis), null (nicht geprobt).
 */
function ic_stationsprobe_lauf()
{
    $st = ic_stationen();
    if (!$st || !ic_mqtt_an()) { return null; }
    $mk = ic_merker_lesen('stationskontakt');
    if ($mk !== null && (time() - (int) $mk['zeit']) < 50) { return null; }
    $alle = true;
    foreach ($st as $s) {
        list($ok, $grund) = ic_stationsprobe($s);
        if (!$ok) {
            $alle = false;
            ic_log_gebremst('probe_' . preg_replace('/[^a-z0-9]/i', '', $s['name']),
                'Stationsprobe: "' . $s['name'] . '" antwortet nicht (' . $grund . ') - status/ok geht auf 0.');
        }
    }
    ic_merker_setzen('stationskontakt', $alle ? '1' : '0');
    return $alle;
}

/* ------------------------------------------------------------------
 * Intercom-b1: der letzte Webhook-Fehler im Reiter Test
 * ------------------------------------------------------------------ */

/** Merkername eines Webhooks: "Video-Webhook 1" -> "VideoWebhook1". */
function ic_webhook_kennung($name)
{
    return preg_replace('/[^a-z0-9]/i', '', (string) $name);
}

/** Die Zeile "Letzter Webhook-Fehler" - aus den Merkern von ic_webhook_pruefen(). */
function ic_pruefe_webhooks()
{
    $cfg = ic_config();
    $namen = array('webhook1' => 'Webhook 1', 'webhook2' => 'Webhook 2', 'webhook3' => 'Webhook 3',
                   'webhook4' => 'Webhook 4', 'videowebhook1' => 'Video-Webhook 1',
                   'videowebhook2' => 'Video-Webhook 2');
    $eingetragen = 0;
    $zuletzt_ok = 0;
    foreach ($namen as $k => $n) {
        if (isset($cfg[$k]) && trim((string) $cfg[$k]) !== '') { $eingetragen++; }
        $m = ic_merker_lesen('webhook_ok_' . ic_webhook_kennung($n));
        if ($m !== null && $m['zeit'] > $zuletzt_ok) { $zuletzt_ok = (int) $m['zeit']; }
    }
    $f = ic_merker_lesen('webhookfehler');
    if ($f !== null) {
        $teile = explode("\t", (string) $f['text'], 2);
        $name = $teile[0];
        $grund = isset($teile[1]) ? $teile[1] : '?';
        $wann = date('d.m.Y H:i', (int) $f['zeit']);
        $schluessel = array_search($name, $namen, true);
        $ok = ic_merker_lesen('webhook_ok_' . ic_webhook_kennung($name));
        if ($ok !== null && (int) $ok['zeit'] >= (int) $f['zeit']) {
            return ic_pz('hinweis', 'TEST.F_WEBHOOKFEHLER', 'TEST.A_WEBHOOKFEHLER_BEHOBEN', '', array(),
                         array($name, $grund, $wann, date('d.m.Y H:i', (int) $ok['zeit'])));
        }
        if ($schluessel === false || !isset($cfg[$schluessel]) || trim((string) $cfg[$schluessel]) === '') {
            return ic_pz('hinweis', 'TEST.F_WEBHOOKFEHLER', 'TEST.A_WEBHOOKFEHLER_WEG', '', array(),
                         array($name, $grund, $wann));
        }
        return ic_pz('fehl', 'TEST.F_WEBHOOKFEHLER', 'TEST.A_WEBHOOKFEHLER', 'TEST.R_WEBHOOKFEHLER',
                     array(), array($name, $grund, $wann));
    }
    if ($eingetragen === 0) {
        return ic_pz('hinweis', 'TEST.F_WEBHOOKFEHLER', 'TEST.A_WEBHOOK_KEINER');
    }
    if ($zuletzt_ok === 0) {
        // Kein Haken fuer etwas, das nie lief (Klasse 8).
        return ic_pz('hinweis', 'TEST.F_WEBHOOKFEHLER', 'TEST.A_WEBHOOK_NOCH_NIE');
    }
    return ic_pz('ok', 'TEST.F_WEBHOOKFEHLER', 'TEST.A_WEBHOOK_OK', '', array(),
                 array(date('d.m.Y H:i', $zuletzt_ok)));
}

/* ------------------------------------------------------------------
 * Klingel-1 (D): Meldung ueber SignalBot, Ansage ueber die Sprachsteuerung
 * ------------------------------------------------------------------
 * Ab Werk AUS (vb_RAHMEN, D-Punkte). Der Weg ist der vorhandene
 * HTTP-Endpunkt der anderen Linie, gelesen in deren Quelltext:
 *   SignalBot 0.9.25       /plugins/<ordner>/index.php?token=..&aktion=senden
 *                          &text=..[&an=+49..]   -> "SIGNAL;OK=1;..."
 *   Sprachsteuerung 0.11.12 /plugins/<ordner>/index.php?token=..&aktion=sprechen
 *                          &text=..              -> "SET;OK=1;..."
 *   beide                  ?selftest=1&token=..  -> "SELFTEST;OK=1;TOKEN=OK"
 * Nie ueber deren Dateien. Gerufen wird ueber 127.0.0.1 und den Port des
 * LoxBerry-Webservers, ohne einer Umleitung zu folgen (das Token steht in der
 * Adresse). Fehlt die andere Linie oder schweigt sie: eine gebremste
 * Protokollzeile, ein Merker, der Reiter Test wird gelb - Bild, Archiv, MQTT
 * und Webhooks laufen wie ohne die Einstellung. Wiederholt wird nicht.
 */

/** Ein Plugin-Ordner: Kleinbuchstaben, Ziffern, _ und -. */
function ic_nachbar_ordner_gueltig($o)
{
    return is_string($o) && preg_match('/^[a-z0-9][a-z0-9_\-]{0,39}\z/', $o) === 1;
}

/** Die Textschluessel der Kopplung (die Haken pruefen sich wie jeder Haken). */
function ic_klingel_textschluessel()
{
    return array('klingel_ausloeser', 'klingel_signal_ordner', 'klingel_signal_token', 'klingel_signal_an',
                 'klingel_sprache_ordner', 'klingel_sprache_token');
}

/** EINE Pruefung fuer Formular und Zurueckspielen. Leerer Ordner = Vorgabe. */
function ic_klingel_wert_gueltig($schluessel, $wert)
{
    if (!is_string($wert) && !is_int($wert)) { return false; }
    $t = (string) $wert;
    if (substr($schluessel, -7) === '_ordner') {
        return $t === '' || ic_nachbar_ordner_gueltig($t);
    }
    if (substr($schluessel, -6) === '_token') {
        return preg_match('/^[A-Za-z0-9_.\-]{0,128}\z/', $t) === 1;
    }
    if ($schluessel === 'klingel_ausloeser') {
        // Namen wie im Parameter trigger, durch Komma getrennt; leer = nie.
        return $t === '' || preg_match('/^\s*[A-Za-z0-9_\-]{1,32}\s*(,\s*[A-Za-z0-9_\-]{1,32}\s*){0,19}\z/', $t) === 1;
    }
    if ($schluessel === 'klingel_signal_an') {
        // Dieselbe Form, die SignalBot selbst verlangt (sonst 400).
        return $t === '' || preg_match('/^\+[0-9]{6,20}\z/', $t) === 1;
    }
    return false;
}

/** Ordner der Nachbarlinie fuer 'signal' oder 'sprache'. */
function ic_klingel_ordner($art)
{
    $cfg = ic_config();
    $k = 'klingel_' . $art . '_ordner';
    $o = isset($cfg[$k]) ? trim((string) $cfg[$k]) : '';
    return ic_nachbar_ordner_gueltig($o) ? $o : (string) ic_vorgabe($k, '');
}

/** Ist die Kopplung eingeschaltet? */
function ic_klingel_an($art)
{
    $cfg = ic_config();
    return isset($cfg['klingel_' . $art]) && $cfg['klingel_' . $art] === 'on';
}

/**
 * Den Endpunkt einer anderen Linie rufen. Rueckgabe array(code, rumpf, fehler);
 * code 0 = keine Antwort. Die Adresse (mit Token) geht nirgends hin - nicht
 * ins Protokoll, nicht in die Fehlermeldung.
 */
function ic_nachbar_rufen($ordner, array $parameter, $zeitgrenze = 5)
{
    if (!ic_nachbar_ordner_gueltig($ordner)) { return array(0, '', 'Ordner unzulaessig'); }
    $port = ic_webport();
    $url = 'http://127.0.0.1' . ($port === 80 ? '' : ':' . $port) . '/plugins/' . $ordner
         . '/index.php?' . http_build_query($parameter, '', '&', PHP_QUERY_RFC3986);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $zeitgrenze,
            CURLOPT_CONNECTTIMEOUT => min(2, $zeitgrenze),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'LoxBerry Intercom',
        ));
        $antwort = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $fehler = curl_error($ch);
        if (PHP_VERSION_ID < 80000) { curl_close($ch); }
    } else {
        $ctx = stream_context_create(array('http' => array(
            'method' => 'GET', 'timeout' => $zeitgrenze, 'ignore_errors' => true,
            'follow_location' => 0, 'user_agent' => 'LoxBerry Intercom')));
        $f = @fopen($url, 'r', false, $ctx);
        if ($f === false) {
            $antwort = false;
            $code = 0;
            $fehler = 'keine Verbindung';
        } else {
            $code = ic_status_aus_kopf(ic_strom_kopfzeilen($f));
            $antwort = (string) @stream_get_contents($f, 2000);
            @fclose($f);
            $fehler = '';
        }
    }
    $fehler = preg_replace('#[a-z][a-z0-9+.\-]*://\S+#i', '<Adresse>', (string) $fehler);
    return array($code, is_string($antwort) ? substr($antwort, 0, 2000) : '',
                 str_replace(array("\t", "\n", "\r"), ' ', $fehler));
}

/** Der Grund einer gescheiterten Kopplung in einer Zeile - ohne Adresse, ohne Token. */
function ic_klingel_grund($code, $rumpf, $fehler)
{
    if ((int) $code === 0) {
        return 'keine Antwort' . ($fehler !== '' ? ' (' . substr($fehler, 0, 120) . ')' : '');
    }
    $g = preg_match('/(?:GRUND|ERR)=([A-Z_]{1,40})/', (string) $rumpf, $m) ? $m[1] : '';
    return 'HTTP ' . (int) $code . ($g !== '' ? ' ' . $g : '')
         . ((int) $code === 404 ? ' - Plugin nicht installiert oder anderer Ordner' : '');
}

/**
 * Die Sprachdatei am Endpunkt (Entscheidung 13: Text aus der Sprachdatei).
 * In der Oberflaeche ist $L schon geladen; am unangemeldeten Endpunkt wird
 * templates/plugins/<ordner>/lang/language_<sprache>.ini gelesen - so, wie
 * LBSystem::readlanguage() sie liest (Abschnitt.Schluessel, INI_SCANNER_RAW).
 * Fehlt die Datei der Sprache, die deutsche; fehlt auch die, bleibt $L leer
 * und ic_uebersetzt() nimmt den deutschen Ersatz.
 */
function ic_sprache_laden()
{
    global $L;
    if (is_array($L) && $L) { return; }
    $sprache = (class_exists('LBSystem', false) && method_exists('LBSystem', 'lblanguage'))
             ? strtolower((string) LBSystem::lblanguage()) : 'de';
    if (!preg_match('/^[a-z]{2}$/', $sprache)) { $sprache = 'de'; }
    $o = ic_paths()['home'] . '/templates/plugins/' . ic_plugin_ordner() . '/lang/';
    foreach (array_unique(array($sprache, 'de')) as $s) {
        $f = $o . 'language_' . $s . '.ini';
        if (!@is_file($f)) { continue; }
        $ini = @parse_ini_file($f, true, INI_SCANNER_RAW);
        if (!is_array($ini)) { continue; }
        $L = array();
        foreach ($ini as $abschnitt => $paare) {
            if (!is_array($paare)) { continue; }
            foreach ($paare as $k => $v) { $L[$abschnitt . '.' . $k] = (string) $v; }
        }
        return;
    }
}

/** Die Ausloeser, bei denen gemeldet wird (Entscheidung 13, Vorgabe "klingel"). */
function ic_klingel_ausloeser()
{
    $cfg = ic_config();
    $roh = array_key_exists('klingel_ausloeser', $cfg) ? (string) $cfg['klingel_ausloeser']
         : (string) ic_vorgabe('klingel_ausloeser', 'klingel');
    if (!ic_klingel_wert_gueltig('klingel_ausloeser', $roh)) { return array(); }
    $aus = array();
    foreach (explode(',', $roh) as $n) {
        $n = strtolower(trim($n));
        if ($n !== '') { $aus[] = $n; }
    }
    return $aus;
}

/** Wird bei diesem Aufruf gemeldet? Eingeschaltet UND Ausloeser in der Liste. */
function ic_klingel_gefragt($trigger)
{
    if (!ic_klingel_an('signal') && !ic_klingel_an('sprache')) { return false; }
    return $trigger !== '' && in_array(strtolower((string) $trigger), ic_klingel_ausloeser(), true);
}

/**
 * Hoechstens eine Meldung je 60 s und Station (Entscheidung 13). Der Merker
 * wird unter einer Sperre gelesen und gesetzt; ist die Sperre nicht zu
 * bekommen, wird NICHT gemeldet (faellt geschlossen aus).
 */
function ic_klingel_bremse_frei($station)
{
    $d = ic_paths()['datadir'];
    if (!@is_dir($d)) { @mkdir($d, 0775, true); }
    $sperre = ic_sperre_warten($d . '/.klingel_bremse.sperre', 5);
    if ($sperre === false) { return false; }
    $name = 'klingelbremse_' . substr(md5((string) $station), 0, 12);
    $m = ic_merker_lesen($name);
    $frei = ($m === null || (time() - (int) $m['zeit']) >= 60 || (time() - (int) $m['zeit']) < -5);
    if ($frei) { ic_merker_setzen($name, (string) $station); }
    ic_sperre_frei($sperre);
    return $frei;
}

/** Der Meldetext aus der Sprachdatei - Klingel oder anderer Ausloeser, bei mehreren Stationen mit Namen. */
function ic_klingel_text($trigger, $station)
{
    ic_sprache_laden();
    $t = (strcasecmp($trigger, 'klingel') === 0 || $trigger === '')
       ? ic_uebersetzt('UI.KLINGEL_M_KLINGEL', array(), 'Es hat geklingelt')
       : ic_uebersetzt('UI.KLINGEL_M_AUSLOESER', array($trigger), 'Auslöser: ' . $trigger);
    if ($station !== '') {
        $t = ic_uebersetzt('UI.KLINGEL_M_STATION', array($t, $station), $t . ' (' . $station . ')');
    }
    return $t . '.';
}

/** Ergebnis festhalten: Merker immer, Protokoll bei Erfolg je Klingeln, bei Fehler gebremst. */
function ic_klingel_ergebnis($art, $name, $ok, $grund)
{
    if ($ok) {
        ic_merker_setzen('klingel_' . $art, "ok\t");
        ic_log('Klingel: Meldung an ' . $name . ' abgegeben.');
        return true;
    }
    ic_merker_setzen('klingel_' . $art, "fehler\t" . $grund);
    ic_log_gebremst('klingel_' . $art, 'Klingel: die Meldung an ' . $name . ' kam nicht an: ' . $grund
        . '. Das Klingeln selbst ist davon nicht betroffen; es wird nicht wiederholt.');
    return false;
}

/**
 * Nach dem Klingeln melden (getpicture.php, NACH der Antwort an Loxone).
 * $bildquelle ist DIESES Bild: der befristete Link wird daran gebunden (C11).
 * Leer heisst: der Bildabruf scheiterte - gemeldet wird trotzdem, ohne Bild
 * (Entscheidung 13). Rueckgabe: array(art => true|false) fuer die
 * eingeschalteten Wege; array('gebremst' => true), wenn die 60-s-Bremse griff;
 * leer, wenn nicht gefragt (aus oder Ausloeser nicht in der Liste).
 */
function ic_klingel_melden($station, $trigger, $mehrere, $bildquelle, $basis)
{
    $aus = array();
    if (!ic_klingel_gefragt((string) $trigger)) { return $aus; }
    if (!ic_klingel_bremse_frei($station)) {
        ic_log_gebremst('klingel_bremse_' . substr(md5((string) $station), 0, 12),
            'Klingel: Meldung fuer "' . $station . '" unterdrueckt - hoechstens eine je 60 s und Station.', 300);
        return array('gebremst' => true);
    }
    $sig = ic_klingel_an('signal');
    $spr = ic_klingel_an('sprache');
    $cfg = ic_config();
    $text = ic_klingel_text((string) $trigger, $mehrere ? (string) $station : '');
    if ($sig) {
        $tok = isset($cfg['klingel_signal_token']) ? (string) $cfg['klingel_signal_token'] : '';
        if ($tok === '') {
            $aus['signal'] = ic_klingel_ergebnis('signal', 'SignalBot', false, 'kein Token eingetragen');
        } else {
            $code = ((string) $bildquelle !== '') ? ic_bildlink_erzeugen(24, 5, (string) $bildquelle) : '';
            if ((string) $bildquelle === '') {
                $zusatz = ic_uebersetzt('UI.KLINGEL_M_OHNE_BILD', array(),
                                        'Ohne Bild: die Türstation lieferte keines.');
            } elseif ($code === '') {
                $zusatz = ic_uebersetzt('UI.KLINGEL_M_OHNE_LINK', array(),
                                        'Ohne Bild: der befristete Bildlink ließ sich nicht anlegen.');
            } else {
                $zusatz = ic_uebersetzt('UI.KLINGEL_M_BILD',
                    array($basis . 'bild.php?link=' . rawurlencode($code)),
                    'Bild (24 h, 5 Abrufe): ' . $basis . 'bild.php?link=' . rawurlencode($code));
            }
            $t = $text . ' ' . $zusatz;
            $p = array('token' => $tok, 'aktion' => 'senden', 'text' => $t);
            $an = isset($cfg['klingel_signal_an']) ? trim((string) $cfg['klingel_signal_an']) : '';
            if ($an !== '') { $p['an'] = $an; }
            list($c, $r, $f) = ic_nachbar_rufen(ic_klingel_ordner('signal'), $p, 5);
            $ok = ($c === 200 && strpos(ltrim($r), 'SIGNAL;OK=1') === 0);
            $aus['signal'] = ic_klingel_ergebnis('signal', 'SignalBot', $ok,
                                                 $ok ? '' : ic_klingel_grund($c, $r, $f));
        }
    }
    if ($spr) {
        $tok = isset($cfg['klingel_sprache_token']) ? (string) $cfg['klingel_sprache_token'] : '';
        if ($tok === '') {
            $aus['sprache'] = ic_klingel_ergebnis('sprache', 'Sprachsteuerung', false, 'kein Token eingetragen');
        } else {
            list($c, $r, $f) = ic_nachbar_rufen(ic_klingel_ordner('sprache'),
                array('token' => $tok, 'aktion' => 'sprechen', 'text' => $text), 5);
            $ok = ($c === 200 && strpos(ltrim($r), 'SET;OK=1') === 0);
            $aus['sprache'] = ic_klingel_ergebnis('sprache', 'Sprachsteuerung', $ok,
                                                  $ok ? '' : ic_klingel_grund($c, $r, $f));
        }
    }
    return $aus;
}

/**
 * Die Zeile im Reiter Test. Mit Netz fragt sie den Selbsttest der anderen
 * Linie (loest dort nichts aus); ohne Netz liest sie den Merker des letzten
 * Klingelns. Gelb (unklar) heisst: eingeschaltet, aber die Kopplung traegt
 * nicht - das Klingeln selbst laeuft weiter.
 */
function ic_pruefe_klingel($art, $mit_netz = false)
{
    $frage = $art === 'signal' ? 'TEST.F_KLINGEL_SIGNAL' : 'TEST.F_KLINGEL_SPRACHE';
    if (!ic_klingel_an($art)) {
        return ic_pz('hinweis', $frage, 'TEST.A_KLINGEL_AUS');
    }
    $cfg = ic_config();
    $ordner = ic_klingel_ordner($art);
    $tok = isset($cfg['klingel_' . $art . '_token']) ? (string) $cfg['klingel_' . $art . '_token'] : '';
    if ($tok === '') {
        return ic_pz('unklar', $frage, 'TEST.A_KLINGEL_OHNE_TOKEN', 'TEST.R_KLINGEL');
    }
    if ($mit_netz) {
        list($c, $r, $f) = ic_nachbar_rufen($ordner, array('token' => $tok, 'selftest' => '1'), 5);
        if ($c === 200 && strpos(ltrim($r), 'SELFTEST;OK=1') === 0) {
            return ic_pz('ok', $frage, 'TEST.A_KLINGEL_ERREICHT', '', array(), array($ordner));
        }
        return ic_pz('unklar', $frage, 'TEST.A_KLINGEL_NICHT', 'TEST.R_KLINGEL', array(),
                     array($ordner, ic_klingel_grund($c, $r, $f)));
    }
    $m = ic_merker_lesen('klingel_' . $art);
    if ($m === null) {
        return ic_pz('hinweis', $frage, 'TEST.A_KLINGEL_NOCH_NIE', 'TEST.R_KLINGEL_NETZ', array(),
                     array($ordner));
    }
    $teile = explode("\t", (string) $m['text'], 2);
    if ($teile[0] === 'ok') {
        return ic_pz('ok', $frage, 'TEST.A_KLINGEL_ZULETZT', '', array(),
                     array($ordner, date('d.m.Y H:i', (int) $m['zeit'])));
    }
    return ic_pz('unklar', $frage, 'TEST.A_KLINGEL_GESCHEITERT', 'TEST.R_KLINGEL', array(),
                 array($ordner, isset($teile[1]) ? $teile[1] : '?', date('d.m.Y H:i', (int) $m['zeit'])));
}

/* ------------------------------------------------------------------
 * X-3: besteht die eigene Sicherung das eigene Zurueckspielen?
 * ------------------------------------------------------------------
 * Ueber DIESELBE Funktion wie das Zurueckspielen (ic_sicherung_lesen()).
 * Rueckgabe: die Beanstandungen (maskiert, wie beim Zurueckspielen), leer =
 * die Sicherung liesse sich zurueckspielen. Der Knopf liefert die Datei
 * trotzdem - die Oberflaeche warnt nur (vb_RAHMEN, X-3).
 */
function ic_sicherung_selbstpruefung()
{
    $js = json_encode(ic_config(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($js === false) { return array(ic_txt('UI.SICH_KEIN_JSON')); }
    $erg = ic_sicherung_lesen($js);
    return ($erg[0] === null && isset($erg[1]) && is_array($erg[1])) ? $erg[1] : array();
}

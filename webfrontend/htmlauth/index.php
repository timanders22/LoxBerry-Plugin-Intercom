<?php
/* Der Gerueststandard des Hauses. Bis 2.2.5 stand die Zeile in keiner
 * Oberflaechendatei; welche Meldungen in der Seite landen, entschied
 * allein die php.ini der Anlage. */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
/**
 * Intercom - Bedienoberflaeche
 *
 * Eine Seite mit sechs Reitern statt sechs Einzelseiten:
 * Einstellungen | MQTT | Einbindung in Loxone | Archiv | Test | Logdateien
 *
 * Alle Variablen tragen das Praefix ic_, weil LBWeb::lbheader() eigene
 * globale Variablen setzt und es sonst zu Namenskollisionen kommt.
 *
 * (c) Intercom LoxBerry Plugin Authors - MIT-Lizenz
 * Fortfuehrung von bladerb/intercom22lox, siehe NOTICE.
 */

require_once "config.php";

$L = LBSystem::readlanguage("language.ini");

/**
 * Text holen und maskieren - in EINEM Schritt.
 *
 * Bis 2.1.13 waren die Sprachwerte maschinell aus Satzfragmenten erzeugt und
 * wurden in der Seite ohne Trennung aneinandergesetzt. Am gerenderten HTML
 * gemessen ergab das zwoelf Stellen der Art "Tragen Sie im ReiterEinstellungen
 * die Adresse" - lesbar war das nicht. Jetzt steht je Satz EIN Schluessel, und
 * Auszeichnung kommt ueber Platzhalter hinein.
 *
 * Damit ist zugleich die doppelte Maskierung ausgeschlossen: die Sprachwerte
 * enthalten keinerlei Auszeichnung, laufen also alle durch ic_e(), und was
 * eingesetzt wird, ist bewusst HTML.
 */

/* ic_gateway_fassung() ist am 04.09.2026 (2.2.6) nach ic_lib.php gezogen.
 *
 * Zwei Gruende: die Selbstpruefung im Reiter Test braucht sie (die Zeile
 * "Welches Abo gehoert ins Gateway?" haengt seither an der Fassung), und sie
 * suchte hier ihren Pfad ueber getenv('LBHOMEDIR') allein, waehrend
 * ic_paths() eine Kandidatenliste fuehrt. War die Umgebungsvariable im
 * Webkontext nicht gesetzt, meldete sie "nicht feststellbar", waehrend die
 * Autostart- und die Portpruefung dieselbe general.json sehr wohl lasen -
 * zwei Wahrheiten ueber denselben Dateipfad.
 *
 * Zwei gleichnamige Funktionen landen im selben PHP-Prozess und enden mit
 * "Cannot redeclare"; deshalb steht hier keine Kopie.
 */

/**
 * Der Abo-Hinweis in der Fassung, die zum Gateway passt.
 *
 * Gelesen wird direkt aus $L, NICHT ueber ic_txt(): die Texte tragen HTML
 * (<b>, &#8209;), und ic_txt() maskiert. Wer sie da hindurchschickt, zeigt
 * dem Anwender die spitzen Klammern.
 */
function ic_abo_text()
{
    global $L;
    $hol = function ($k) use ($L) {
        return isset($L[$k]) ? $L[$k] : $k;
    };
    $f = ic_gateway_fassung();
    if ($f <= 0) {
        return $hol('UI.MQTT_ABO_UNBEKANNT');
    }
    return $hol($f >= 2 ? 'UI.MQTT_ABO_V2' : 'UI.MQTT_ABO_PFLICHT')
         . ' <span class="sm-mono">'
         . sprintf($hol('UI.MQTT_ABO_GEMESSEN'), $f) . '</span>';
}

function ic_txtf($schluessel)
{
    $args = func_get_args();
    array_shift($args);
    $f = ic_txt($schluessel);
    return $args ? vsprintf($f, $args) : $f;
}

/**
 * Der Sprachwert OHNE Maskierung.
 *
 * Nur fuer Werte, die anschliessend durch ic_fett()/ic_mono() laufen - die
 * maskieren selbst. ic_fett(ic_txt(...)) waere zweimal maskiert, und genau das
 * ist der teuerste Befund der Hausdokumentation: auf dem Bildschirm stuende
 * dann woertlich "l&auml;uft".
 */
function ic_roh($schluessel)
{
    global $L;
    return isset($L[$schluessel]) ? $L[$schluessel] : $schluessel;
}

/**
 * Der Sprachwert OHNE Maskierung, mit eingesetzten Platzhaltern.
 *
 * NEU 04.09.2026 (2.2.6). Es gab ic_txtf() (maskiert, mit Platzhaltern) und
 * ic_roh() (roh, ohne Platzhalter) - fuer einen Wert, der BEIDES braucht,
 * gab es keinen Weg. Genau zwei solche Werte gibt es: BILDSCHUTZ_TEXT und
 * ARCHIVSCHUTZ_TEXT. Beide liefen ueber ic_txtf(), und die gerenderte Seite
 * zeigte dem Anwender woertlich "&lt;b&gt;" - ausgerechnet in den zwei
 * Kaesten, die vor einem offenen Archiv warnen.
 *
 * Nur fuer die neun Schluessel, die der Kopf der Sprachdatei namentlich
 * fuehrt. Der eingesetzte Wert wird NICHT mitmaskiert - er kommt aus
 * ic_mono()/ic_fett(), die selbst maskieren.
 */
function ic_rohf($schluessel)
{
    $args = func_get_args();
    array_shift($args);
    $f = ic_roh($schluessel);
    return $args ? vsprintf($f, $args) : $f;
}

/** Ein Stueck Festbreitenschrift fuer die Platzhalter. */
function ic_mono($t) { return '<span class="sm-mono">' . ic_e($t) . '</span>'; }

/**
 * Der Satz zu einem gescheiterten Speicherort (NEU 2.2.13, C1). Rot, denn
 * "kopieren_gescheitert" heisst: das Archiv liegt unveraendert am alten Ort.
 */
function ic_speicher_meldung($m)
{
    global $ic_cfg;
    $sp = isset($ic_cfg['storage_path']) ? rtrim(trim((string) $ic_cfg['storage_path']), '/') : '';
    $ziel = $sp . '/' . ic_plugin_ordner() . '_data';
    if ($m === 'kopieren_gescheitert') { return ic_txtf('UI.M_SPEICHER_KOPIE', ic_e($ziel)); }
    if ($m === 'verweis_gescheitert') { return ic_txtf('UI.M_SPEICHER_VERWEIS', ic_e($ziel)); }
    if ($m === 'belegt') { return ic_txt('UI.M_SPEICHER_BELEGT'); }
    return ic_txtf('UI.M_SPEICHER_NICHT', ic_e($m));
}
function ic_fett($t) { return '<b>' . ic_e($t) . '</b>'; }

/**
 * X-2 (Regeln/04, seit 2.2.15): die eingetippten Werte EINES beanstandeten
 * Formulars fuer die Einmalmeldung. Nie Geheimnisse: die Kennwoerter der
 * Stationen und die Token von SignalBot und Sprachsteuerung reisen nicht mit
 * (ihre Felder bleiben leer, der Platzhalter sagt "unveraendert"). Steuer-
 * zeichen fallen weg, jeder Wert ist auf 4096 Zeichen begrenzt.
 */
function ic_eingaben_sammeln($form, array $falsch)
{
    $str = function ($v) {
        return is_string($v) ? substr(preg_replace('/[\x00-\x1F\x7F]/', '', $v), 0, 4096) : '';
    };
    $e = array('form' => $form, 'falsch' => array_values($falsch), 'werte' => array(),
               'haken' => array(), 'stationen' => array());
    if ($form === 'mqtt') {
        $e['werte']['mqtt_praefix'] = $str(isset($_POST['mqtt_praefix']) ? $_POST['mqtt_praefix'] : '');
        $e['haken']['mqtt_enable'] = isset($_POST['mqtt_enable']);
        return $e;
    }
    foreach (array('storage_path', 'timelapse_time', 'tv_ip', 'tv_port', 'ai_url', 'ai_minconf',
                   'cleanup_days', 'cleanup_count', 'cleanup_mb', 'intervall_min', 'standbild_pfad',
                   'webhook1', 'webhook2', 'webhook3', 'webhook4', 'videowebhook1', 'videowebhook2',
                   'bildweg', 'klingel_ausloeser', 'klingel_signal_ordner', 'klingel_signal_an',
                   'klingel_sprache_ordner') as $k) {
        $e['werte'][$k] = $str(isset($_POST[$k]) ? $_POST[$k] : '');
    }
    foreach (array('timestamp_image', 'timestamp_video', 'timelapse_enable', 'timelapse_video',
                   'tv_enable', 'ai_enable', 'bild_schuetzen', 'archiv_schutz',
                   'klingel_signal', 'klingel_sprache') as $k) {
        $e['haken'][$k] = isset($_POST[$k]);
    }
    $namen = (isset($_POST['st_name']) && is_array($_POST['st_name'])) ? $_POST['st_name'] : array();
    foreach (array_keys($namen) as $i) {
        if (count($e['stationen']) >= 51) { break; }
        $z = array();
        foreach (array('st_name' => 'name', 'st_ip' => 'ip', 'st_user' => 'user', 'st_ms' => 'ms',
                       'st_standbild' => 'standbild', 'st_alt' => 'alt') as $pk => $zk) {
            $z[$zk] = (isset($_POST[$pk]) && is_array($_POST[$pk]) && isset($_POST[$pk][$i]))
                    ? $str($_POST[$pk][$i]) : '';
        }
        $z['pass_weg'] = isset($_POST['st_pass_weg']) && is_array($_POST['st_pass_weg'])
                       && isset($_POST['st_pass_weg'][$i]);
        $e['stationen'][] = $z;
    }
    return $e;
}

/**
 * Ein Textfeld aus dem Formular (Nr. 19, B-Nachzug 01.10.2026): nur Leerraum am
 * Rand wird still abgeschnitten. Steuerzeichen IM Wert (etwa ein mitkopierter
 * Tabulator) und eine Liste statt eines Textes sind eine Beanstandung - null.
 * Bis 2.2.16 wurden Steuerzeichen still entfernt und eine Liste still zu ''.
 */
function ic_feld_text($v)
{
    if (!is_string($v)) { return null; }
    $t = trim($v);
    return preg_match('/[\x00-\x1F\x7F]/', $t) === 1 ? null : $t;
}

/*
 * DIE REITERLISTE STEHT GENAU EINMAL.
 *
 * Aus ihr entstehen die Leiste, die Positivliste fuer den offenen Reiter und
 * die Pruefzeile im Reiter Test, die Leiste, Bereiche und Liste
 * gegeneinander zaehlt. Wer einen Reiter ergaenzt, kann nichts mehr vergessen.
 */
$ic_reiter = array(
    'settings' => 'UI.REITER_EINSTELLUNGEN',
    'mqtt'     => 'UI.REITER_MQTT',
    'loxone'   => 'UI.REITER_LOXONE',
    'archiv'   => 'UI.REITER_ARCHIV',
    'test'     => 'UI.REITER_TEST',
    'log'      => 'UI.REITER_LOG',
);

/* Die Positivliste, ausgeschrieben.
 *
 * Warum nicht aus $ic_reiter erzeugt: eine in einer Schleife gebaute Liste
 * findet die Hauspruefung nicht - sie sucht Literale. Genau dieser Fehler
 * steht in der Hausdokumentation zweimal, und beide Male hat eine "saubere"
 * Schleife die Pruefung blind gemacht statt sie zu erfuellen.
 *
 * Die Aufloesung ist nicht "Schleife oder Hand", sondern: ausschreiben UND
 * die Uebereinstimmung im Reiter Test pruefen lassen. Das tut
 * ic_pruefe_reiter() - sie zaehlt Leiste, Bereiche und diese Liste
 * gegeneinander und wird rot, sobald eine der drei Stellen abweicht. */
$ic_tabliste = array('tab-settings', 'tab-mqtt', 'tab-loxone', 'tab-archiv',
                     'tab-test', 'tab-log');

$ic_datei   = ic_paths()['config'] . '/data.json';
$ic_host    = ic_host();
$ic_plugin  = ic_plugin_ordner();
$ic_meldungen = array();     // Beanstandungen SAMMELN, nicht ueberschreiben
$ic_fehler    = array();
$ic_eingaben  = null;        // X-2: eingetippte Werte eines beanstandeten Formulars

/* ==================================================================
 * Waehrend einer Aktualisierung: nichts erzeugen, nichts speichern
 * ==================================================================
 *
 * Zwischen den neuen Dateien und postinstall.sh ist data.json die leere
 * Vorgabe aus dem Archiv. Bis 2.2.10 erzeugte ein Aufruf dieser Seite in der
 * Zeit ein neues Token und schrieb es samt Zweitschrift - die Stationen und
 * das alte Token waren danach weg (Pruefung-Upgradeluecke-2026-09-17, Fall F).
 * Solange die Marke aus preupgrade.sh gilt, zeigt die Seite nur einen
 * Hinweis; auch ein Formular von vorher wird nicht mehr angenommen.
 *
 * Unabhaengig davon (die Marke kann fehlen oder veraltet sein): eine
 * Konfiguration ohne Token und ohne Station wird ZUERST aus der Zweitschrift
 * geheilt, bevor unten ein Token entstehen kann. Nur hier, nicht im
 * unangemeldeten Bereich. */
$ic_upgrade = ic_upgrade_laeuft();
$ic_heilung = array('ok', '');
if (!$ic_upgrade) {
    $ic_heilung = ic_config_heilen();
    if ($ic_heilung[0] === 'geheilt') {
        $ic_meldungen[] = ic_txtf('UI.M_AUS_ZWEITSCHRIFT', ic_e($ic_heilung[1]));
    } elseif ($ic_heilung[0] === 'fehler') {
        $ic_fehler[] = ic_txtf('UI.M_HEILUNG_NICHT', ic_e($ic_heilung[1]));
    }
}

$ic_cfg = ic_config();

if ($ic_upgrade) {
    require_once "menu.php";
    $navbar[1]['active'] = True;
    LBWeb::lbheader(ic_titel(), 'https://github.com/timanders22/LoxBerry-Plugin-Intercom/', 'help.html');
    require_once __DIR__ . "/ic_stil.php";
    echo '<div class="smw">' . "\n"
       . '<h1>' . ic_txt('UI.TITEL') . '</h1>' . "\n"
       . '<div class="sm-hinweis sm-warn"><b>' . ic_txt('UI.UPGRADE_LAEUFT') . '</b> '
       . ic_txt('UI.UPGRADE_LAEUFT_TEXT') . '</div>' . "\n"
       . '</div>' . "\n";
    LBWeb::lbfooter();
    exit;
}

/* ==================================================================
 * Handler - ALLES vor der ersten Ausgabe
 * ==================================================================
 *
 * Seit 2.2.13 (O1, Regeln/04) endet JEDER POST mit einer Umleitung 303 auf
 * index.php?tab=<reiter>; Meldungen und Pruefzeilen reisen als Einmalmeldung
 * (ic_einmal_schreiben()). Bis 2.2.12 antworteten alle neun Zweige mit 200 -
 * F5 auf "Bildlink erzeugen" legte jedes Mal einen weiteren Zugang ohne
 * Anmeldung an, und nach "Neues Token" stand eine Merkmal-Fehlermeldung da.
 * Ausgenommen sind nur die Downloads (Vorlage, Sicherung): sie liefern ihre
 * Datei unmittelbar. */

$ic_offen = 'settings';
if (isset($_POST['activetab']) && is_string($_POST['activetab'])
    && isset($ic_reiter[$_POST['activetab']])) {
    $ic_offen = $_POST['activetab'];
} elseif (isset($_GET['tab']) && is_string($_GET['tab']) && isset($ic_reiter[$_GET['tab']])) {
    $ic_offen = $_GET['tab'];
}

/** Jeder auslösende Aufruf verlangt das Merkmal - mit genau einer Ausnahme.
 *
 * Das Merkmal haengt am Zugriffstoken (ic_merkmal()). Ist keines
 * eingerichtet, gibt es keines - und damit keinen Weg zurueck: jedes
 * Formular wird abgewiesen, auch der Knopf "Neues Zugriffstoken". Seit
 * 2.2.11 kann dieser Zustand stehen bleiben, weil ein leeres Token nicht
 * mehr stillschweigend ersetzt wird (unten, und Regeln/05: unterschieden
 * wird per array_key_exists(), nicht per empty()).
 *
 * Genau dieser eine Knopf wird deshalb ohne Merkmal angenommen, solange
 * kein Token eingerichtet ist - und nur er. Zu schuetzen ist dann nichts:
 * ohne Token weist jeder Endpunkt jeden Aufruf ab, keine Adresse im
 * Miniserver arbeitet, und wer den Aufruf von aussen ausloest, bekommt die
 * Antwort nicht zu sehen. Gemessen im Pruefstand: TK4 (der Knopf wirkt
 * ohne Merkmal), TK5 (mit eingerichtetem Token wird derselbe Knopf ohne
 * Merkmal abgewiesen), TK2 (jedes andere Formular bleibt abgewiesen).
 *
 * Die Meldung nennt in dieser Lage die Sache und nicht das Merkmal: "Seite
 * neu laden und noch einmal absenden" waere eine Schleife ohne Ausgang. */
$ic_darf = ic_merkmal_gueltig();
$ic_wollte = ($_SERVER['REQUEST_METHOD'] === 'POST');
$ic_ohne_token = (ic_merkmal() === '');
$ic_darf_token = $ic_darf || ($ic_ohne_token && isset($_POST['token_neu']));
if ($ic_wollte && !$ic_darf && !$ic_darf_token) {
    $ic_fehler[] = $ic_ohne_token ? ic_txt('UI.M_OHNE_TOKEN') : ic_txt('UI.M_MERKMAL');
}

/* ---------------- Loxone-Vorlage herunterladen ---------------- */
// Steht ganz vorn: hier darf noch keine Zeile HTML ausgegeben sein.
if ($ic_wollte && $ic_darf && isset($_POST['vorlage'])) {
    $ic_token = isset($ic_cfg['aktionstoken']) ? (string) $ic_cfg['aktionstoken'] : '';
    if ($_POST['vorlage'] === 'ausgang') {
        ic_vorlage_ausliefern('VQ_Intercom_LoxBerry.xml',
                              ic_vorlage_ausgang($ic_host, $ic_token));
        exit;
    }
    if ($_POST['vorlage'] === 'eingang') {
        ic_vorlage_ausliefern('VI_Intercom_LoxBerry.xml', ic_vorlage_eingang($ic_host));
        exit;
    }
}

/* ==================================================================
 * DIE DOWNLOAD-HANDLER STEHEN VOR JEDER AUSGABE - DAS IST BAUVORSCHRIFT
 * ==================================================================
 *
 * Massgeblich ist die ERSTE AUSGABE, nicht der Aufruf von lbheader().
 *
 * Bis 2.2.5 stand hier "vor lbheader()", und die beiden Handler lagen
 * entsprechend kurz davor - aber ic_stil.php wurde 100 Zeilen frueher
 * eingebunden und gab beim Einbinden 7200 Byte aus. Ueber HTTP gemessen
 * am 04.09.2026: der Knopf "Einstellungen sichern" antwortete mit
 * Content-type: text/html, OHNE Content-Disposition, und der Rumpf war
 * das Stylesheet mit dem JSON am Ende - eine Seite statt einer Datei.
 * Gemessen mit output_buffering Off, 4096 und 65536, jedes Mal gleich.
 * Derselbe Fehler stand schon in 2.2.3 und 2.2.4.
 *
 * Seit 2.2.6 stehen beide Handler ganz vorn bei den uebrigen Downloads,
 * und ic_stil.php wird erst NACH lbheader() eingebunden - so, wie es die
 * eigene Kopfzeile dieser Datei ohnehin verlangt.
 *
 * Der zweite Gewinn: was hier geschieht, geschieht VOR den abgeleiteten
 * Groessen weiter unten. Nach einem Zurueckspielen stimmen Token,
 * Formularmerkmal, Stationsliste und alle angezeigten Adressen wieder -
 * bis 2.2.5 zeigte die Seite danach den Vorstand.
 * ================================================================== */
/* ---------------- Einstellungen sichern ----------------
 *
 * Ausgegeben wird die VOLLE Konfiguration - samt Aktionstoken. Ohne ihn
 * stuenden nach dem Zurueckspielen alle Felder richtig, und das Plugin
 * kaeme trotzdem nicht an die Stationen; die Datei waere wertlos. Damit
 * traegt sie ein Geheimnis, und der Hinweis am Knopf sagt das.
 *
 * Der lesbare Kopf (_plugin, _fassung, _erzeugt) sagt beim Wiederfinden,
 * woher die Datei stammt; ic_sicherung_lesen() uebergeht jeden Schluessel
 * mit fuehrendem Unterstrich. */
if ($ic_wollte && $ic_darf && isset($_POST['ic_sichern'])) {
    $ic_kopf = array(
        '_plugin'  => 'Intercom (LoxBerry)',
        '_fassung' => ic_fassung(),
        '_erzeugt' => date('Y-m-d H:i:s'),
        '_hinweis' => 'Diese Datei enthaelt das Zugriffstoken und die '
                    . 'Zugangsdaten der Tuerstationen, gegebenenfalls auch die Token '
                    . 'fuer SignalBot und Sprachsteuerung. Wie ein Passwort behandeln.',
    );
    $ic_js = json_encode($ic_kopf + ic_config(),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($ic_js !== false) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="intercom_einstellungen_'
               . date('Ymd_His') . '.json"');
        echo $ic_js;
        exit;
    }
    $ic_fehler[] = ic_txt('UI.SICH_SCHREIBFEHLER');
}

/* ---------------- Einstellungen zurueckspielen ----------------
 *
 * is_uploaded_file() ZUERST: ohne diese Pruefung liesse sich jede Datei
 * des Servers unterschieben. Dann die Groessengrenze - eine Sicherung
 * dieses Plugins ist wenige Kilobyte gross; alles darueber wird gar
 * nicht erst gelesen. */
if ($ic_wollte && $ic_darf && isset($_POST['ic_zurueck'])) {
    if (!isset($_FILES['ic_sicherung']) || !is_array($_FILES['ic_sicherung'])
        || !isset($_FILES['ic_sicherung']['tmp_name'])
        || !@is_uploaded_file($_FILES['ic_sicherung']['tmp_name'])) {
        $ic_fehler[] = ic_txt('UI.SICH_KEINE_DATEI');
    } elseif ((int) $_FILES['ic_sicherung']['size'] > 262144) {
        $ic_fehler[] = ic_txt('UI.SICH_ZU_GROSS');
    } else {
        $ic_erg = ic_sicherung_lesen(
            (string) @file_get_contents($_FILES['ic_sicherung']['tmp_name']));
        list($ic_neu_s, $ic_mangel, $ic_n) = $ic_erg;
        /* isset statt fester Stelle: eine aeltere Bibliothek neben einer
         * neueren Oberflaeche gibt drei Werte zurueck, und dann darf hier
         * keine Meldung ueber einen Wert entstehen, den es nicht gibt. */
        $ic_uebergangen = (isset($ic_erg[3]) && is_array($ic_erg[3]))
                        ? $ic_erg[3] : array();
        /* Ein LEERES Aktionstoken in der Datei heisst "kein Token
         * gesichert" (Regeln/05) - das laufende bleibt in Kraft, und der
         * Anwender erfaehrt es. Derselbe isset-Vorbehalt wie eine Zeile
         * darueber: eine aeltere Bibliothek gibt vier Werte zurueck. */
        $ic_tokleer = (isset($ic_erg[4]) && $ic_erg[4]);
        if ($ic_neu_s === null) {
            /* ALLE Beanstandungen, nicht nur die erste - und geaendert
             * wird nichts. */
            /* ic_roh, nicht ic_txt: der Wert traegt <b>, und ic_txt()
             * maskiert selbst. Ausgegeben wird die Zeile im Kasten roh. */
            $ic_fehler[] = ic_roh('UI.SICH_ABGELEHNT') . ' '
                         . implode(' ', $ic_mangel);
        } elseif (ic_config_ablegen($ic_neu_s)) {
            $ic_cfg = $ic_neu_s;
            $ic_meldungen[] = sprintf(ic_txt('UI.SICH_UEBERNOMMEN'), $ic_n);
            /* "n Werte zurueckgespielt" sieht nach Vollstaendigkeit aus.
             * Was die Datei nicht mitbrachte, wird deshalb benannt. */
            if ($ic_uebergangen) {
                $ic_meldungen[] = sprintf(ic_txt('UI.SICH_UEBERGANGEN'),
                    count($ic_uebergangen),
                    ic_e(implode(', ', $ic_uebergangen)));
            }
            if ($ic_tokleer) {
                $ic_meldungen[] = ic_txt('UI.SICH_TOKEN_LEER');
            }
            /* Den Dienst nachziehen und sagen, was mit ihm geschah. Das
             * Plugin fuehrt keinen Dauerlaeufer; nachzuziehen sind der
             * Speicherort (Symlink) und der Archivschutz - beide stehen in
             * der Sicherung. Bis 2.2.5 geschah das nur beim Speichern, und
             * der Reiter Test meldete danach A_SPEICHER_FALSCH, ohne dass
             * jemand verstand, warum. */
            list($ic_sok2, $ic_smeldung2) = ic_speicherort_anwenden();
            if (!$ic_sok2) {
                $ic_fehler[] = ic_speicher_meldung($ic_smeldung2);
            } elseif ($ic_smeldung2 === 'verschoben') {
                $ic_meldungen[] = ic_txt('UI.M_SPEICHER_UMGEZOGEN');
            }
            // O5: der Rueckgabewert wird gelesen, nicht verworfen.
            $ic_schutz_soll2 = isset($ic_neu_s['archiv_schutz'])
                && in_array((string) $ic_neu_s['archiv_schutz'], array('1', 'on', 'true'), true);
            if (!ic_archiv_schutz_anwenden() && $ic_schutz_soll2) {
                $ic_fehler[] = ic_txtf('UI.M_SCHUTZ_NICHT', ic_e(ic_archiv_schutzdatei()));
            }
        } else {
            $ic_fehler[] = ic_txt('UI.SICH_SCHREIBFEHLER');
        }
    }
}

/* ---------------- Neues Token ---------------- */
if ($ic_wollte && $ic_darf_token && isset($_POST['token_neu'])) {
    $ic_neu = $ic_cfg;
    try {
        $ic_neu['aktionstoken'] = ic_token_neu();
        list($ic_ok, $ic_was) = ic_config_speichern($ic_neu);
        if ($ic_ok) {
            $ic_cfg = $ic_neu;
            // Die Tatsache gehoert ins Protokoll, der Wert nie - ein Token im
            // Log waere ein Token auf Platte.
            ic_log('Neues Zugriffstoken erzeugt. Alle Adressen im Miniserver muessen '
                . 'jetzt nachgezogen werden.');
            $ic_meldungen[] = ic_txt('UI.M_TOKEN_NEU');
        } else {
            $ic_fehler[] = ic_txtf('UI.M_TOKEN_NICHT', ic_e($ic_was));
        }
    } catch (RuntimeException $ic_e) {
        $ic_fehler[] = ic_txt('UI.M_ZUFALL');
    }
}

/* ---------------- Einstellungen speichern ---------------- */
if ($ic_wollte && $ic_darf && isset($_POST['speichern'])) {
    $ic_neu = $ic_cfg;

    /* Stationen. BERICHTIGT im B-Nachzug (01.10.2026, Entscheidung 16 ohne
     * Ausnahme, Nr. 19): eine Zeile ohne Adresse oder mit ungueltiger Adresse
     * verhindert das Speichern - wie jede andere Beanstandung. Bis 2.2.16 wurde
     * sie uebergangen, und der Rest gespeichert: die Station war danach still
     * weg. Die eingetippten Zeilen kommen ueber X-2 zurueck, das Adressfeld ist
     * markiert. Eine Station entfernt, wer Name UND Adresse leert. */
    $ic_stationen = array();
    /* NEU 2.2.13 (O2): was nicht passt, wird ABGEWIESEN - gesammelt, und dann
     * wird nichts gespeichert. Bis 2.2.12 wurde still verbogen: st_ms "abc"
     * wurde 1, "2.7" wurde 2, cleanup_days -5 schaltete die Aufbewahrung ab,
     * und das Praefix "haus tuer" wurde "haus_tuer" (Oberflaechen-Pruefer,
     * Befund 2). Die Regeln stehen in ic_zahlregeln(), dieselben prueft das
     * Zurueckspielen. */
    $ic_abweisung = array();
    $ic_falsch = array();        // X-2: welche Felder beanstandet sind
    $ic_namen = isset($_POST['st_name']) && is_array($_POST['st_name']) ? $_POST['st_name'] : array();
    $ic_zeile = 0;
    $ic_behalten = array();   // Intercom-n50: Formularindex jeder uebernommenen Zeile
    foreach ($ic_namen as $ic_i => $ic_name) {
        $ic_zeile++;
        /* Nr. 19: Steuerzeichen oder eine Liste in einem Feld der Zeile sind eine
         * Beanstandung (ic_feld_text()), nicht mehr still entfernt. */
        $ic_hole = function ($ic_feld) use ($ic_i, $ic_zeile, &$ic_abweisung, &$ic_falsch) {
            if (!isset($_POST[$ic_feld]) || !is_array($_POST[$ic_feld]) || !isset($_POST[$ic_feld][$ic_i])) {
                return '';
            }
            $ic_w = ic_feld_text($_POST[$ic_feld][$ic_i]);
            if ($ic_w === null) {
                $ic_abweisung[] = ic_txtf('UI.M_STEUERZEICHEN', ic_e($ic_feld . '[' . $ic_zeile . ']'));
                $ic_falsch[] = $ic_feld . '.' . $ic_i;
                return '';
            }
            return $ic_w;
        };
        $ic_ip = $ic_hole('st_ip');
        $ic_name = $ic_hole('st_name');
        // Name UND Adresse leer: die Zeile ist geleert, die Station entfernt.
        if ($ic_ip === '' && $ic_name === '') { continue; }
        if ($ic_ip === '') {
            $ic_abweisung[] = ic_txtf('UI.M_STATION_OHNE_IP', ic_e($ic_name));
            $ic_falsch[] = 'st_ip.' . $ic_i;
        } elseif (!ic_adresse_gueltig($ic_ip)) {
            // Dieselbe Pruefung wie beim Zurueckspielen (ic_stationen_mangel()).
            $ic_abweisung[] = ic_txtf('UI.M_STATION_ADRESSE', ic_e($ic_ip));
            $ic_falsch[] = 'st_ip.' . $ic_i;
        }
        /* Nr. 19: ein leeres ms wird nicht mehr still zu 1 - die neue Zeile
         * bringt die 1 schon mit, ein geleertes Feld ist eine Beanstandung. */
        $ic_ms_roh = $ic_hole('st_ms');
        if (!ic_zahl_gueltig('ms', $ic_ms_roh)) {
            $ic_abweisung[] = ic_txtf('UI.M_ZAHL', ic_e('st_ms'), ic_e(ic_zahlbereich('ms')),
                                      $ic_ms_roh === '' ? ic_txt('UI.WERT_LEER') : ic_e($ic_ms_roh));
            $ic_falsch[] = 'st_ms.' . $ic_i;
        }
        $ic_pass = $ic_hole('st_pass');
        /* NEU 2.2.13 (O12): ein Haken je Station loescht das Kennwort. Bis
         * 2.2.12 liess sich ein hinterlegtes Kennwort nur loswerden, indem man
         * die ganze Zeile loeschte und neu anlegte. Ein neu eingetipptes
         * Kennwort geht vor. Der Index ist ausgeschrieben (st_pass_weg[<i>]),
         * weil ein nicht gesetzter Haken gar nicht mitgeschickt wird. */
        $ic_pass_weg = isset($_POST['st_pass_weg']) && is_array($_POST['st_pass_weg'])
                     && isset($_POST['st_pass_weg'][$ic_i]);
        if ($ic_pass === '' && !$ic_pass_weg) {
            /* Leer heisst UNVERAENDERT, nicht "geloescht". Das Feld wird nie
             * mit dem Wert gefuellt - ein Passwort gehoert nicht in den
             * ausgelieferten Quelltext.
             *
             * Zugeordnet wird ueber die URSPRUENGLICHE Adresse, die die Zeile
             * als verstecktes Feld mitfuehrt - nicht ueber die Zeilennummer
             * und nicht ueber die neue Adresse:
             *
             *   Zeilennummer: wird eine Zeile uebergangen (leere oder
             *     unbrauchbare Adresse) oder umsortiert, verschieben sich die
             *     Nummern, und eine Station bekaeme das Passwort einer anderen.
             *     Genau dann passiert das, wenn jemand eine Station LOESCHEN
             *     will oder sich bei einer Adresse vertippt.
             *   Neue Adresse: wer die Adresse einer Station aendert und das
             *     Passwortfeld leer laesst, verloere das Passwort.
             *
             * Das versteckte Feld reist mit der Zeile und trifft beides. */
            $ic_urspruenglich = $ic_hole('st_alt');
            foreach (ic_stationen() as $ic_a) {
                if ($ic_urspruenglich !== '' && $ic_a['ip'] === $ic_urspruenglich) {
                    $ic_pass = $ic_a['pass'];
                    break;
                }
            }
        }
        $ic_behalten[] = $ic_i;
        $ic_stationen[] = array(
            'name' => $ic_name !== '' ? $ic_name : $ic_ip,
            'ip'   => $ic_ip,
            'user' => $ic_hole('st_user'),
            'pass' => $ic_pass,
            'ms'   => (int) $ic_ms_roh,
            'standbild' => $ic_hole('st_standbild'),
        );
    }
    /* NEU (Intercom-n50): mehr Stationen als das Zurueckspielen annimmt sind eine
     * Beanstandung - bis 2.2.17 speicherte das Formular sie, und die eigene
     * Sicherung liess sich danach nicht zurueckspielen. Markiert werden die Namen
     * der Zeilen ueber der Grenze; gespeichert wird nichts (Nr. 16). */
    if (count($ic_stationen) > ic_stationen_hoechstens()) {
        $ic_abweisung[] = ic_txtf('UI.M_STATIONEN_ZU_VIELE', count($ic_stationen),
                                  ic_stationen_hoechstens());
        foreach (array_slice($ic_behalten, ic_stationen_hoechstens()) as $ic_bi) {
            $ic_falsch[] = 'st_name.' . $ic_bi;
        }
    }
    $ic_neu['stationen'] = $ic_stationen;
    // "intercomip" bleibt gleichlautend mit der ersten Station: ein
    // Rueckschritt auf 2.1.13 findet damit weiterhin seine Adresse.
    $ic_neu['intercomip'] = $ic_stationen ? $ic_stationen[0]['ip'] : '';

    /* Einfache Textfelder. Nur Steuerzeichen entfernen - niemals Doppelpunkt,
     * Schraegstrich oder Punkt, sonst wird aus einer eingefuegten Adresse
     * Buchstabensalat. */
    $ic_felder = array('storage_path', 'timelapse_time', 'tv_ip', 'tv_port',
                    'ai_url', 'ai_minconf', 'cleanup_days', 'cleanup_count',
                    'cleanup_mb', 'intervall_min', 'standbild_pfad',
                    'webhook1', 'webhook2', 'webhook3', 'webhook4',
                    'videowebhook1', 'videowebhook2');
    foreach ($ic_felder as $ic_k) {
        // Nr. 19: Steuerzeichen werden abgewiesen, nicht mehr still entfernt.
        $ic_w = isset($_POST[$ic_k]) ? ic_feld_text($_POST[$ic_k]) : '';
        if ($ic_w === null) {
            $ic_abweisung[] = ic_txtf('UI.M_STEUERZEICHEN', ic_e($ic_k));
            $ic_falsch[] = $ic_k;
            $ic_w = '';
        }
        $ic_neu[$ic_k] = $ic_w;
    }
    foreach (array('cleanup_days', 'cleanup_count', 'cleanup_mb', 'intervall_min',
                   'tv_port', 'ai_minconf') as $ic_k) {
        if (!ic_zahl_gueltig($ic_k, $ic_neu[$ic_k])) {
            $ic_abweisung[] = ic_txtf('UI.M_ZAHL', ic_e($ic_k), ic_e(ic_zahlbereich($ic_k)),
                                      ic_e($ic_neu[$ic_k]));
            $ic_falsch[] = $ic_k;
        }
    }
    if ($ic_neu['timelapse_time'] !== ''
        && !preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $ic_neu['timelapse_time'])) {
        $ic_abweisung[] = ic_txtf('UI.M_UHRZEIT', ic_e($ic_neu['timelapse_time']));
        $ic_falsch[] = 'timelapse_time';
    }
    if ($ic_neu['storage_path'] !== '' && !@is_dir($ic_neu['storage_path'])) {
        $ic_meldungen[] = ic_txtf('UI.M_SPEICHER_WEG', ic_e($ic_neu['storage_path']));
    }

    /* Klingel-1 (D, seit 2.2.15): Meldung ueber SignalBot und Ansage ueber die
     * Sprachsteuerung, ab Werk AUS. Ordner, Rufnummer und Token werden mit
     * DERSELBEN Pruefung abgewiesen wie beim Zurueckspielen
     * (ic_klingel_wert_gueltig()). Ein leeres Tokenfeld heisst "unveraendert"
     * - wie das Kennwort einer Station; das Token geht nie in die Seite
     * zurueck, auch nicht in eine Beanstandung. */
    foreach (array('klingel_signal', 'klingel_sprache') as $ic_k) {
        $ic_neu[$ic_k] = isset($_POST[$ic_k]) ? 'on' : '';
    }
    foreach (array('klingel_ausloeser', 'klingel_signal_ordner', 'klingel_signal_an', 'klingel_sprache_ordner',
                   'klingel_signal_token', 'klingel_sprache_token') as $ic_k) {
        $ic_w = isset($_POST[$ic_k]) ? ic_feld_text($_POST[$ic_k]) : '';
        $ic_geheim = (substr($ic_k, -6) === '_token');
        if ($ic_w === null) {
            // Nr. 19: abweisen statt still entfernen; der Wert wird nie genannt.
            $ic_abweisung[] = ic_txtf('UI.M_STEUERZEICHEN', ic_e($ic_k));
            $ic_falsch[] = $ic_k;
            continue;
        }
        if ($ic_geheim && $ic_w === '') { continue; }
        if (!ic_klingel_wert_gueltig($ic_k, $ic_w)) {
            $ic_abweisung[] = ic_txtf('UI.M_KLINGEL_WERT', ic_e($ic_k), $ic_geheim ? '***' : ic_e($ic_w));
            $ic_falsch[] = $ic_k;
            continue;
        }
        $ic_neu[$ic_k] = $ic_w;
    }
    foreach (array('signal' => 'UI.L_KLINGEL_SIGNAL', 'sprache' => 'UI.L_KLINGEL_SPRACHE') as $ic_art => $ic_lk) {
        if ($ic_neu['klingel_' . $ic_art] === 'on'
            && (!isset($ic_neu['klingel_' . $ic_art . '_token'])
                || (string) $ic_neu['klingel_' . $ic_art . '_token'] === '')
            && !in_array('klingel_' . $ic_art . '_token', $ic_falsch, true)) {
            $ic_abweisung[] = ic_txtf('UI.M_KLINGEL_TOKEN', ic_e(ic_roh($ic_lk)));
            $ic_falsch[] = 'klingel_' . $ic_art . '_token';
        }
    }

    /* Haken */
    foreach (array('timestamp_image', 'timestamp_video', 'timelapse_enable',
                   'timelapse_video', 'tv_enable', 'ai_enable') as $ic_k) {
        $ic_neu[$ic_k] = isset($_POST[$ic_k]) ? 'on' : '';
    }
    // Der Haken ist umgekehrt beschriftet ("nicht oeffentlich"), damit der
    // Vorgabewert das bisherige Verhalten bleibt: eine fehlende Angabe in
    // einer bestehenden data.json bedeutet dann "wie bisher".
    $ic_neu['bild_oeffentlich'] = isset($_POST['bild_schuetzen']) ? '0' : '1';
    /* NEU 2.2.6, ab Werk AUS: Schutz vor das Bild- und Videoarchiv.
     * Gemessen am LoxBerry-Quelltext: /legacy/ wird ohne Anmeldung und mit
     * Verzeichnisauflistung ausgeliefert. Der Haken legt neben dem Archiv
     * eine .htaccess an, wortgleich mit der des angemeldeten Bereichs.
     * Warum nicht ab Werk an: ob der Browser die Anmeldung der Plugin-Seite
     * zu den Bildern der Galerie mitnimmt, ist an einem Geraet zu messen
     * und hier nicht messbar - eine Aenderung, die eine bestehende Anlage
     * still zerbrechen koennte, wird nicht ungefragt eingeschaltet. */
    $ic_neu['archiv_schutz'] = isset($_POST['archiv_schutz']) ? '1' : '0';

    /* Bildweg */
    /* Nr. 19: ein unbekannter Bildweg wird abgewiesen, nicht still zu "strom". */
    $ic_weg = isset($_POST['bildweg']) && is_string($_POST['bildweg']) ? $_POST['bildweg'] : '';
    if (in_array($ic_weg, array('strom', 'standbild', 'auto'), true)) {
        $ic_neu['bildweg'] = $ic_weg;
    } else {
        $ic_abweisung[] = ic_txtf('UI.M_BILDWEG', ic_e($ic_weg));
        $ic_falsch[] = 'bildweg';
    }

    // mqtt_* wohnen im MQTT-Reiter mit eigenem Formular und eigenem Handler -
    // hier nicht anfassen, sonst stellte jedes Speichern die Haken auf 0.

    /* Ist noch nie eines gesetzt worden, eines erzeugen und behalten. Ein
     * vorhandenes wird hier NIEMALS ersetzt, sonst waeren nach jedem
     * Speichern alle Adressen im Miniserver ungueltig.
     *
     * Gefragt wird, ob der SCHLUESSEL da ist, nicht ob der Wert leer ist
     * (Regeln/05): ein fehlender Schluessel heisst "noch nie gesetzt" und
     * bekommt eines, ein leerer heisst "bewusst geleert" und bleibt leer.
     * Bis 2.2.11 stand hier empty(): wer das Feld leerte oder eine
     * Sicherung mit leerem Token zurueckspielte, bekam beim naechsten
     * Speichern ein neues - und jede Adresse im Miniserver war stumm
     * ungueltig. */
    if (!array_key_exists('aktionstoken', $ic_neu)) {
        try {
            $ic_neu['aktionstoken'] = ic_token_neu();
        } catch (RuntimeException $ic_e) {
            // Lieber gar kein Token als ein erratbares: die Endpunkte
            // weisen dann konsequent alles ab. Der Schluessel wird dabei
            // NICHT leer angelegt - sonst hiesse er beim naechsten Mal
            // "bewusst geleert", und es entstuende nie wieder eines.
            unset($ic_neu['aktionstoken']);
            $ic_fehler[] = ic_txt('UI.M_ZUFALL');
        }
    }

    if ($ic_abweisung) {
        // X-2: die eingetippten Werte reisen mit der Einmalmeldung (nie Geheimnisse).
        $ic_eingaben = ic_eingaben_sammeln('settings', $ic_falsch);
        $ic_fehler[] = ic_txt('UI.M_NICHTS_GESPEICHERT');
        foreach ($ic_abweisung as $ic_m) { $ic_fehler[] = $ic_m; }
        $ic_ok = null;
        $ic_was = '';
    } else {
        list($ic_ok, $ic_was) = ic_config_speichern($ic_neu);
    }
    if ($ic_ok) {
        $ic_cfg = $ic_neu;
        ic_log('Einstellungen gespeichert.');

        /* DER SCHUTZHAKEN WIRKT SOFORT, NICHT ERST BEIM NAECHSTEN KLINGELN.
         *
         * getpicture.php entfernt die offene Kopie beim naechsten Bildabruf.
         * Wer den Haken setzt, um die Haustuerkamera aus dem unangemeldeten
         * Bereich zu nehmen, haette bis dahin das zuletzt aufgenommene Bild
         * weiter offen im Netz liegen - auf einer Anlage, an der tagelang
         * niemand klingelt, tagelang. Ein Sicherheitsschalter, der erst beim
         * naechsten fremden Ausloeser greift, ist kein Schalter. */
        $ic_offene_kopie = ic_paths()['html'] . '/lastpicture.jpg';
        if ($ic_neu['bild_oeffentlich'] === '0') {
            if (@is_file($ic_offene_kopie) && @unlink($ic_offene_kopie)) {
                ic_log('Die offene Kopie des letzten Bildes wurde entfernt.');
                $ic_meldungen[] = ic_txt('UI.M_BILD_ENTFERNT');
            }
        } else {
            // Umgekehrt: wer den Haken wieder wegnimmt, soll das Bild nicht
            // erst nach dem naechsten Klingeln zurueckbekommen.
            $ic_innen = ic_paths()['datadir'] . '/lastpicture.jpg';
            if (@is_file($ic_innen) && !@is_file($ic_offene_kopie)) {
                ic_datei_ersetzen($ic_offene_kopie, (string) @file_get_contents($ic_innen));
            }
        }
        list($ic_sok, $ic_smeldung) = ic_speicherort_anwenden();
        if (!$ic_sok) {
            $ic_fehler[] = ic_speicher_meldung($ic_smeldung);
        } elseif ($ic_smeldung === 'verschoben') {
            $ic_meldungen[] = ic_txt('UI.M_SPEICHER_UMGEZOGEN');
        }
        /* BERICHTIGT 2.2.13 (O5): der Rueckgabewert wird gelesen. Bis 2.2.12
         * wurde er verworfen - liess sich die Schutzdatei nicht anlegen,
         * stand der Haken als gesetzt da, und die einzige Meldung war
         * "gespeichert", waehrend das Archiv offen blieb. */
        if (!ic_archiv_schutz_anwenden() && $ic_neu['archiv_schutz'] === '1') {
            $ic_fehler[] = ic_txtf('UI.M_SCHUTZ_NICHT', ic_e(ic_archiv_schutzdatei()));
        }
        $ic_meldungen[] = ic_txt('UI.M_GESPEICHERT');
    } elseif ($ic_ok === false) {
        ic_log('Die Einstellungen liessen sich NICHT schreiben: ' . $ic_was);
        $ic_fehler[] = ic_txtf('UI.M_NICHT_GESPEICHERT', ic_e($ic_was));
    }
}

/* ---------------- MQTT speichern ---------------- */
if ($ic_wollte && $ic_darf && isset($_POST['mqtt_speichern'])) {
    $ic_neu = $ic_cfg;
    $ic_neu['mqtt_enable'] = isset($_POST['mqtt_enable']) ? '1' : '0';
    /* Nr. 19: eine Liste statt eines Textes wird abgewiesen, nicht still zum
     * leeren Praefix (= Ordnername). */
    $ic_p = isset($_POST['mqtt_praefix'])
       ? (is_string($_POST['mqtt_praefix']) ? trim($_POST['mqtt_praefix']) : null) : '';
    /* BERICHTIGT 2.2.13 (O2): abweisen statt ersetzen. Bis 2.2.12 wurde aus
     * "haus tuer" still "haus_tuer" und aus "/x//y/" "x/y" - ein anderes Abo
     * und andere Gateway-Namen, ohne dass der Anwender es erfuhr. */
    if ($ic_p === null || !ic_praefix_gueltig($ic_p)) {
        $ic_fehler[] = ic_txtf('UI.M_PRAEFIX', ic_e((string) $ic_p));
        $ic_eingaben = ic_eingaben_sammeln('mqtt', array('mqtt_praefix'));   // X-2
    } else {
        $ic_alt_an = ic_mqtt_an();
        $ic_alt_p = ic_mqtt_praefix();
        $ic_neu['mqtt_praefix'] = $ic_p;
        list($ic_ok, $ic_was) = ic_config_speichern($ic_neu);
        if ($ic_ok) {
            $ic_cfg = $ic_neu;
            ic_log('MQTT-Einstellungen gespeichert (Praefix ' . ic_mqtt_praefix() . ').');
            $ic_meldungen[] = ic_txt('UI.M_GESPEICHERT');
            /* NEU 2.2.13 (M2, Entscheidung 3): beim Praefixwechsel und beim
             * Abschalten die behaltenen Themen unter dem ALTEN Praefix abraeumen.
             * Bis 2.2.12 standen danach intercom/bilder und haus/tuer/bilder
             * nebeneinander im Broker (mqtt-Pruefer, F9). Ehrlich gemeldet: UDP
             * sagt nur, dass das Paket hinausging. */
            if ($ic_alt_an && (!ic_mqtt_an() || ic_mqtt_praefix() !== $ic_alt_p)) {
                list($ic_rok, $ic_rges, $ic_rthemen) = ic_mqtt_behaltene_abraeumen($ic_alt_p);
                ic_log('MQTT: behaltene Themen unter ' . $ic_alt_p . ' abgeraeumt ('
                    . $ic_rok . ' von ' . $ic_rges . ' Paketen hinausgegangen).');
                if ($ic_rges > 0 && $ic_rok === $ic_rges) {
                    $ic_meldungen[] = ic_txtf('UI.M_MQTT_ABGERAEUMT', ic_e(implode(', ', $ic_rthemen)));
                } else {
                    $ic_fehler[] = ic_txtf('UI.M_MQTT_NICHT_ABGERAEUMT', $ic_rok, $ic_rges,
                                           ic_e(implode(', ', $ic_rthemen)));
                }
            }
        } else {
            $ic_fehler[] = ic_txtf('UI.M_NICHT_GESPEICHERT', ic_e($ic_was));
        }
    }
}

/* ---------------- Knoepfe im Reiter Test ---------------- */
$ic_pruefzeilen = null;
$ic_ausgabe = array();
if ($ic_wollte && $ic_darf && isset($_POST['tat'])) {
    $ic_tat = is_string($_POST['tat']) ? $_POST['tat'] : '';
    if ($ic_tat === 'pruefen') {
        // Die Netzpruefung laeuft NUR hier - nicht bei jedem Seitenaufbau.
        $ic_pruefzeilen = ic_selbsttest(true);
    } elseif ($ic_tat === 'timelapse') {
        list($ic_ok, $ic_meldung) = ic_timelapse_lauf(true);
        $ic_ausgabe[] = $ic_ok ? ic_txtf('UI.M_TL_OK', ic_e($ic_meldung))
                            : ic_txtf('UI.M_TL_NICHT', ic_e($ic_meldung));
    } elseif ($ic_tat === 'aufraeumen_probe' || $ic_tat === 'aufraeumen') {
        $ic_probe = ($ic_tat === 'aufraeumen_probe');
        /* NEU 2.2.13 (O10): der loeschende Knopf verlangt das Haekchen
         * "wirklich loeschen" - serverseitig, zusaetzlich zu confirm(). Ohne
         * JavaScript oder bei erneutem Senden gab es bis 2.2.12 keine Rueckfrage. */
        if (!$ic_probe && empty($_POST['wirklich'])) {
            $ic_fehler[] = ic_txt('UI.M_WIRKLICH_FEHLT');
        } else {
            list($ic_zahl, $ic_byte, $ic_zeilen) = ic_aufraeumen($ic_probe);
            $ic_ausgabe[] = $ic_probe ? ic_txtf('UI.M_CU_PROBE', $ic_zahl, ic_e(ic_byte($ic_byte)))
                                   : ic_txtf('UI.M_CU_OK', $ic_zahl, ic_e(ic_byte($ic_byte)));
            foreach ($ic_zeilen as $ic_z) { $ic_ausgabe[] = ic_e($ic_z); }
        }
    } elseif ($ic_tat === 'bildlink') {
        /* BERICHTIGT 2.2.13 (O7): die Meldung nennt die GEKAPPTE Stundenzahl -
         * dieselbe Rechnung wie der Link. Bis 2.2.12 meldete "0" hier 0 Stunden
         * und "10000" 10000 Stunden, die Datei sagte 1 bzw. 720. */
        $ic_stunden = ic_bildlink_stunden(isset($_POST['link_stunden']) ? $_POST['link_stunden'] : 24);
        $ic_code = ic_bildlink_erzeugen($ic_stunden, 5);
        if ($ic_code !== '') {
            ic_log('Ein befristeter Bildlink wurde erzeugt (gueltig ' . $ic_stunden . ' Stunden).');
            $ic_ausgabe[] = ic_txtf('UI.M_LINK_OK', $ic_stunden)
                . '<br><span class="sm-mono">http://' . ic_e($ic_host) . '/plugins/'
                . ic_e($ic_plugin) . '/bild.php?link=' . ic_e($ic_code) . '</span>';
        } else {
            $ic_ausgabe[] = ic_txt('UI.M_LINK_NICHT');
        }
    }
    $ic_offen = 'test';
}

/* ---------------- Archiv: loeschen ---------------- */
if ($ic_wollte && $ic_darf && isset($_POST['loeschen'])) {
    $ic_was = is_string($_POST['loeschen']) ? $_POST['loeschen'] : '';
    if (empty($_POST['wirklich'])) {
        // O10: ohne Haekchen passiert nichts (Regeln/04, loeschender Knopf).
        $ic_fehler[] = ic_txt('UI.M_WIRKLICH_FEHLT');
        $ic_was = '';
    }
    $ic_o = ic_archivordner();
    $ic_zahl = 0;
    if ($ic_was === 'bilder') {
        foreach (glob($ic_o['bild'] . '*.jpg') ?: array() as $ic_d) { if (@unlink($ic_d)) { $ic_zahl++; } }
    } elseif ($ic_was === 'videos') {
        // BEIDES loeschen, Video UND Vorschaubild. Bis 2.1.13 wurde der
        // .avi-Name zwar berechnet, aber nie benutzt: nach "Alle loeschen"
        // war die Galerie leer und die Videos lagen weiter auf der Karte.
        foreach (glob($ic_o['video'] . '*') ?: array() as $ic_d) { if (@unlink($ic_d)) { $ic_zahl++; } }
    } elseif ($ic_was === 'timelapse') {
        foreach (glob($ic_o['timelapse'] . '*') ?: array() as $ic_d) { if (@unlink($ic_d)) { $ic_zahl++; } }
    }
    if ($ic_zahl > 0) { ic_log('Archiv geleert (' . $ic_was . '): ' . $ic_zahl . ' Datei(en).'); }
    if ($ic_was !== '') { $ic_meldungen[] = ic_txtf('UI.M_GELOESCHT', $ic_zahl); }
    $ic_offen = 'archiv';
}

/* ==================================================================
 * Beim ersten Oeffnen ein Token erzeugen
 * ================================================================== */
/* Nicht, wenn die Heilung oben scheiterte: ein neues Token auf eine
 * Konfiguration, deren Zweitschrift Inhalt hat, ist genau der Verlust, den
 * sie verhindern soll.
 *
 * Auch hier der SCHLUESSEL, nicht der Wert (Regeln/05, und derselbe Grund
 * wie oben beim Speichern). Ein leeres Token bleibt stehen; der Reiter Test
 * meldet es, und der Knopf "Neues Zugriffstoken" erzeugt auf Wunsch eines -
 * melden ist richtig, stillschweigend ersetzen nicht. */
if ($ic_heilung[0] !== 'fehler' && !array_key_exists('aktionstoken', $ic_cfg)) {
    try {
        $ic_cfg['aktionstoken'] = ic_token_neu();
        list($ic_ok, $ic_was) = ic_config_speichern($ic_cfg);
        if (!$ic_ok) {
            $ic_fehler[] = ic_txtf('UI.M_TOKEN_NICHT', ic_e($ic_was));
        }
    } catch (RuntimeException $ic_e) {
        $ic_fehler[] = ic_txt('UI.M_ZUFALL');
    }
}

/* Hat ein Speichern dieses Aufrufs die Zweitschrift geschont, erfaehrt der
 * Anwender es hier und nicht erst beim naechsten Update. */
if (ic_zweitschrift_geschont()) {
    $ic_fehler[] = ic_txtf('UI.M_ZWEITSCHRIFT_GESCHONT', ic_e(ic_zweitschrift()),
                           ic_e(implode(', ', ic_zweitschrift_geschont())));
}

/* ==================================================================
 * Jeder POST endet mit einer Umleitung (PRG, seit 2.2.13, O1; Regeln/04)
 * ==================================================================
 *
 * Auch ein abgewiesener POST (Merkmal falsch). Laesst sich die Einmalmeldung
 * nicht ablegen, wird wie bis 2.2.12 unmittelbar gerendert - eine
 * verschluckte Meldung waere schlimmer als ein F5-Risiko. */
if ($ic_wollte) {
    if (ic_einmal_schreiben(array(
            'meldungen'   => $ic_meldungen,
            'fehler'      => $ic_fehler,
            'ausgabe'     => $ic_ausgabe,
            'pruefzeilen' => $ic_pruefzeilen,
            'eingaben'    => $ic_eingaben,     // X-2, nur nach einer Beanstandung
        ))) {
        header('Location: index.php?tab=' . rawurlencode($ic_offen), true, 303);
        exit;
    }
} else {
    // Die Einmalmeldung wird NUR beim GET gelesen und dabei geloescht.
    $ic_einmal = ic_einmal_lesen();
    foreach (array('meldungen' => 'ic_meldungen', 'fehler' => 'ic_fehler',
                   'ausgabe' => 'ic_ausgabe') as $ic_q => $ic_zv) {
        if (isset($ic_einmal[$ic_q]) && is_array($ic_einmal[$ic_q])) {
            foreach ($ic_einmal[$ic_q] as $ic_m) {
                if (is_string($ic_m)) { ${$ic_zv}[] = $ic_m; }
            }
        }
    }
    if (isset($ic_einmal['pruefzeilen']) && is_array($ic_einmal['pruefzeilen'])) {
        $ic_pz_ok = array();
        foreach ($ic_einmal['pruefzeilen'] as $ic_pzz) {
            if (is_array($ic_pzz) && isset($ic_pzz['lage'], $ic_pzz['frage'], $ic_pzz['antwort'],
                                           $ic_pzz['rat'], $ic_pzz['fargs'], $ic_pzz['aargs'])
                && in_array($ic_pzz['lage'], array('ok', 'fehl', 'hinweis', 'unklar'), true)
                && is_array($ic_pzz['fargs']) && is_array($ic_pzz['aargs'])) {
                $ic_pz_ok[] = $ic_pzz;
            }
        }
        if ($ic_pz_ok) { $ic_pruefzeilen = $ic_pz_ok; }
    }
    /* X-2: nach einer Beanstandung fuellt dieser GET das Formular aus den
     * eingetippten Werten. Die Einmalmeldung ist schon geloescht - der naechste
     * GET zeigt wieder die gespeicherten. */
    if (isset($ic_einmal['eingaben']) && is_array($ic_einmal['eingaben'])
        && isset($ic_einmal['eingaben']['form']) && is_string($ic_einmal['eingaben']['form'])) {
        $ic_eingaben = $ic_einmal['eingaben'];
        $ic_offen = $ic_eingaben['form'] === 'mqtt' ? 'mqtt' : 'settings';
    }
}

/* Vorgabewerte fuer noch nie gespeicherte Felder - an EINER Stelle, und
 * seit 2.2.6 steht diese Stelle in der Bibliothek, damit auch
 * ic_aufbewahrung() daraus schoepft. Bis 2.2.5 zeigte das Formular hier
 * 90 Tage, waehrend ic_aufbewahrung() mit 0 rechnete. */
$ic_cfg += ic_vorgaben();

$ic_token   = (string) $ic_cfg['aktionstoken'];
$ic_merkmal = ic_merkmal();
$ic_st      = ic_stationen();
$ic_adr     = ic_adressen($ic_host, $ic_token);
$ic_zahlen  = ic_archiv_zahlen();

/* Die stehende Selbstpruefung OHNE Netz - sie laeuft bei jedem Seitenaufbau
 * und darf deshalb nichts abrufen, was Zeit kostet. */
if ($ic_pruefzeilen === null) { $ic_pruefzeilen = ic_selbsttest(false); }
$ic_bilanz = ic_selbsttest_bilanz($ic_pruefzeilen);

/* Protokoll */
$ic_log = '';
$ic_logdatei = '';
$ic_kandidaten = array();
foreach (array($ic_plugin . '.log', 'intercom22lox.log') as $ic_dn) {
    if (defined('LBPLOGDIR')) { $ic_kandidaten[] = LBPLOGDIR . '/' . $ic_dn; }
    $ic_kandidaten[] = ic_paths()['log'] . '/' . $ic_dn;
}
foreach ($ic_kandidaten as $ic_p) {
    if (@is_file($ic_p)) { $ic_logdatei = $ic_p; break; }
}
/* Rueckwaerts mit fseek, nicht die ganze Datei in den Speicher. Die
 * Kandidatenliste nimmt auch die Altdatei intercom22lox.log auf, und die
 * kappt ic_log() nie - bis 2.2.5 wurde sie nach einem Neustart
 * vollstaendig gelesen. */
if ($ic_logdatei !== '') {
    $ic_log = ic_log_ende($ic_logdatei, 200);
}

/* ==================================================================
 * Ausgabe
 * ================================================================== */

/* menu.php setzt nur $navbar und gibt nichts aus - lbheader() braucht es.
 * ic_stil.php dagegen GIBT AUS (gemessen: 7200 Byte) und wird deshalb seit
 * 2.2.6 erst nach lbheader() eingebunden, unten. */
require_once "menu.php";
$navbar[1]['active'] = True;

/* X-2: Werte eines Formulars - nach einer Beanstandung die eingetippten,
 * sonst die gespeicherten. ic_fk() markiert ein beanstandetes Feld. */
function ic_eingabe($form)
{
    global $ic_eingaben;
    return (is_array($ic_eingaben) && isset($ic_eingaben['form']) && $ic_eingaben['form'] === $form)
         ? $ic_eingaben : null;
}
function ic_fw($form, $k, $gespeichert)
{
    $e = ic_eingabe($form);
    if ($e !== null && isset($e['werte']) && is_array($e['werte'])
        && isset($e['werte'][$k]) && is_string($e['werte'][$k])) {
        return $e['werte'][$k];
    }
    return (string) $gespeichert;
}
function ic_fh($form, $k, $gespeichert)
{
    $e = ic_eingabe($form);
    if ($e !== null && isset($e['haken']) && is_array($e['haken'])) {
        return !empty($e['haken'][$k]);
    }
    return (bool) $gespeichert;
}
function ic_fk($form, $k)
{
    $e = ic_eingabe($form);
    return ($e !== null && isset($e['falsch']) && is_array($e['falsch'])
            && in_array($k, $e['falsch'], true)) ? ' class="sm-beanstandet" aria-invalid="true"' : '';
}

/** Ein verstecktes Feldpaar, das JEDES Formular mitfuehrt. */
function ic_formularfelder($tab)
{
    global $ic_merkmal;
    return '<input type="hidden" name="merkmal" value="' . ic_e($ic_merkmal) . '">'
         . '<input type="hidden" name="activetab" value="' . ic_e($tab) . '">';
}

/** Eine Zeile der Selbstpruefung ausgeben. */
function ic_pz_zeile(array $z)
{
    global $L;
    $marken = array('ok' => array('&#10003;', 'sm-ok'), 'fehl' => array('&#10007;', 'sm-fehl'),
                    'unklar' => array('?', 'sm-unklar'), 'hinweis' => array('&bull;', 'sm-hinw'));
    $m = $marken[$z['lage']];
    $aufl = function ($t, $args) use ($L) {
        $s = (strpos($t, 'TEST.') === 0 || strpos($t, 'UI.') === 0)
           ? (isset($L[$t]) ? $L[$t] : $t) : $t;
        $s = ic_e($s);
        return $args ? vsprintf($s, array_map('ic_e', $args)) : $s;
    };
    $o = '<tr><td class="sm-marke ' . $m[1] . '">' . $m[0] . '</td>';
    $o .= '<td>' . $aufl($z['frage'], $z['fargs']) . '</td>';
    $o .= '<td>' . $aufl($z['antwort'], $z['aargs']);
    if ($z['rat'] !== '') {
        $o .= '<br><span class="sm-rat">' . $aufl($z['rat'], array()) . '</span>';
    }
    $o .= '</td></tr>';
    return $o;
}


LBWeb::lbheader(ic_titel(), 'https://github.com/timanders22/LoxBerry-Plugin-Intercom/', 'help.html');

/* Erst hier - die Datei gibt aus. Ihre eigene Kopfzeile sagt es seit jeher,
 * live.php, archive.php und videoarchive.php halten sich daran. */
require_once __DIR__ . "/ic_stil.php";

?>

<div class="smw">
<h1><?= ic_txt('UI.TITEL') ?></h1>
<p><?= ic_txt('UI.UNTERTITEL') ?></p>

<?php foreach ($ic_fehler as $m) { ?>
<div class="sm-hinweis sm-warn"><b><?= ic_txt('UI.FEHLER') ?></b> <?= $m ?></div>
<?php } ?>
<?php foreach ($ic_meldungen as $m) { ?>
<div class="sm-hinweis"><?= $m ?></div>
<?php } ?>

<?php if (!$ic_st) { ?>
<div class="sm-hinweis sm-warn"><b><?= ic_txt('UI.NICHT_EINGERICHTET') ?></b>
<?= ic_txtf('UI.NICHT_EINGERICHTET_TEXT', ic_fett(ic_roh('UI.REITER_EINSTELLUNGEN'))) ?></div>
<?php } ?>

<div class="sm-reiter">
<a class="<?= $ic_offen === 'settings' ? 'sm-active' : '' ?>" data-ziel="tab-settings" href="index.php?tab=settings"><?= ic_txt('UI.REITER_EINSTELLUNGEN') ?></a>
<a class="<?= $ic_offen === 'mqtt' ? 'sm-active' : '' ?>" data-ziel="tab-mqtt" href="index.php?tab=mqtt"><?= ic_txt('UI.REITER_MQTT') ?></a>
<a class="<?= $ic_offen === 'loxone' ? 'sm-active' : '' ?>" data-ziel="tab-loxone" href="index.php?tab=loxone"><?= ic_txt('UI.REITER_LOXONE') ?></a>
<a class="<?= $ic_offen === 'archiv' ? 'sm-active' : '' ?>" data-ziel="tab-archiv" href="index.php?tab=archiv"><?= ic_txt('UI.REITER_ARCHIV') ?></a>
<a class="<?= $ic_offen === 'test' ? 'sm-active' : '' ?>" data-ziel="tab-test" href="index.php?tab=test"><?= ic_txt('UI.REITER_TEST') ?></a>
<a class="<?= $ic_offen === 'log' ? 'sm-active' : '' ?>" data-ziel="tab-log" href="index.php?tab=log"><?= ic_txt('UI.REITER_LOG') ?></a>
</div>

<!-- ===================== Einstellungen ===================== -->
<div class="sm-seite<?= $ic_offen === 'settings' ? ' sm-active' : '' ?>" id="tab-settings">
<!-- EINE gesammelte Legende oben im Reiter, nicht je Knopfreihe - und sie
     nennt genau die beiden Farben, die hier vorkommen. Bis 2.2.5 stand sie
     113 Zeilen weiter unten, und der gruene Punkt trug den Text des
     orangen (zweimal UI.LEG_AKTION). -->
<div class="sm-legende">
<span><i class="sm-punkt sm-b-aktion"></i> <?= ic_txt('UI.LEG_AKTION') ?></span>
<span><i class="sm-punkt sm-b-lesen"></i> <?= ic_txt('UI.LEG_LESEN') ?></span>
</div>
<form method="post" action="index.php">
<?= ic_formularfelder('settings') ?>

<h2><?= ic_txt('UI.H_STATIONEN') ?></h2>
<p class="sm-klein"><?= ic_txt('UI.STATIONEN_TEXT') ?></p>
<div class="sm-tabrahmen">
<table>
<tr><th><?= ic_txt('UI.SP_NAME') ?></th><th><?= ic_txt('UI.SP_ADRESSE') ?></th>
    <th><?= ic_txt('UI.SP_BENUTZER') ?></th><th><?= ic_txt('UI.SP_PASSWORT') ?></th>
    <th><?= ic_txt('UI.SP_MS') ?></th><th><?= ic_txt('UI.SP_STANDBILD') ?></th></tr>
<?php
$ic_reihen = $ic_st;
$ic_reihen[] = array('name' => '', 'ip' => '', 'user' => '', 'pass' => '', 'ms' => 1,
                     'standbild' => '');
/* X-2: nach einer Beanstandung die eingetippten Zeilen - das Kennwort nie.
 * Der Platzhalter "unveraendert" kommt von der Station, zu der die Zeile
 * urspruenglich gehoerte (st_alt), wie beim Speichern. */
$ic_e_st = ic_eingabe('settings');
if ($ic_e_st !== null && isset($ic_e_st['stationen']) && is_array($ic_e_st['stationen'])
    && $ic_e_st['stationen']) {
    $ic_reihen = array();
    foreach ($ic_e_st['stationen'] as $ic_z) {
        if (!is_array($ic_z)) { continue; }
        $ic_r = array('pass' => '', 'pass_weg' => !empty($ic_z['pass_weg']));
        foreach (array('name', 'ip', 'user', 'ms', 'standbild', 'alt') as $ic_zk) {
            $ic_r[$ic_zk] = (isset($ic_z[$ic_zk]) && is_string($ic_z[$ic_zk])) ? $ic_z[$ic_zk] : '';
        }
        foreach ($ic_st as $ic_a) {
            if ($ic_r['alt'] !== '' && $ic_a['ip'] === $ic_r['alt']) { $ic_r['pass'] = $ic_a['pass']; break; }
        }
        $ic_reihen[] = $ic_r;
    }
}
foreach ($ic_reihen as $ic_i => $ic_s) { ?>
<tr>
<td><input type="text" data-role="none" name="st_name[]" value="<?= ic_e(isset($ic_s['alt']) || $ic_s['name'] !== $ic_s['ip'] ? $ic_s['name'] : '') ?>"<?= ic_fk('settings', 'st_name.' . $ic_i) ?> placeholder="<?= ic_txt('UI.PH_NAME') ?>"></td>
<td><input type="text" data-role="none" name="st_ip[]" value="<?= ic_e($ic_s['ip']) ?>"<?= ic_fk('settings', 'st_ip.' . $ic_i) ?> placeholder="192.168.1.50"><input type="hidden" name="st_alt[]" value="<?= ic_e(isset($ic_s['alt']) ? $ic_s['alt'] : $ic_s['ip']) ?>"></td>
<td><input type="text" data-role="none" name="st_user[]" value="<?= ic_e($ic_s['user']) ?>"<?= ic_fk('settings', 'st_user.' . $ic_i) ?> placeholder="<?= ic_txt('UI.PH_MINISERVER') ?>"></td>
<td><input type="password" data-role="none" name="st_pass[]" value=""<?= ic_fk('settings', 'st_pass.' . $ic_i) ?> placeholder="<?= $ic_s['pass'] !== '' ? ic_txt('UI.PH_UNVERAENDERT') : '' ?>"><?php if ($ic_s['pass'] !== '') { ?><br><label class="sm-klein"><input type="checkbox" data-role="none" name="st_pass_weg[<?= (int) $ic_i ?>]" value="1"<?= !empty($ic_s['pass_weg']) ? ' checked' : '' ?>> <?= ic_txt('UI.L_PASS_WEG') ?></label><?php } ?></td>
<td><input type="number" data-role="none" name="st_ms[]" min="1" max="10" value="<?= ic_e((string) $ic_s['ms']) ?>"<?= ic_fk('settings', 'st_ms.' . $ic_i) ?>></td>
<td><input type="text" data-role="none" name="st_standbild[]" value="<?= ic_e($ic_s['standbild']) ?>"<?= ic_fk('settings', 'st_standbild.' . $ic_i) ?> placeholder="/jpg/image.jpg"></td>
</tr>
<?php } ?>
</table>
</div>
<p class="sm-klein"><?= ic_txt('UI.STATIONEN_HINWEIS') ?></p>

<h2><?= ic_txt('UI.H_BILDWEG') ?></h2>
<label for="bildweg"><?= ic_txt('UI.L_BILDWEG') ?></label>
<select id="bildweg" name="bildweg" class="sm-auswahl<?= ic_fk('settings', 'bildweg') !== '' ? ' sm-beanstandet' : '' ?>"<?= ic_fk('settings', 'bildweg') !== '' ? ' aria-invalid="true"' : '' ?> data-role="none">
<?php foreach (array('strom' => 'UI.WEG_STROM', 'standbild' => 'UI.WEG_STANDBILD',
                     'auto' => 'UI.WEG_AUTO') as $ic_w => $ic_s) { ?>
<option value="<?= $ic_w ?>"<?= ic_fw('settings', 'bildweg', $ic_cfg['bildweg']) === $ic_w ? ' selected' : '' ?>><?= ic_txt($ic_s) ?></option>
<?php } ?>
</select>
<p class="sm-auswahlhinweis"><?= ic_txt('UI.AUSWAHL_HINWEIS') ?></p>
<label><?= ic_txt('UI.L_STANDBILDPFAD') ?></label>
<input type="text" data-role="none" name="standbild_pfad" value="<?= ic_e(ic_fw('settings', 'standbild_pfad', $ic_cfg['standbild_pfad'])) ?>"<?= ic_fk('settings', 'standbild_pfad') ?> placeholder="/jpg/image.jpg">
<p class="sm-klein"><?= ic_txtf('UI.BILDWEG_TEXT', ic_mono('/mjpg/video.mjpg'), ic_mono('/jpg/image.jpg')) ?></p>
<p class="sm-klein"><?= ic_txtf('UI.BILDWEG_MESSEN', ic_fett(ic_roh('UI.REITER_TEST'))) ?></p>

<h2><?= ic_txt('UI.H_BILDSCHUTZ') ?></h2>
<label><input type="checkbox" data-role="none" name="bild_schuetzen"<?= ic_fh('settings', 'bild_schuetzen', $ic_cfg['bild_oeffentlich'] === '0') ? ' checked' : '' ?>> <?= ic_txt('UI.L_BILDSCHUTZ') ?></label>
<p class="sm-klein"><?= ic_rohf('UI.BILDSCHUTZ_TEXT', ic_mono('lastpicture.jpg'), ic_mono('bild.php?token=...')) ?></p>

<h2><?= ic_txt('UI.H_ARCHIVSCHUTZ') ?></h2>
<?php
/* O5 (seit 2.2.13): der Haken steht nur, wenn der Schutz WIRKT. Ist er
 * eingeschaltet und fehlt die Schutzdatei, sagt es ein roter Kasten, und der
 * Reiter Test zeigt ein Kreuz. */
$ic_schutz_soll = in_array((string) $ic_cfg['archiv_schutz'], array('1', 'on', 'true'), true);
$ic_schutz_ist = ic_archiv_geschuetzt();
?>
<label><input type="checkbox" data-role="none" name="archiv_schutz"<?= ic_fh('settings', 'archiv_schutz', $ic_schutz_soll && $ic_schutz_ist) ? ' checked' : '' ?>> <?= ic_txt('UI.L_ARCHIVSCHUTZ') ?></label>
<?php if ($ic_schutz_soll && !$ic_schutz_ist) { ?>
<div class="sm-hinweis sm-warn"><?= ic_txtf('UI.SCHUTZ_FEHLT', ic_e(ic_archiv_schutzdatei())) ?></div>
<?php } ?>
<div class="sm-hinweis sm-warn"><?= ic_rohf('UI.ARCHIVSCHUTZ_TEXT', ic_mono('/legacy/' . $ic_plugin . '_data/')) ?></div>
<p class="sm-klein"><?= ic_txt('UI.ARCHIVSCHUTZ_PROBE') ?></p>

<h2><?= ic_txt('UI.H_SPEICHERORT') ?></h2>
<label><?= ic_txt('UI.L_SPEICHERPFAD') ?></label>
<input type="text" data-role="none" name="storage_path" value="<?= ic_e(ic_fw('settings', 'storage_path', $ic_cfg['storage_path'])) ?>"<?= ic_fk('settings', 'storage_path') ?> placeholder="/media/usbstick">
<p class="sm-klein"><?= ic_txt('UI.SPEICHERORT_TEXT') ?></p>

<h2><?= ic_txt('UI.H_AUFBEWAHRUNG') ?></h2>
<div class="sm-zeile">
<div><label><?= ic_txt('UI.L_TAGE') ?></label>
<input type="number" data-role="none" name="cleanup_days" min="0" max="3650" value="<?= ic_e(ic_fw('settings', 'cleanup_days', $ic_cfg['cleanup_days'])) ?>"<?= ic_fk('settings', 'cleanup_days') ?>></div>
<div><label><?= ic_txt('UI.L_ANZAHL') ?></label>
<input type="number" data-role="none" name="cleanup_count" min="0" value="<?= ic_e(ic_fw('settings', 'cleanup_count', $ic_cfg['cleanup_count'])) ?>"<?= ic_fk('settings', 'cleanup_count') ?>></div>
<div><label><?= ic_txt('UI.L_MB') ?></label>
<input type="number" data-role="none" name="cleanup_mb" min="0" value="<?= ic_e(ic_fw('settings', 'cleanup_mb', $ic_cfg['cleanup_mb'])) ?>"<?= ic_fk('settings', 'cleanup_mb') ?>></div>
</div>
<p class="sm-klein"><?= ic_txt('UI.AUFBEWAHRUNG_TEXT') ?></p>

<h2><?= ic_txt('UI.H_ZEITSTEMPEL') ?></h2>
<label><input type="checkbox" data-role="none" name="timestamp_image"<?= ic_fh('settings', 'timestamp_image', $ic_cfg['timestamp_image'] === 'on') ? ' checked' : '' ?>> <?= ic_txt('UI.L_STEMPEL_BILD') ?></label>
<label><input type="checkbox" data-role="none" name="timestamp_video"<?= ic_fh('settings', 'timestamp_video', $ic_cfg['timestamp_video'] === 'on') ? ' checked' : '' ?>> <?= ic_txt('UI.L_STEMPEL_VIDEO') ?></label>
<p class="sm-klein"><?= ic_txtf('UI.STEMPEL_TEXT', ic_mono('php-gd')) ?></p>

<h2><?= ic_txt('UI.H_ZEITRAFFER') ?></h2>
<label><input type="checkbox" data-role="none" name="timelapse_enable"<?= ic_fh('settings', 'timelapse_enable', $ic_cfg['timelapse_enable'] === 'on') ? ' checked' : '' ?>> <?= ic_txt('UI.L_ZEITRAFFER') ?></label>
<label><?= ic_txt('UI.L_UHRZEIT') ?></label>
<input type="text" data-role="none" name="timelapse_time" value="<?= ic_e(ic_fw('settings', 'timelapse_time', $ic_cfg['timelapse_time'])) ?>"<?= ic_fk('settings', 'timelapse_time') ?> placeholder="12:00">
<label><input type="checkbox" data-role="none" name="timelapse_video"<?= ic_fh('settings', 'timelapse_video', $ic_cfg['timelapse_video'] === 'on') ? ' checked' : '' ?>> <?= ic_txt('UI.L_ZEITRAFFER_VIDEO') ?></label>
<p class="sm-klein"><?= ic_txtf('UI.ZEITRAFFER_TEXT', ic_mono('ffmpeg')) ?></p>

<h2><?= ic_txt('UI.H_INTERVALL') ?></h2>
<label><?= ic_txt('UI.L_INTERVALL') ?></label>
<input type="number" data-role="none" name="intervall_min" min="0" max="1440" value="<?= ic_e(ic_fw('settings', 'intervall_min', $ic_cfg['intervall_min'])) ?>"<?= ic_fk('settings', 'intervall_min') ?>>
<p class="sm-klein"><?= ic_txt('UI.INTERVALL_TEXT') ?></p>

<h2><?= ic_txt('UI.H_TV') ?></h2>
<label><input type="checkbox" data-role="none" name="tv_enable"<?= ic_fh('settings', 'tv_enable', $ic_cfg['tv_enable'] === 'on') ? ' checked' : '' ?>> <?= ic_txt('UI.L_TV') ?></label>
<div class="sm-zeile">
<div><label><?= ic_txt('UI.L_TV_ADRESSE') ?></label>
<input type="text" data-role="none" name="tv_ip" value="<?= ic_e(ic_fw('settings', 'tv_ip', $ic_cfg['tv_ip'])) ?>"<?= ic_fk('settings', 'tv_ip') ?>></div>
<div><label><?= ic_txt('UI.L_PORT') ?></label>
<input type="text" data-role="none" name="tv_port" value="<?= ic_e(ic_fw('settings', 'tv_port', $ic_cfg['tv_port'])) ?>"<?= ic_fk('settings', 'tv_port') ?>></div>
</div>
<p class="sm-klein"><?= ic_txt('UI.TV_TEXT') ?></p>

<h2><?= ic_txt('UI.H_KI') ?></h2>
<label><input type="checkbox" data-role="none" name="ai_enable"<?= ic_fh('settings', 'ai_enable', $ic_cfg['ai_enable'] === 'on') ? ' checked' : '' ?>> <?= ic_txt('UI.L_KI') ?></label>
<label><?= ic_txt('UI.L_KI_ADRESSE') ?></label>
<input type="text" data-role="none" name="ai_url" value="<?= ic_e(ic_fw('settings', 'ai_url', $ic_cfg['ai_url'])) ?>"<?= ic_fk('settings', 'ai_url') ?> placeholder="http://192.168.1.60:32168/v1/vision/detection">
<label><?= ic_txt('UI.L_KI_SICHERHEIT') ?></label>
<input type="number" data-role="none" name="ai_minconf" min="0" max="100" value="<?= ic_e(ic_fw('settings', 'ai_minconf', $ic_cfg['ai_minconf'])) ?>"<?= ic_fk('settings', 'ai_minconf') ?>>
<p class="sm-klein"><?= ic_txt('UI.KI_TEXT') ?></p>

<h2><?= ic_txt('UI.H_WEBHOOKS') ?></h2>
<p class="sm-klein"><?= ic_txt('UI.WEBHOOK_TEXT') ?></p>
<label><?= ic_txt('UI.L_WH1') ?></label>
<input type="text" data-role="none" name="webhook1" value="<?= ic_e(ic_fw('settings', 'webhook1', $ic_cfg['webhook1'])) ?>"<?= ic_fk('settings', 'webhook1') ?>>
<label><?= ic_txtf('UI.L_WH2', ic_mono('<imgurl>')) ?></label>
<input type="text" data-role="none" name="webhook2" value="<?= ic_e(ic_fw('settings', 'webhook2', $ic_cfg['webhook2'])) ?>"<?= ic_fk('settings', 'webhook2') ?>>
<label><?= ic_txt('UI.L_WH3') ?></label>
<input type="text" data-role="none" name="webhook3" value="<?= ic_e(ic_fw('settings', 'webhook3', $ic_cfg['webhook3'])) ?>"<?= ic_fk('settings', 'webhook3') ?>>
<label><?= ic_txtf('UI.L_WH4', ic_mono('<imgurl>')) ?></label>
<input type="text" data-role="none" name="webhook4" value="<?= ic_e(ic_fw('settings', 'webhook4', $ic_cfg['webhook4'])) ?>"<?= ic_fk('settings', 'webhook4') ?>>
<label><?= ic_txt('UI.L_VWH1') ?></label>
<input type="text" data-role="none" name="videowebhook1" value="<?= ic_e(ic_fw('settings', 'videowebhook1', $ic_cfg['videowebhook1'])) ?>"<?= ic_fk('settings', 'videowebhook1') ?>>
<label><?= ic_txtf('UI.L_VWH2', ic_mono('<fileurl>')) ?></label>
<input type="text" data-role="none" name="videowebhook2" value="<?= ic_e(ic_fw('settings', 'videowebhook2', $ic_cfg['videowebhook2'])) ?>"<?= ic_fk('settings', 'videowebhook2') ?>>

<h2><?= ic_txt('UI.H_KLINGEL') ?></h2>
<p class="sm-klein"><?= ic_txt('UI.KLINGEL_TEXT') ?></p>
<label><input type="checkbox" data-role="none" name="klingel_signal"<?= ic_fh('settings', 'klingel_signal', $ic_cfg['klingel_signal'] === 'on') ? ' checked' : '' ?>> <?= ic_txt('UI.L_KLINGEL_SIGNAL') ?></label>
<div class="sm-zeile">
<div><label><?= ic_txt('UI.L_KLINGEL_ORDNER') ?></label>
<input type="text" data-role="none" name="klingel_signal_ordner" value="<?= ic_e(ic_fw('settings', 'klingel_signal_ordner', $ic_cfg['klingel_signal_ordner'])) ?>"<?= ic_fk('settings', 'klingel_signal_ordner') ?> placeholder="signalbot"></div>
<div><label><?= ic_txt('UI.L_KLINGEL_TOKEN') ?></label>
<input type="password" data-role="none" name="klingel_signal_token" value="" autocomplete="off"<?= ic_fk('settings', 'klingel_signal_token') ?> placeholder="<?= (string) $ic_cfg['klingel_signal_token'] !== '' ? ic_txt('UI.PH_UNVERAENDERT') : '' ?>"></div>
<div><label><?= ic_txt('UI.L_KLINGEL_AN') ?></label>
<input type="text" data-role="none" name="klingel_signal_an" value="<?= ic_e(ic_fw('settings', 'klingel_signal_an', $ic_cfg['klingel_signal_an'])) ?>"<?= ic_fk('settings', 'klingel_signal_an') ?> placeholder="+49..."></div>
</div>
<p class="sm-klein"><?= ic_txtf('UI.KLINGEL_SIGNAL_TEXT', ic_mono('/plugins/<ordner>/index.php?aktion=senden')) ?></p>
<label><input type="checkbox" data-role="none" name="klingel_sprache"<?= ic_fh('settings', 'klingel_sprache', $ic_cfg['klingel_sprache'] === 'on') ? ' checked' : '' ?>> <?= ic_txt('UI.L_KLINGEL_SPRACHE') ?></label>
<div class="sm-zeile">
<div><label><?= ic_txt('UI.L_KLINGEL_ORDNER') ?></label>
<input type="text" data-role="none" name="klingel_sprache_ordner" value="<?= ic_e(ic_fw('settings', 'klingel_sprache_ordner', $ic_cfg['klingel_sprache_ordner'])) ?>"<?= ic_fk('settings', 'klingel_sprache_ordner') ?> placeholder="sprachsteuerung"></div>
<div><label><?= ic_txt('UI.L_KLINGEL_TOKEN') ?></label>
<input type="password" data-role="none" name="klingel_sprache_token" value="" autocomplete="off"<?= ic_fk('settings', 'klingel_sprache_token') ?> placeholder="<?= (string) $ic_cfg['klingel_sprache_token'] !== '' ? ic_txt('UI.PH_UNVERAENDERT') : '' ?>"></div>
</div>
<p class="sm-klein"><?= ic_txtf('UI.KLINGEL_SPRACHE_TEXT', ic_mono('/plugins/<ordner>/index.php?aktion=sprechen')) ?></p>
<label><?= ic_txt('UI.L_KLINGEL_AUSLOESER') ?></label>
<input type="text" data-role="none" name="klingel_ausloeser" value="<?= ic_e(ic_fw('settings', 'klingel_ausloeser', $ic_cfg['klingel_ausloeser'])) ?>"<?= ic_fk('settings', 'klingel_ausloeser') ?> placeholder="klingel">
<p class="sm-klein"><?= ic_txtf('UI.KLINGEL_AUSLOESER_TEXT', ic_mono('&trigger=klingel'), ic_fett(ic_roh('UI.REITER_LOXONE'))) ?></p>
<p class="sm-klein"><?= ic_txtf('UI.KLINGEL_TEST', ic_fett(ic_roh('UI.REITER_TEST'))) ?></p>

<div class="sm-knopfreihe">
<button data-role="none" class="sm-btn sm-b-aktion" type="submit" name="speichern" value="1"><?= ic_txt('UI.SPEICHERN') ?></button>
</div>
</form>

<h2><?= ic_txt('UI.H_SICHERUNG') ?></h2>
<!-- ic_roh, nicht ic_txt: beide Werte tragen <b>, und ic_txt() maskiert
     selbst (ic_lib.php: return ic_e($L[...])). Auf dem Bildschirm standen
     deshalb die maskierten spitzen Klammern der Fettschrift im Klartext.
     (Das Beispiel steht hier bewusst NICHT woertlich: ein Sucher nach
     genau diesem Muster wuerde sonst diesen Kommentar melden.)
     ic_abo_text() greift aus demselben Grund unmittelbar auf $L zu. -->
<div class="sm-hinweis"><?= ic_roh('UI.SICH_ERKLAERUNG') ?></div>
<!-- sm-hinweis sm-warn, nicht sm-warnung: die Klasse .sm-warnung ist in
     ic_stil.php nirgends definiert. Bis 2.2.5 stand ausgerechnet der Satz
     "Die Datei enthaelt Ihre Zugangsdaten" deshalb als nackter Fliesstext
     ohne Warnrahmen da. -->
<div class="sm-hinweis sm-warn"><?= ic_roh('UI.SICH_WARNUNG') ?></div>
<?php
/* X-3 (seit 2.2.15): besteht die eigene Sicherung das eigene Zurueckspielen?
 * Dieselbe Pruefung wie beim Zurueckspielen (ic_sicherung_selbstpruefung()).
 * Gelb, und der Knopf liefert die Datei trotzdem. Die Beanstandungen kommen
 * maskiert aus ic_sicherung_lesen(). */
$ic_sich_mangel = ic_sicherung_selbstpruefung();
if ($ic_sich_mangel) { ?>
<div class="sm-hinweis sm-gelb"><b><?= ic_txt('UI.SICH_SELBST_WARN') ?></b> <?= implode(' ', $ic_sich_mangel) ?></div>
<?php } ?>
<div class="sm-knopfreihe">
  <!-- ZWEI GETRENNTE Formulare. Das Sichern schickt einen Download und ruft
       exit auf; das Zurueckspielen braucht enctype="multipart/form-data".
       Wer beides in ein Formular legt, bekommt entweder keinen Upload oder
       einen Download, der das Speichern verschluckt.

       ic_formularfelder() liefert Merkmal und Reiter - dieselbe Stelle, aus
       der sich auch die uebrigen Formulare bedienen. Ohne das Merkmal weist
       ic_merkmal_gueltig() den POST ab. -->
  <form method="post" action="index.php">
    <?= ic_formularfelder('settings') ?>
    <button data-role="none" class="sm-btn sm-b-lesen" type="submit" name="ic_sichern" value="1"><?= ic_txt('UI.K_SICHERN') ?></button>
  </form>
  <form method="post" action="index.php" enctype="multipart/form-data">
    <?= ic_formularfelder('settings') ?>
    <input data-role="none" type="file" name="ic_sicherung" accept=".json">
    <!-- Eigener Schluessel und eine Rueckfrage. Bis 2.2.5 trug dieser Knopf
         UI.K_ZURUECK - "Zurueck zur Uebersicht" -, denselben Text, den die
         beiden Galerien fuer ihren Navigationsverweis benutzen. Ein oranger
         Knopf neben einem Dateifeld, der wie eine Rueckkehr zur Startseite
         beschriftet ist und die gesamte Konfiguration samt Token
         ueberschreibt. -->
    <button data-role="none" class="sm-btn sm-b-aktion" type="submit" name="ic_zurueck" value="1"
        onclick="return confirm(this.getAttribute('data-frage'));"
        data-frage="<?= ic_txt('UI.SICH_FRAGE') ?>"><?= ic_txt('UI.K_ZURUECKSPIELEN') ?></button>
  </form>
</div>
</div>

<!-- ===================== MQTT ===================== -->
<div class="sm-seite<?= $ic_offen === 'mqtt' ? ' sm-active' : '' ?>" id="tab-mqtt">
<form method="post" action="index.php">
<?= ic_formularfelder('mqtt') ?>
<input type="hidden" name="mqtt_speichern" value="1">

<h2><?= ic_txt('UI.H_MQTT') ?></h2>
<label><input type="checkbox" data-role="none" name="mqtt_enable"<?= ic_fh('mqtt', 'mqtt_enable', ic_mqtt_an()) ? ' checked' : '' ?>> <?= ic_txt('UI.L_MQTT') ?></label>
<p class="sm-klein"><?= ic_txt('UI.MQTT_GATEWAY_TEXT') ?></p>

<label><?= ic_txt('UI.L_MQTT_PRAEFIX') ?></label>
<input type="text" data-role="none" name="mqtt_praefix" value="<?= ic_e(ic_fw('mqtt', 'mqtt_praefix', $ic_cfg['mqtt_praefix'])) ?>"<?= ic_fk('mqtt', 'mqtt_praefix') ?> placeholder="<?= ic_e($ic_plugin) ?>">
<p class="sm-klein"><?= ic_txt('UI.MQTT_PRAEFIX_TEXT') ?></p>

<h2><?= ic_txt('UI.H_MQTT_ABO') ?></h2>
<div class="sm-hinweis"><?= ic_txtf('UI.MQTT_ABO_TEXT', ic_mono(ic_mqtt_praefix() . '/#')) ?> <?= ic_abo_text() ?></div>

<h2><?= ic_txt('UI.H_MQTT_THEMEN') ?></h2>
<div class="sm-tabrahmen">
<table>
<!-- Dritte Spalte seit 2.2.6: der Hausstandard verlangt, dass je Thema
     dasteht, ob es retained gesendet wird - wer einen virtuellen Eingang
     baut, muss wissen, ob nach einem Neustart ein Wert da ist. Die Angabe
     kommt aus derselben Liste wie der Sender (ic_mqtt_themen()), sie kann
     also nicht auseinanderlaufen. -->
<tr><th><?= ic_txt('UI.SP_THEMA') ?></th><th><?= ic_txt('UI.SP_WANN') ?></th><th><?= ic_txt('UI.SP_RETAIN') ?></th></tr>
<?php foreach (ic_mqtt_themen() as $ic_t) { ?>
<tr><td><span class="sm-mono"><?= ic_e($ic_t[0]) ?></span></td><td><?= ic_txt($ic_t[1]) ?></td><td><?= ic_txt($ic_t[2] ? 'UI.JA' : 'UI.NEIN') ?></td></tr>
<?php } ?>
</table>
</div>

<h2><?= ic_txt('UI.H_MQTT_ZUSTAND') ?></h2>
<div class="sm-tabrahmen">
<table>
<tr><td><?= ic_txt('UI.MQTT_PORT') ?></td><td><?php
$ic_port = ic_mqtt_udpport();
echo $ic_port ? ic_e($ic_port) : ic_txt('UI.MQTT_PORT_KEINER'); ?></td></tr>
<tr><td><?= ic_txt('UI.MQTT_AUTOSTART') ?></td><td><?php
$ic_auto = ic_mqtt_autostart();
echo $ic_auto === true ? ic_txt('UI.JA') : ($ic_auto === false ? ic_txt('UI.NEIN') : ic_txt('UI.UNBEKANNT')); ?></td></tr>
<tr><td><?= ic_txt('UI.MQTT_SOCKETS') ?></td><td><?= function_exists('socket_create') ? ic_txt('UI.JA') : ic_txt('UI.NEIN') ?></td></tr>
</table>
</div>
<?php if ($ic_auto === false) { ?>
<div class="sm-hinweis sm-warn"><?= ic_txt('UI.MQTT_AUTOSTART_WARN') ?></div>
<?php } ?>

<div class="sm-legende">
<span><i class="sm-punkt sm-b-aktion"></i> <?= ic_txt('UI.LEG_AKTION') ?></span>
</div>
<div class="sm-knopfreihe">
<button data-role="none" class="sm-btn sm-b-aktion" type="submit"><?= ic_txt('UI.SPEICHERN') ?></button>
</div>
</form>
</div>

<!-- ===================== Einbindung in Loxone ===================== -->
<div class="sm-seite<?= $ic_offen === 'loxone' ? ' sm-active' : '' ?>" id="tab-loxone">
<h2><?= ic_txt('LOX.H_WEG') ?></h2>
<p><?= ic_txt('LOX.WEG_TEXT') ?></p>

<div class="sm-step"><b><?= ic_txt('LOX.S1') ?></b><br>
<?= ic_txt('LOX.S1_TEXT') ?>
<div class="sm-hinweis"><b><?= ic_txt('LOX.TOKEN_WICHTIG') ?></b><br><?= ic_txt('LOX.TOKEN_TEXT') ?></div>
<label><?= ic_txt('LOX.L_TOKEN') ?></label>
<input type="text" data-role="none" value="<?= ic_e($ic_token) ?>" readonly onclick="this.select();">
<div class="sm-legende">
<span><i class="sm-punkt sm-b-aktion"></i> <?= ic_txt('UI.LEG_AKTION') ?></span>
</div>
<div class="sm-knopfreihe">
<form method="post" action="index.php">
<?= ic_formularfelder('loxone') ?>
<input type="hidden" name="token_neu" value="1">
<button type="submit" data-role="none" class="sm-btn sm-b-aktion"
        onclick="return confirm(this.getAttribute('data-frage'));"
        data-frage="<?= ic_txt('LOX.TOKEN_FRAGE') ?>"><?= ic_txt('LOX.TOKEN_NEU') ?></button>
</form>
</div>
</div>

<div class="sm-step"><b><?= ic_txt('LOX.S2') ?></b><br>
<?= ic_txt('LOX.S2_TEXT') ?><br>
<span class="sm-mono"><?= ic_e($ic_adr['bild']) ?></span>
<p class="sm-klein"><?= ic_txtf('LOX.S2_VERZOEGERUNG', ic_fett('3')) ?></p>
</div>

<div class="sm-step"><b><?= ic_txt('LOX.S3') ?></b><br>
<?= ic_txtf('LOX.S3_TEXT', ic_mono('?trigger=NAME')) ?><br>
<span class="sm-mono"><?= ic_e(ic_adressen($ic_host, $ic_token, 'klingel')['bild_trig']) ?></span><br>
<span class="sm-mono"><?= ic_e(ic_adressen($ic_host, $ic_token, 'briefkasten')['bild_trig']) ?></span>
<?php if (count($ic_st) > 1) { ?>
<p class="sm-klein"><?= ic_txtf('LOX.S3_STATION', ic_mono('&station=2')) ?></p>
<?php foreach ($ic_st as $ic_i => $ic_s) { ?>
<span class="sm-mono"><?= ic_e(ic_adressen($ic_host, $ic_token, 'klingel', (string) ($ic_i + 1))['bild_trig']) ?></span><br>
<?php } ?>
<?php } ?>
</div>

<div class="sm-step"><b><?= ic_txt('LOX.S4') ?></b><br>
<span class="sm-mono"><?= ic_e($ic_adr['video']) ?></span>
<p class="sm-klein"><?= ic_txtf('LOX.S4_TEXT', ic_mono('s'), ic_mono('1'), ic_mono('300')) ?></p>
</div>

<div class="sm-step"><b><?= ic_txt('LOX.S5') ?></b><br>
<?= ic_txt('LOX.S5_TEXT') ?><br>
<span class="sm-mono"><?= ic_e($ic_adr['letztes']) ?></span><br>
<?php if ($ic_cfg['bild_oeffentlich'] === '1') { ?>
<span class="sm-mono">http://<?= ic_e($ic_host) ?>/plugins/<?= ic_e($ic_plugin) ?>/lastpicture.jpg</span>
<p class="sm-klein"><?= ic_txt('LOX.S5_OFFEN') ?></p>
<?php } ?>
</div>

<div class="sm-step"><b><?= ic_txt('LOX.S6') ?></b><br>
<span class="sm-mono"><?= ic_e($ic_adr['strom']) ?></span>
<p class="sm-klein"><?= ic_txt('LOX.S6_TEXT') ?></p>
</div>

<div class="sm-step"><b><?= ic_txt('LOX.S7') ?></b><br>
<?= ic_txt('LOX.S7_TEXT') ?><br>
<span class="sm-mono"><?= ic_e($ic_adr['selftest']) ?></span>
<!-- NEU 2.2.6: die drei auswertbaren Felder stehen namentlich da.
     Bis 2.2.5 versprach der Satz, die Antwort sei "auch fuer den Miniserver
     auswertbar" - und liess offen, WORAUF man auswerten soll. Zwei der drei
     Felder gab es damals ueberhaupt nicht; sie sind mit dieser Fassung
     dazugekommen (ic_start.php). Die Feldnamen sind JSON, kein uebersetzbarer
     Text, und stehen deshalb hier und nicht in der Sprachdatei. -->
<p class="sm-klein"><?= ic_txt('LOX.S7_FELDER') ?><br>
<span class="sm-mono">bestanden</span> - <?= ic_txt('LOX.S7_E1') ?><br>
<span class="sm-mono">alter</span> - <?= ic_txt('LOX.S7_E2') ?><br>
<span class="sm-mono">zaehler</span> - <?= ic_txt('LOX.S7_E3') ?></p>
</div>

<div class="sm-step"><b><?= ic_txt('LOX.S8') ?></b><br>
<?= ic_txt('LOX.S8_TEXT') ?>
<div class="sm-hinweis sm-warn"><?= ic_txt('LOX.IMPORT_WARN') ?></div>
<div class="sm-legende">
<span><i class="sm-punkt sm-b-technik"></i> <?= ic_txt('UI.LEG_TECHNIK') ?></span>
</div>
<div class="sm-knopfreihe">
<form method="post" action="index.php">
<?= ic_formularfelder('loxone') ?>
<button type="submit" data-role="none" class="sm-btn sm-b-technik" name="vorlage" value="ausgang"><?= ic_txt('LOX.VORLAGE_AUSGANG') ?></button>
</form>
<form method="post" action="index.php">
<?= ic_formularfelder('loxone') ?>
<button type="submit" data-role="none" class="sm-btn sm-b-technik" name="vorlage" value="eingang"><?= ic_txt('LOX.VORLAGE_EINGANG') ?></button>
</form>
</div>
</div>

<div class="sm-step"><b><?= ic_txt('LOX.S9') ?></b><br>
<?= ic_txt('LOX.S9_TEXT') ?>
<div class="sm-tabrahmen">
<table>
<tr><th>#</th><th><?= ic_txt('LOX.SP_BAUSTEIN') ?></th><th><?= ic_txt('LOX.SP_NAME') ?></th>
    <th><?= ic_txt('LOX.SP_PARAMETER') ?></th><th><?= ic_txt('LOX.SP_VERBINDEN') ?></th></tr>
<tr><td>1</td><td><?= ic_txt('LOX.B1_TYP') ?></td><td><?= ic_txt('LOX.B1_NAME') ?></td>
    <td><?= ic_txt('LOX.B1_PARAM') ?></td><td><?= ic_txt('LOX.B1_VERB') ?></td></tr>
<tr><td>2</td><td><?= ic_txt('LOX.B2_TYP') ?></td><td><?= ic_txt('LOX.B2_NAME') ?></td>
    <td><?= ic_txt('LOX.B2_PARAM') ?></td><td><?= ic_txt('LOX.B2_VERB') ?></td></tr>
<tr><td>3</td><td><?= ic_txt('LOX.B3_TYP') ?></td><td><?= ic_txt('LOX.B3_NAME') ?></td>
    <td><?= ic_txt('LOX.B3_PARAM') ?></td><td><?= ic_txt('LOX.B3_VERB') ?></td></tr>
<tr><td>4</td><td><?= ic_txt('LOX.B4_TYP') ?></td><td><?= ic_txt('LOX.B4_NAME') ?></td>
    <td><?= ic_txt('LOX.B4_PARAM') ?></td><td><?= ic_txt('LOX.B4_VERB') ?></td></tr>
<!-- Der Name wird GERECHNET, nicht getippt: das Gateway benennt den Eingang
     nach dem Thema und ersetzt dabei "/" durch "_". Bis 2.2.5 stand hier
     woertlich "intercom_ok" - bei einer Zweitinstallation (intercom_01) oder
     eigenem Praefix zeigte die Baustein-Liste auf einen Eingang, den das
     Gateway nie anlegt. Und ausgerechnet an ihm haengt die Ausfallerkennung.
     Baustein 5 haengt seit 2.2.6 an status/ts statt an ok. BERICHTIGT 2.2.13
     (M8): hier stand "ok kommt nur nach einem Bildabruf" - gemessen geht ok
     bei jedem Herzschlag hinaus, genau wie status/ts (mqtt-Pruefer, Befund 10).
     Beide tragen denselben Zeitpunkt; status/ts ist der Name nach Hausschema,
     ok bleibt fuer bestehende Anlagen. -->
<tr><td>5</td><td><?= ic_txt('LOX.B5_TYP') ?></td><td><?= ic_txtf('LOX.B5_NAME', ic_mono(ic_gatewayname(ic_mqtt_praefix()) . '_status_ts')) ?></td>
    <td><?= ic_txt('LOX.B5_PARAM') ?></td><td><?= ic_txt('LOX.B5_VERB') ?></td></tr>
<tr><td>6</td><td><?= ic_txt('LOX.B6_TYP') ?></td><td><?= ic_txt('LOX.B6_NAME') ?></td>
    <td><?= ic_txt('LOX.B6_PARAM') ?></td><td><?= ic_txt('LOX.B6_VERB') ?></td></tr>
<tr><td>7</td><td><?= ic_txt('LOX.B7_TYP') ?></td><td><?= ic_txt('LOX.B7_NAME') ?></td>
    <td><?= ic_txt('LOX.B7_PARAM') ?></td><td><?= ic_txt('LOX.B7_VERB') ?></td></tr>
</table>
</div>
<p class="sm-klein"><?= ic_txt('LOX.B_ZU2') ?></p>
<p class="sm-klein"><?= ic_txt('LOX.B_ZU5') ?></p>
<p class="sm-klein"><?= ic_txt('LOX.B_ZU7') ?></p>
</div>

<div class="sm-step"><b><?= ic_txt('LOX.S10') ?></b><br>
<?= ic_txt('LOX.S10_TEXT') ?>
</div>
</div>

<!-- ===================== Archiv ===================== -->
<div class="sm-seite<?= $ic_offen === 'archiv' ? ' sm-active' : '' ?>" id="tab-archiv">
<h2><?= ic_txt('UI.H_ARCHIV') ?></h2>
<div class="sm-tabrahmen">
<table>
<tr><th><?= ic_txt('UI.SP_ART') ?></th><th><?= ic_txt('UI.SP_ANZAHL') ?></th><th><?= ic_txt('UI.SP_PLATZ') ?></th></tr>
<tr><td><?= ic_txt('UI.A_BILDER') ?></td><td><?= (int) $ic_zahlen['bilder'] ?></td><td><?= ic_e(ic_byte($ic_zahlen['bilder_byte'])) ?></td></tr>
<tr><td><?= ic_txt('UI.A_VIDEOS') ?></td><td><?= (int) $ic_zahlen['videos'] ?></td><td><?= ic_e(ic_byte($ic_zahlen['videos_byte'])) ?></td></tr>
<tr><td><?= ic_txt('UI.A_TIMELAPSE') ?></td><td><?= (int) $ic_zahlen['timelapse'] ?></td><td><?= ic_e(ic_byte($ic_zahlen['timelapse_byte'])) ?></td></tr>
</table>
</div>
<?php
list($ic_frei, $ic_ganz) = ic_platz();
$ic_g = ic_aufbewahrung();
?>
<p class="sm-klein"><?= $ic_ganz > 0 ? ic_txtf('UI.ARCHIV_PLATZ', ic_e(ic_byte($ic_frei)), ic_e(ic_byte($ic_ganz))) : ic_txt('UI.ARCHIV_PLATZ_UNKLAR') ?></p>
<p class="sm-klein"><?= ($ic_g['tage'] || $ic_g['zahl'] || $ic_g['mb'])
    ? ic_txtf('UI.ARCHIV_GRENZE', $ic_g['tage'], $ic_g['zahl'], $ic_g['mb'])
    : ic_txt('UI.ARCHIV_KEINE_GRENZE') ?></p>

<div class="sm-legende">
<span><i class="sm-punkt sm-b-lesen"></i> <?= ic_txt('UI.LEG_LESEN') ?></span>
<span><i class="sm-punkt sm-b-aktion"></i> <?= ic_txt('UI.LEG_AKTION') ?></span>
</div>
<div class="sm-knopfreihe">
<a class="sm-btn sm-b-lesen" href="live.php"><?= ic_txt('UI.K_LIVE') ?></a>
<a class="sm-btn sm-b-lesen" href="archive.php"><?= ic_txt('UI.K_BILDARCHIV') ?></a>
<a class="sm-btn sm-b-lesen" href="videoarchive.php"><?= ic_txt('UI.K_VIDEOARCHIV') ?></a>
</div>
<div class="sm-knopfreihe">
<?php foreach (array('bilder' => 'UI.K_DEL_BILDER', 'videos' => 'UI.K_DEL_VIDEOS',
                     'timelapse' => 'UI.K_DEL_TL') as $ic_w => $ic_s) { ?>
<form method="post" action="index.php">
<?= ic_formularfelder('archiv') ?>
<label class="sm-klein"><input type="checkbox" data-role="none" name="wirklich" value="1"> <?= ic_txt('UI.L_WIRKLICH') ?></label>
<button type="submit" data-role="none" class="sm-btn sm-b-aktion" name="loeschen" value="<?= $ic_w ?>"
        onclick="return confirm(this.getAttribute('data-frage'));"
        data-frage="<?= ic_txt('UI.DEL_FRAGE') ?>"><?= ic_txt($ic_s) ?></button>
</form>
<?php } ?>
</div>

<?php
$ic_neueste = glob(ic_archivordner()['bild'] . '*.jpg') ?: array();
rsort($ic_neueste);
if ($ic_neueste) { ?>
<h2><?= ic_txt('UI.H_NEUESTE') ?></h2>
<div class="sm-gal">
<?php foreach (array_slice($ic_neueste, 0, 8) as $ic_f) { $ic_n = basename($ic_f); ?>
<figure>
    <img src="/legacy/<?= rawurlencode($ic_plugin) ?>_data/img_archive/<?= rawurlencode($ic_n) ?>" alt="">
    <figcaption><?= ic_e($ic_n) ?></figcaption>
</figure>
<?php } ?>
</div>
<?php } else { ?>
<div class="sm-hinweis"><?= ic_txtf('UI.ARCHIV_LEER', ic_fett(ic_roh('UI.REITER_TEST'))) ?></div>
<?php } ?>
</div>

<!-- ===================== Test ===================== -->
<div class="sm-seite<?= $ic_offen === 'test' ? ' sm-active' : '' ?>" id="tab-test">
<h2><?= ic_txt('UI.H_TEST') ?></h2>

<?php foreach ($ic_ausgabe as $ic_a) { ?>
<div class="sm-hinweis"><?= $ic_a ?></div>
<?php } ?>

<?php if ($ic_bilanz['bestanden']) { ?>
<div class="sm-hinweis">
<?php } else { ?>
<div class="sm-hinweis sm-warn">
<?php } ?>
<b><?= ic_txtf('UI.BILANZ', $ic_bilanz['ok'], $ic_bilanz['gewertet']) ?></b>
<?php if ($ic_bilanz['fehl'] > 0) { ?> <?= ic_txtf('UI.BILANZ_FEHL', $ic_bilanz['fehl']) ?><?php } ?>
<?php if ($ic_bilanz['unklar'] > 0) { ?> <?= ic_txtf('UI.BILANZ_UNKLAR', $ic_bilanz['unklar']) ?><?php } ?>
<?php if ($ic_bilanz['hinweis'] > 0) { ?> <?= ic_txtf('UI.BILANZ_HINWEIS', $ic_bilanz['hinweis']) ?><?php } ?>
</div>

<div class="sm-tabrahmen">
<table class="sm-pz">
<?php foreach ($ic_pruefzeilen as $ic_z) { echo ic_pz_zeile($ic_z) . "\n"; } ?>
</table>
</div>

<div class="sm-legende">
<span><i class="sm-punkt sm-b-lesen"></i> <?= ic_txt('UI.LEG_LESEN') ?></span>
<span><i class="sm-punkt sm-b-technik"></i> <?= ic_txt('UI.LEG_TECHNIK') ?></span>
<span><i class="sm-punkt sm-b-aktion"></i> <?= ic_txt('UI.LEG_AKTION') ?></span>
</div>

<h3 class="sm-h3"><?= ic_txt('UI.H_ANSEHEN') ?></h3>
<div class="sm-knopfreihe">
<a class="sm-btn sm-b-lesen" href="<?= ic_e($ic_adr['letztes']) ?>" target="_blank"><?= ic_txt('UI.K_LETZTES') ?></a>
<a class="sm-btn sm-b-lesen" href="<?= ic_e($ic_adr['strom']) ?>" target="_blank"><?= ic_txt('UI.K_STROM') ?></a>
</div>

<h3 class="sm-h3"><?= ic_txt('UI.H_TECHNIK') ?></h3>
<div class="sm-knopfreihe">
<form method="post" action="index.php">
<?= ic_formularfelder('test') ?>
<button type="submit" data-role="none" class="sm-btn sm-b-technik" name="tat" value="pruefen"><?= ic_txt('UI.K_PRUEFEN') ?></button>
</form>
<a class="sm-btn sm-b-technik" href="<?= ic_e($ic_adr['selftest']) ?>" target="_blank"><?= ic_txt('UI.K_SELFTEST') ?></a>
</div>

<h3 class="sm-h3"><?= ic_txt('UI.H_AUSLOESEN') ?></h3>
<p class="sm-klein"><?= ic_txt('UI.AUSLOESEN_TEXT') ?></p>
<!-- BERICHTIGT 2.2.13 (O9): "Bildabruf pruefen (JSON)" steht hier, orange.
     Bis 2.2.12 stand er grau unter "Technische Auskunft" - er ruft aber
     getpicture.php?hook=false auf, und das ersetzt das zuletzt aufgenommene
     Bild (lastpicture.jpg), das Loxone anzeigt. Das Verhalten bleibt (wer
     ?hook=false in Loxone nutzt, verlaesst sich darauf); Farbe und Text sagen
     es jetzt. Umgekehrt ist "Aufraeumen: nur zeigen" grau: es loescht nichts. -->
<div class="sm-knopfreihe">
<a class="sm-btn sm-b-aktion" href="/plugins/<?= ic_e($ic_plugin) ?>/getpicture.php?hook=false&amp;token=<?= rawurlencode($ic_token) ?>" target="_blank"><?= ic_txt('UI.K_JSON') ?></a>
<a class="sm-btn sm-b-aktion" href="/plugins/<?= ic_e($ic_plugin) ?>/getpicture.php?trigger=test&amp;token=<?= rawurlencode($ic_token) ?>" target="_blank"><?= ic_txt('UI.K_BILD') ?></a>
<a class="sm-btn sm-b-aktion" href="/plugins/<?= ic_e($ic_plugin) ?>/getvideo.php?s=10&amp;token=<?= rawurlencode($ic_token) ?>" target="_blank"><?= ic_txt('UI.K_VIDEO') ?></a>
<form method="post" action="index.php">
<?= ic_formularfelder('test') ?>
<button type="submit" data-role="none" class="sm-btn sm-b-aktion" name="tat" value="timelapse"><?= ic_txt('UI.K_TL') ?></button>
</form>
<form method="post" action="index.php">
<?= ic_formularfelder('test') ?>
<button type="submit" data-role="none" class="sm-btn sm-b-technik" name="tat" value="aufraeumen_probe"><?= ic_txt('UI.K_CU_PROBE') ?></button>
</form>
<form method="post" action="index.php">
<?= ic_formularfelder('test') ?>
<label class="sm-klein"><input type="checkbox" data-role="none" name="wirklich" value="1"> <?= ic_txt('UI.L_WIRKLICH') ?></label>
<button type="submit" data-role="none" class="sm-btn sm-b-aktion" name="tat" value="aufraeumen"
        onclick="return confirm(this.getAttribute('data-frage'));"
        data-frage="<?= ic_txt('UI.CU_FRAGE') ?>"><?= ic_txt('UI.K_CU') ?></button>
</form>
</div>
<p class="sm-klein"><?= ic_txtf('UI.TECHNIK_TEXT', ic_mono('?hook=false')) ?></p>

<h3 class="sm-h3"><?= ic_txt('UI.H_BILDLINK') ?></h3>
<p class="sm-klein"><?= ic_txt('UI.BILDLINK_TEXT') ?></p>
<div class="sm-knopfreihe">
<form method="post" action="index.php">
<?= ic_formularfelder('test') ?>
<input type="hidden" name="link_stunden" value="24">
<!-- Orange, nicht grau: der Knopf legt einen Zugangscode an, mit dem sich
     das Haustuerbild 24 Stunden lang fuenfmal OHNE Anmeldung abrufen laesst.
     Das ist "loest etwas aus", nicht "technische Auskunft". -->
<button type="submit" data-role="none" class="sm-btn sm-b-aktion" name="tat" value="bildlink"><?= ic_txt('UI.K_BILDLINK') ?></button>
</form>
</div>
</div>

<!-- ===================== Logdateien ===================== -->
<div class="sm-seite<?= $ic_offen === 'log' ? ' sm-active' : '' ?>" id="tab-log">
<h2><?= ic_txt('UI.H_LOG') ?></h2>
<div class="sm-hinweis"><b><?= ic_txt('UI.LOG_RAMDISK') ?></b> <?= ic_txtf('UI.LOG_RAMDISK_TEXT', ic_mono('log/')) ?></div>
<p class="sm-klein"><?= ic_txt('UI.LOG_TEXT') ?></p>
<?php if ($ic_log !== '') { ?>
<p class="sm-klein"><?= ic_txtf('UI.LOG_QUELLE', ic_mono($ic_logdatei)) ?></p>
<pre><?= ic_e($ic_log) ?></pre>
<?php } else { ?>
<div class="sm-hinweis"><?= ic_txt('UI.LOG_LEER') ?></div>
<?php } ?>
</div>

</div><!-- /smw -->

<script>
/* Die Reiter sind echte Verweise; welcher offen ist, entscheidet der Server.
   Dieses Skript schaltet nur ohne Neuladen um und zieht die activetab-Felder
   nach - faellt es aus, bleibt die Seite ueber die Verweise bedienbar. */
(function () {
    var leiste = document.querySelectorAll('.smw .sm-reiter a');
    for (var i = 0; i < leiste.length; i++) {
        leiste[i].addEventListener('click', function (e) {
            var ziel = this.getAttribute('data-ziel');
            var s = document.getElementById(ziel);
            if (!s) { return; }
            e.preventDefault();
            var alle = document.querySelectorAll('.smw .sm-reiter a');
            for (var j = 0; j < alle.length; j++) { alle[j].classList.remove('sm-active'); }
            this.classList.add('sm-active');
            var seiten = document.querySelectorAll('.smw .sm-seite');
            for (var k = 0; k < seiten.length; k++) { seiten[k].classList.remove('sm-active'); }
            s.classList.add('sm-active');
            var felder = document.querySelectorAll('.smw input[name="activetab"]');
            for (var m = 0; m < felder.length; m++) { felder[m].value = ziel.replace('tab-', ''); }
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', 'index.php?tab=' + ziel.replace('tab-', ''));
            }
        });
    }
})();
</script>

<?php
LBWeb::lbfooter();

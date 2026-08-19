<?php
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
function ic_txt($schluessel)
{
    global $L;
    return isset($L[$schluessel]) ? ic_e($L[$schluessel]) : $schluessel;
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

/** Ein Stueck Festbreitenschrift fuer die Platzhalter. */
function ic_mono($t) { return '<span class="sm-mono">' . ic_e($t) . '</span>'; }
function ic_fett($t) { return '<b>' . ic_e($t) . '</b>'; }

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

$ic_cfg = ic_config();

/* ==================================================================
 * Handler - ALLES vor der ersten Ausgabe
 * ================================================================== */

$ic_offen = 'settings';
if (isset($_POST['activetab']) && is_string($_POST['activetab'])
    && isset($ic_reiter[$_POST['activetab']])) {
    $ic_offen = $_POST['activetab'];
} elseif (isset($_GET['tab']) && is_string($_GET['tab']) && isset($ic_reiter[$_GET['tab']])) {
    $ic_offen = $_GET['tab'];
}

/** Jeder auslösende Aufruf verlangt das Merkmal - ausnahmslos. */
$ic_darf = ic_merkmal_gueltig();
$ic_wollte = ($_SERVER['REQUEST_METHOD'] === 'POST');
if ($ic_wollte && !$ic_darf) {
    $ic_fehler[] = ic_txt('UI.M_MERKMAL');
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

/* ---------------- Neues Token ---------------- */
if ($ic_wollte && $ic_darf && isset($_POST['token_neu'])) {
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

    /* Stationen. Eine halb ausgefuellte Zeile wird UEBERGANGEN und gemeldet -
     * sie verhindert nicht das Speichern des Uebrigen. Genau andersherum
     * gebaut hat es im Bestand schon zweimal dazu gefuehrt, dass der Anwender
     * alles noch einmal tippen musste. */
    $ic_stationen = array();
    $ic_namen = isset($_POST['st_name']) && is_array($_POST['st_name']) ? $_POST['st_name'] : array();
    foreach ($ic_namen as $ic_i => $ic_name) {
        $ic_hole = function ($ic_feld) use ($ic_i) {
            return (isset($_POST[$ic_feld]) && is_array($_POST[$ic_feld]) && isset($_POST[$ic_feld][$ic_i])
                    && is_string($_POST[$ic_feld][$ic_i]))
                   ? trim(preg_replace('/[\x00-\x1F\x7F]/', '', $_POST[$ic_feld][$ic_i])) : '';
        };
        $ic_ip = $ic_hole('st_ip');
        $ic_name = is_string($ic_name) ? trim(preg_replace('/[\x00-\x1F\x7F]/', '', $ic_name)) : '';
        if ($ic_ip === '' && $ic_name === '') { continue; }
        if ($ic_ip === '') {
            $ic_meldungen[] = ic_txtf('UI.M_STATION_OHNE_IP', ic_e($ic_name));
            continue;
        }
        // Adresse pruefen statt zurechtbiegen: erlaubt sind Name oder
        // IP-Adresse, wahlweise mit Port.
        if (!preg_match('/^[A-Za-z0-9\.\-]+(:[0-9]{1,5})?$/', $ic_ip)) {
            $ic_meldungen[] = ic_txtf('UI.M_STATION_ADRESSE', ic_e($ic_ip));
            continue;
        }
        $ic_pass = $ic_hole('st_pass');
        if ($ic_pass === '') {
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
        $ic_stationen[] = array(
            'name' => $ic_name !== '' ? $ic_name : $ic_ip,
            'ip'   => $ic_ip,
            'user' => $ic_hole('st_user'),
            'pass' => $ic_pass,
            'ms'   => max(1, (int) $ic_hole('st_ms')),
            'standbild' => $ic_hole('st_standbild'),
        );
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
        $ic_w = (isset($_POST[$ic_k]) && is_string($_POST[$ic_k])) ? $_POST[$ic_k] : '';
        $ic_neu[$ic_k] = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $ic_w));
    }
    foreach (array('cleanup_days', 'cleanup_count', 'cleanup_mb', 'intervall_min',
                   'tv_port', 'ai_minconf') as $ic_k) {
        if ($ic_neu[$ic_k] !== '' && !is_numeric($ic_neu[$ic_k])) {
            $ic_meldungen[] = ic_txtf('UI.M_ZAHL', ic_e($ic_k), ic_e($ic_neu[$ic_k]));
            $ic_neu[$ic_k] = isset($ic_cfg[$ic_k]) ? $ic_cfg[$ic_k] : '';
        }
    }
    if ($ic_neu['timelapse_time'] !== ''
        && !preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $ic_neu['timelapse_time'])) {
        $ic_meldungen[] = ic_txtf('UI.M_UHRZEIT', ic_e($ic_neu['timelapse_time']));
        $ic_neu['timelapse_time'] = isset($ic_cfg['timelapse_time']) ? $ic_cfg['timelapse_time'] : '12:00';
    }
    if ($ic_neu['storage_path'] !== '' && !@is_dir($ic_neu['storage_path'])) {
        $ic_meldungen[] = ic_txtf('UI.M_SPEICHER_WEG', ic_e($ic_neu['storage_path']));
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

    /* Bildweg */
    $ic_weg = isset($_POST['bildweg']) && is_string($_POST['bildweg']) ? $_POST['bildweg'] : 'strom';
    $ic_neu['bildweg'] = in_array($ic_weg, array('strom', 'standbild', 'auto'), true) ? $ic_weg : 'strom';

    // mqtt_* wohnen im MQTT-Reiter mit eigenem Formular und eigenem Handler -
    // hier nicht anfassen, sonst stellte jedes Speichern die Haken auf 0.

    /* Ist noch kein Token da, eines erzeugen und behalten. Ein vorhandenes
     * wird hier NIEMALS ersetzt, sonst waeren nach jedem Speichern alle
     * Adressen im Miniserver ungueltig. */
    if (empty($ic_neu['aktionstoken'])) {
        try {
            $ic_neu['aktionstoken'] = ic_token_neu();
        } catch (RuntimeException $ic_e) {
            // Lieber gar kein Token als ein erratbares: die Endpunkte
            // weisen dann konsequent alles ab.
            $ic_neu['aktionstoken'] = '';
            $ic_fehler[] = ic_txt('UI.M_ZUFALL');
        }
    }

    list($ic_ok, $ic_was) = ic_config_speichern($ic_neu);
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
            $ic_innen = ic_paths()['data'] . '/lastpicture.jpg';
            if (@is_file($ic_innen) && !@is_file($ic_offene_kopie)) {
                ic_datei_ersetzen($ic_offene_kopie, (string) @file_get_contents($ic_innen));
            }
        }
        list($ic_sok, $ic_smeldung) = ic_speicherort_anwenden();
        if (!$ic_sok) {
            $ic_fehler[] = ic_txtf('UI.M_SPEICHER_NICHT', ic_e($ic_smeldung));
        } elseif ($ic_smeldung === 'verschoben') {
            $ic_meldungen[] = ic_txt('UI.M_SPEICHER_UMGEZOGEN');
        }
        $ic_meldungen[] = ic_txt('UI.M_GESPEICHERT');
    } else {
        ic_log('Die Einstellungen liessen sich NICHT schreiben: ' . $ic_was);
        $ic_fehler[] = ic_txtf('UI.M_NICHT_GESPEICHERT', ic_e($ic_was));
    }
}

/* ---------------- MQTT speichern ---------------- */
if ($ic_wollte && $ic_darf && isset($_POST['mqtt_speichern'])) {
    $ic_neu = $ic_cfg;
    $ic_neu['mqtt_enable'] = isset($_POST['mqtt_enable']) ? '1' : '0';
    $ic_p = (isset($_POST['mqtt_praefix']) && is_string($_POST['mqtt_praefix']))
       ? trim($_POST['mqtt_praefix']) : '';
    $ic_neu['mqtt_praefix'] = ic_mqtt_thema($ic_p);
    list($ic_ok, $ic_was) = ic_config_speichern($ic_neu);
    if ($ic_ok) {
        $ic_cfg = $ic_neu;
        ic_log('MQTT-Einstellungen gespeichert (Praefix ' . ic_mqtt_praefix() . ').');
        $ic_meldungen[] = ic_txt('UI.M_GESPEICHERT');
    } else {
        $ic_fehler[] = ic_txtf('UI.M_NICHT_GESPEICHERT', ic_e($ic_was));
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
        list($ic_zahl, $ic_byte, $ic_zeilen) = ic_aufraeumen($ic_probe);
        $ic_ausgabe[] = $ic_probe ? ic_txtf('UI.M_CU_PROBE', $ic_zahl, ic_e(ic_byte($ic_byte)))
                               : ic_txtf('UI.M_CU_OK', $ic_zahl, ic_e(ic_byte($ic_byte)));
        foreach ($ic_zeilen as $ic_z) { $ic_ausgabe[] = ic_e($ic_z); }
    } elseif ($ic_tat === 'bildlink') {
        $ic_stunden = (isset($_POST['link_stunden']) && is_numeric($_POST['link_stunden']))
                 ? (int) $_POST['link_stunden'] : 24;
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
    $ic_meldungen[] = ic_txtf('UI.M_GELOESCHT', $ic_zahl);
    $ic_offen = 'archiv';
}

/* ==================================================================
 * Beim ersten Oeffnen ein Token erzeugen
 * ================================================================== */
if (!isset($ic_cfg['aktionstoken']) || (string) $ic_cfg['aktionstoken'] === '') {
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

/* Vorgabewerte fuer noch nie gespeicherte Felder - an EINER Stelle. */
$ic_cfg += array(
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
    'aktionstoken' => '',
);

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
if ($ic_logdatei !== '') {
    $ic_zeilen = @file($ic_logdatei);
    if (is_array($ic_zeilen)) { $ic_log = implode('', array_slice($ic_zeilen, -200)); }
}

/* ==================================================================
 * Ausgabe
 * ================================================================== */

require_once "menu.php";
$navbar[1]['active'] = True;
LBWeb::lbheader(ic_titel(), 'https://github.com/timanders22/LoxBerry-Plugin-Intercom/', 'help.html');
require_once __DIR__ . "/ic_stil.php";

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
<a class="sm-reiter-el<?= $ic_offen === 'settings' ? ' sm-active' : '' ?>" data-ziel="tab-settings" href="index.php?tab=settings"><?= ic_txt('UI.REITER_EINSTELLUNGEN') ?></a>
<a class="sm-reiter-el<?= $ic_offen === 'mqtt' ? ' sm-active' : '' ?>" data-ziel="tab-mqtt" href="index.php?tab=mqtt"><?= ic_txt('UI.REITER_MQTT') ?></a>
<a class="sm-reiter-el<?= $ic_offen === 'loxone' ? ' sm-active' : '' ?>" data-ziel="tab-loxone" href="index.php?tab=loxone"><?= ic_txt('UI.REITER_LOXONE') ?></a>
<a class="sm-reiter-el<?= $ic_offen === 'archiv' ? ' sm-active' : '' ?>" data-ziel="tab-archiv" href="index.php?tab=archiv"><?= ic_txt('UI.REITER_ARCHIV') ?></a>
<a class="sm-reiter-el<?= $ic_offen === 'test' ? ' sm-active' : '' ?>" data-ziel="tab-test" href="index.php?tab=test"><?= ic_txt('UI.REITER_TEST') ?></a>
<a class="sm-reiter-el<?= $ic_offen === 'log' ? ' sm-active' : '' ?>" data-ziel="tab-log" href="index.php?tab=log"><?= ic_txt('UI.REITER_LOG') ?></a>
</div>

<!-- ===================== Einstellungen ===================== -->
<div class="sm-seite<?= $ic_offen === 'settings' ? ' sm-active' : '' ?>" id="tab-settings">
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
foreach ($ic_reihen as $ic_i => $ic_s) { ?>
<tr>
<td><input type="text" data-role="none" name="st_name[]" value="<?= ic_e($ic_s['name'] === $ic_s['ip'] ? '' : $ic_s['name']) ?>" placeholder="<?= ic_txt('UI.PH_NAME') ?>"></td>
<td><input type="text" data-role="none" name="st_ip[]" value="<?= ic_e($ic_s['ip']) ?>" placeholder="192.168.1.50"><input type="hidden" name="st_alt[]" value="<?= ic_e($ic_s['ip']) ?>"></td>
<td><input type="text" data-role="none" name="st_user[]" value="<?= ic_e($ic_s['user']) ?>" placeholder="<?= ic_txt('UI.PH_MINISERVER') ?>"></td>
<td><input type="password" data-role="none" name="st_pass[]" value="" placeholder="<?= $ic_s['pass'] !== '' ? ic_txt('UI.PH_UNVERAENDERT') : '' ?>"></td>
<td><input type="number" data-role="none" name="st_ms[]" min="1" max="10" value="<?= (int) $ic_s['ms'] ?>"></td>
<td><input type="text" data-role="none" name="st_standbild[]" value="<?= ic_e($ic_s['standbild']) ?>" placeholder="/jpg/image.jpg"></td>
</tr>
<?php } ?>
</table>
</div>
<p class="sm-klein"><?= ic_txt('UI.STATIONEN_HINWEIS') ?></p>

<h2><?= ic_txt('UI.H_BILDWEG') ?></h2>
<label for="bildweg"><?= ic_txt('UI.L_BILDWEG') ?></label>
<select id="bildweg" name="bildweg" data-role="none">
<?php foreach (array('strom' => 'UI.WEG_STROM', 'standbild' => 'UI.WEG_STANDBILD',
                     'auto' => 'UI.WEG_AUTO') as $ic_w => $ic_s) { ?>
<option value="<?= $ic_w ?>"<?= $ic_cfg['bildweg'] === $ic_w ? ' selected' : '' ?>><?= ic_txt($ic_s) ?></option>
<?php } ?>
</select>
<label><?= ic_txt('UI.L_STANDBILDPFAD') ?></label>
<input type="text" data-role="none" name="standbild_pfad" value="<?= ic_e($ic_cfg['standbild_pfad']) ?>" placeholder="/jpg/image.jpg">
<p class="sm-klein"><?= ic_txtf('UI.BILDWEG_TEXT', ic_mono('/mjpg/video.mjpg'), ic_mono('/jpg/image.jpg')) ?></p>
<p class="sm-klein"><?= ic_txtf('UI.BILDWEG_MESSEN', ic_fett(ic_roh('UI.REITER_TEST'))) ?></p>

<h2><?= ic_txt('UI.H_BILDSCHUTZ') ?></h2>
<label><input type="checkbox" data-role="none" name="bild_schuetzen"<?= $ic_cfg['bild_oeffentlich'] === '0' ? ' checked' : '' ?>> <?= ic_txt('UI.L_BILDSCHUTZ') ?></label>
<p class="sm-klein"><?= ic_txtf('UI.BILDSCHUTZ_TEXT', ic_mono('lastpicture.jpg'), ic_mono('bild.php?token=...')) ?></p>

<h2><?= ic_txt('UI.H_SPEICHERORT') ?></h2>
<label><?= ic_txt('UI.L_SPEICHERPFAD') ?></label>
<input type="text" data-role="none" name="storage_path" value="<?= ic_e($ic_cfg['storage_path']) ?>" placeholder="/media/usbstick">
<p class="sm-klein"><?= ic_txt('UI.SPEICHERORT_TEXT') ?></p>

<h2><?= ic_txt('UI.H_AUFBEWAHRUNG') ?></h2>
<div class="sm-zeile">
<div><label><?= ic_txt('UI.L_TAGE') ?></label>
<input type="number" data-role="none" name="cleanup_days" min="0" max="3650" value="<?= ic_e($ic_cfg['cleanup_days']) ?>"></div>
<div><label><?= ic_txt('UI.L_ANZAHL') ?></label>
<input type="number" data-role="none" name="cleanup_count" min="0" value="<?= ic_e($ic_cfg['cleanup_count']) ?>"></div>
<div><label><?= ic_txt('UI.L_MB') ?></label>
<input type="number" data-role="none" name="cleanup_mb" min="0" value="<?= ic_e($ic_cfg['cleanup_mb']) ?>"></div>
</div>
<p class="sm-klein"><?= ic_txt('UI.AUFBEWAHRUNG_TEXT') ?></p>

<h2><?= ic_txt('UI.H_ZEITSTEMPEL') ?></h2>
<label><input type="checkbox" data-role="none" name="timestamp_image"<?= $ic_cfg['timestamp_image'] === 'on' ? ' checked' : '' ?>> <?= ic_txt('UI.L_STEMPEL_BILD') ?></label>
<label><input type="checkbox" data-role="none" name="timestamp_video"<?= $ic_cfg['timestamp_video'] === 'on' ? ' checked' : '' ?>> <?= ic_txt('UI.L_STEMPEL_VIDEO') ?></label>
<p class="sm-klein"><?= ic_txtf('UI.STEMPEL_TEXT', ic_mono('php-gd')) ?></p>

<h2><?= ic_txt('UI.H_ZEITRAFFER') ?></h2>
<label><input type="checkbox" data-role="none" name="timelapse_enable"<?= $ic_cfg['timelapse_enable'] === 'on' ? ' checked' : '' ?>> <?= ic_txt('UI.L_ZEITRAFFER') ?></label>
<label><?= ic_txt('UI.L_UHRZEIT') ?></label>
<input type="text" data-role="none" name="timelapse_time" value="<?= ic_e($ic_cfg['timelapse_time']) ?>" placeholder="12:00">
<label><input type="checkbox" data-role="none" name="timelapse_video"<?= $ic_cfg['timelapse_video'] === 'on' ? ' checked' : '' ?>> <?= ic_txt('UI.L_ZEITRAFFER_VIDEO') ?></label>
<p class="sm-klein"><?= ic_txtf('UI.ZEITRAFFER_TEXT', ic_mono('ffmpeg')) ?></p>

<h2><?= ic_txt('UI.H_INTERVALL') ?></h2>
<label><?= ic_txt('UI.L_INTERVALL') ?></label>
<input type="number" data-role="none" name="intervall_min" min="0" max="1440" value="<?= ic_e($ic_cfg['intervall_min']) ?>">
<p class="sm-klein"><?= ic_txt('UI.INTERVALL_TEXT') ?></p>

<h2><?= ic_txt('UI.H_TV') ?></h2>
<label><input type="checkbox" data-role="none" name="tv_enable"<?= $ic_cfg['tv_enable'] === 'on' ? ' checked' : '' ?>> <?= ic_txt('UI.L_TV') ?></label>
<div class="sm-zeile">
<div><label><?= ic_txt('UI.L_TV_ADRESSE') ?></label>
<input type="text" data-role="none" name="tv_ip" value="<?= ic_e($ic_cfg['tv_ip']) ?>"></div>
<div><label><?= ic_txt('UI.L_PORT') ?></label>
<input type="text" data-role="none" name="tv_port" value="<?= ic_e($ic_cfg['tv_port']) ?>"></div>
</div>
<p class="sm-klein"><?= ic_txt('UI.TV_TEXT') ?></p>

<h2><?= ic_txt('UI.H_KI') ?></h2>
<label><input type="checkbox" data-role="none" name="ai_enable"<?= $ic_cfg['ai_enable'] === 'on' ? ' checked' : '' ?>> <?= ic_txt('UI.L_KI') ?></label>
<label><?= ic_txt('UI.L_KI_ADRESSE') ?></label>
<input type="text" data-role="none" name="ai_url" value="<?= ic_e($ic_cfg['ai_url']) ?>" placeholder="http://192.168.1.60:32168/v1/vision/detection">
<label><?= ic_txt('UI.L_KI_SICHERHEIT') ?></label>
<input type="number" data-role="none" name="ai_minconf" min="1" max="99" value="<?= ic_e($ic_cfg['ai_minconf']) ?>">
<p class="sm-klein"><?= ic_txt('UI.KI_TEXT') ?></p>

<h2><?= ic_txt('UI.H_WEBHOOKS') ?></h2>
<p class="sm-klein"><?= ic_txt('UI.WEBHOOK_TEXT') ?></p>
<label><?= ic_txt('UI.L_WH1') ?></label>
<input type="text" data-role="none" name="webhook1" value="<?= ic_e($ic_cfg['webhook1']) ?>">
<label><?= ic_txtf('UI.L_WH2', ic_mono('<imgurl>')) ?></label>
<input type="text" data-role="none" name="webhook2" value="<?= ic_e($ic_cfg['webhook2']) ?>">
<label><?= ic_txt('UI.L_WH3') ?></label>
<input type="text" data-role="none" name="webhook3" value="<?= ic_e($ic_cfg['webhook3']) ?>">
<label><?= ic_txtf('UI.L_WH4', ic_mono('<imgurl>')) ?></label>
<input type="text" data-role="none" name="webhook4" value="<?= ic_e($ic_cfg['webhook4']) ?>">
<label><?= ic_txt('UI.L_VWH1') ?></label>
<input type="text" data-role="none" name="videowebhook1" value="<?= ic_e($ic_cfg['videowebhook1']) ?>">
<label><?= ic_txtf('UI.L_VWH2', ic_mono('<fileurl>')) ?></label>
<input type="text" data-role="none" name="videowebhook2" value="<?= ic_e($ic_cfg['videowebhook2']) ?>">

<div class="sm-legende">
<span><i class="sm-punkt sm-b-aktion"></i> <?= ic_txt('UI.LEG_AKTION') ?></span>
</div>
<div class="sm-knopfreihe">
<button data-role="none" class="sm-btn sm-b-aktion" type="submit" name="speichern" value="1"><?= ic_txt('UI.SPEICHERN') ?></button>
</div>
</form>
</div>

<!-- ===================== MQTT ===================== -->
<div class="sm-seite<?= $ic_offen === 'mqtt' ? ' sm-active' : '' ?>" id="tab-mqtt">
<form method="post" action="index.php">
<?= ic_formularfelder('mqtt') ?>
<input type="hidden" name="mqtt_speichern" value="1">

<h2><?= ic_txt('UI.H_MQTT') ?></h2>
<label><input type="checkbox" data-role="none" name="mqtt_enable"<?= (string) $ic_cfg['mqtt_enable'] === '1' ? ' checked' : '' ?>> <?= ic_txt('UI.L_MQTT') ?></label>
<p class="sm-klein"><?= ic_txt('UI.MQTT_GATEWAY_TEXT') ?></p>

<label><?= ic_txt('UI.L_MQTT_PRAEFIX') ?></label>
<input type="text" data-role="none" name="mqtt_praefix" value="<?= ic_e($ic_cfg['mqtt_praefix']) ?>" placeholder="<?= ic_e($ic_plugin) ?>">
<p class="sm-klein"><?= ic_txt('UI.MQTT_PRAEFIX_TEXT') ?></p>

<h2><?= ic_txt('UI.H_MQTT_ABO') ?></h2>
<div class="sm-hinweis"><?= ic_txtf('UI.MQTT_ABO_TEXT', ic_mono(ic_mqtt_praefix() . '/#')) ?></div>

<h2><?= ic_txt('UI.H_MQTT_THEMEN') ?></h2>
<div class="sm-tabrahmen">
<table>
<tr><th><?= ic_txt('UI.SP_THEMA') ?></th><th><?= ic_txt('UI.SP_WANN') ?></th></tr>
<?php foreach (ic_mqtt_themen() as $ic_t) { ?>
<tr><td><span class="sm-mono"><?= ic_e($ic_t[0]) ?></span></td><td><?= ic_txt($ic_t[1]) ?></td></tr>
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
<tr><td>5</td><td><?= ic_txt('LOX.B5_TYP') ?></td><td><?= ic_txt('LOX.B5_NAME') ?></td>
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
<a class="sm-btn sm-b-technik" href="/plugins/<?= ic_e($ic_plugin) ?>/getpicture.php?hook=false&amp;token=<?= rawurlencode($ic_token) ?>" target="_blank"><?= ic_txt('UI.K_JSON') ?></a>
</div>
<p class="sm-klein"><?= ic_txtf('UI.TECHNIK_TEXT', ic_mono('?hook=false')) ?></p>

<h3 class="sm-h3"><?= ic_txt('UI.H_AUSLOESEN') ?></h3>
<p class="sm-klein"><?= ic_txt('UI.AUSLOESEN_TEXT') ?></p>
<div class="sm-knopfreihe">
<a class="sm-btn sm-b-aktion" href="/plugins/<?= ic_e($ic_plugin) ?>/getpicture.php?trigger=test&amp;token=<?= rawurlencode($ic_token) ?>" target="_blank"><?= ic_txt('UI.K_BILD') ?></a>
<a class="sm-btn sm-b-aktion" href="/plugins/<?= ic_e($ic_plugin) ?>/getvideo.php?s=10&amp;token=<?= rawurlencode($ic_token) ?>" target="_blank"><?= ic_txt('UI.K_VIDEO') ?></a>
<form method="post" action="index.php">
<?= ic_formularfelder('test') ?>
<button type="submit" data-role="none" class="sm-btn sm-b-aktion" name="tat" value="timelapse"><?= ic_txt('UI.K_TL') ?></button>
</form>
<form method="post" action="index.php">
<?= ic_formularfelder('test') ?>
<button type="submit" data-role="none" class="sm-btn sm-b-aktion" name="tat" value="aufraeumen_probe"><?= ic_txt('UI.K_CU_PROBE') ?></button>
</form>
<form method="post" action="index.php">
<?= ic_formularfelder('test') ?>
<button type="submit" data-role="none" class="sm-btn sm-b-aktion" name="tat" value="aufraeumen"
        onclick="return confirm(this.getAttribute('data-frage'));"
        data-frage="<?= ic_txt('UI.CU_FRAGE') ?>"><?= ic_txt('UI.K_CU') ?></button>
</form>
</div>

<h3 class="sm-h3"><?= ic_txt('UI.H_BILDLINK') ?></h3>
<p class="sm-klein"><?= ic_txt('UI.BILDLINK_TEXT') ?></p>
<div class="sm-knopfreihe">
<form method="post" action="index.php">
<?= ic_formularfelder('test') ?>
<input type="hidden" name="link_stunden" value="24">
<button type="submit" data-role="none" class="sm-btn sm-b-technik" name="tat" value="bildlink"><?= ic_txt('UI.K_BILDLINK') ?></button>
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

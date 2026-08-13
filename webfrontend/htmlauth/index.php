<?php
/**
 * Intercom - Bedienoberflaeche
 *
 * Eine Seite mit fuenf Reitern statt fuenf Einzelseiten:
 * Einstellungen | Einbindung in Loxone | Archiv | Test | Protokoll
 *
 * Alle Variablen tragen das Praefix ic_, weil LBWeb::lbheader() eigene
 * globale Variablen setzt und es sonst zu Namenskollisionen kommt.
 *
 * (c) Intercom LoxBerry Plugin Authors - MIT-Lizenz
 * Fortfuehrung von bladerb/intercom22lox, siehe NOTICE.
 */

require_once "config.php";

$L = LBSystem::readlanguage("language.ini");

$ic_titel   = ic_titel();
$ic_hilfe   = "https://github.com/timanders22/LoxBerry-Plugin-Intercom/";
$ic_hilfetp = "help.html";

require_once "menu.php";
$navbar[1]['active'] = True;
LBWeb::lbheader($ic_titel, $ic_hilfe, $ic_hilfetp);

$ic_datei   = ic_paths()['config'] . '/data.json';
// ic_host() beschraenkt HTTP_HOST auf unbedenkliche Zeichen - der Wert
// kommt aus der Host-Kopfzeile und ist damit fremdbestimmt.
$ic_host    = ic_host();
// Der Ordnername wird ermittelt, nicht eingetragen: bei einer
// Zweitinstallation heisst er intercom_01.
$ic_plugin  = ic_plugin_ordner();
$ic_log     = '';
$ic_meldung = '';


/* Den LoxBerry-Wurzelordner ohne festen Systempfad bestimmen.
 *
 * Vom eigenen Ablageort aufwaerts, bis ein Verzeichnis gefunden ist, das
 * config/plugins UND webfrontend enthaelt. Das trifft die uebliche
 * Installation genauso wie eine an einem anderen Ort - und es trifft auch
 * den Fall, dass das Plugin noch als entpacktes Archiv daliegt (dann findet
 * es nichts und gibt einen Leerstring zurueck, was der Aufrufer ohnehin
 * abfangen muss).
 *
 * Der Name traegt kein Plugin-Kuerzel und ist deshalb abgesichert: zwei
 * Bibliotheken landen nie im selben Prozess, aber die Pruefung kostet nichts.
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

function ic_e($wert) { return htmlspecialchars((string) $wert, ENT_QUOTES, 'UTF-8'); }

/** Felder, die gespeichert werden duerfen. Alles andere wird verworfen. */
$ic_felder_text = array('intercomip', 'storage_path', 'timelapse_time', 'tv_ip', 'tv_port',
                        'ai_url', 'ai_minconf', 'cleanup_days', 'cleanup_count',
                        // mqtt_* wohnen seit 2.1.8 im eigenen Reiter mit eigenem Formular
                        'webhook1', 'webhook2', 'webhook3', 'webhook4',
                        'videowebhook1', 'videowebhook2');
$ic_felder_mqtt = array('mqtt_server', 'mqtt_port', 'mqtt_user', 'mqtt_password');
$ic_felder_haken = array('timestamp_image', 'timestamp_video', 'timelapse_enable',
                         'timelapse_video', 'tv_enable', 'ai_enable');

$ic_cfg = file_exists($ic_datei) ? (json_decode(file_get_contents($ic_datei), true) ?: array()) : array();
if (!is_array($ic_cfg)) { $ic_cfg = array(); }

/*
 * Nur das Token neu erzeugen - OHNE die uebrigen Felder anzufassen.
 *
 * Dieses Formular schickt ausschliesslich token_neu mit. Liefe es durch
 * die Feldschleife weiter unten, waeren dort alle Textfelder leer, und die
 * Schleife schriebe genau diese Leere in die Konfiguration: IP der
 * Tuerstation, Webhooks, KI-Adresse - alles weg, nur weil jemand ein neues
 * Token wollte.
 */
if (isset($_POST['token_neu']) && !isset($_POST['intercomip'])) {
    $ic_neu = $ic_cfg;
    try {
        $ic_neu['aktionstoken'] = ic_token_neu();
        $ic_js = json_encode($ic_neu, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                                      | JSON_UNESCAPED_SLASHES);
        if ($ic_js !== false && ic_datei_ersetzen($ic_datei, $ic_js, 0600)) {
            $ic_cfg = $ic_neu;
            // Die Tatsache gehoert ins Protokoll, der Wert nie - ein Token im
            // Log waere ein Token auf Platte.
            ic_log('Neues Zugriffstoken erzeugt. Alle Adressen im Miniserver muessen '
                . 'jetzt nachgezogen werden.');
            $ic_meldung = 'Neues Zugriffstoken erzeugt. Die Adressen im Miniserver '
                        . 'muessen angepasst werden.';
        } else {
            $ic_meldung = 'FEHLER: Das neue Token konnte nicht gespeichert werden.';
        }
    } catch (RuntimeException $e) {
        $ic_meldung = 'FEHLER: Auf diesem System ist keine sichere Zufallsquelle '
                    . 'verfuegbar - es wurde bewusst KEIN Token erzeugt.';
    }
} elseif (isset($_POST['mqtt_speichern'])) {
    // MQTT speichern (eigener Reiter seit 2.1.8, Hausstandard)
    $ic_neu = $ic_cfg;
    foreach ($ic_felder_mqtt as $ic_k) {
        $ic_w = isset($_POST[$ic_k]) ? (string) $_POST[$ic_k] : '';
        $ic_neu[$ic_k] = trim(preg_replace('/[\x00-\x1F\x7F"\']/', '', $ic_w));
    }
    $ic_neu['mqtt_enable']   = isset($_POST['mqtt_enable']) ? '1' : '0';
    $ic_neu['mqtt_uselocal'] = isset($_POST['mqtt_uselocal']) ? '1' : '0';
    $ic_js = json_encode($ic_neu, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES);
    if ($ic_js !== false && ic_datei_ersetzen($ic_datei, $ic_js, 0600)) {
        $ic_cfg = $ic_neu;
        // Zweitschrift wie im Einstellungs-Zweig - sie ueberlebt ein Update,
        // bei dem die Sicherungskette reisst.
        $ic_zweit = dirname(ic_paths()['config']) . '/' . basename(ic_paths()['config']) . '.backup.json';
        if (!ic_datei_ersetzen($ic_zweit, $ic_js, 0600)) {
            ic_log('WARNUNG: Die Zweitschrift liess sich nicht schreiben: ' . $ic_zweit);
        }
        $ic_meldung = 'Gespeichert.';
    } else {
        $ic_meldung = 'FEHLER: Die Konfiguration konnte nicht gespeichert werden.';
    }
} elseif (isset($_POST['speichern'])) {
    $ic_neu = $ic_cfg;
    foreach ($ic_felder_text as $ic_k) {
        // Nur Steuerzeichen und Anfuehrungszeichen entfernen - niemals Doppelpunkt,
        // Schraegstrich oder Punkt, sonst wird aus einer eingefuegten Adresse Buchstabensalat.
        $ic_w = isset($_POST[$ic_k]) ? (string) $_POST[$ic_k] : '';
        $ic_neu[$ic_k] = trim(preg_replace('/[\x00-\x1F\x7F"\']/', '', $ic_w));
    }
    foreach ($ic_felder_haken as $ic_k) {
        $ic_neu[$ic_k] = isset($_POST[$ic_k]) ? 'on' : '';
    }
    // mqtt_enable/mqtt_uselocal wohnen im MQTT-Reiter (eigenes Formular) -
    // hier nicht anfassen, sonst stellte jedes Speichern sie auf 0.

    // Ist noch kein Token da, eines erzeugen und behalten. Ein neues auf
    // Wunsch macht der Zweig weiter oben - hier wird ein vorhandenes
    // NIEMALS ersetzt, sonst waeren nach jedem Speichern alle Adressen im
    // Miniserver ungueltig.
    if (empty($ic_neu['aktionstoken'])) {
        try {
            $ic_neu['aktionstoken'] = ic_token_neu();
        } catch (RuntimeException $e) {
            // Lieber gar kein Token als ein erratbares: die Endpunkte
            // weisen dann konsequent alles ab.
            $ic_neu['aktionstoken'] = '';
            $ic_meldung = 'FEHLER: Es liess sich kein sicheres Token erzeugen ('
                        . ic_e($e->getMessage()) . '). Die Endpunkte bleiben gesperrt.';
        }
    }

    // json_encode liefert bei ungueltigem UTF-8 FALSE, und
    // file_put_contents($pfad, false) schreibt 0 Bytes - und gibt 0
    // zurueck, nicht false. Die alte Pruefung haette das fuer einen Erfolg
    // gehalten und die Konfiguration geleert. Deshalb wird die Kodierung
    // vorher geprueft und danach unteilbar geschrieben.
    $ic_js = json_encode($ic_neu, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                                  | JSON_UNESCAPED_SLASHES);
    if ($ic_js !== false && ic_datei_ersetzen($ic_datei, $ic_js, 0600)) {
        $ic_cfg = $ic_neu;
        /* Zweitschrift NEBEN den Ordner - nicht hinein.
         *
         * Beim Update kopiert der Installer den Inhalt von config/ aus dem
         * Archiv ueber config/plugins/intercom/, und dort liegt ein leeres
         * data.json. Bisher haing alles daran, dass preupgrade.sh gesichert
         * UND postupgrade.sh zurueckgestellt hat; reisst diese Kette an
         * einer Stelle, sind saemtliche Einstellungen weg, ohne dass
         * irgendwo ein Fehler steht. Diese Zweitschrift liegt AUSSERHALB
         * des ueberschriebenen Ordners und wird von postinstall.sh
         * zurueckgespielt, wenn die eigentliche Datei leer ist - sie
         * ueberlebt also auch ein Update, bei dem die Kette reisst.
         *
         * 0600, weil hier Zugangsdaten und das Zugriffstoken stehen. */
        $ic_zweit = dirname(ic_paths()['config']) . '/' . basename(ic_paths()['config']) . '.backup.json';
        if (ic_datei_ersetzen($ic_zweit, $ic_js, 0600)) {
            ic_log('Zweitschrift der Einstellungen geschrieben.');
        } else {
            ic_log('WARNUNG: Die Zweitschrift liess sich nicht schreiben: ' . $ic_zweit);
        }
        ic_log('Einstellungen gespeichert.');
        if ($ic_meldung === '') {
            $ic_meldung = 'Die Einstellungen wurden gespeichert.';
        }
    } else {
        ic_log('Die Einstellungen liessen sich NICHT schreiben: ' . $ic_datei);
        $ic_meldung = 'FEHLER: Die Einstellungen konnten nicht geschrieben werden.';
    }
}

// Voreinstellungen fuer noch nie gespeicherte Felder
$ic_cfg += array('intercomip' => '', 'storage_path' => '', 'timelapse_time' => '12:00',
    'tv_ip' => '', 'tv_port' => '', 'ai_url' => '', 'ai_minconf' => '50',
    'cleanup_days' => '90', 'cleanup_count' => '', 'mqtt_enable' => '0',
    'mqtt_uselocal' => '1', 'mqtt_server' => '', 'mqtt_port' => '1883',
    'mqtt_user' => '', 'mqtt_password' => '', 'webhook1' => '', 'webhook2' => '',
    'webhook3' => '', 'webhook4' => '', 'videowebhook1' => '', 'videowebhook2' => '',
    'timestamp_image' => '', 'timestamp_video' => '', 'timelapse_enable' => '',
    'timelapse_video' => '', 'tv_enable' => '', 'ai_enable' => '',
    'aktionstoken' => '');

// Beim ersten Oeffnen ein Token erzeugen. Ohne Token weisen die Endpunkte
// jeden Aufruf ab - das Plugin waere sonst unbenutzbar.
if ((string) $ic_cfg['aktionstoken'] === '' && !isset($_POST['speichern'])) {
    try {
        $ic_cfg['aktionstoken'] = ic_token_neu();
        $ic_js = json_encode($ic_cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                                      | JSON_UNESCAPED_SLASHES);
        if ($ic_js === false || !ic_datei_ersetzen($ic_datei, $ic_js, 0600)) {
            $ic_meldung = 'FEHLER: Das Zugriffstoken konnte nicht gespeichert werden - '
                        . 'Schreibrechte auf ' . ic_e($ic_datei) . ' pruefen.';
        }
    } catch (RuntimeException $e) {
        $ic_meldung = 'FEHLER: Auf diesem System ist keine sichere Zufallsquelle '
                    . 'verfuegbar. Es wurde bewusst KEIN Token erzeugt - die '
                    . 'Endpunkte bleiben gesperrt.';
    }
}

/** Der Tokenanhang fuer eine Adresse - immer ueber diese Funktion. */
$ic_token = (string) $ic_cfg['aktionstoken'];
function ic_tok($erstes = false)
{
    global $ic_token;
    return ($erstes ? '?' : '&amp;') . 'token=' . rawurlencode($ic_token !== '' ? $ic_token : 'TOKEN');
}

function ic_haken($cfg, $k) { return (isset($cfg[$k]) && $cfg[$k] === 'on') ? ' checked' : ''; }

// Archivstand ermitteln
$ic_bilder = glob($folder_img_archive . '*.jpg') ?: array();
$ic_videos = glob($folder_video_archive . '*') ?: array();
rsort($ic_bilder);
rsort($ic_videos);

// Protokoll
$ic_logdatei = '';
// Der Name der Protokolldatei folgt dem Ordner; bis 1.6.0 hiess sie
// intercom22lox.log. Beide werden gesucht, damit ein alter Bestand nach
// der Umbenennung nicht unsichtbar wird.
$ic_kandidaten = array();
foreach (array($ic_plugin . '.log', 'intercom22lox.log') as $ic_dn) {
    if (defined('LBPLOGDIR')) { $ic_kandidaten[] = LBPLOGDIR . '/' . $ic_dn; }
    $ic_kandidaten[] = lb_wurzel_ermitteln() . '/log/plugins/' . $ic_plugin . '/' . $ic_dn;
}
foreach ($ic_kandidaten as $ic_p) {
    if (@is_file($ic_p)) { $ic_logdatei = $ic_p; break; }
}
if ($ic_logdatei !== '') {
    $ic_zeilen = @file($ic_logdatei);
    if (is_array($ic_zeilen)) { $ic_log = implode('', array_slice($ic_zeilen, -200)); }
}
?>
<?php require_once __DIR__ . "/ic_stil.php"; ?>

<div class="icw">
<h1><?= $L['UI.INTERCOM'] ?></h1>
<p><?= $L['UI.HOLT_DAS_KAMERABILD_DER_LOXONE_INT'] ?></p>

<?php if ($ic_meldung !== '') { ?>
<div class="ic-hinweis<?= strpos($ic_meldung, 'FEHLER') === 0 ? ' ic-warn' : '' ?>"><?= ic_e($ic_meldung) ?></div>
<?php } ?>
<?php if (trim((string) $ic_cfg['intercomip']) === '') { ?>
<div class="ic-hinweis ic-warn"><b><?= $L['UI.NOCH_NICHT_EINGERICHTET'] ?></b><?= $L['UI.TRAGEN_SIE_IM_REITER'] ?><b><?= $L['UI.EINSTELLUNGEN'] ?></b><?= $L['UI.DIE_ADRESSE_DER_INTERCOM_EIN_OHNE'] ?></div>
<?php } ?>

<div class="ic-reiter">
    <div class="aktiv" data-seite="tab-settings"><?= $L['UI.EINSTELLUNGEN_2'] ?></div>
    <div data-seite="tab-mqtt">MQTT</div>
    <div data-seite="tab-loxone"><?= $L['UI.EINBINDUNG_IN_LOXONE'] ?></div>
    <div data-seite="tab-archiv"><?= $L['UI.ARCHIV'] ?></div>
    <div data-seite="tab-test"><?= $L['UI.TEST'] ?></div>
    <div data-seite="tab-log"><?= $L['UI.PROTOKOLL'] ?></div>
</div>

<!-- ===================== Einstellungen ===================== -->
<div class="ic-seite aktiv" id="tab-settings">
<form method="post" action="index.php">

<h2><?= $L['UI.INTERCOM_2'] ?></h2>
<label><?= $L['UI.ADRESSE_DER_INTERCOM'] ?></label>
<input type="text" data-role="none" name="intercomip" value="<?= ic_e($ic_cfg['intercomip']) ?>" placeholder="192.168.1.50">
<p class="ic-klein"><?= $L['UI.IP_ADRESSE_DER_INTERCOM_BEI_BEDARF'] ?><span class="ic-mono">192.168.1.50:8080</span><?= $L['UI.BENUTZERNAME_UND_PASSWORT_HOLT_SIC'] ?></p>

<h2><?= $L['UI.SPEICHERORT'] ?></h2>
<label><?= $L['UI.EIGENER_SPEICHERPFAD_OPTIONAL'] ?></label>
<input type="text" data-role="none" name="storage_path" value="<?= ic_e($ic_cfg['storage_path']) ?>" placeholder="/media/usbstick">
<p class="ic-klein"><?= $L['UI.LEER_LASSEN_DANN_LIEGT_ALLES_AUF_D'] ?></p>

<h2><?= $L['UI.AUFBEWAHRUNG'] ?></h2>
<label><?= $L['UI.AUFNAHMEN_LOESCHEN_NACH_TAGEN'] ?></label>
<input type="number" data-role="none" name="cleanup_days" min="0" max="3650" value="<?= ic_e($ic_cfg['cleanup_days']) ?>">
<p class="ic-klein"><?= $L['UI.0_BEDEUTET_NIE_AUTOMATISCH_LOESCHE'] ?></p>
<label><?= $L['UI.HOECHSTZAHL_AUFBEWAHRTER_AUFNAHMEN'] ?></label>
<input type="number" data-role="none" name="cleanup_count" min="0" value="<?= ic_e($ic_cfg['cleanup_count']) ?>">
<p class="ic-klein"><?= $L['UI.ZUSAETZLICHE_OBERGRENZE_SIND_MEHR'] ?></p>

<h2><?= $L['UI.ZEITSTEMPEL_IM_BILD'] ?></h2>
<label><input type="checkbox" data-role="none" name="timestamp_image"<?= ic_haken($ic_cfg, 'timestamp_image') ?>><?= $L['UI.DATUM_UND_UHRZEIT_IN_ARCHIVIERTE_B'] ?></label>
<label><input type="checkbox" data-role="none" name="timestamp_video"<?= ic_haken($ic_cfg, 'timestamp_video') ?>><?= $L['UI.DATUM_UND_UHRZEIT_IN_VIDEOS_SCHREI'] ?></label>
<p class="ic-klein"><?= $L['UI.DER_STEMPEL_WIRD_LINKS_OBEN_INS_BI'] ?><span class="ic-mono"><?= $L['UI.PHP_GD'] ?></span><?= $L['UI.BENOETIGT_FEHLT_ES_BLEIBT_DAS_BILD'] ?></p>

<h2><?= $L['UI.ZEITRAFFER'] ?></h2>
<label><input type="checkbox" data-role="none" name="timelapse_enable"<?= ic_haken($ic_cfg, 'timelapse_enable') ?>><?= $L['UI.TAEGLICH_EIN_ZEITRAFFERBILD_AUFNEH'] ?></label>
<label><?= $L['UI.UHRZEIT'] ?></label>
<input type="text" data-role="none" name="timelapse_time" value="<?= ic_e($ic_cfg['timelapse_time']) ?>" placeholder="12:00">
<label><input type="checkbox" data-role="none" name="timelapse_video"<?= ic_haken($ic_cfg, 'timelapse_video') ?>><?= $L['UI.NACH_JEDER_AUFNAHME_EIN_VIDEO_AUS'] ?></label>
<p class="ic-klein"><?= $L['UI.ERGIBT_UEBER_WOCHEN_EINEN_FILM_DER'] ?><span class="ic-mono"><?= $L['UI.FFMPEG'] ?></span><?= $L['UI.BENOETIGT_NACHINSTALLIEREN_MIT'] ?><span class="ic-mono"><?= $L['UI.SUDO_APT_GET_INSTALL_Y_FFMPEG'] ?></span>).</p>

<h2><?= $L['UI.BILD_AUF_EINEN_BILDSCHIRM_SCHICKEN'] ?></h2>
<label><input type="checkbox" data-role="none" name="tv_enable"<?= ic_haken($ic_cfg, 'tv_enable') ?>><?= $L['UI.BEIM_KLINGELN_DAS_BILD_AN_EIN_ANZE'] ?></label>
<label><?= $L['UI.ADRESSE_DES_GERAETS'] ?></label>
<input type="text" data-role="none" name="tv_ip" value="<?= ic_e($ic_cfg['tv_ip']) ?>">
<label><?= $L['UI.PORT'] ?></label>
<input type="text" data-role="none" name="tv_port" value="<?= ic_e($ic_cfg['tv_port']) ?>">
<p class="ic-klein"><?= $L['UI.GEDACHT_FUER_GERAETE_DIE_BILDER_PE'] ?></p>

<h2><?= $L['UI.OBJEKTERKENNUNG_OPTIONAL'] ?></h2>
<label><input type="checkbox" data-role="none" name="ai_enable"<?= ic_haken($ic_cfg, 'ai_enable') ?>><?= $L['UI.ERKENNEN_WAS_AUF_DEM_BILD_ZU_SEHEN'] ?></label>
<label><?= $L['UI.ADRESSE_DES_ERKENNUNGSDIENSTES'] ?></label>
<input type="text" data-role="none" name="ai_url" value="<?= ic_e($ic_cfg['ai_url']) ?>" placeholder="http://192.168.1.60:32168/v1/vision/detection">
<label><?= $L['UI.MINDESTSICHERHEIT_IN_PROZENT'] ?></label>
<input type="number" data-role="none" name="ai_minconf" min="1" max="99" value="<?= ic_e($ic_cfg['ai_minconf']) ?>">
<p class="ic-klein"><?= $L['UI.DAS_BILD_WIRD_AN_EINEN_SELBST_BETR'] ?></p>

<h2><?= $L['UI.WEBHOOKS'] ?></h2>
<p class="ic-klein"><?= $L['UI.EIN_WEBHOOK_IST_EINE_ADRESSE_DIE_D'] ?></p>
<label><?= $L['UI.NACH_EINEM_BILD_ALS_POST_MIT_JSON'] ?></label>
<input type="text" data-role="none" name="webhook1" value="<?= ic_e($ic_cfg['webhook1']) ?>">
<label><?= $L['UI.NACH_EINEM_BILD_BILDADRESSE_ALS_PA'] ?></label>
<input type="text" data-role="none" name="webhook2" value="<?= ic_e($ic_cfg['webhook2']) ?>">
<label><?= $L['UI.NACH_EINEM_BILD_WEITERER_EMPFAENGE'] ?></label>
<input type="text" data-role="none" name="webhook3" value="<?= ic_e($ic_cfg['webhook3']) ?>">
<label><?= $L['UI.NACH_EINEM_BILD_WEITERER_EMPFAE_2'] ?></label>
<input type="text" data-role="none" name="webhook4" value="<?= ic_e($ic_cfg['webhook4']) ?>">
<label><?= $L['UI.NACH_EINEM_VIDEO_ALS_POST_MIT_JSON'] ?></label>
<input type="text" data-role="none" name="videowebhook1" value="<?= ic_e($ic_cfg['videowebhook1']) ?>">
<label><?= $L['UI.NACH_EINEM_VIDEO_VIDEOADRESSE_ALS'] ?></label>
<input type="text" data-role="none" name="videowebhook2" value="<?= ic_e($ic_cfg['videowebhook2']) ?>">

<div style="margin-top:16px;"><button data-role="none" class="ic-btn" type="submit" name="speichern" value="1"><?= $L['UI.SPEICHERN'] ?></button></div>
</form>
</div>

<!-- ===================== Einbindung in Loxone ===================== -->
<!-- ================= MQTT (eigener Reiter seit 2.1.8, Hausstandard) ================= -->
<div class="ic-seite" id="tab-mqtt">
<form method="post" action="index.php">
<input type="hidden" name="mqtt_speichern" value="1">
<h2><?= $L['UI.MQTT'] ?></h2>
<label><input type="checkbox" data-role="none" name="mqtt_enable"<?= ((string) $ic_cfg['mqtt_enable'] === '1') ? ' checked' : '' ?>><?= $L['UI.MELDUNGEN_PER_MQTT_VERSCHICKEN'] ?></label>
<label><input type="checkbox" data-role="none" name="mqtt_uselocal"<?= ((string) $ic_cfg['mqtt_uselocal'] !== '0') ? ' checked' : '' ?>><?= $L['UI.DEN_MQTT_DIENST_DES_LOXBERRY_VERWE'] ?></label>
<p class="ic-klein"><?= $L['UI.DER_LOXBERRY_BRINGT_EINEN_EIGENEN'] ?></p>
<label><?= $L['UI.EIGENER_BROKER_ADRESSE'] ?></label>
<input type="text" data-role="none" name="mqtt_server" value="<?= ic_e($ic_cfg['mqtt_server']) ?>">
<label><?= $L['UI.PORT_2'] ?></label>
<input type="text" data-role="none" name="mqtt_port" value="<?= ic_e($ic_cfg['mqtt_port']) ?>">
<label><?= $L['UI.BENUTZER'] ?></label>
<input type="text" data-role="none" name="mqtt_user" value="<?= ic_e($ic_cfg['mqtt_user']) ?>">
<label><?= $L['UI.PASSWORT'] ?></label>
<input type="password" data-role="none" name="mqtt_password" value="<?= ic_e($ic_cfg['mqtt_password']) ?>">
<table>
<tr><th><?= $L['UI.THEMA'] ?></th><th><?= $L['UI.WIRD_GESENDET'] ?></th></tr>
<tr><td><span class="ic-mono"><?= $L['UI.INTERCOM_3'] ?></span></td><td><?= $L['UI.NACH_JEDEM_BILD'] ?></td></tr>
<tr><td><span class="ic-mono"><?= $L['UI.INTERCOMVIDEO'] ?></span></td><td><?= $L['UI.NACH_JEDEM_VIDEO'] ?></td></tr>
<tr><td><span class="ic-mono">intercom/trigger/NAME</span></td><td><?= $L['UI.BEI_AUFRUF_MIT'] ?><span class="ic-mono">?trigger=NAME</span></td></tr>
<tr><td><span class="ic-mono">intercom/ai</span></td><td><?= $L['UI.NUR_BEI_EINGESCHALTETER_OBJEKTERKE'] ?></td></tr>
</table>

<div style="margin-top:16px;"><button data-role="none" class="ic-btn" type="submit"><?= $L['UI.SPEICHERN'] ?></button></div>
</form>
</div>

<div class="ic-seite" id="tab-loxone">
<h2><?= $L['UI.SO_KOMMT_DAS_BILD_NACH_LOXONE'] ?></h2>
<p><?= $L['UI.LOXONE_RUFT_EINE_ADRESSE_AUF_DEM_L'] ?><b><?= $L['UI.VIRTUELLER_AUSGANG'] ?></b>.</p>

<div class="ic-schritt"><b><?= $L['UI.SCHRITT_1_VIRTUELLEN_AUSGANG_ANLEG'] ?></b><br><?= $L['UI.IN_LOXONE_CONFIG_EINEN_VIRTUELLEN'] ?><br>
<div class="ic-hinweis"><b><?= $L['UI.ALLE_ADRESSEN_VERLANGEN_DAS_ZUGRIF'] ?></b><br><?= $L['UI.BIS_1_5_0_WAREN_DIE_ENDPUNKTE_UNGE'] ?></div>

<label><?= $L['UI.ZUGRIFFSTOKEN'] ?></label>
<input type="text" data-role="none" value="<?= ic_e($ic_token) ?>" readonly onclick="this.select();">
<div class="ic-legende">
<span><i class="ic-punkt ic-b-aktion"></i><?= $L['UI.LOEST_ETWAS_AUS_SENDET_ODER_VERAEN'] ?></span>
</div>
<div class="ic-knopfreihe">
<form method="post" action="index.php">
  <input type="hidden" data-role="none" name="token_neu" value="1">
  <button type="submit" data-role="none" class="ic-btn ic-b-aktion"
          onclick="return confirm('Neues Token erzeugen? Alle Adressen im Miniserver m\u00fcssen danach angepasst werden.');"><?= $L['UI.NEUES_TOKEN_ERZEUGEN'] ?></button>
</form>
</div>

<span class="ic-mono">http://<?= ic_e($ic_host) ?>/plugins/<?= ic_e($ic_plugin) ?>/getpicture.php<?= ic_tok(true) ?></span>
<p class="ic-klein"><b><?= $L['UI.WICHTIG'] ?></b><?= $L['UI.ZWISCHEN_DEM_KLINGELN_UND_DEM_AUFR'] ?><b><?= $L['UI.3_SEKUNDEN'] ?></b><?= $L['UI.LIEGEN_VORHER_LIEFERT_DIE_INTERCOM'] ?></p>
</div>

<div class="ic-schritt"><b><?= $L['UI.SCHRITT_2_BILD_ANZEIGEN'] ?></b><br><?= $L['UI.DAS_JEWEILS_NEUESTE_BILD_LIEGT_IMM'] ?><br>
<span class="ic-mono">http://<?= ic_e($ic_host) ?>/plugins/<?= ic_e($ic_plugin) ?>/lastpicture.jpg</span>
</div>

<div class="ic-schritt"><b><?= $L['UI.SCHRITT_3_UNTERSCHEIDEN_WER_AUSGEL'] ?></b><br><?= $L['UI.HAENGT_MAN'] ?><span class="ic-mono">?trigger=NAME</span><?= $L['UI.AN_WANDERT_DER_NAME_IN_DEN_DATEINA'] ?><br>
<span class="ic-mono">&hellip;/getpicture.php<?= ic_tok(true) ?>&amp;trigger=klingel</span><br>
<span class="ic-mono">&hellip;/getpicture.php<?= ic_tok(true) ?>&amp;trigger=bewegung</span>
</div>

<div class="ic-schritt"><b><?= $L['UI.SCHRITT_4_VIDEO_STATT_EINZELBILD'] ?></b><br>
<span class="ic-mono">http://<?= ic_e($ic_host) ?>/plugins/<?= ic_e($ic_plugin) ?>/getvideo.php<?= ic_tok(true) ?>&amp;s=15</span>
<div class="ic-klein"><?= $L['UI.DER_PARAMETER_HEISST'] ?><span class="ic-mono">s</span><?= $L['UI.SEKUNDEN_NICHT'] ?><span class="ic-mono"><?= $L['UI.TIME'] ?></span> &ndash; in der Anleitung stand bis 1.5.0 der falsche Name,
weshalb jede Aufnahme die voreingestellten 20&nbsp;Sekunden lang war. Erlaubt sind 1 bis 300.</div>
<p class="ic-klein"><?= $L['UI.NIMMT_15_SEKUNDEN_AUF_HOECHSTENS_1'] ?></p>
</div>

<div class="ic-schritt"><b><?= $L['UI.SCHRITT_5_LIVEBILD_OHNE_PASSWORT'] ?></b><br>
<span class="ic-mono">http://<?= ic_e($ic_host) ?>/plugins/<?= ic_e($ic_plugin) ?>/mjpgproxy.php<?= ic_tok(true) ?></span>
<p class="ic-klein"><?= $L['UI.DER_LOXBERRY_MELDET_SICH_BEI_DER_I'] ?></p>
</div>
</div>

<!-- ===================== Archiv ===================== -->
<div class="ic-seite" id="tab-archiv">
<h2><?= $L['UI.GESPEICHERTE_AUFNAHMEN'] ?></h2>
<p><?= $L['UI.GESPEICHERT_SIND_DERZEIT'] ?><b><?= count($ic_bilder) ?></b><?= $L['UI.BILDER_UND'] ?><b><?= count($ic_videos) ?></b> Videos.
Aufbewahrt wird <?= ((int) $ic_cfg['cleanup_days'] === 0) ? 'unbegrenzt' : ((int) $ic_cfg['cleanup_days']) . ' Tage lang' ?>.</p>

<div class="ic-knopfreihe">
<a class="ic-btn ic-b-lesen" href="live.php"><?= $L['UI.LIVEBILD_ANSEHEN'] ?></a>
<a class="ic-btn ic-b-lesen" href="archive.php"><?= $L['UI.BILDER_ARCHIV_OEFFNEN'] ?></a>
<a class="ic-btn ic-b-lesen" href="videoarchive.php"><?= $L['UI.VIDEO_ARCHIV_OEFFNEN'] ?></a>
</div>

<?php if ($ic_bilder) { ?>
<h2><?= $L['UI.DIE_NEUESTEN_BILDER'] ?></h2>
<div class="ic-gal">
<?php foreach (array_slice($ic_bilder, 0, 8) as $ic_f) { $ic_n = basename($ic_f); ?>
<figure>
    <img src="/legacy/<?= rawurlencode(ic_plugin_ordner()) ?>_data/img_archive/<?= rawurlencode($ic_n) ?>" alt="">
    <figcaption><?= ic_e($ic_n) ?></figcaption>
</figure>
<?php } ?>
</div>
<?php } else { ?>
<div class="ic-hinweis"><?= $L['UI.NOCH_KEINE_AUFNAHMEN_VORHANDEN_IM'] ?><b><?= $L['UI.TEST_2'] ?></b><?= $L['UI.LAESST_SICH_SOFORT_EINE_AUSLOESEN'] ?></div>
<?php } ?>
</div>

<!-- ===================== Test ===================== -->
<div class="ic-seite" id="tab-test">
<h2><?= $L['UI.TEST_3'] ?></h2>
<div class="ic-legende">
<span><i class="ic-punkt ic-b-lesen"></i><?= $L['UI.ANSEHEN_FRAGT_NUR_AB_VERAENDERT_NI'] ?></span>
<span><i class="ic-punkt ic-b-technik"></i><?= $L['UI.TECHNISCHE_AUSKUNFT_FUER_DIE_FEHLE'] ?></span>
<span><i class="ic-punkt ic-b-aktion"></i><?= $L['UI.LOEST_ETWAS_AUS_NIMMT_AUF_ODER_VER'] ?></span>
</div>

<h3 class="ic-h3"><?= $L['UI.ANSEHEN'] ?></h3>
<div class="ic-knopfreihe">
<a class="ic-btn ic-b-lesen" href="/plugins/<?= ic_e($ic_plugin) ?>/lastpicture.jpg" target="_blank"><?= $L['UI.LETZTES_BILD_OEFFNEN'] ?></a>
<a class="ic-btn ic-b-lesen" href="/plugins/<?= ic_e($ic_plugin) ?>/mjpgproxy.php<?= ic_tok(true) ?>" target="_blank"><?= $L['UI.LIVEBILD_OEFFNEN'] ?></a>
</div>

<h3 class="ic-h3"><?= $L['UI.TECHNISCHE_AUSKUNFT'] ?></h3>
<div class="ic-knopfreihe">
<a class="ic-btn ic-b-technik" href="/plugins/<?= ic_e($ic_plugin) ?>/getpicture.php?hook=false<?= ic_tok() ?>" target="_blank"><?= $L['UI.BILDABRUF_PRUEFEN_JSON'] ?></a>
</div>
<p class="ic-klein"><?= $L['UI.HOLT_EIN_BILD_UND_ZEIGT_DIE_ANTWOR'] ?><span class="ic-mono">?hook=false</span><?= $L['UI.WIRD_DAS_BILD'] ?><b><?= $L['UI.NICHT'] ?></b><?= $L['UI.INS_ARCHIV_GELEGT'] ?></p>

<h3 class="ic-h3"><?= $L['UI.LOEST_ETWAS_AUS'] ?></h3>
<div class="ic-knopfreihe">
<a class="ic-btn ic-b-aktion" href="/plugins/<?= ic_e($ic_plugin) ?>/getpicture.php?trigger=test<?= ic_tok() ?>" target="_blank"><?= $L['UI.JETZT_EIN_BILD_AUFNEHMEN'] ?></a>
<a class="ic-btn ic-b-aktion" href="/plugins/<?= ic_e($ic_plugin) ?>/getvideo.php?s=10<?= ic_tok() ?>" target="_blank"><?= $L['UI.10_SEKUNDEN_VIDEO_AUFNEHMEN'] ?></a>
<a class="ic-btn ic-b-aktion" href="/plugins/<?= ic_e($ic_plugin) ?>/timelapse.php" target="_blank"><?= $L['UI.ZEITRAFFERBILD_AUFNEHMEN'] ?></a>
<a class="ic-btn ic-b-aktion" href="/plugins/<?= ic_e($ic_plugin) ?>/cleanup.php" target="_blank"><?= $L['UI.ALTE_AUFNAHMEN_JETZT_AUFRAEUMEN'] ?></a>
</div>
</div>

<!-- ===================== Protokoll ===================== -->
<div class="ic-seite" id="tab-log">
<h2><?= $L['UI.PROTOKOLL_2'] ?></h2>
<div class="ic-hinweis"><b><?= $L['UI.DAS_PROTOKOLL_LIEGT_AUF_EINER_RAMD'] ?></b><?= $L['UI.LOXBERRY_LEGT'] ?><span class="ic-mono">log/</span><?= $L['UI.IM_ARBEITSSPEICHER_AN_DAMIT_DIE_SP'] ?></div>
<p class="ic-klein"><?= $L['UI.PROTOKOLLIERT_WERDEN_GEHOLTE_BILDE'] ?></p>
<?php if ($ic_log !== '') { ?>
<p class="ic-klein"><?= $L['UI.DIE_LETZTEN_200_ZEILEN_AUS'] ?><span class="ic-mono"><?= ic_e($ic_logdatei) ?></span>.</p>
<pre><?= ic_e($ic_log) ?></pre>
<?php } else { ?>
<div class="ic-hinweis"><?= $L['UI.ES_LIEGT_NOCH_KEIN_PROTOKOLL_VOR_S'] ?></div>
<?php } ?>
</div>

</div>

<script>
(function () {
    var reiter = document.querySelectorAll('.icw .ic-reiter div');
    for (var i = 0; i < reiter.length; i++) {
        reiter[i].addEventListener('click', function () {
            var ziel = this.getAttribute('data-seite');
            var alle = document.querySelectorAll('.icw .ic-reiter div');
            for (var j = 0; j < alle.length; j++) { alle[j].classList.remove('aktiv'); }
            this.classList.add('aktiv');
            var seiten = document.querySelectorAll('.icw .ic-seite');
            for (var k = 0; k < seiten.length; k++) { seiten[k].classList.remove('aktiv'); }
            var s = document.getElementById(ziel);
            if (s) { s.classList.add('aktiv'); }
        });
    }
})();
</script>

<?php
echo '</div><!-- /icw -->';
LBWeb::lbfooter();
?>

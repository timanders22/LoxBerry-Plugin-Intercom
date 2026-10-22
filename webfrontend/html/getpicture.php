<?php
/**
 * Intercom - ein Bild von der Tuerstation holen
 *
 * Aufruf (vom Miniserver):
 *   /plugins/<ordner>/getpicture.php?token=<TOKEN>
 *   /plugins/<ordner>/getpicture.php?token=<TOKEN>&trigger=klingel
 *   /plugins/<ordner>/getpicture.php?token=<TOKEN>&station=2
 *   /plugins/<ordner>/getpicture.php?token=<TOKEN>&selftest=1   (loest nichts aus)
 *
 * SEIT 2.2.23 (Gruppe 7, Entwurf 3.4, E5) steht die Arbeit in ic_foto_ausloesen() (ic_lib.php) - derselbe Weg fuer
 * diesen Endpunkt und fuer die Befehle ueber MQTT (Befehlsbruecke -> Auftragsprozess). Hier bleiben nur Token,
 * Selbsttest, Station und Ausloeser aus der Adresse und die Antwort an Loxone (Kopfzeilen, Content-Length,
 * Connection: close, wie seit 2.2.13). Die Objekterkennung laeuft seither NACH der Antwort; das Feld 'ai' der
 * Antwort ist eine leere Liste, MQTT und Webhooks tragen die Objekte.
 */

require_once __DIR__ . '/ic_start.php';

// Diese Datei loest etwas aus (Archivbild, MQTT, Webhooks) und liest den
// Kamerastrom mit. Bis 1.5.0 ging das ohne jede Pruefung - sie liegt im
// unangemeldeten Bereich.
ic_token_pruefen();

// Der Selbsttest steht VOR allem, was wirkt: er soll nichts ausloesen.
if (isset($_GET['selftest'])) {
    ic_selftest_antwort('getpicture.php');
}

header('Content-Type: application/json; charset=utf-8');

/* ---------------- Station ---------------- */
$ic_stationsangabe = isset($_GET['station']) && is_string($_GET['station'])
                   ? substr($_GET['station'], 0, 32) : '';
$station = ic_station($ic_stationsangabe);
if ($station === null) {
    // Abweisen, nicht zurechtbiegen: waere hier die erste Station genommen
    // worden, haette ein Tippfehler in Loxone stillschweigend die falsche
    // Tuer fotografiert.
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(array('success' => false, 'error' => ic_stationen()
        ? 'Unbekannte Station: ' . $ic_stationsangabe
        : 'Es ist keine Tuerstation eingerichtet. Bitte im Reiter Einstellungen eintragen.'));
    exit;
}

/* ---------------- Ausloeser ---------------- */
// is_string VOR allem anderen: ?trigger[]=x ergibt ein Feld, und substr()
// bricht damit unter PHP 8 mit einem TypeError ab.
$trigger = '';
if (isset($_GET['trigger']) && is_string($_GET['trigger'])) {
    $trigger = preg_replace('/[^A-Za-z0-9_\-]/', '', substr($_GET['trigger'], 0, 32));
}

$nur_vorschau = isset($_GET['hook']);   // ?hook=false: die Oberflaeche schaut nur nach

/* ---------------- Die Antwort an Loxone ----------------
 * $nacharbeit: danach geht die Arbeit weiter (Anzeigegeraet, MQTT, Meldungen, Webhooks). Dann sagen Content-Length
 * und "Connection: close" dem Aufrufer, dass er nach dem JSON fertig ist (M6, seit 2.2.13 - mod_php hielt die
 * Verbindung sonst bis zum Skriptende offen), und ignore_user_abort(true) laesst die Nacharbeit weiterlaufen. */
$ic_antwort = function ($code, $json, $nacharbeit) {
    if ((int) $code !== 200) {
        header(ic_status_zeile((int) $code));
    }
    if ($nacharbeit) {
        @ignore_user_abort(true);
        @ini_set('zlib.output_compression', '0');
        header('Content-Length: ' . strlen($json));
        header('Connection: close');
    }
    echo $json;
    if ($nacharbeit) {
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } else {
            while (ob_get_level() > 0) { @ob_end_flush(); }
            @flush();
        }
    }
};

ic_foto_ausloesen($station, $trigger, $nur_vorschau, $ic_antwort);

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
 * Bis 1.5.0 setzte diese Datei $_SERVER['HTTP_HOST'] unmaskiert in eine Befehlszeile ein, die an die Shell ging -
 * aus dem unangemeldeten Bereich, ohne Token: wer den LoxBerry ueber HTTP erreichte, hatte eine Befehlszeile darauf.
 * Seither: Aufrufe an den eigenen Rechner ueber 127.0.0.1 (ic_eigene_basis()), JEDER Wert an die Shell durch
 * escapeshellarg(), und der Aufruf verlangt das Zugriffstoken. 2.2.0: kein behaupteter Erfolg mehr. 2.2.13: C2
 * (Sperre ueber die Laufzeit von ffmpeg, Marke), C3 (Rueckgabewert von ffmpeg), C4 (kein Token auf der Befehlszeile).
 *
 * SEIT 2.2.23 (Gruppe 7, Entwurf 3.4, E5) steht die Arbeit in ic_video_starten() (ic_lib.php) - derselbe Weg fuer
 * diesen Endpunkt und fuer den Befehl video ueber MQTT (Befehlsbruecke -> Auftragsprozess). Hier bleiben Token,
 * Selbsttest, Station, die Pruefung der Dauer und die Antwort.
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

/* ---------------- Dauer ----------------
 * Ganze Zahl, 1 bis 300 Sekunden - ABGEWIESEN, nicht still zurechtgebogen (seit 2.2.6). */
$seconds = 20;
if (isset($_GET['s'])) {
    if (!is_string($_GET['s']) || !is_numeric($_GET['s'])
        || (int) $_GET['s'] < 1 || (int) $_GET['s'] > 300) {
        ic_log_gebremst('dauer', 'Eine Videoaufzeichnung wurde mit einer unzulaessigen '
            . 'Dauer angefordert (Absender ' . ic_absender() . ') - abgewiesen.');
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(array('success' => false,
            'error' => 'Die Dauer muss eine ganze Zahl von 1 bis 300 Sekunden sein.'));
        exit;
    }
    $seconds = (int) $_GET['s'];
}

$ic_r = ic_video_starten($station, $seconds);
if ($ic_r['code'] !== 200) {
    header(ic_status_zeile($ic_r['code']));
}
echo $ic_r['json'];

<?php
/**
 * Intercom - einzelne Archivdatei loeschen
 *
 * Aufruf (POST, aus den Galerien):
 *   ajax.php   merkmal=<Merkmal>&art=bild|video|timelapse&datei=<Name>
 *
 * Bis 2.1.13 loeschte diese Datei auf einen blossen GET hin, allein nach
 * $_REQUEST['f'], ohne Merkmal, ohne Pruefung der Endung, ohne Rueckmeldung
 * im Fehlerfall - und ohne Protokollzeile. Der angemeldete Bereich schuetzt
 * gegen Fremde, nicht gegen fremd ausgeloeste Aufrufe.
 */

require_once "config.php";

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(array('success' => false, 'error' => 'Nur POST.'));
    exit;
}
if (!ic_merkmal_gueltig()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(array('success' => false, 'error' => 'Formularmerkmal fehlt oder stimmt nicht.'));
    exit;
}

$art = (isset($_POST['art']) && is_string($_POST['art'])) ? $_POST['art'] : '';
$o = ic_archivordner();
if (!isset($o[$art === 'video' ? 'video' : ($art === 'timelapse' ? 'timelapse' : 'bild')])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(array('success' => false, 'error' => 'Unbekannte Art.'));
    exit;
}
$ordner = $art === 'video' ? $o['video'] : ($art === 'timelapse' ? $o['timelapse'] : $o['bild']);

/* Der Dateiname wird GEPRUEFT, nicht uebernommen: basename() gegen jeden
 * Pfadanteil, ein Muster gegen alles, was das Plugin nicht selbst vergibt,
 * und zuletzt muss die Datei im Archiv wirklich liegen. */
$roh = (isset($_POST['datei']) && is_string($_POST['datei'])) ? $_POST['datei'] : '';
$datei = basename($roh);
if ($datei === '' || !preg_match('/^[A-Za-z0-9._-]{1,128}\.(jpg|avi|mp4)$/', $datei)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(array('success' => false, 'error' => 'Ungueltiger Dateiname.'));
    exit;
}
if (!@is_file($ordner . $datei)) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(array('success' => false, 'error' => 'Datei nicht im Archiv.'));
    exit;
}

$geloescht = array();
if (@unlink($ordner . $datei)) { $geloescht[] = $datei; }
// Zu einem Video gehoert das Vorschaubild - bis 2.1.13 blieb jeweils das
// andere Stueck liegen.
if ($art === 'video') {
    $tn = preg_replace('/\.avi$/', '.jpg', $datei);
    if ($tn !== $datei && @is_file($ordner . $tn) && @unlink($ordner . $tn)) {
        $geloescht[] = $tn;
    }
}

if (!$geloescht) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(array('success' => false,
        'error' => 'Die Datei liess sich nicht loeschen - Rechte pruefen.'));
    exit;
}

ic_log('Aus dem Archiv geloescht: ' . implode(', ', $geloescht));
echo json_encode(array('success' => true, 'deleted' => $geloescht));

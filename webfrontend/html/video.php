<?php
/**
 * Intercom - ein Video ueber einen befristeten Link ausliefern (NEU 2.2.23, I10, E11)
 *
 * Aufruf: /plugins/<ordner>/video.php?link=<code>
 *
 * WARUM ES DIESE DATEI GIBT
 * -------------------------
 * Mit eingeschaltetem Archivschutz verlangt /legacy/ die LoxBerry-Anmeldung. Die Videoadresse, die das Plugin in
 * <praefix>/video, an Video-Webhook 1 und an Video-Webhook 2 schickt, antwortete damit jedem Abnehmer ohne Anmeldung
 * 401 (Pruefbericht I10). Nur bei eingeschaltetem Schutz geht deshalb statt der /legacy/-Adresse ein Link auf DIESE
 * Datei hinaus: an genau EINE Datei im Videoarchiv gebunden, 24 Stunden, fuenf Abrufe (ic_videolink_erzeugen()).
 * Ohne Schutz bleibt die /legacy/-Adresse - bestehende Abnehmer merken nichts.
 *
 * bild.php wurde dafuer NICHT erweitert: es liefert zugesichert nur .jpg (:88). Diese Datei liefert nur .avi aus dem
 * Videoarchiv, ohne Kopie und ohne Range (AVI/mpeg4 spielt kein Browser ab; die Abnehmer laden herunter), in
 * 1-MB-Stuecken, und nennt nie einen Pfad (I6). Jeder weitere Parameter wird abgewiesen (403, wie bild.php :71-76).
 */

require_once __DIR__ . '/ic_start.php';

foreach (array_keys($_GET) as $ic_k) {
    if ($ic_k !== 'link') {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: text/plain; charset=utf-8');
        echo "Dieser Link gilt nur fuer ein einzelnes Video.\n";
        exit;
    }
}
$ic_code = isset($_GET['link']) && is_string($_GET['link']) ? $_GET['link'] : '';
if (!preg_match('/^[A-Za-z0-9]{10,40}\z/', $ic_code)) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Ungueltiger Link.\n";
    exit;
}
$ic_datei = ic_videolink_einloesen($ic_code);
if ($ic_datei === false) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Dieser Link ist abgelaufen oder verbraucht.\n";
    exit;
}
if ($ic_datei === '') {
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Das Video zu diesem Link liegt nicht mehr vor.\n";
    exit;
}
$ic_fh = @fopen($ic_datei, 'rb');
$ic_groesse = $ic_fh !== false ? @filesize($ic_datei) : false;
if ($ic_fh === false || $ic_groesse === false) {
    // I6: der Pfad steht nur im Protokoll, nie in der Antwort.
    ic_log_gebremst('video_lesen', 'video.php: ' . $ic_datei . ' nicht lesbar.');
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Das Video liess sich nicht lesen.\n";
    exit;
}
@set_time_limit(0);
while (ob_get_level() > 0) { @ob_end_clean(); }
header('Content-Type: video/x-msvideo');
header('Content-Length: ' . (int) $ic_groesse);
header('Accept-Ranges: none');
header('Content-Disposition: attachment; filename="' . basename($ic_datei) . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
while (!feof($ic_fh)) {
    $ic_stueck = fread($ic_fh, 1048576);
    if ($ic_stueck === false || $ic_stueck === '') { break; }
    echo $ic_stueck;
    @flush();
    if (connection_aborted()) { break; }
}
fclose($ic_fh);

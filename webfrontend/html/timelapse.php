<?php
/**
 * Intercom - Zeitraffer und Intervallaufnahme (Cron, minuetlich)
 *
 * Beendet sich sofort, wenn beides ausgeschaltet ist oder die eingestellte
 * Uhrzeit nicht der aktuellen Minute entspricht.
 *
 * Die eigentliche Arbeit steht in ic_lib.php - derselbe Code laeuft auf
 * Knopfdruck aus dem Reiter Test. Bis 2.1.13 zeigte der Knopf dort auf DIESE
 * Datei, und die weist HTTP-Aufrufe ab: der Knopf endete ausnahmslos auf
 * einer Fehlerseite.
 */

require_once __DIR__ . '/ic_start.php';

/*
 * Nur ueber die Kommandozeile (Cron), nicht ueber HTTP.
 *
 * Diese Datei liegt unter webfrontend/html/ und waere damit fuer jeden im
 * Netz aufrufbar. Sie loest Arbeit aus und hat im Web nichts verloren.
 * php-cli setzt PHP_SAPI auf "cli"; ueber den Apache steht dort etwas anderes.
 */
if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Dieses Skript laeuft nur ueber den Cron, nicht ueber HTTP.\n";
    echo "Die Oberflaeche hat dafuer einen Knopf im Reiter Test.\n";
    exit;
}

/* ---- Sperre gegen Parallellaeufe ----
 *
 * Der Bildabruf von der Kamera wartet auf ein Netz. Dauert der Lauf laenger
 * als der Cron-Takt, startet der naechste, waehrend dieser noch laeuft. Die
 * Sperre ist nicht blockierend - wer nicht drankommt, geht kommentarlos
 * wieder (der naechste Takt kommt ohnehin gleich).
 *
 * Der Name traegt den Plugin-Ordner: bis 2.1.13 hiess die Datei fest
 * ic_cron.lock, und eine Zweitinstallation haette die erste ausgesperrt.
 */
$ic_sperre = ic_sperre('cron');
if ($ic_sperre === false) {
    exit(0);
}

$ic_etwas = false;

/* ---------------- Zeitraffer ---------------- */
list($ok, $meldung, $datei) = ic_timelapse_lauf(false);
if ($ok) {
    echo "OK: Zeitrafferbild " . $meldung . "\n";
    $ic_etwas = true;
} elseif ($meldung !== '') {
    // Ein Fehlschlag steht im Protokoll (ic_timelapse_lauf schreibt ihn) und
    // zusaetzlich hier - der Cron leitet die Ausgabe an den Systemlogger.
    echo "FEHLER Zeitraffer: " . $meldung . "\n";
    $ic_etwas = true;
}

/* ---------------- Intervallaufnahme ---------------- */
list($ok2, $meldung2) = ic_intervall_lauf();
if ($ok2) {
    echo "OK: Intervallaufnahme " . $meldung2 . "\n";
    $ic_etwas = true;
} elseif ($meldung2 !== '') {
    echo "FEHLER Intervall: " . $meldung2 . "\n";
    $ic_etwas = true;
}

/* ---------------- Herzschlag ---------------- */
// Ohne ihn ist ein totes Plugin nicht von einem ruhigen zu unterscheiden:
// ein virtueller Eingang behaelt seinen letzten Wert, und in der App sieht
// dann alles normal aus.
ic_mqtt_herzschlag();

flock($ic_sperre, LOCK_UN);
fclose($ic_sperre);
exit(0);

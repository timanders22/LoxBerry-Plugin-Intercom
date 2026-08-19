<?php
/**
 * Intercom - automatische Archiv-Bereinigung (Cron, taeglich)
 *
 * Loescht in den Archiven (Bilder, Videos, Zeitraffer), was aelter als
 * "cleanup_days" Tage ist, was ueber "cleanup_count" hinausgeht und - neu in
 * 2.2.0 - was ueber "cleanup_mb" Megabyte hinausgeht. Alle drei Grenzen sind
 * einzeln abschaltbar (0 = keine Begrenzung).
 *
 * Aufruf mit --probe loescht nichts und sagt nur, was geschaehe.
 *
 * Die eigentliche Arbeit steht in ic_lib.php - derselbe Code laeuft auf
 * Knopfdruck aus dem Reiter Test.
 */

require_once __DIR__ . '/ic_start.php';

/*
 * Nur ueber die Kommandozeile (Cron), nicht ueber HTTP.
 *
 * Diese Datei liegt unter webfrontend/html/ und waere damit fuer jeden im
 * Netz aufrufbar. Sie loescht im Archiv und hat im Web nichts verloren.
 */
if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Dieses Skript laeuft nur ueber den Cron, nicht ueber HTTP.\n";
    echo "Die Oberflaeche hat dafuer einen Knopf im Reiter Test.\n";
    exit;
}

// Eigene Sperre: das Aufraeumen loescht im Zeitraffer-Ordner, in den
// timelapse.php schreibt. Beide Laeufe liegen zwar auf verschiedene Zeiten,
// aber eine Ueberschneidung ist damit nicht ausgeschlossen.
$ic_sperre = ic_sperre('cron');
if ($ic_sperre === false) {
    echo "Ein anderer Lauf ist noch beschaeftigt - dieser Durchgang entfaellt.\n";
    exit(0);
}

$probe = in_array('--probe', $argv, true);

list($zahl, $byte, $zeilen) = ic_aufraeumen($probe);

foreach ($zeilen as $z) {
    echo '  ' . $z . "\n";
}
if ($probe) {
    // Ein Trockenlauf, der die Sprache des Ernstfalls spricht, ist eine
    // stille Falschaussage. Deshalb steht hier ausdruecklich, was NICHT
    // geschehen ist.
    echo "PROBE: es wurde NICHTS geloescht. Ein echter Lauf wuerde "
       . $zahl . " Datei(en) entfernen (" . ic_byte($byte) . ").\n";
} else {
    echo "OK: " . $zahl . " Datei(en) bereinigt, " . ic_byte($byte) . " frei geworden.\n";
}

flock($ic_sperre, LOCK_UN);
fclose($ic_sperre);
exit(0);

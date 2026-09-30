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

/* ---- Waehrend einer Aktualisierung nichts tun ----
 *
 * Der Installer legt die Cron-Datei rund eine Minute vor postinstall.sh an
 * (Regeln/06). In dieser Zeit fehlt der Datenordner, und data.json ist
 * gewoehnlich die leere Vorgabe - dann tut ein Lauf nichts ausser einer
 * Sperre im Temp-Ordner. Steht dort aber schon eine volle Konfiguration,
 * legt er Merker an und sendet an das Gateway, bevor postinstall.sh fertig
 * ist (in WSL mit kuenstlich eingesetzter Konfiguration gemessen,
 * Pruefung-Intercom-2.2.11, Fall T1). Die Marke kommt aus preupgrade.sh;
 * der naechste Takt nach postinstall.sh arbeitet wieder.
 *
 * BERICHTIGT 2.2.13 (Entscheidung 1 und Nr. 8, Frage 17): die Marke gilt hier
 * OHNE Altersgrenze. Bis 2.2.12 arbeitete der Lauf nach einer Stunde Luecke
 * wieder, obwohl postinstall.sh noch ausstand. */
if (ic_upgrade_marke_liegt()) {
    echo "Aktualisierung laeuft - dieser Durchgang entfaellt.\n";
    exit(0);
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

/* ---------------- Stationsprobe (seit 2.2.15, Intercom-a2) ----------------
 *
 * Ohne Aufnahme im Takt blieb status/ok bis 2.2.14 beim letzten Klingeln
 * stehen, gleich wie alt. Die Probe fragt die Station einmal je Minute kurz an
 * (HEAD auf das Standbild bzw. nur die Kopfzeilen des Stroms, 3 s), holt KEIN
 * Bild und legt nichts ins Archiv. Sie entfaellt, wenn in diesem Takt schon ein
 * Abruf lief, und ohne MQTT (sie speist allein status/ok). */
ic_stationsprobe_lauf();

/* ---------------- Herzschlag ---------------- */
// Ohne ihn ist ein totes Plugin nicht von einem ruhigen zu unterscheiden:
// ein virtueller Eingang behaelt seinen letzten Wert, und in der App sieht
// dann alles normal aus.
ic_mqtt_herzschlag();

/* NEU 2.2.13 (M1): die behaltenen Themen aus 2.2.5 und frueher abraeumen -
 * mehrfach ueber den ersten Tag verteilt, danach nie wieder. Nur mit MQTT. */
ic_mqtt_altlast_lauf();

flock($ic_sperre, LOCK_UN);
fclose($ic_sperre);
exit(0);

<?php
/**
 * Intercom - das letzte Bild ausliefern, MIT Token
 *
 * Aufruf:
 *   /plugins/<ordner>/bild.php?token=<TOKEN>              das letzte Bild
 *   /plugins/<ordner>/bild.php?token=<TOKEN>&datei=<name> ein Archivbild
 *   /plugins/<ordner>/bild.php?link=<code>                befristeter Link
 *
 * WARUM ES DIESE DATEI GIBT
 * -------------------------
 * Bis 2.1.13 schuetzte das Token das AUSLOESEN, nicht das ERGEBNIS.
 * lastpicture.jpg lag unter webfrontend/html/plugins/<ordner>/ und wurde vom
 * Apache unmittelbar ausgeliefert: jedes Geraet im Netz konnte die Datei im
 * Sekundentakt abrufen und damit die Haustuer beobachten. Der Aufwand fuer
 * einen Mitleser sank gegenueber 1.5.0 nur von "Strom mitlesen" auf "alle
 * zwei Sekunden ein Bild holen".
 *
 * Der befristete Link ist fuer Mails und Meldungen gedacht. Bis 2.1.13
 * verschickten Anwender dort die Adresse von getpicture.php - also die
 * AUSLOESEADRESSE samt Token. Wer im Mail darauf klickte, machte eine neue
 * Aufnahme statt das Bild vom Klingeln zu sehen, und das Token stand
 * dauerhaft in der Mail.
 */

require_once __DIR__ . '/ic_start.php';

/*
 * DER BEFRISTETE LINK GILT NUR FUER DAS LETZTE BILD.
 *
 * Er ist fuer Mails gedacht und traegt deshalb kein Zugriffstoken. Wuerde er
 * auch den ?datei=-Zweig oeffnen, waere er ein Zugang zum gesamten Archiv:
 * die Namen sind aus Datum und Uhrzeit gebildet und damit ratbar. Ein Link,
 * der laut Beschriftung "auf das letzte Bild" zeigt, darf nicht mehr koennen
 * als das.
 */
$ic_code = isset($_GET['link']) && is_string($_GET['link']) ? $_GET['link'] : '';
$ic_nur_letztes = false;
if ($ic_code !== '') {
    if (!ic_bildlink_pruefen($ic_code)) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: text/plain; charset=utf-8');
        echo "Dieser Link ist abgelaufen oder verbraucht.\n";
        exit;
    }
    $ic_nur_letztes = true;
} else {
    ic_token_pruefen();
    if (isset($_GET['selftest'])) {
        ic_selftest_antwort('bild.php');
    }
}

/* ---------------- Welche Datei? ---------------- */
$datei = ic_paths()['datadir'] . '/lastpicture.jpg';
$name = 'lastpicture.jpg';

if ($ic_nur_letztes && isset($_GET['datei'])) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Dieser Link gilt nur fuer das letzte Bild.\n";
    exit;
}

if (isset($_GET['datei']) && is_string($_GET['datei']) && $_GET['datei'] !== '') {
    /*
     * Der Dateiname wird GEPRUEFT, nicht uebernommen - dieselben drei Stufen
     * wie in videowebhook.php:
     *   1. basename() entfernt jeden Pfadanteil (../../ und /etc/passwd),
     *   2. ein Muster laesst nur die Zeichen zu, die das Plugin selbst
     *      vergibt,
     *   3. und zuletzt muss die Datei im Archiv WIRKLICH existieren.
     */
    $n = basename((string) $_GET['datei']);
    if (!preg_match('/^[A-Za-z0-9._-]{1,128}\.jpg$/', $n)) {
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: text/plain; charset=utf-8');
        echo "Ungueltiger Dateiname.\n";
        exit;
    }
    $o = ic_archivordner();
    $gefunden = '';
    foreach (array($o['bild'], $o['timelapse'], $o['video']) as $ordner) {
        if (@is_file($ordner . $n)) { $gefunden = $ordner . $n; break; }
    }
    if ($gefunden === '') {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: text/plain; charset=utf-8');
        echo "Datei nicht im Archiv.\n";
        exit;
    }
    $datei = $gefunden;
    $name = $n;
}

/* ---------------- Rueckfall, in drei Stufen ---------------- */
//
// 1. data/plugins/<ordner>/lastpicture.jpg   - der Regelfall
// 2. webfrontend/html/plugins/<ordner>/...   - wer von 2.1.13 kommt, hat das
//    letzte Bild noch nicht im Datenverzeichnis
// 3. NEU 2.2.9: das juengste Bild im Archiv
//
// Zur dritten Stufe, gemessen am 07.09.2026: postupgrade.sh sagte dem
// Anwender "die offene Kopie des letzten Bildes bleibt liegen - dieses
// Update entfernt sie nicht". Das Installationsprotokoll desselben Laufs
// entfernt BEIDE Kopien (data/plugins/<ordner>/ wird bei jedem Upgrade
// abgeraeumt, und den html-Baum loescht der Installer vor dem Kopieren
// vollstaendig). Nachgemessen: /plugins/intercom/lastpicture.jpg antwortete
// mit HTTP 404, und in Loxone stand an einem Kamera-Baustein ein totes Bild -
// bis zum naechsten Klingeln.
//
// Statt die Zusage zurueckzunehmen, wird sie eingeloest: das juengste Bild im
// Archiv IST das letzte Bild, das es noch gibt. Ist die Archivierung
// abgeschaltet, ist auch kein neueres entstanden - die Aussage bleibt wahr.
// Der Dateiname der Antwort bleibt lastpicture.jpg, damit sich an der
// Adresse, die in Loxone eingetragen ist, nichts aendert.
$ic_quelle = 'datei';
if (!@is_file($datei)) {
    $alt = __DIR__ . '/lastpicture.jpg';
    if (@is_file($alt)) {
        $datei = $alt;
        $ic_quelle = 'offene-kopie';
    } else {
        $ic_neuestes = ic_archiv_neuestes_bild();
        if ($ic_neuestes !== '') {
            $datei = $ic_neuestes;
            $ic_quelle = 'archiv';
        } else {
            header('HTTP/1.1 404 Not Found');
            header('Content-Type: text/plain; charset=utf-8');
            echo "Es liegt noch kein Bild vor. Einmal ausloesen - dann steht es hier.\n";
            exit;
        }
    }
}
// Damit sich die Stufe MESSEN laesst, ohne ins Protokoll zu schreiben -
// bild.php wird oft aufgerufen, eine Protokollzeile je Aufruf waere Laerm.
header('X-Intercom-Bildquelle: ' . $ic_quelle);

$d = @file_get_contents($datei);
if ($d === false) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Das Bild liess sich nicht lesen: " . $datei . "\n";
    exit;
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . strlen($d));
header('Content-Disposition: inline; filename="' . $name . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
echo $d;

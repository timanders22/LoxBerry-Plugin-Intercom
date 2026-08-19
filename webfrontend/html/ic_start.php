<?php
/**
 * Intercom - der gemeinsame Vorlauf aller Endpunkte
 *
 * Bis 2.1.13 stand dieser Block in SECHS Dateien wortgleich, jeweils rund
 * vierzig Zeilen. Sechs Kopien einer Kandidatensuche laufen beim naechsten
 * Umbau auseinander - und die Reihenfolge "erst einbinden, dann pruefen"
 * liess sich nur an sechs Stellen gleichzeitig geradeziehen.
 *
 * Eingebunden wird diese Datei ueber __DIR__, also aus demselben Verzeichnis.
 * Das trifft in beiden Zustaenden - installiert wie im entpackten Archiv -
 * und braucht keine Kandidatenliste.
 *
 * DIE BIBLIOTHEK liegt dagegen im ANGEMELDETEN Bereich, diese Dateien nicht.
 * Der Weg dorthin sieht in den beiden Zustaenden verschieden aus:
 *
 *   installiert  <home>/webfrontend/html/plugins/<ordner>/diese-datei.php
 *                <home>/webfrontend/htmlauth/plugins/<ordner>/config.php
 *   Archiv       <wurzel>/webfrontend/html/diese-datei.php
 *                <wurzel>/webfrontend/htmlauth/config.php
 *
 * Bis 2.1.1 stand dort ein fester Ausdruck mit ZWEI dirname(). Installiert
 * ergab der <home>/webfrontend/html/htmlauth/plugins/<ordner>/config.php -
 * ein Verzeichnis, das es nicht gibt. require_once auf eine fehlende Datei
 * ist ein Fatal Error, und weil display_errors erst danach abgeschaltet
 * wurde, kam nicht einmal eine lesbare Meldung zurueck, sondern HTTP 500.
 *
 * Deshalb eine Kandidatenliste statt einer Rechnung: genommen wird die
 * Datei, die wirklich da ist.
 */

/*
 * Diese Datei ist ein Vorlauf, kein Endpunkt.
 *
 * Sie liegt im unangemeldeten Bereich und waere damit unmittelbar aufrufbar.
 * Ein direkter Aufruf tut zwar nichts, aber er soll auch nicht 200 antworten:
 * was kein Endpunkt ist, weist ab.
 */
if (isset($_SERVER['SCRIPT_FILENAME'])
    && @realpath($_SERVER['SCRIPT_FILENAME']) === @realpath(__FILE__)) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Diese Datei ist ein Vorlauf, kein Endpunkt.\n";
    exit;
}

$ic_config_gefunden = false;
foreach (array(
    dirname(dirname(dirname(__DIR__))) . '/htmlauth/plugins/' . basename(__DIR__) . '/config.php',
    dirname(dirname(__DIR__)) . '/htmlauth/plugins/' . basename(__DIR__) . '/config.php',
    dirname(__DIR__) . '/htmlauth/config.php',
) as $ic_kandidat) {
    if (is_file($ic_kandidat)) {
        require_once $ic_kandidat;
        $ic_config_gefunden = true;
        break;
    }
}
if (!$ic_config_gefunden) {
    /* Sagen, was fehlt, statt mit 500 zu enden. Diese Dateien werden von
     * Loxone und von der Tuerstation aufgerufen - dort sieht niemand ein
     * Apache-Protokoll. */
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo "FEHLER: config.php des Plugins wurde nicht gefunden.\n";
    echo "Gesucht ausgehend von: " . __DIR__ . "\n";
    echo "Bitte das Plugin neu installieren.\n";
    exit;
}

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

/**
 * Der Selbsttest des Endpunkts.
 *
 * Aufruf: <endpunkt>?selftest=1&token=<TOKEN>
 *
 * Er beantwortet die einzige Frage, die man von Loxone aus stellen will,
 * ohne etwas auszuloesen: stimmt das eingetragene Token noch, und laeuft
 * dieser Endpunkt? Bis 2.1.13 liess sich das nur feststellen, indem man
 * klingelte oder eine Aufzeichnung startete.
 *
 * Aufgerufen wird die Funktion NACH der Token-Pruefung - ein Selbsttest, der
 * ohne Token antwortet, waere eine Auskunft an jeden im Netz.
 */
function ic_selftest_antwort($endpunkt)
{
    header('Content-Type: application/json; charset=utf-8');
    $z = ic_selbsttest(false);
    $b = ic_selbsttest_bilanz($z);
    $fehl = array();
    foreach ($z as $zeile) {
        if ($zeile['lage'] === 'fehl') { $fehl[] = $zeile['frage']; }
    }
    echo json_encode(array(
        'selftest'  => true,
        'endpunkt'  => $endpunkt,
        'plugin'    => ic_plugin_ordner(),
        'version'   => ic_fassung(),
        'timestamp' => date('d.m.Y-H:i:s'),
        'ok'        => $b['ok'],
        'fehl'      => $b['fehl'],
        'unklar'    => $b['unklar'],
        'bestanden' => $b['bestanden'],
        'fehlende'  => $fehl,
    ));
    exit;
}

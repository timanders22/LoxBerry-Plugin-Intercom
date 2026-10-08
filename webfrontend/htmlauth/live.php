<?php
/**
 * Intercom - Livebild
 *
 * Seit 2.2.20 ein Reiter der Startseite (Entscheidung Nr. 43: gruene
 * Reiter statt der LoxBerry-Navigationsleiste). Der Inhalt steht in
 * ic_bereiche.php. Diese Datei leitet nur noch um, damit alte
 * Lesezeichen und Links nicht ins Leere laufen - wie settings.php.
 * Der Parameter station reist mit, aber nur in der erwarteten Form.
 */
$ic_ziel = 'index.php?tab=live';
if (isset($_GET['station']) && is_string($_GET['station'])
    && preg_match('/^[0-9]{1,3}$/', $_GET['station'])) {
    $ic_ziel .= '&station=' . $_GET['station'];
}
header('Location: ' . $ic_ziel);
exit;

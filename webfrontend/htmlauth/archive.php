<?php
/**
 * Intercom - Bilderarchiv
 *
 * Seit 2.2.20 ein Reiter der Startseite (Entscheidung Nr. 43: gruene
 * Reiter statt der LoxBerry-Navigationsleiste). Der Inhalt steht in
 * ic_bereiche.php. Diese Datei leitet nur noch um, damit alte
 * Lesezeichen und Links nicht ins Leere laufen - wie settings.php.
 * Der Parameter page reist mit, aber nur in der erwarteten Form.
 */
$ic_ziel = 'index.php?tab=bilder';
if (isset($_GET['page']) && is_string($_GET['page'])
    && preg_match('/^[0-9]{1,6}$/', $_GET['page'])) {
    $ic_ziel .= '&page=' . $_GET['page'];
}
header('Location: ' . $ic_ziel);
exit;

<?php
/**
 * Absichtlich ohne Inhalt.
 *
 * Diese Datei verhindert, dass der Apache bei einem Aufruf von
 * /plugins/<ordner>/ das Verzeichnis auflistet - dort liegen der
 * Bildzwischenspeicher (lastpicture.jpg) und die Endpunkte.
 *
 * Die eigentliche Oberflaeche liegt unter webfrontend/htmlauth/.
 */
header('HTTP/1.1 403 Forbidden');
header('Content-Type: text/plain; charset=utf-8');
echo "Hier gibt es nichts zu sehen. Die Oberflaeche des Plugins liegt im\n"
   . "angemeldeten Bereich von LoxBerry unter Plugins.\n";

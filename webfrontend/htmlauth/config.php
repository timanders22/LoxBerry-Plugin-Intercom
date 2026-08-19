<?php
/**
 * Intercom - gemeinsame Einbindung
 *
 * DIESE DATEI LEGT NICHTS MEHR AN UND VERSCHIEBT NICHTS.
 *
 * Bis 2.1.13 stand hier die gesamte Einrichtung des Speicherorts: bis zu vier
 * mkdir() und - war ein eigener Pfad hinterlegt - zwei Shell-Aufrufe
 * (cp -rn und rm -rf) samt symlink(). Eingebunden wird die Datei aber von
 * JEDEM Endpunkt ganz oben, also VOR der Token-Pruefung. Eine Anfrage ohne
 * Token loeste damit Arbeit im Dateisystem aus, und im Umzugsfall sogar zwei
 * Shell-Aufrufe. Die Argumente waren durch escapeshellarg() gedeckt, die
 * Reihenfolge blieb trotzdem falsch: der unangemeldete Endpunkt darf nichts
 * anlegen.
 *
 * Angelegt wird jetzt in ic_archiv_sicherstellen() - nach der Pruefung -,
 * und der Speicherort wird in ic_speicherort_anwenden() eingerichtet, wenn
 * der Anwender in der Oberflaeche speichert.
 */

require_once "loxberry_io.php";
require_once "loxberry_web.php";
require_once "loxberry_system.php";
require_once __DIR__ . "/ic_lib.php";

// KEINE Ordnervariablen mehr.
//
// Bis 2.1.13 setzte diese Datei $legacyfolder, $folder_img_archive,
// $folder_video_archive und $folder_timelapse. Seit 2.2.0 kommen die Ordner
// aus ic_archivordner() - einer Quelle, die auch die Selbstpruefung und das
// Aufraeumen benutzen. Die vier Variablen blieben nach dem Umbau uebrig und
// wurden von keiner Zeile mehr gelesen; eine Variable, die niemand benutzt,
// ist eine falsche Faehrte fuer den naechsten Umbau.

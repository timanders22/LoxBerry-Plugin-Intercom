<?php

require_once "loxberry_io.php";
require_once "loxberry_web.php";
require_once "loxberry_system.php";
require_once __DIR__ . "/ic_lib.php";

// Der Ordnername wird ERMITTELT, nicht eingetragen.
//
// Bis 1.5.0 stand hier "<LoxBerry-Wurzel>/webfrontend/legacy/<Ordner>_data/"
// mit festem Namen. Haengt LoxBerry bei der Installation einen Zaehler an
// (intercom_01, weil der Ordner schon belegt war), zeigten beide
// Installationen auf dasselbe Archiv - und die Verweise in den Skripten
// zusaetzlich auf die falsche Konfiguration.
$legacyfolder = ic_paths()['legacy'];

// Konfigurierbarer Speicherort (z.B. externer USB-Speicher):
// Ist in den Einstellungen ein Pfad hinterlegt und beschreibbar, wird
// /webfrontend/legacy/<Ordner>_data als Symlink dorthin gefuehrt -
// alle Archiv-URLs funktionieren dadurch unveraendert weiter.
$icfg = array();
if (defined('LBPCONFIGDIR') && file_exists(LBPCONFIGDIR.'/data.json')) {
	$icfg = json_decode(file_get_contents(LBPCONFIGDIR.'/data.json'), true);
	if (!is_array($icfg)) { $icfg = array(); }
}
$storage = isset($icfg['storage_path']) ? rtrim(trim($icfg['storage_path']), '/') : '';
if ($storage !== '' && is_dir($storage) && is_writable($storage)) {
	// Aus dem ORDNERNAMEN abgeleitet, nicht fest eingetragen: sonst zeigt
	// eine Zweitinstallation (intercom_01) auf dasselbe Archiv.
	$target = $storage . '/' . ic_plugin_ordner() . '_data';
	if (!file_exists($target)) { @mkdir($target, 0775, true); }
	$linkbase = rtrim($legacyfolder, '/');
	if (is_link($linkbase)) {
		if (readlink($linkbase) !== $target) { @unlink($linkbase); @symlink($target, $linkbase); }
	} elseif (is_dir($linkbase)) {
		// vorhandene Daten einmalig auf den neuen Speicher uebernehmen
		@shell_exec('cp -rn ' . escapeshellarg($linkbase) . '/. ' . escapeshellarg($target) . '/ 2>/dev/null');
		@shell_exec('rm -rf ' . escapeshellarg($linkbase));
		@symlink($target, $linkbase);
	} else {
		@symlink($target, $linkbase);
	}
}

if (!file_exists($legacyfolder)) {
	// 0775 statt 0777: fuer alle beschreibbar muss das Archiv nicht sein.
	@mkdir(rtrim($legacyfolder, '/'), 0775, true);
}

$folder_img_archive = $legacyfolder."img_archive/";
$folder_video_archive = $legacyfolder."video_archive/";

if (!file_exists($folder_img_archive)) {
	@mkdir($folder_img_archive,0775,true);
} 

if (!file_exists($folder_video_archive)) {
	@mkdir($folder_video_archive,0775,true);
}

$folder_timelapse = $legacyfolder."timelapse/";
if (!file_exists($folder_timelapse)) {
	@mkdir($folder_timelapse,0775,true);
}


<?php
/**
 * Intercom - Bilderarchiv
 *
 * Rein anzeigend. Geloescht wird ueber ajax.php (einzelne Datei) und ueber
 * den Reiter Archiv der Startseite (alle) - beides mit dem Formularmerkmal.
 *
 * Bis 2.1.13 loeschte ein blosser Aufruf archive.php?submit=1 das GESAMTE
 * Bildarchiv, ohne Merkmal und ohne POST: ein Bild auf einer beliebigen
 * Seite mit dieser Adresse genuegte, solange der Anwender angemeldet war.
 */

require_once "config.php";

$L = LBSystem::readlanguage("language.ini");

require_once "menu.php";
$navbar[3]['active'] = True;
LBWeb::lbheader(ic_titel(), 'https://github.com/timanders22/LoxBerry-Plugin-Intercom/', 'help.html');
require_once __DIR__ . "/ic_stil.php";

function ic_txt($schluessel)
{
    global $L;
    return isset($L[$schluessel]) ? ic_e($L[$schluessel]) : $schluessel;
}

/** Datum und Uhrzeit aus dem Dateinamen, verlustfrei. */
function ic_datum_aus_name($name)
{
    $n = basename($name);
    // Neu ab 2.2.0: 2026_08_19-17_05_00[-zusatz]-intercom.jpg
    if (preg_match('/^(\d{4})_(\d{2})_(\d{2})-(\d{2})_(\d{2})_(\d{2})(?:-(.+?))?-intercom\./', $n, $m)) {
        return $m[3] . '.' . $m[2] . '.' . $m[1] . ' ' . $m[4] . ':' . $m[5] . ':' . $m[6]
             . (isset($m[7]) && $m[7] !== '' ? ' (' . $m[7] . ')' : '');
    }
    // Bis 2.1.13: 2026.08.19-17:05:00[-zusatz]-intercom.jpg
    if (preg_match('/^(\d{4})\.(\d{2})\.(\d{2})-(\d{2}):(\d{2}):(\d{2})(?:-(.+?))?-intercom\./', $n, $m)) {
        return $m[3] . '.' . $m[2] . '.' . $m[1] . ' ' . $m[4] . ':' . $m[5] . ':' . $m[6]
             . (isset($m[7]) && $m[7] !== '' ? ' (' . $m[7] . ')' : '');
    }
    // Unbekannte Form: den Namen zeigen statt eine Zahl zu erfinden.
    return $n;
}

$ic_plugin = ic_plugin_ordner();
$ic_www = '/legacy/' . rawurlencode($ic_plugin) . '_data/img_archive/';
$ic_dateien = glob(ic_archivordner()['bild'] . '*.jpg') ?: array();
rsort($ic_dateien);

list($ic_seite, $ic_letzte, $ic_versatz, $ic_ende) =
    ic_blaettern(count($ic_dateien), 18, isset($_GET['page']) ? $_GET['page'] : 1);
?>
<script>
document.body.setAttribute('data-ic-admin', '/admin/plugins/<?= ic_e($ic_plugin) ?>');
document.body.setAttribute('data-ic-merkmal', '<?= ic_e(ic_merkmal()) ?>');
</script>
<script type="text/javascript" src="script.js"></script>

<div class="smw">
<h1><?= ic_txt('UI.TITEL') ?></h1>
<h2><?= ic_txt('COMMON.BACKUP') ?></h2>
<p><?= ic_txt('COMMON.BACKUPTXT') ?></p>

<p><b><?= ic_txt('COMMON.GALINFO1') ?></b> <?= count($ic_dateien) ?>
&nbsp;&nbsp;<b><?= ic_txt('COMMON.PAGE') ?></b> <?= $ic_seite ?>/<?= $ic_letzte ?></p>

<div class="sm-legende">
<span><i class="sm-punkt sm-b-lesen"></i> <?= ic_txt('UI.LEG_LESEN') ?></span>
</div>
<div class="sm-knopfreihe">
<?php if ($ic_seite > 1) { ?>
<a class="sm-btn sm-b-lesen" href="archive.php?page=<?= $ic_seite - 1 ?>">&laquo; <?= ic_txt('COMMON.PREV') ?></a>
<?php } ?>
<?php if ($ic_seite < $ic_letzte) { ?>
<a class="sm-btn sm-b-lesen" href="archive.php?page=<?= $ic_seite + 1 ?>"><?= ic_txt('COMMON.NEXT') ?> &raquo;</a>
<?php } ?>
<a class="sm-btn sm-b-lesen" href="index.php?tab=archiv"><?= ic_txt('UI.K_ZURUECK') ?></a>
</div>

<?php if (!$ic_dateien) { ?>
<div class="sm-hinweis"><?= ic_txt('UI.GAL_LEER') ?></div>
<?php } ?>

<div class="sm-gal">
<?php for ($ic_i = $ic_versatz; $ic_i < $ic_ende; $ic_i++) {
    $ic_n = basename($ic_dateien[$ic_i]); ?>
<figure>
    <a href="<?= $ic_www . rawurlencode($ic_n) ?>" target="_blank">
        <img src="<?= $ic_www . rawurlencode($ic_n) ?>" alt="<?= ic_e($ic_n) ?>">
    </a>
    <figcaption><?= ic_e(ic_datum_aus_name($ic_n)) ?><br>
    <a href="#" class="sm-del" data-datei="<?= ic_e($ic_n) ?>" data-art="bild"><?= ic_txt('UI.K_LOESCHEN') ?></a></figcaption>
</figure>
<?php } ?>
</div>

</div><!-- /smw -->
<?php
LBWeb::lbfooter();

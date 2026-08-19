<?php
/**
 * Intercom - Videoarchiv
 *
 * Rein anzeigend. Geloescht wird ueber ajax.php (einzelne Aufnahme, samt
 * Vorschaubild) und ueber den Reiter Archiv der Startseite (alle).
 *
 * Bis 2.1.13 loeschte "Alle loeschen" hier ausschliesslich die
 * VORSCHAUBILDER: der Name der .avi-Datei wurde zwar berechnet, die Variable
 * aber nie benutzt. Nachgebaut mit 25 Aufnahmen: hinterher 0 Vorschaubilder
 * und 25 unveraendert daliegende Videos, die ueber die Oberflaeche nicht mehr
 * erreichbar waren. Wer den Knopf drueckte, weil die Karte voll war, hat
 * nichts gewonnen.
 */

require_once "config.php";

$L = LBSystem::readlanguage("language.ini");

require_once "menu.php";
$navbar[4]['active'] = True;
LBWeb::lbheader(ic_titel(), 'https://github.com/timanders22/LoxBerry-Plugin-Intercom/', 'help.html');
require_once __DIR__ . "/ic_stil.php";

function ic_txt($schluessel)
{
    global $L;
    return isset($L[$schluessel]) ? ic_e($L[$schluessel]) : $schluessel;
}

/** Datum, Uhrzeit und Laenge aus dem Dateinamen, verlustfrei. */
function ic_datum_aus_videoname($name)
{
    $n = basename($name);
    if (preg_match('/^(\d{4})_(\d{2})_(\d{2})-(\d{2})_(\d{2})_(\d{2})-(?:(.+?)-)?(\d+)s-intercom\./', $n, $m)) {
        return $m[3] . '.' . $m[2] . '.' . $m[1] . ' ' . $m[4] . ':' . $m[5] . ':' . $m[6]
             . ' (' . $m[8] . ' s' . (isset($m[7]) && $m[7] !== '' ? ', ' . $m[7] : '') . ')';
    }
    return $n;
}

$ic_plugin = ic_plugin_ordner();
$ic_www = '/legacy/' . rawurlencode($ic_plugin) . '_data/video_archive/';
// Gelistet werden die VIDEOS, nicht die Vorschaubilder - sonst zaehlt die
// Seite Vorschaubilder als Aufnahmen.
$ic_dateien = glob(ic_archivordner()['video'] . '*.avi') ?: array();
rsort($ic_dateien);

list($ic_seite, $ic_letzte, $ic_versatz, $ic_ende) =
    ic_blaettern(count($ic_dateien), 20, isset($_GET['page']) ? $_GET['page'] : 1);

$ic_adr = ic_adressen(ic_host(), (string) (isset(ic_config()['aktionstoken'])
    ? ic_config()['aktionstoken'] : ''));
?>
<script>
document.body.setAttribute('data-ic-admin', '/admin/plugins/<?= ic_e($ic_plugin) ?>');
document.body.setAttribute('data-ic-merkmal', '<?= ic_e(ic_merkmal()) ?>');
</script>
<script type="text/javascript" src="script.js"></script>

<div class="smw">
<h1><?= ic_txt('UI.TITEL') ?></h1>
<h2><?= ic_txt('COMMON.BACKUPVIDEO') ?></h2>
<p><?= ic_txt('COMMON.BACKUPVIDEOTXT') ?></p>
<p class="sm-klein"><?= ic_txt('COMMON.BACKUPVIDEOTXT2') ?></p>
<p class="sm-klein"><?= ic_txt('COMMON.BACKUPVIDEOTXT3') ?></p>
<p><span class="sm-mono"><?= ic_e($ic_adr['video']) ?></span></p>

<p><b><?= ic_txt('COMMON.GALINFO2') ?></b> <?= count($ic_dateien) ?>
&nbsp;&nbsp;<b><?= ic_txt('COMMON.PAGE') ?></b> <?= $ic_seite ?>/<?= $ic_letzte ?></p>

<div class="sm-legende">
<span><i class="sm-punkt sm-b-lesen"></i> <?= ic_txt('UI.LEG_LESEN') ?></span>
</div>
<div class="sm-knopfreihe">
<?php if ($ic_seite > 1) { ?>
<a class="sm-btn sm-b-lesen" href="videoarchive.php?page=<?= $ic_seite - 1 ?>">&laquo; <?= ic_txt('COMMON.PREV') ?></a>
<?php } ?>
<?php if ($ic_seite < $ic_letzte) { ?>
<a class="sm-btn sm-b-lesen" href="videoarchive.php?page=<?= $ic_seite + 1 ?>"><?= ic_txt('COMMON.NEXT') ?> &raquo;</a>
<?php } ?>
<a class="sm-btn sm-b-lesen" href="index.php?tab=archiv"><?= ic_txt('UI.K_ZURUECK') ?></a>
</div>

<?php if (!$ic_dateien) { ?>
<div class="sm-hinweis"><?= ic_txt('UI.GAL_LEER') ?></div>
<?php } ?>

<div class="sm-gal">
<?php for ($ic_i = $ic_versatz; $ic_i < $ic_ende; $ic_i++) {
    $ic_n = basename($ic_dateien[$ic_i]);
    $ic_tn = preg_replace('/\.avi$/', '.jpg', $ic_n);
    $ic_hat_tn = @is_file(ic_archivordner()['video'] . $ic_tn); ?>
<figure>
    <a href="<?= $ic_www . rawurlencode($ic_n) ?>" target="_blank">
        <?php if ($ic_hat_tn) { ?>
        <img src="<?= $ic_www . rawurlencode($ic_tn) ?>" alt="<?= ic_e($ic_n) ?>">
        <?php } else { ?>
        <span class="sm-mono"><?= ic_txt('UI.KEIN_VORSCHAUBILD') ?></span>
        <?php } ?>
    </a>
    <figcaption><?= ic_e(ic_datum_aus_videoname($ic_n)) ?><br>
    <?= ic_e(ic_byte((int) @filesize($ic_dateien[$ic_i]))) ?><br>
    <a href="#" class="sm-del" data-datei="<?= ic_e($ic_n) ?>" data-art="video"><?= ic_txt('UI.K_LOESCHEN') ?></a></figcaption>
</figure>
<?php } ?>
</div>

</div><!-- /smw -->
<?php
LBWeb::lbfooter();

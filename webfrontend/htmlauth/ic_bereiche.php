<?php
/**
 * Intercom - Inhalt der Reiter Live, Bilderarchiv und Videoarchiv
 *
 * Bis 2.2.19 drei Einzelseiten hinter der LoxBerry-Navigationsleiste
 * (live.php, archive.php, videoarchive.php). Seit 2.2.20 gruene Reiter
 * der Startseite (Entscheidung Nr. 43). Der Inhalt ist wortgleich
 * hierher gezogen; die drei Dateien leiten nur noch um.
 *
 * Funktionen statt eingebundener Seiten: die Seiten setzten $ic_cfg,
 * $ic_st, $ic_plugin, $ic_dateien, $ic_i, $ic_n ... - in index.php
 * tragen dieselben Namen die Werte der Startseite. In einer Funktion
 * bleiben sie lokal.
 *
 * Gibt nichts aus, solange keine Funktion gerufen wird.
 */
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

/**
 * Reiter live (seit 2.2.20; bis 2.2.19 die Einzelseite live.php).
 * Der Rumpf ist der Inhalt von live.php zwischen ic_stil.php und
 * </div><!-- /smw --> - unveraendert bis auf die Ueberschrift und die
 * Verweise auf index.php?tab=live. Gerufen nur, wenn der Reiter
 * offen ist (index.php).
 */
function ic_bereich_live()
{
$ic_cfg = ic_config();
$ic_token = isset($ic_cfg['aktionstoken']) ? (string) $ic_cfg['aktionstoken'] : '';
$ic_st = ic_stationen();
$ic_wahl = (isset($_GET['station']) && is_string($_GET['station'])) ? $_GET['station'] : '1';
$ic_nr = (is_numeric($ic_wahl) && (int) $ic_wahl >= 1 && (int) $ic_wahl <= count($ic_st))
       ? (int) $ic_wahl : 1;
?>
<h2><?= ic_txt('COMMON.LIVE') ?></h2>
<p><?= ic_txt('COMMON.LIVETXT') ?></p>

<?php if (!$ic_st) { ?>
<div class="sm-hinweis sm-warn"><?= ic_txt('UI.NICHT_EINGERICHTET') ?></div>
<?php } else { ?>

<?php if (count($ic_st) > 1) { ?>
<div class="sm-legende">
<span><i class="sm-punkt sm-b-lesen"></i> <?= ic_txt('UI.LEG_LESEN') ?></span>
</div>
<div class="sm-knopfreihe">
<?php foreach ($ic_st as $ic_i => $ic_s) { ?>
<a class="sm-btn sm-b-lesen" href="index.php?tab=live&amp;station=<?= $ic_i + 1 ?>"><?= ic_e($ic_s['name']) ?></a>
<?php } ?>
</div>
<?php } ?>

<?php
$ic_url = '/plugins/' . rawurlencode(ic_plugin_ordner()) . '/mjpgproxy.php?token='
        . rawurlencode($ic_token) . '&amp;station=' . $ic_nr;
?>
<?php
/* NEU (Intercom-a1): die Grenzen und die Leser jetzt; laeuft die Zeit ab oder
 * wird der Abruf abgewiesen (503), sagt es die Seite statt eines stehenden
 * oder leeren Bildes. */
list($ic_lv_s, $ic_lv_max) = ic_livebild_grenzen();
$ic_lv_n = ic_livebild_leser();
?>
<p class="sm-klein"><?= ic_e(ic_uebersetzt('UI.LIVE_GRENZEN', array((int) round($ic_lv_s / 60), $ic_lv_max, $ic_lv_n),
    'Ein Abruf laeuft hoechstens ' . (int) round($ic_lv_s / 60) . ' min; hoechstens ' . $ic_lv_max
    . ' Leser gleichzeitig (jetzt ' . $ic_lv_n . ').')) ?></p>
<p class="sm-klein"><?= ic_txt('UI.LIVE_ADRESSE') ?></p>
<p><span class="sm-mono">http://<?= ic_e(ic_host()) ?><?= $ic_url ?></span></p>

<div id="ic_live_hinweis" class="sm-hinweis sm-warn" hidden></div>
<img id="ic_live" src="<?= $ic_url ?>" alt="<?= ic_txt('UI.LIVE_ALT') ?>"
     style="max-width: 960px; width: 75%; height: auto; display: block; margin: 0 auto;">
<script>
(function () {
    var b = document.getElementById('ic_live'), h = document.getElementById('ic_live_hinweis'), fertig = false;
    function zeige(t) {
        if (fertig) { return; }
        fertig = true; h.textContent = t; h.hidden = false;
        b.removeAttribute('src'); b.style.display = 'none';
    }
    b.addEventListener('error', function () { zeige(<?= json_encode(ic_uebersetzt('UI.LIVE_ABGEWIESEN', array($ic_lv_max), 'Das Livebild wurde abgewiesen.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>); });
    setTimeout(function () { zeige(<?= json_encode(ic_uebersetzt('UI.LIVE_ABGELAUFEN', array((int) round($ic_lv_s / 60)), 'Das Livebild ist abgelaufen.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>); }, <?= (int) $ic_lv_s * 1000 ?>);
})();
</script>
<?php } ?>

<?php
}

/**
 * Reiter bilder (seit 2.2.20; bis 2.2.19 die Einzelseite archive.php).
 * Der Rumpf ist der Inhalt von archive.php zwischen ic_stil.php und
 * </div><!-- /smw --> - unveraendert bis auf die Ueberschrift und die
 * Verweise auf index.php?tab=bilder. Gerufen nur, wenn der Reiter
 * offen ist (index.php).
 */
function ic_bereich_bilder()
{
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

<h2><?= ic_txt('COMMON.BACKUP') ?></h2>
<p><?= ic_txt('COMMON.BACKUPTXT') ?></p>

<p><b><?= ic_txt('COMMON.GALINFO1') ?></b> <?= count($ic_dateien) ?>
&nbsp;&nbsp;<b><?= ic_txt('COMMON.PAGE') ?></b> <?= $ic_seite ?>/<?= $ic_letzte ?></p>

<div class="sm-legende">
<span><i class="sm-punkt sm-b-lesen"></i> <?= ic_txt('UI.LEG_LESEN') ?></span>
</div>
<div class="sm-knopfreihe">
<?php if ($ic_seite > 1) { ?>
<a class="sm-btn sm-b-lesen" href="index.php?tab=bilder&amp;page=<?= $ic_seite - 1 ?>">&laquo; <?= ic_txt('COMMON.PREV') ?></a>
<?php } ?>
<?php if ($ic_seite < $ic_letzte) { ?>
<a class="sm-btn sm-b-lesen" href="index.php?tab=bilder&amp;page=<?= $ic_seite + 1 ?>"><?= ic_txt('COMMON.NEXT') ?> &raquo;</a>
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

<?php
}

/**
 * Reiter videos (seit 2.2.20; bis 2.2.19 die Einzelseite videoarchive.php).
 * Der Rumpf ist der Inhalt von videoarchive.php zwischen ic_stil.php und
 * </div><!-- /smw --> - unveraendert bis auf die Ueberschrift und die
 * Verweise auf index.php?tab=videos. Gerufen nur, wenn der Reiter
 * offen ist (index.php).
 */
function ic_bereich_videos()
{
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
<a class="sm-btn sm-b-lesen" href="index.php?tab=videos&amp;page=<?= $ic_seite - 1 ?>">&laquo; <?= ic_txt('COMMON.PREV') ?></a>
<?php } ?>
<?php if ($ic_seite < $ic_letzte) { ?>
<a class="sm-btn sm-b-lesen" href="index.php?tab=videos&amp;page=<?= $ic_seite + 1 ?>"><?= ic_txt('COMMON.NEXT') ?> &raquo;</a>
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

<?php
}

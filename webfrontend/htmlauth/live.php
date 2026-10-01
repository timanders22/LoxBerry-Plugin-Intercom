<?php
/**
 * Intercom - Livebild
 *
 * Der LoxBerry meldet sich bei der Tuerstation an; wer den Strom hier sieht,
 * braucht deren Zugangsdaten nicht. Der Strom verlangt seit 1.6.0 das
 * Zugriffstoken.
 */

require_once "config.php";

$L = LBSystem::readlanguage("language.ini");

require_once "menu.php";
$navbar[2]['active'] = True;
LBWeb::lbheader(ic_titel(), 'https://github.com/timanders22/LoxBerry-Plugin-Intercom/', 'help.html');
require_once __DIR__ . "/ic_stil.php";


$ic_cfg = ic_config();
$ic_token = isset($ic_cfg['aktionstoken']) ? (string) $ic_cfg['aktionstoken'] : '';
$ic_st = ic_stationen();
$ic_wahl = (isset($_GET['station']) && is_string($_GET['station'])) ? $_GET['station'] : '1';
$ic_nr = (is_numeric($ic_wahl) && (int) $ic_wahl >= 1 && (int) $ic_wahl <= count($ic_st))
       ? (int) $ic_wahl : 1;
?>
<div class="smw">
<h1><?= ic_txt('UI.TITEL') ?></h1>
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
<a class="sm-btn sm-b-lesen" href="live.php?station=<?= $ic_i + 1 ?>"><?= ic_e($ic_s['name']) ?></a>
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

</div><!-- /smw -->
<?php
LBWeb::lbfooter();

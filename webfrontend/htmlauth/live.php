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

function ic_txt($schluessel)
{
    global $L;
    return isset($L[$schluessel]) ? ic_e($L[$schluessel]) : $schluessel;
}

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
<p class="sm-klein"><?= ic_txt('UI.LIVE_ADRESSE') ?></p>
<p><span class="sm-mono">http://<?= ic_e(ic_host()) ?><?= $ic_url ?></span></p>

<img src="<?= $ic_url ?>" alt="<?= ic_txt('UI.LIVE_ALT') ?>"
     style="max-width: 960px; width: 75%; height: auto; display: block; margin: 0 auto;">
<?php } ?>

</div><!-- /smw -->
<?php
LBWeb::lbfooter();

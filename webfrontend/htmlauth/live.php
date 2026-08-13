<?php
require_once "config.php";


// This will read your language files to the array $L
$L = LBSystem::readlanguage("language.ini");


// ic_host() beschraenkt HTTP_HOST auf unbedenkliche Zeichen; der
// Ordnername wird ermittelt, nicht eingetragen (Zweitinstallation heisst
// intercom_01). Der Livestrom verlangt seit 1.6.0 das Token.
$loxberryip = ic_host();
$icp        = ic_plugin_ordner();
$ictok      = '?token=' . rawurlencode((string) (ic_config()['aktionstoken'] ?? ''));

$template_title = ic_titel();
$helplink = "https://github.com/timanders22/LoxBerry-Plugin-Intercom/";
$helptemplate = "help.html";

require_once "menu.php";
// Activate the first element
$navbar[2]['active'] = True;
  
// Now output the header, it will include your navigation bar
LBWeb::lbheader($template_title, $helplink, $helptemplate);
require_once __DIR__ . "/ic_stil.php";
 


?>
    <script>
// Adressen fuer script.js - Ordnername und Token stehen nur HIER, nicht in
// der mitgelieferten .js-Datei.
document.body.setAttribute('data-ic-admin', '/admin/plugins/<?= ic_plugin_ordner() ?>');
document.body.setAttribute('data-ic-picture', '/plugins/<?= ic_plugin_ordner() ?>/getpicture.php?hook=false&token=<?= rawurlencode((string)(ic_config()['aktionstoken'] ?? '')) ?>');
</script>
<script type="text/javascript" src="script.js"></script>
<div class="icw">
    <h1><?= $L['UI.INTERCOM'] ?></h1>
    <p><?=$L['COMMON.LIVETXT']?></p>

<p></p>

<p><a href="http://<?= $loxberryip; ?>/plugins/<?= $icp ?>/mjpgproxy.php<?= $ictok ?>" target="_blank">http://<?= $loxberryip; ?>/plugins/<?= $icp ?>/mjpgproxy.php<?= $ictok ?></a></p>

<!-- Fix v1.4.0: MJPEG-Stream direkt als <img> einbinden (statt unvollstaendigem iframe) -->
<img src="http://<?= $loxberryip; ?>/plugins/<?= $icp ?>/mjpgproxy.php<?= $ictok ?>" alt="Intercom Live" style="max-width: 960px; width: 75%; height: auto; display: block; margin: 0 auto;">

<?php
// Finally print the footer 
echo '</div><!-- /icw -->';
LBWeb::lbfooter();
?>

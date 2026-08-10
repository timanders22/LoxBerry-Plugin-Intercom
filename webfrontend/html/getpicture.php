<?php

require_once dirname(dirname(__DIR__)) . '/htmlauth/plugins/'
           . basename(__DIR__) . '/config.php';

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

// Diese Datei loest etwas aus (Archivbild, MQTT, Webhooks) und liest den
// Kamerastrom mit. Bis 1.5.0 ging das ohne jede Pruefung - sie liegt im
// unangemeldeten Bereich.
ic_token_pruefen();

$miniserver_config = LBSystem::get_miniservers();
$lastpicture = __DIR__ . '/lastpicture.jpg';

	
function add_text_to_jpg($jpg_file, $text) {
    if (!function_exists('imagecreatefromjpeg')) { return; } // php-gd fehlt - Zeitstempel ueberspringen statt Fatal Error
    $img = imagecreatefromjpeg($jpg_file);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    imagefilledrectangle($img, 9, 29, strlen($text) * imagefontwidth(5) + 11, 45, $black);
    imagestring($img, 5, 10, 30, $text, $white);
    imagejpeg($img, $jpg_file);
    imagedestroy($img);
}

if(file_exists(LBPCONFIGDIR.'/data.json')){

	header('Content-type:application/json;charset=utf-8');
	$arr = json_decode(file_get_contents(LBPCONFIGDIR.'/data.json'),true);

	$camurl="http://". $miniserver_config[1]["Admin_RAW"] .":". $miniserver_config[1]["Pass_RAW"] ."@". $arr["intercomip"]. "/mjpg/video.mjpg";

	$boundary="\n--";
	// Zeitgrenze auf den Strom legen, BEVOR gelesen wird: ohne sie wartet
	// fread beliebig lange, wenn die Tuerstation die Verbindung offen
	// haelt, aber nichts mehr schickt.
	$ctx = stream_context_create(array('http' => array('timeout' => 8)));
	$f = @fopen($camurl, "r", false, $ctx);
	$r="";
	if(!$f)
	{
	    // Gebremst: bei einer ausgeschalteten Station kaeme sonst bei jedem
	    // Klingeln eine Zeile, und log/ ist eine Ramdisk.
	    ic_log_gebremst('station', 'Die Tuerstation war nicht erreichbar. Adresse und '
	        . 'Zugangsdaten pruefen; die Diagnose im Reiter Einstellungen zeigt die '
	        . 'benutzte Adresse mit maskiertem Passwort.');
	    header('HTTP/1.1 502 Bad Gateway');
	    echo json_encode(array("success"=>false,"error"=>"Tuerstation nicht erreichbar."));
	    exit;
	}else{
			stream_set_timeout($f, 8);
			// WAECHTER gegen die Endlosschleife.
			//
			// Bis 1.5.0 stand hier
			//     while (substr_count($r,"Content-Length") != 2) $r.=fread($f,512);
			// ohne jede Abbruchbedingung. Schickt die Tuerstation andere
			// Kopfzeilen, bricht ein Paket weg oder haengt das Netz, dreht
			// diese Schleife bis zur Zeitgrenze von PHP - und der Aufrufer
			// bekommt eine 500er-Seite statt eines Bildes. In timelapse.php
			// war dieser Waechter bereits richtig eingebaut; hier hat er
			// gefehlt.
			//
			// 4000 Durchlaeufe a 512 Byte sind rund 2 MB - weit mehr als ein
			// einzelner Rahmen, aber endlich.
			$guard = 0;
			while (substr_count($r, "Content-Length") != 2 && $guard++ < 4000) {
				$teil = fread($f, 512);
				if ($teil === false || $teil === '') {
					$meta = stream_get_meta_data($f);
					if (!empty($meta['timed_out']) || feof($f)) { break; }
					continue;
				}
				$r .= $teil;
			}
			$start = strpos($r,"\xff");
			$end   = strpos($r,$boundary,$start)-1;
			$frame = ($start === false) ? "" : substr("$r",$start,$end - $start);
			if ($frame === "" || strlen($frame) < 100) {
				fclose($f);
				header('HTTP/1.1 502 Bad Gateway');
				echo json_encode(array("success"=>false,
					"error"=>"Kein vollstaendiges Bild aus dem Kamerastrom gelesen."));
				exit;
			}

			// Unteilbar schreiben: erst Zwischendatei, dann rename().
			// Treffen zwei Ausloeser gleichzeitig ein - Klingel und
			// Bewegungsmelder sind genau dafuer gebaut -, schrieben bis
			// 1.5.0 beide Prozesse ineinander, und wer die Datei in diesem
			// Augenblick las, bekam ein halbes JPEG.
			ic_datei_ersetzen($lastpicture, $frame);
			
			// add timestamp
			if(isset($arr['timestamp_image'])){
				if($arr['timestamp_image']=="on"){
					$timestamp = date('d.m.Y H:i:s');
					add_text_to_jpg($lastpicture, $timestamp);
				}
			}

			// Mehrere Ausloeser (z.B. Klingel, Briefkasten): ?trigger=NAME
			// landet mit Namenszusatz im Archiv und im MQTT-Thema intercom/trigger/NAME
			$trigger = "";
			if(isset($_REQUEST['trigger'])){
				$trigger = preg_replace('/[^A-Za-z0-9_\-]/', '', substr($_REQUEST['trigger'], 0, 32));
			}
			$triggerpart = ($trigger !== "") ? "-".$trigger : "";

			$archived = false;
			$archive_error = "";
         	if(!isset($_REQUEST['hook'])){ // archivieren nur bei Aufruf OHNE ?hook (Webfrontend nutzt ?hook=false)
				$archiveimg = $folder_img_archive.date("Y.m.d-H:i:s").$triggerpart."-intercom.jpg";
				$written = @file_put_contents($archiveimg, $frame);
				if($written === false){
					$archive_error = "Archivbild konnte nicht geschrieben werden: ".$archiveimg;
					ic_log_gebremst('archiv', 'Ein Archivbild liess sich nicht schreiben: '
					    . $archiveimg . ' - Rechte und freien Platz pruefen.');
				}else{
					$archived = true;
					// add timestamp
					if(isset($arr['timestamp_image'])){
						if($arr['timestamp_image']=="on"){
							$timestamp = date('d.m.Y H:i:s');
							add_text_to_jpg($archiveimg, $timestamp);
						}
					}
				}
			}else{
				$archive_error = "Nicht archiviert: Aufruf mit ?hook-Parameter (nur Vorschau).";
			}

	   }

	fclose($f);

	// KI-Objekterkennung (DeepStack / CodeProject.AI-kompatibel): Bild an
	// /v1/vision/detection senden, erkannte Objekte in JSON/MQTT/Webhooks liefern.
	$ai = array();
	if(isset($arr['ai_enable']) && $arr['ai_enable']=="on" && !empty($arr['ai_url']) && function_exists('curl_init')){
		$minconf = (isset($arr['ai_minconf']) && is_numeric($arr['ai_minconf'])) ? ((float)$arr['ai_minconf'])/100 : 0.5;
		$ch = curl_init($arr['ai_url']);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array(
			'image' => new CURLFile($lastpicture, 'image/jpeg', 'lastpicture.jpg'),
			'min_confidence' => $minconf,
		));
		$airesp = @curl_exec($ch);
		curl_close($ch);
		$aidata = @json_decode((string)$airesp, true);
		if(is_array($aidata) && isset($aidata['predictions']) && is_array($aidata['predictions'])){
			$labels = array();
			foreach($aidata['predictions'] as $p){
				if(isset($p['label'])){
					$labels[] = $p['label'].(isset($p['confidence']) ? " (".round($p['confidence']*100)."%)" : "");
				}
			}
			$ai = array("objects"=>$labels, "count"=>count($labels));
		}
	}

	$url = str_replace(basename($_SERVER['REQUEST_URI']), "", $_SERVER['REQUEST_URI']);
	ic_log('Bild von der Tuerstation geholt'
	    . (isset($trigger) && $trigger !== '' ? ' (Ausloeser ' . $trigger . ')' : '')
	    . (isset($archived) && $archived ? ', archiviert' : ''));
	$json = json_encode(array("success"=>true,"timestamp"=>date("d.m.Y-H:i:s"),"trigger"=>(isset($trigger)?$trigger:""),"ai"=>$ai,"archived"=>(isset($archived)?$archived:false),"archive_info"=>(isset($archive_error)?$archive_error:""),"image"=>'http://'.ic_host().$url.'lastpicture.jpg'));
	echo $json;
	$jsonarr = json_decode($json,true);

	// Bild an Android TV senden (App "Notifications for Android TV", Port 7676)
	if(isset($arr['tv_enable']) && $arr['tv_enable']=="on" && !empty($arr['tv_ip']) && function_exists('curl_init')){
		$tvport = (isset($arr['tv_port']) && is_numeric($arr['tv_port'])) ? (int)$arr['tv_port'] : 7676;
		$tvtitle = "Loxone Intercom";
		$tvmsg = "Jemand hat geklingelt";
		if(isset($trigger) && $trigger !== ""){ $tvmsg = "Ausloeser: ".$trigger; }
		$ch = curl_init('http://'.$arr['tv_ip'].':'.$tvport.'/');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array(
			'type' => '0',
			'title' => $tvtitle,
			'msg' => $tvmsg,
			'duration' => '10',
			'fontsize' => '0',
			'position' => '0',
			'bkgcolor' => '#009688',
			'transparency' => '0',
			'offset' => '0',
			'app' => 'intercom',
			'force' => 'true',
			'interrupt' => '0',
			'filename' => new CURLFile($lastpicture, 'image/jpeg', 'lastpicture.jpg'),
		));
		@curl_exec($ch);
		curl_close($ch);
	}


	// hook nicht aufrufen wenn aus webfrontend aufgerufen
	if(isset($_REQUEST['hook'])){
		exit;
	}

	// MQTT ueber das LoxBerry-Gateway (UDP) statt ueber Bluerhinos\phpMQTT.
	//
	// Die Bibliothek ist seit Jahren unveraendert, baut eine eigene
	// TCP-Verbindung samt Anmeldung auf und war bis 1.5.0 der einzige Weg -
	// faellt sie unter PHP 8 aus, meldet das Plugin gar nichts mehr. Das
	// Gateway gehoert seit LoxBerry 3 zum System und nimmt eine Zeile auf
	// einem UDP-Port entgegen. Ein Paket, keine Verbindung, keine fremde
	// Bibliothek - und vor allem nichts, was den Aufruf aufhalten kann.
	//
	// Der eigene Broker aus den Einstellungen wird damit nicht mehr direkt
	// angesprochen; wer einen fremden Broker benutzt, traegt ihn im
	// MQTT-Gateway von LoxBerry ein. Das ist ohnehin die Stelle, an der er
	// hingehoert - sonst gaebe es zwei Wahrheiten.
	if ( isset($arr['mqtt_enable']) && $arr['mqtt_enable']=="1" ){
		ic_mqtt_senden("intercom", $json);
		if(isset($trigger) && $trigger !== ""){
			ic_mqtt_senden("intercom/trigger/".$trigger, $json);
		}
		if(!empty($ai)){
			ic_mqtt_senden("intercom/ai", json_encode($ai));
		}
	}// end mqtt post


	foreach (array(1,3) as $key => $value)
	if(isset($arr["webhook$value"])){
		if($arr["webhook$value"]!=""){
			$url = $arr["webhook$value"];
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			// Zeitgrenzen: ohne sie wartet cURL unbegrenzt.
			curl_setopt($ch, CURLOPT_TIMEOUT, 5);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
			$result = curl_exec($ch);
			curl_close($ch);
		}
	} // end webhook 1

	foreach (array(2,4) as $key => $value)
	if(isset($arr["webhook$value"])){
		if($arr["webhook$value"]!=""){
			$url = $arr["webhook$value"];
			$url = str_replace("<imgurl>", urlencode($jsonarr['image']) , $url);
			// MIT Zeitgrenze. Bis 1.5.0 stand hier file_get_contents($url)
			// ohne Zusammenhang - PHP nahm dann default_socket_timeout,
			// ueblicherweise 60 Sekunden. Ein abgeschalteter Node-RED
			// genuegte, damit der Aufruf eine Minute stillstand, und der
			// Miniserver wartete mit.
			ic_http_holen($url, 5);
		}
	} // end webhook2

} // end json data exists

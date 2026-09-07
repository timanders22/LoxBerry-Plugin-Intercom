![Logo](icons/icon_256.png)

# LoxBerry-Plugin Intercom

Version 2.2.7 · LoxBerry ab 3.0 · PHP 7.4

Dieses Loxberry Plugin greift Fotos der Loxone Intercom ab um sie für andere Anwendungen vorzuhalten. Das Plugin kann über einen Virtuellen Ausgang aus der Loxone Config heraus aufgerufen werden. Anschließend werden die Bilder über eine URL bereitgestellt und es besteht die möglichkeit einen weitern Webhook aufzurufen um die Bild URL an andere Programme / Scripte weiterzugeben.

## Unterstützte Türstationen

**Loxone Intercom (Gen. 1)**, **Loxone Intercom Gen. 2** und **Loxone Intercom XL**.

Es gibt **keine Modellauswahl** — welcher Abrufweg benutzt wird, ist eine
Einstellung und keine Modellfrage. Zur Wahl stehen im Reiter *Einstellungen*
der MJPEG-Strom unter `/mjpg/video.mjpg`, das Standbild unter
`/jpg/image.jpg` und „erst das Standbild, sonst den Strom"; ab Werk bleibt es
beim Strom. Die Zugangsdaten holt sich das Plugin aus der
Miniserver-Konfiguration, je Station lässt sich ein eigener Standbildpfad
eintragen. Der Reiter *Test* holt ein Bild ohne Umweg über Loxone und
beantwortet in einem Schritt, welcher Weg Ihre Station trägt.

*Berichtigt in 2.2.6:* bis dahin stand hier „nichts modellabhängig
einzustellen" — und 110 Zeilen weiter unten beschrieb dieselbe Datei die
Umstellung auf das Standbild.

Das Plugin ist QuickAndDirty aus einem Beitrag des Loxforum.com entstanden.

https://www.loxforum.com/forum/hardware-zubeh%C3%B6r-sensorik/330121-loxone-intercom-gen2-webschnittstelle-um-bild-video-rauszubekommen/page3#post343007
https://www.loxforum.com/forum/hardware-zubeh%C3%B6r-sensorik/353631-warnung-loxone-intercom-gen-2-aktuell-bekannte-probleme#post356031


## Neu in 2.2.7

Drei Wörter in den erzeugten Loxone-Vorlagen: „Laufzaehler", „Rueckmeldungen"
und „ueberholt" heißen jetzt „Laufzähler", „Rückmeldungen" und „überholt".
Sichtbar waren sie in Loxone Config als Bezeichnung und Anzeigename.

## Neu in 2.2.0

Diese Fassung behebt siebzehn Befunde und baut die Oberfläche auf den
Hausstandard um. Die drei wichtigsten Punkte zuerst.

### Ein halbes Bild wurde als gültiges Bild veröffentlicht

`getpicture.php` schnitt den Rahmen bisher zwischen dem ersten `\xff` und der
Grenzmarke `\n--` heraus. Fehlt die Grenzmarke — Zeitgrenze, abgebrochene
Verbindung, andere Kopfzeilen —, liefert `strpos()` `false`, die berechnete
Länge wird **negativ**, und `substr()` schneidet dann nicht ab, sondern gibt
fast den ganzen Puffer zurück. Gemessen unter PHP 7.4.33 und 8.4.24: aus 2000
Byte Puffer wurde ein „Bild" von 1989 Byte — die Prüfung `strlen($frame) < 100`
greift dabei **nicht**. Das Bruchstück landete als `lastpicture.jpg` im Netz,
im Archiv und als gültige Meldung in MQTT und Webhook.

Gesucht wird jetzt nach den beiden Marken, die ein JPEG selbst trägt: `FFD8`
am Anfang, `FFD9` am Ende. Fehlt eine davon, gibt es kein Bild — und das wird
gemeldet, nicht zurechtgebogen.

### „Alle löschen" im Videoarchiv hat keine Videos gelöscht

Die Schleife suchte `*.jpg`, berechnete den zugehörigen `.avi`-Namen — und
benutzte ihn nie. Nachgebaut mit 25 Aufnahmen: hinterher 0 Vorschaubilder und
25 unverändert daliegende Videos, über die Oberfläche nicht mehr erreichbar.
Wer den Knopf drückte, weil die Karte voll war, hat nichts gewonnen.

### Zwei Knöpfe im Reiter Test konnten nicht funktionieren

Sie zeigten auf `timelapse.php` und `cleanup.php`; beide weisen HTTP-Aufrufe
ab. Über HTTP gemessen: HTTP 403, jedes Mal. Die Arbeit steht jetzt in der
Bibliothek, und Cron wie Knopf rufen dieselbe Funktion auf.

### Was sonst behoben ist

- **Die Sprachdateien waren mit dem Vorgabe-Zerleger nicht lesbar.**
  `parse_ini_file(…, true)` gab für beide Dateien `false` zurück (vier
  unquotierte Werte mit Klammern und Anführungszeichen). Jetzt steht jeder
  Wert in Anführungszeichen; gemessen in allen drei Zerlegermodi und unter
  beiden PHP-Fassungen.
- **Zwölf Textstellen klebten im Browser zusammen** („im ReiterEinstellungendie
  Adresse"). Die Sprachschlüssel sind keine Satzfragmente mehr, sondern ganze
  Sätze mit Platzhaltern.
- **Das Blättern im Archiv übersprang Aufnahmen.** Bei 40 Bildern waren vier
  über die Oberfläche nicht erreichbar, und bei 10 Bildern stand „Seite 1/0".
- **Die Startseite zählte Vorschaubilder als Videos** — 25 Aufnahmen wurden als
  50 Videos angezeigt.
- **`archive.php?submit=1` löschte auf einen bloßen GET hin das gesamte
  Archiv.** Jedes Formular führt jetzt ein Merkmal mit, und gelöscht wird nur
  noch per POST.
- **`mjpgproxy.php` rief `apache_setenv()` ungeschützt auf.** Fehlt mod_php,
  ist das ein Fatal Error — gemessen: Rückgabewert 255, leere Ausgabe, HTTP 500
  ohne Rumpf. Damit wären Livebild und Videoaufzeichnung ausgefallen.
- **Die Zugangsdaten stehen nicht mehr in der Adresse.** Ein Passwort mit `/`
  oder `#` zerlegte die Kameraadresse vollständig (gemessen mit `parse_url()`:
  danach gibt es nicht einmal mehr einen Rechnernamen). Sie gehen jetzt als
  `Authorization`-Kopfzeile hinaus.
- **`getvideo.php` behauptet keinen Erfolg mehr, den es nicht kennt.** Fehlt
  `ffmpeg` oder ist das Archiv nicht beschreibbar, kommt eine Absage statt
  `success:true`.
- **`wget` steht jetzt in `dpkg/apt`** — es wurde aufgerufen, ohne angefordert
  zu sein. Fehlt es trotzdem, weicht das Plugin auf `php` aus.
- **Der Cron schreibt nicht mehr nach `/dev/null`.** Zeitraffer und Aufräumen
  protokollieren, was sie getan haben, und der Reiter Test zeigt, wann sie
  zuletzt gelaufen sind.
- **Die Sperrdatei trägt den Plugin-Ordner im Namen**, ebenso das MQTT-Thema:
  eine Zweitinstallation sperrt die erste nicht mehr aus und schreibt nicht
  mehr auf deren Themen. Auf einer gewöhnlichen Anlage heißt der Ordner
  `intercom`, und die Themen bleiben damit wörtlich dieselben wie in 2.1.13.
  Nur wer das Plugin in einem anders benannten Ordner installiert hat, bekommt
  ein anderes Präfix — im Reiter *MQTT* lässt es sich von Hand auf den alten
  Wert stellen.
- **Die Deinstallation räumt beide Zweitschriften weg.** Bis 2.1.13 blieb eine
  davon mit Token und Zugangsdaten liegen.
- **`ic_titel()` findet die `plugin.cfg` jetzt auch im installierten Zustand.**
  Die beiden bisherigen Kandidaten zeigten auf gemeinsame LoxBerry-Ordner; die
  Funktion fiel still auf ihren Vorgabewert zurück.
- **Rechte vor Inhalt, und eine kurze Schreibung ist ein Fehler.** Eine volle
  Karte meldete sich bisher nicht als solche.

### Neue Funktionen

- **Reiter Test mit echter Selbstprüfung.** Eine Zeile je Frage, mit Haken,
  Kreuz oder Fragezeichen: Token, Zugangsdaten, Erreichbarkeit jeder Station,
  der eigene Endpunkt, ffmpeg, wget, php-gd, sockets, MQTT-Gateway samt
  Autostart, Archivgröße, freier Platz, Aufbewahrungsgrenzen, letzte
  Cron-Läufe, Speicherort, die Reiterstruktur und die erzeugte Loxone-Vorlage.
  Ein Punkt, der nicht geprüft werden konnte, zählt **nicht** als bestanden.
- **Loxone-Vorlage zum Herunterladen** im Reiter *Einbindung in Loxone* — je
  Station ein Befehl für Foto und einen für Video —, dazu die vollständige
  Baustein-Liste zum Nachbauen.
- **Mehrere Türstationen.** Eine Tabelle statt eines Feldes, je Zeile Name,
  Adresse, eigene Zugangsdaten und der Miniserver, von dem die Zugangsdaten
  kommen. Am Endpunkt wählt `&station=2` oder `&station=Haustuer` aus.
- **`bild.php`** liefert das letzte Bild aus und verlangt dafür das Token. Das
  offene `lastpicture.jpg` bleibt vorerst bestehen, damit bestehende Anlagen
  nicht stehenbleiben — im Reiter *Einstellungen* lässt es sich abschalten.
- **Befristete Bildlinks** für Mails und Meldungen: gültig für eine bestimmte
  Zeit und für wenige Abrufe, ohne Zugriffstoken und ohne Auslösewirkung.
- **Standbild statt Videostrom.** Viele Türstationen liefern unter
  `/jpg/image.jpg` ein fertiges Einzelbild. Der Reiter Test misst, ob Ihre das
  tut; umgestellt wird von Hand — ab Werk bleibt der bisherige Weg.
- **Aufräumen nach Platz** (Megabyte), zusätzlich zu Tagen und Anzahl, mit
  Trockenlauf.
- **Aufnahme in festem Takt**, unabhängig von der Klingel — ab Werk aus.
- **Endpunkt-Selbsttest** `?selftest=1`: beantwortet ohne Auslösung, ob das in
  Loxone eingetragene Token noch stimmt.
- **MQTT: Herzschlag und Themenpräfix.** `…/ok` trägt die Loxone-Zeit des
  letzten Abrufs — daran erkennt Loxone einen stillstehenden Dienst. Der
  MQTT-Reiter zeigt das einzutragende Abo und den Zustand des Gateways.

### Was Sie nach dem Update tun sollten

1. Die Oberfläche einmal öffnen und in den Reiter **Test** sehen. Der Knopf
   *Jetzt vollständig prüfen* fragt auch das Netz ab.
2. Wenn dort steht, dass Ihre Station die Standbild-Adresse beantwortet: im
   Reiter *Einstellungen* auf **Erst das Standbild** umstellen.
3. Wenn Sie mehrere Klingeltaster haben: an die virtuellen Ausgänge
   `&trigger=name` anhängen — dann steht im Dateinamen und im MQTT-Thema, wer
   geklingelt hat.
4. Überlegen, ob das letzte Bild weiterhin ohne Token im Netz stehen soll.

### Was bei der Deinstallation liegen bleibt

> **Das Archiv liegt ohne Anmeldung im Netz.** Der Webserver des LoxBerry
> liefert `/legacy/` ohne Anmeldung und mit Verzeichnisauflistung aus —
> am ausgelieferten LoxBerry gemessen (Vhost `000-default.conf`, keine
> `.htaccess` unter `webfrontend/legacy/`). Der Haken *Das letzte Bild nur
> mit Token* schützt nur das letzte Bild. Seit 2.2.6 gibt es daneben den
> Haken *Das Archiv nur mit Anmeldung herausgeben*; er ist **ab Werk aus**,
> weil sich ohne Gerät nicht messen lässt, ob die Galerien danach noch
> Bilder zeigen. Der Reiter *Test* nennt den Zustand in einer Zeile.

Das Bild- und Videoarchiv unter `webfrontend/legacy/<ordner>_data` (und, falls
eingestellt, unter `<Speicherort>/<ordner>_data`) bleibt erhalten — es sind
Ihre Aufnahmen, und eine Deinstallation kann auch ein Umzug sein. Diese
Aufnahmen zeigen Personen vor der Tür; wer sie nicht mehr braucht, entfernt
den Ordner von Hand. Die Deinstallation nennt den Befehl dafür. Token,
Zugangsdaten und beide Zweitschriften werden entfernt.

## Neu in 2.1.12

**Die unterstützten Türstationen stehen jetzt richtig da.** README und Hilfe
nannten nur die *Intercom Version 2*. Tatsächlich arbeitet das Plugin
gleichermaßen mit **Intercom (Gen. 1)**, **Intercom Gen. 2** und
**Intercom XL** — es fragt bei allen denselben MJPEG-Strom unter
`/mjpg/video.mjpg` ab und kennt keine Modellunterscheidung. Wer eine Gen. 1
oder eine XL besitzt, hätte nach der alten Beschreibung angenommen, das Plugin
sei nichts für ihn.

**Der Plugin-Titel wird wieder aus der `plugin.cfg` gelesen.** `ic_titel()`
holte ihn mit `parse_ini_file()` — die Datei kommentiert aber mit `#`, und PHPs
INI-Zerleger kennt seit PHP 7 nur noch `;`. Er las die Kommentare als
Zuweisungen und brach an der ersten Zeile mit einem Sonderzeichen ab.
`parse_ini_file()` gab dann `false` zurück (gemessen unter 7.4.33 und 8.4.24,
beide gleich), das vorangestellte `@` verschluckte die Warnung, und die
Funktion lieferte still ihren Vorgabewert.

Aufgefallen war es nie, weil der Vorgabewert zufällig derselbe Titel ist. Wer
die `plugin.cfg` änderte, hätte sich aber gewundert, warum die Kopfzeile
stehen bleibt — und genau dagegen war die Funktion gebaut.

Jetzt werden die `#`-Kommentarzeilen vor dem Zerlegen entfernt. Nur ganze
Zeilen, deren erstes sichtbares Zeichen `#` ist — ein `#` **innerhalb** eines
Wertes bleibt erhalten.

## Installation

Aktuelle Release URL in das URL Feld bei der Loxberry Plugininstallation kopieren.

## Funktionsumfang

- Manuelle Bildaufname über Trigger
  ( http://<IP>/plugins/intercom/getpicture.php?token=<TOKEN> )
- LoxConfig Intercom Bild an Loxberry Plugin über Virtuellen Ausgang übergeben
- Webhook via POST-/GET-Request bzw. MQTT-Broker
- Bilder Archiv für Bilder die über URL Trigger angestossen wurden
- Video aufnahme durch URL Trigger mit Angabe der Videolänge (1 bis 300 Sekunden;
  ein Wert ausserhalb wird seit 2.2.6 abgewiesen, nicht mehr still gekappt:
  http://<IP>/plugins/intercom/getvideo.php?s=<SEKUNDEN>&token=<TOKEN> )
- Videoaufnahmen mit Zeitstempel (optional) über Trigger
  ( http://<IP>/plugins/intercom/getvideo.php?s=<SEKUNDEN>&token=<TOKEN> )
- Video Archiv
- Video stream Proxy
  ( http://<IP>/plugins/intercom/mjpgproxy.php?token=<TOKEN> ) — der Abrufer
  braucht die Zugangsdaten der Türstation nicht, wohl aber das Zugriffstoken.
  *Berichtigt in 2.2.6:* hier stand „ohne authentifizierung"; seit 1.6.0
  verlangen alle Endpunkte ein Token.

## Anwendungsfälle

- Bild / Video aufzeichnen wenn der Briefkasten über einen Sensor auslöst
- Bild / Video aufzeichnen wenn der Näherungssensor auslöst oder ein Bewegungsmelder
- Bilder der Intercom inhouse archivieren (sonst liegen sie nur auf der SD Karte in der Intercom)

## Dank

Vielen Dank an das Loxberry Forum speziell Laubi und hismastersvoice für die Informationen zum Bilderauslesen.

Folgende Librarys wurden verwendet

- https://github.com/simonwalz/php-mjpeg-proxy
- http://www.lavrsen.dk/foswiki/bin/view/Motion/MjpegFrameGrabPHP

## Änderungen

Die Freigabenotiz zu jeder Fassung steht bei den Releases:
<https://github.com/timanders22/LoxBerry-Plugin-Intercom/releases>

Die Liste darunter stammt vom Vorautor und endet bei 1.3.6. Sie bleibt stehen,
weil es zu v1.3.5 und v1.3.6 **keine Release-Seite** gibt — nachgesehen am
31.08.2026 über die Schnittstelle: 20 Releases, diese beiden nicht darunter.
Für alles ab 2.0.0 gilt der Verweis oben.

1.3.6

- FUntionalität für das Löschen aller Bilder / Videos hinzugefügt.

1.3.5

- Video Webhook funktionierte nicht wurde nun behoben

1.3.4

- Video Webhook (POST/GET/MQTT)

1.3.3

- update fix

1.3.2

- Einstellungen Verzeichnis gewechselt und Speicherung übersteht nun das Update
- Alte Medien werden nach Update nun nicht mehr gelöscht

1.3.1

- ffmpeg hinzugefügt
- Videoaufzeichnung kann über URL Trigger angestossen werden. Videolänge als Parameter.
- video von mjpgproxy.php mit ffmpeg aufnehmen und abspeichern.

## Feature Requests 

- Zeitstempel als option video / Foto
- ffmpeg mit plugin mit installieren
- update testen ob bilderarchiv bleibt
- update testen ob einstellungen bleiben
- eine Einstellmöglichkeit, wo die Bilder genau landen (z.B. auf einem externen USB-Speicher)
- evtl objekterkennung
- Bild an TV senden (Android TV)
- Timelapse Funktion jeden Tag ein Foto schießen zu bestimmter Uhrzeit
- Bild alle X Sekunden mit Bilderkennung!? javscript library?
- Bild bei Briefkaseten trigger (mehrere trigger ermöglichen)
- aktuell geht das Auslesen nur für den ersten hinterlegten Miniserver

- Bilder bei update nicht löschen
- AI erkennung bei getpicture.php 
- schauen was machen andere klingeln noch so was man übernehmen kann

## Umstieg auf 2.0.0

Ab 2.0.0 heißt das Plugin **Intercom**, Ordner `intercom`. Es ist damit
vollständig vom Original getrennt und kann daneben installiert werden. Dafür
wandern die Adressen mit — **bitte vor dem Update lesen.**

### In Loxone Config nachziehen

Jeder virtuelle Ausgang, der auf das Plugin zeigt, muss von
`/plugins/intercom22lox/…` auf `/plugins/intercom/…` umgestellt werden:

| bisher | ab 2.0.0 |
|---|---|
| `http://<LoxBerry>/plugins/intercom22lox/getpicture.php` | `http://<LoxBerry>/plugins/intercom/getpicture.php` |
| `http://<LoxBerry>/plugins/intercom22lox/getvideo.php?s=…` | `http://<LoxBerry>/plugins/intercom/getvideo.php?s=…` |
| `http://<LoxBerry>/plugins/intercom22lox/mjpgproxy.php` | `http://<LoxBerry>/plugins/intercom/mjpgproxy.php` |
| `http://<LoxBerry>/plugins/intercom22lox/lastpicture.jpg` | `http://<LoxBerry>/plugins/intercom/lastpicture.jpg` |

Das Zugriffstoken (seit 1.6.0 Pflicht) hängt unverändert an jeder dieser
Adressen. Die fertigen Adressen samt Token stehen im Reiter
**Einbindung in Loxone**.

### Im MQTT-Gateway nachziehen

Die Themen heißen ebenfalls neu. Das Abo `intercom22lox/#` ist auf `intercom/#`
zu ändern, und die Bausteine, die auf die Themen hören, entsprechend:

| bisher | ab 2.0.0 |
|---|---|
| `intercom22lox` | `intercom` |
| `intercom22lox/video` | `intercom/video` |
| `intercom22lox/trigger/NAME` | `intercom/trigger/NAME` |
| `intercom22lox/ai` | `intercom/ai` |

### Was von selbst passiert

- **Das Bild- und Videoarchiv wird übernommen.** `postinstall.sh` verschiebt
  `webfrontend/legacy/intercom22lox_data` nach `…/intercom_data`. Verschoben
  wird nur, wenn der neue Ordner noch nicht existiert — ein vorhandenes Archiv
  wird unter keinen Umständen überschrieben. Klappt das Verschieben nicht, steht
  der Befehl für die Hand in der Installationsausgabe.
- **Die Protokollansicht findet auch den alten Bestand.** Gesucht werden
  `intercom.log` und `intercom22lox.log`.
- **Der Speicherort auf externem Medium** wird jetzt aus dem Ordnernamen
  abgeleitet statt fest eingetragen. Wer einen Pfad hinterlegt hat, bekommt dort
  einen Ordner `intercom_data`; der alte `intercom22lox_data` bleibt liegen und
  kann nach dem Umstieg von Hand entfernt werden.

### Was Sie selbst tun müssen

Die Konfiguration (Adresse der Intercom, Webhooks, Speicherort, KI-Einstellungen)
wandert **nicht** mit, weil LoxBerry das Plugin als neues führt. Sie ist einmal
neu einzutragen. Ein Blick in die alte Oberfläche vor der Deinstallation lohnt
sich.

## Änderungen in 1.6.0

### Sicherheit — bitte zeitnah aktualisieren

**Befehlsausführung über die Host-Kopfzeile (`getvideo.php`).** Das Skript setzte
`$_SERVER['HTTP_HOST']` unmaskiert in eine Zeichenkette ein, die an die Shell
ging — zweimal, im ffmpeg-Aufruf und im angehängten `wget`. `HTTP_HOST` ist der
Inhalt der Host-Kopfzeile und wird ausschließlich vom Aufrufer bestimmt. Eine
Anfrage mit einer passend gewählten Host-Kopfzeile brachte den LoxBerry dazu,
einen beliebigen Befehl auszuführen. Die Datei liegt unter `webfrontend/html/`,
also im **unangemeldeten** Bereich, und hatte **keine** Zugriffsprüfung: wer den
LoxBerry über HTTP erreichte, hatte eine Befehlszeile darauf.

Behoben durch drei Dinge: für Aufrufe an den eigenen Rechner wird `127.0.0.1`
benutzt (die Host-Kopfzeile wird dafür gar nicht gebraucht), jedes Argument läuft
durch `escapeshellarg()`, und der Aufruf verlangt ein Token. Gegen sechs
Einschleusungsmuster geprüft: vorher fünf ausführbar, nachher keines — der Host
kommt in der Befehlszeile nicht mehr vor.

*Zur Einordnung des ursprünglichen Befunds:* der Prüfer zielte auf
`$_REQUEST['s']`. Dort ist die Lage milder — `is_numeric()` lässt zwar `1e3` oder
`" 12"` durch, aber keine Shell-Sonderzeichen. Aus `s` ließ sich keine
Befehlsausführung bauen, wohl aber eine 1000-Sekunden-Aufnahme. `s` ist jetzt
eine ganze Zahl zwischen 1 und 300.

**Alle Endpunkte waren ungeschützt.** `getpicture.php`, `getvideo.php`,
`videowebhook.php` und `mjpgproxy.php` liegen im unangemeldeten Bereich und
hatten keinerlei Prüfung. Jedes Gerät im Netz konnte den **Kamerastrom der
Haustür** mitlesen, Bilder auslösen und Videoaufzeichnungen starten. Alle vier
verlangen jetzt ein Zugriffstoken; `timelapse.php` und `cleanup.php` laufen nur
noch über die Kommandozeile (Cron) und weisen HTTP-Aufrufe ab.

### Weitere behobene Fehler

- **Endlosschleife im Bildabruf.** `while (substr_count($r,"Content-Length") != 2)`
  hatte keine Abbruchbedingung. In `timelapse.php` war der Wächter bereits
  richtig eingebaut, in `getpicture.php` fehlte er — jetzt dort ebenso, dazu eine
  Zeitgrenze auf dem Datenstrom.
- **Halbe JPEGs.** `file_put_contents("lastpicture.jpg", ...)` schrieb unmittelbar
  in die Zieldatei. Klingel und Bewegungsmelder gleichzeitig, und beide Prozesse
  schrieben ineinander. Jetzt Zwischendatei und `rename()`.
- **Fest eingetragener Ordnername.** `require_once "../../../htmlauth/plugins/<Ordner>/config.php"`
  stand in sechs Dateien, dazu in `script.js`, `live.php` und `videoarchive.php`.
  Bei einer Zweitinstallation heißt der Ordner `intercom_01` — dann zeigten
  alle Verweise auf die Vorgängerinstallation oder ins Leere. Der Ordnername wird
  jetzt ermittelt.
- **Ungeprüfter Dateiname** in `videowebhook.php`: jetzt `basename()`, ein Muster
  und die Prüfung, dass die Datei im Archiv wirklich existiert.
- **Webhooks ohne Zeitgrenze.** `file_get_contents($url)` ohne Zusammenhang nimmt
  `default_socket_timeout`, üblicherweise 60 Sekunden. Ein abgeschalteter
  Node-RED hielt den Aufruf eine Minute fest, und der Miniserver wartete mit.
  Auch die cURL-Aufrufe hatten keine Zeitgrenze.
- **MQTT ohne `Bluerhinos\phpMQTT`.** Jetzt über das LoxBerry-MQTT-Gateway per
  UDP — ein Paket statt einer TCP-Verbindung mit Anmeldung, und keine fremde
  Bibliothek mehr, die unter PHP 8 ausfallen kann.
- **`json_encode`-Falle beim Speichern.** Die alte Prüfung hätte eine geleerte
  Konfigurationsdatei für einen Erfolg gehalten.
- **Falscher Parametername in der Anleitung.** Dort stand `getvideo.php?time=15`;
  das Skript liest `s`. Jede so ausgelöste Aufnahme war die voreingestellten
  20 Sekunden lang.

### Update-Skripte

`${ARGV1}_upgrade` statt `$ARGV1\_upgrade`, und die Sicherung liegt nicht mehr
unter `/tmp` (auf dem LoxBerry eine Ramdisk), sondern unter
`data/plugins/<Ordner>/upgrade_sicherung`. Das Bild- und Videoarchiv wird dabei
**nicht** kopiert, sondern nur an seinen dauerhaften Ort verschoben, falls es
noch im alten Plugin-Ordner liegt — eine Kopie von mehreren Gigabyte in einer
Ramdisk wäre im besten Fall sinnlos.

Der auskommentierte `websocat`-Block in `preroot.sh` ist ersatzlos entfernt.

### Nach dem Update

Die Plugin-Oberfläche einmal öffnen: dort steht das neu erzeugte Zugriffstoken
und die fertigen Adressen für die Virtuellen Ausgänge. **Ohne Token weisen die
Endpunkte jeden Aufruf ab** — bestehende Loxone-Konfigurationen müssen einmalig
angepasst werden. Das ist der Preis dafür, dass die Haustürkamera nicht mehr für
jeden im Netz offensteht.

### Zur Frage einer Abspaltung

Diese Fassung wird in einem **eigenen Repository** gepflegt:
[timanders22/LoxBerry-Plugin-Intercom](https://github.com/timanders22/LoxBerry-Plugin-Intercom).
Daraus folgt dreierlei.

**Die Angaben unter `[AUTHOR]` sind eine eigene Projektkennung geworden.** Sie
sind kein Urhebervermerk, sondern das Feld, aus dem LoxBerry zusammen mit
`[PLUGIN] NAME` die Kennzahl bildet, unter der es Installation und Updates
führt. Bis 1.3.6 stand dort der Originalautor mit seiner privaten Mailadresse —
und `RELEASECFG` zeigte auf sein Repository: LoxBerry hätte diese Fassung beim
nächsten Update-Lauf durch die ältere Originalfassung ersetzt. Die
urheberrechtliche Nennung steht jetzt dort, wo sie hingehört: in `NOTICE`, hier
im README und auf der Hilfeseite.

**`NAME` und `FOLDER` heißen seit 2.0.0 `intercom`.** Damit ist die Trennung vom
Original vollständig: eigene Kennung, eigener Ordner, eigener Name. Beide
Fassungen lassen sich nebeneinander installieren, ohne sich in die Quere zu
kommen. Der Preis steht unten unter [Umstieg auf 2.0.0](#umstieg-auf-200) — die
Adressen wandern mit.

**`AUTOMATIC_UPDATES` steht wieder auf `true`**, jetzt aber auf das eigene
Repository gerichtet. Die frühere Begründung für das Abschalten — ein Downgrade
auf das Repository des ursprünglichen Entwicklers — ist damit gegenstandslos
geworden, nicht widerlegt.

### Herkunft und Lizenz

Grundlage ist [intercom22Lox von **bladerb**](https://github.com/bladerb/intercom22lox),
Apache-Lizenz 2.0. Die hier vorgenommenen Änderungen wurden dem Originalautor
zuerst als Pull Requests angeboten; die vollständige Liste steht in `NOTICE`
(Apache-Lizenz 2.0, Abschnitt 4 b). Der Lizenztext in `LICENCE` ist unverändert.

### Aufgeräumt (Altbestand im Paket)

- **`webfrontend/html/tv/send.py`** — ein Bastelversuch mit fest eingetragener
  fest eingetragener fremder IP-Adresse, der ein pip-Paket voraussetzt, das nirgends
  installiert wird. Er lag im **unangemeldeten** Bereich; Apache hat dort keinen
  Handler für `.py` und hätte die Datei samt der IP als Klartext ausgeliefert.
  Die Funktion selbst steckt längst richtig in `getpicture.php`.
- **`webfrontend/html/archive/` und `webfrontend/html/videoarchive/`** — leer, von
  keiner Zeile Code angesprochen. Das Archiv liegt seit Langem unter
  `webfrontend/legacy/<Ordner>_data/`, damit die Bild- und Videoadressen ein
  Plugin-Update überstehen; `preupgrade.sh` holt Altbestände aus den alten
  Ordnern noch ab.
- **`data/data.json`** — 0 Byte groß und im ganzen Plugin nie angesprochen. Der
  Ordner `data/` ist damit ersatzlos entfallen.
- **`config/data.json` war ebenfalls leer** — und eine leere Datei ist kein
  gültiges JSON (`json_decode('')` liefert `null`, nicht ein leeres Objekt).
  Enthält jetzt `{}`.
- **`preinstall.sh` und `preroot.sh`** — beide bestanden ausschließlich aus
  Variablenzuweisungen der LoxBerry-Vorlage und `exit 0`. `preroot.sh` gab
  zusätzlich `$TEMPDIR`, `$ARGV3` und `$ARGV4` aus — drei Namen, die es in
  dieser Datei gar nicht gibt (sie heißen dort `PTEMPDIR`, `PDIR`, `PVERSION`).
  Diese Zeilen haben also seit jeher leere Werte gedruckt.

  Der Nebeneffekt zählt: das Plugin hat damit **kein Skript mehr, das als root
  läuft** — und es braucht auch keines.
- **`postinstall.sh`** war ebenfalls reine Vorlage. Es legt jetzt die
  Archivordner an, setzt `data.json` auf `0600` (darin stehen das Zugriffstoken
  und die Zugangsdaten der Webhooks), prüft, ob `ffmpeg` vorhanden ist, und weist
  auf die neue Token-Pflicht hin.
- **`.gitignore`** ergänzt, damit `lastpicture.jpg`, `.tmp`-Reste und
  `__pycache__` nicht wieder mit ins Paket wandern.


## Fassung 2.2.8 — die Cron-Aufträge erreichen bestehende Anlagen wieder

**Betrifft nur den Aktualisierungsfall** — den Zustand, den eine
Neuinstallation nie durchläuft.

Bis 2.2.7 lieferte dieses Plugin seine beiden Aufträge als `cron/crontab`
aus. Am Quelltext des Installateurs nachgemessen, nicht vermutet:

| Stelle | Was dort steht |
|---|---|
| `plugininstall.pl:989` | kopiert **nur, wenn** `system/cron/cron.d/<Name>` noch **nicht existiert** |
| `plugininstall.pl:1571` | entfernt sie **ausschließlich beim Deinstallieren** („only on uninstall“) |
| `plugininstall.pl:987-998` | die **Ordner** unter `cron/` werden bei **jedem** Upgrade neu kopiert |

Auf einer bestehenden Anlage erreichte eine Änderung an `cron/crontab` also
**nie** an: die Zeilen, die dort liefen, waren die der **ersten**
Installation. Am 07.09.2026 an der laufenden Anlage bestätigt —
`cron.d/intercom` stammte vom 10.08.2026.

43 Linien dieses Bestands liefern über die Ordner aus; diese und
Smartmeter-classic waren die beiden letzten mit `crontab`.

### Die alte Datei wird entfernt — sonst laufen die Aufträge doppelt

Der Installateur lässt `cron.d/intercom` liegen. Wer nur den Ordner
ergänzt, hat danach **beide** Wege aktiv — der Zeitraffer liefe zweimal je
Minute. Das neue `postroot.sh` entfernt sie.

**Warum postroot und nicht postupgrade**, am Gerät gemessen: `postupgrade`
läuft per `sudo -n -u loxberry`, der Ordner `system/cron/cron.d` ist
`drwxrwxr-x root root`, und `loxberry` ist nicht in der Gruppe root —
`sudo -u loxberry touch …/cron.d/.probe` endet mit *Permission denied*. Wer
im Verzeichnis nicht schreiben darf, kann darin auch nichts löschen.
`postroot` läuft als root und nach `postupgrade`.

### Eine Verhaltensänderung, und sie gehört genannt

Die Archiv-Bereinigung lief bis 2.2.7 um **3:35** — die crontab konnte eine
Uhrzeit nennen. Der Ordnerweg kann das nicht: `cron.daily` startet das
System, auf dieser Anlage um **4:23** (gemessen in `/etc/cron.d/lbdefaults`).
Für eine tägliche Bereinigung ist das ohne Belang. Der Zeitraffer bleibt
minütlich.

Die Ausgabe geht weiter über `logger` ins Systemprotokoll — nicht nach
`/dev/null`, aus demselben Grund wie seit 2.1.13.

## Fassung 2.2.6 — was die Durchsicht vom 04.09.2026 gefunden hat

Eine vollständige Gegenlesung von 2.2.5 mit der Hausprüfkette und zwei neu
gebauten Prüfständen (`endpunkte.py`, 55 Aufrufe in drei Tokenlagen;
`sicherung.py`, sechs Fälle am echten Formular). Das Freigabetor war grün;
gefunden wurden 33 Punkte. Die schwersten:

- **Der Knopf „Einstellungen sichern" lieferte keine Datei.** `ic_stil.php`
  wurde 100 Zeilen vor dem Download-Handler eingebunden und gab dabei 7200
  Byte aus; die Kopfzeilen waren gesendet, bevor `header()` lief. Über HTTP
  gemessen: `Content-type: text/html`, kein `Content-Disposition`, und der
  Rumpf war das Stylesheet mit dem JSON am Ende. Der Fehler steckte schon in
  2.2.3. Jetzt stehen beide Sicherungs-Handler ganz vorn bei den übrigen
  Downloads, und der Stilblock kommt nach dem Seitenkopf.
- **Beim Zurückspielen wurde nur der Schlüssel geprüft, nie der Wert.**
  Gemessen mit einer hochgeladenen Datei: aus dem Zugriffstoken wurde die
  Zahl `12345`, aus dem Speicherpfad ein Feld, aus dem MQTT-Präfix eine
  Zeichenkette mit Zeilenumbruch — und ein Zeilenumbruch im Thema schleust
  eine zweite Zeile in jedes Datagramm ans Gateway. Jeder Wert läuft jetzt
  gegen dieselbe Prüfung wie im Formular; eine Beanstandung heißt weiterhin,
  dass **gar nichts** geschrieben wird.
- **Das Bild- und Videoarchiv liegt ohne Anmeldung im Netz** (siehe oben).
  Neuer Haken, ab Werk aus, plus eine Zeile im Reiter *Test*.
- **Der Knopf zum Zurückspielen hieß „Zurück zur Übersicht"** — derselbe
  Text, den die beiden Galerien für ihren Rückweg benutzen — und hatte als
  einziger schaltender Knopf keine Rückfrage. Beides berichtigt.
- **Die Aufnahmedauer wurde still gekappt.** `?s=999` ergab eine
  300-Sekunden-Aufnahme ohne Meldung, während die Oberfläche „1 bis 300"
  zusagt. Jetzt wird abgewiesen und gemeldet.
- **„90 Tage" im Formular, keine Grenze im Aufräumen.** Auf einer frischen
  Anlage zeigte das Feld 90, `ic_aufbewahrung()` rechnete mit 0 und die
  tägliche Bereinigung löschte nichts. Die Vorgabewerte stehen jetzt in
  `ic_vorgaben()`, aus der beide Seiten schöpfen.
- **Das Zugriffstoken verließ das Haus.** Wer die offene Bildkopie
  abschaltete, bekam die Adresse `bild.php?token=…` in die MQTT-Nutzlast
  (mit Retain, also dauerhaft im Broker) und in jeden Webhook. Jetzt steht
  dort ein befristeter Bildlink (24 Stunden, fünf Abrufe).
- **`php-curl` und `php-xml` fehlten in `dpkg/apt`.** Ohne curl feuerten
  Webhooks, Anzeigegerät und Objekterkennung nie, ohne dass etwas darüber
  stand; ohne php-xml starb die Selbstprüfung mit einem fatalen Fehler. Beide
  Pakete stehen jetzt dort, alle Absprungstellen schreiben eine Zeile, und
  der Reiter *Test* fragt danach.
- **Zeitraffer und Bereinigung teilten sich eine Sperre** und trafen sich
  jeden Tag um 03:35; verlor die Bereinigung, entfiel sie für einen Tag.
  Eigene Sperre, mit kurzer Wartezeit.
- Dazu die tote CSS-Klasse `.sm-warnung` an genau dem Satz, der die
  Sicherungsdatei zum Geheimnis erklärt, eine Legende, die den grünen Punkt
  mit dem orangen Text erklärte, vier ungeschützte Zugriffe in `menu.php`,
  ein `rm -rf` auf einen Pfad, den nichts anlegt, zwei Erfolgsmeldungen ohne
  Erfolgsprüfung und zwölf Textstellen, die etwas anderes sagten als der
  Code tut.

**Was ausdrücklich nicht geprüft ist:** keine Fassung dieser Linie ist je an
einer Anlage gemessen worden. Offen bleiben die Türstation (welcher der drei
Bildwege trägt) und MQTT mit Retain am laufenden Gateway.

## Fassung 2.2.5 — drei Ausgaben roh statt maskiert

Drei Sprachwerte tragen `<b>`; sie liefen durch die maskierende
Ausgabefunktion, und auf dem Bildschirm standen die spitzen Klammern im
Klartext. Umgestellt auf die rohe Ausgabe. Sonst nur die Fassungsnummer.

## Fassung 2.2.4 — der Stat-Zwischenspeicher
Die Protokollkappung (262 144 Byte) stand in
`webfrontend/htmlauth/ic_lib.php:1016`. PHP merkt sich aber die Antworten
von `stat()`: innerhalb **eines** Prozesses sieht `filesize()` die erste
Größe und danach nie wieder eine neue — `file_put_contents(…, FILE_APPEND)`
macht den Eintrag nicht ungültig. Die Kappung fällt dann still aus.

Gemessen am 29.08.2026, 20 000 Zeilen im selben Prozess:

| | ohne `clearstatcache` | mit |
|---|---|---|
| PHP 7.4.33 | 1 220 000 Byte, **nicht gekappt** | 220 332 Byte, gekappt |
| PHP 8.4.24 | 220 332 Byte, gekappt | 220 332 Byte, gekappt |

Die beiden PHP-Fassungen verhalten sich also verschieden — und LoxBerry 3.x
fährt 7.4. Wer nur unter 8.4 misst, sieht den Fehler nie. Folgen hatte das
hier nicht: die Aufrufer sind kurzlebig, und ein **frischer** Prozess kappt
richtig. Eine Funktion darf aber nicht davon abhängen, wer sie wie oft ruft.

Abhilfe: `clearstatcache(true, …)` **vor** dem Tor; der zweite Parameter
beschränkt das Leeren auf diese eine Datei. Dasselbe Muster tragen Robonect,
Saugroboter, SignalBot, Octopus, Sprachsteuerung und WärmepumpeCloud schon
länger — es ist am 29.08.2026 im ganzen Bestand nachgezogen worden.


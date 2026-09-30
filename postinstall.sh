#!/bin/bash
# Intercom - postinstall (laeuft als Benutzer loxberry)
#
# Bis 1.5.0 stand hier nur die LoxBerry-Vorlage: sechs Variablenzuweisungen
# und exit 0. Getan wurde nichts.

ARGV3=$3   # Installationsordner des Plugins
ARGV5=$5   # Wurzelverzeichnis des LoxBerry

BASE="${ARGV5:-$LBHOMEDIR}"
PDIR="${ARGV3:-intercom}"
PCONFIG="$BASE/config/plugins/$PDIR"
PDATA="$BASE/data/plugins/$PDIR"
LEGACY="$BASE/webfrontend/legacy/${PDIR}_data"
CF="$PCONFIG/data.json"

mkdir -p "$PCONFIG" "$BASE/log/plugins/$PDIR" "$PDATA" 2>/dev/null

# Das Archiv liegt unter webfrontend/legacy/, damit die Bild- und
# Videoadressen ein Plugin-Update ueberstehen.
#
# ARCHIV AUS DER ZEIT VOR 2.0.0 UEBERNEHMEN
#
# Bis 1.6.0 hiess der Ordner "intercom22lox", seit 2.0.0 heisst er "intercom".
# Das Archiv wandert nicht von selbst mit. Verschoben wird NUR, wenn der neue
# Ordner noch nicht existiert - ein vorhandenes Archiv wird unter keinen
# Umstaenden ueberschrieben.
ALT="$BASE/webfrontend/legacy/intercom22lox_data"
if [ "$PDIR" != "intercom22lox" ] && [ ! -e "$LEGACY" ] && [ -d "$ALT" ]; then
    if mv "$ALT" "$LEGACY" 2>/dev/null; then
        echo "<OK> Bild- und Videoarchiv aus intercom22lox_data uebernommen."
    else
        echo "<WARNING> Das alte Archiv liess sich nicht verschieben."
        echo "<WARNING> Von Hand: sudo mv '$ALT' '$LEGACY'"
    fi
elif [ -d "$ALT" ] && [ -e "$LEGACY" ]; then
    echo "<INFO> Es gibt noch ein altes Archiv unter intercom22lox_data."
    echo "<INFO> Das neue ist bereits angelegt - deshalb wurde nichts angefasst."
fi

mkdir -p "$LEGACY/img_archive" "$LEGACY/video_archive" "$LEGACY/timelapse" 2>/dev/null

# ---------- Die Einstellungen zurueckholen ----------
#
# Beim Update kopiert der Installer config/ aus dem Archiv ueber
# config/plugins/<ordner>/ - und dort liegt ein LEERES data.json ({}).
# Zurueckgespielt wird NUR, wenn nichts Brauchbares dasteht: eine Sicherung,
# die eine gueltige Konfiguration ueberschreibt, waere schlimmer als gar keine.
#
# Erkannt wird der Verlust an dreierlei: die Datei fehlt, sie ist leer, oder
# sie ist zeichengenau die mitgelieferte Vorgabe (Pruefsumme unten). Der letzte
# Fall ist der eigentliche - genau so sieht die Datei nach dem Kopierschritt
# des Installers aus.
#
# Gesucht werden ZWEI Namen: seit 2.2.0 schreiben Oberflaeche und
# preupgrade.sh denselben (<ordner>.backup.json); der zweite stammt von
# Anlagen, die von 2.1.13 oder frueher kommen.
VORGABE_SHA="ca3d163bab055381827226140568f3bef7eaac187cebd76878e0b63e9e442356"

verloren() {
    # Erst pruefen, DANN lesen. Bis 2.1.13 stand hier
    #     INHALT=$(tr -d ' \t\n\r' < "$CF" 2>/dev/null)
    # ohne vorheriges Test auf die Datei. Das 2>/dev/null gilt fuer tr, nicht
    # fuer die fehlgeschlagene Eingabeumleitung - gemessen: die Shell meldet
    # trotzdem "No such file or directory", und die Zeile landet im
    # Installationsprotokoll, wo sie wie ein Fehler aussieht.
    [ -f "$CF" ] || return 0
    [ -s "$CF" ] || return 0
    INHALT=$(tr -d ' \t\n\r' < "$CF" 2>/dev/null)
    [ -z "$INHALT" ] && return 0
    [ "$INHALT" = "{}" ] && return 0
    IST=$(sha256sum "$CF" 2>/dev/null | cut -d" " -f1)
    [ -n "$IST" ] && [ "$IST" = "$VORGABE_SHA" ] && return 0
    return 1
}

# Liegt die Marke aus preupgrade.sh, ist die Zweitschrift die von eben - und
# was jetzt in data.json steht und NICHT die Vorgabe ist, ist waehrend der
# Installation entstanden. Bis 2.2.10 war genau das der Schaden: die
# Plugin-Seite hatte in der Luecke ein Token geschrieben, verloren() sagte
# "vorhanden", und die Rueckholung unterblieb (in WSL nachgestellt,
# Pruefung-Upgradeluecke-2026-09-17, Fall F). Dann wird ohne Blick auf
# data.json zurueckgeholt - aber nur aus <ordner>.backup.json und nur, wenn
# sie selbst Token oder Station traegt.
#
# BERICHTIGT 2.2.13 (I1, I2, I8): hier stand, der alte Name .backup.data.json
# werde "nie blind genommen". Der Zweig darunter nahm ihn trotzdem blind -
# jede nichtleere Datei, ohne Blick auf den Inhalt, und auch bei einer
# NEUINSTALLATION (Installer-Pruefer, N2b und V1). Seit 2.2.13:
#   * zurueckgespielt wird NUR bei liegender Marke (Entscheidung 1, ohne
#     Altersgrenze); ohne Marke ist es eine Neuinstallation, und preinstall.sh
#     hat liegengebliebene Zweitschriften schon nach .alt gelegt,
#   * jeder Kandidat muss Token oder Station tragen (mit_inhalt), sonst
#     <WARNING> statt <OK>,
#   * nach jeder Rueckholung 0600 auf beide Zweitschriftnamen (I7).
MARKE="$BASE/data/plugins/$PDIR.upgrade_laeuft"
ZWEIT="$BASE/config/plugins/$PDIR.backup.json"
ZWEIT_ALT="$BASE/config/plugins/$PDIR.backup.data.json"
KAPUTT="$BASE/config/plugins/$PDIR.data.json.kaputt"
mit_inhalt() {
    [ -s "$1" ] || return 1
    php -r '$d = json_decode((string) @file_get_contents($argv[1]), true);
        if (!is_array($d)) { exit(1); }
        $t = isset($d["aktionstoken"]) && is_string($d["aktionstoken"]) && $d["aktionstoken"] !== "";
        $s = (isset($d["stationen"]) && is_array($d["stationen"]) && count($d["stationen"]) > 0)
          || (isset($d["intercomip"]) && is_string($d["intercomip"]) && trim($d["intercomip"]) !== "");
        exit(($t || $s) ? 0 : 1);' "$1" 2>/dev/null
}
zweitschriften_rechte() {
    for z in "$ZWEIT" "$ZWEIT_ALT"; do
        [ -f "$z" ] && [ ! -L "$z" ] && chmod 0600 "$z" 2>/dev/null
    done
}

if [ -f "$MARKE" ] && ! verloren && mit_inhalt "$ZWEIT" && ! cmp -s "$ZWEIT" "$CF"; then
    if cp -p "$ZWEIT" "$CF" 2>/dev/null; then
        chmod 0600 "$CF" 2>/dev/null
        zweitschriften_rechte
        echo "<OK> Einstellungen aus der Zweitschrift wiederhergestellt ($ZWEIT)."
        echo "<OK> data.json war waehrend der Installation veraendert worden."
    else
        echo "<WARNING> Die Zweitschrift liess sich nicht zurueckspielen."
        echo "<WARNING> Sie liegt unter $ZWEIT und kann von Hand kopiert werden."
    fi
elif verloren && [ -f "$MARKE" ]; then
    ZURUECK=""
    UNBRAUCHBAR=""
    for kandidat in "$ZWEIT" "$ZWEIT_ALT"; do
        if mit_inhalt "$kandidat"; then ZURUECK="$kandidat"; break; fi
        [ -s "$kandidat" ] && UNBRAUCHBAR="$UNBRAUCHBAR $kandidat"
    done
    if [ -n "$ZURUECK" ]; then
        if cp -p "$ZURUECK" "$CF" 2>/dev/null; then
            chmod 0600 "$CF" 2>/dev/null
            zweitschriften_rechte
            echo "<OK> Einstellungen aus der Zweitschrift wiederhergestellt ($ZURUECK)."
        else
            echo "<WARNING> Die Zweitschrift liess sich nicht zurueckspielen."
            echo "<WARNING> Sie liegt unter $ZURUECK und kann von Hand kopiert werden."
        fi
    else
        echo "<WARNING> Aktualisierung: data.json ist leer, und keine Zweitschrift traegt"
        echo "<WARNING> brauchbare Einstellungen (Zugriffstoken oder Tuerstation)."
        [ -n "$UNBRAUCHBAR" ] && echo "<WARNING> Nicht eingespielt, weil ohne lesbaren Inhalt:$UNBRAUCHBAR"
        [ -f "$KAPUTT" ] && echo "<WARNING> Upgrade, Konfiguration unlesbar: der alte Inhalt liegt unter $KAPUTT."
        echo "<WARNING> Bitte die Einstellungen neu eintragen oder eine Sicherung zurueckspielen."
    fi
elif verloren; then
    REST=""
    for kandidat in "$ZWEIT" "$ZWEIT_ALT"; do
        [ -e "$kandidat" ] && REST="$REST $kandidat"
    done
    if [ -n "$REST" ]; then
        echo "<WARNING> Neuinstallation: diese Zweitschriften einer frueheren Installation werden NICHT eingespielt:$REST"
    fi
    echo "<INFO> Neuinstallation - die Einstellungen entstehen beim ersten Oeffnen der Plugin-Seite."
else
    echo "<OK> Die Einstellungen sind vorhanden."
fi

# Die Marke erst NACH der Rueckholung entfernen - vorher haette die
# Plugin-Seite in dieser Sekunde wieder schreiben duerfen.
rm -f "$MARKE" 2>/dev/null
if [ -e "$MARKE" ]; then
    echo "<WARNING> Die Marke der laufenden Aktualisierung liess sich nicht entfernen: $MARKE"
    echo "<WARNING> Die Plugin-Seite speichert bis zu einer Stunde lang nichts, Zeitraffer"
    echo "<WARNING> und Bereinigung setzen aus, bis die Marke entfernt ist."
fi

# In data.json stehen das Zugriffstoken und die Zugangsdaten fremder Dienste.
# Die Datei darf nicht fuer alle lesbar sein.
if [ -f "$CF" ]; then
    chmod 0600 "$CF" 2>/dev/null
fi

# ---------- Voraussetzungen melden ----------
for prog in ffmpeg wget; do
    if command -v "$prog" >/dev/null 2>&1; then
        echo "<OK> $prog vorhanden."
    else
        echo "<WARNING> $prog fehlt. Nachinstallieren: sudo apt-get install -y $prog"
    fi
done
if php -r 'exit(function_exists("imagecreatefromjpeg") ? 0 : 1);' 2>/dev/null; then
    echo "<OK> php-gd vorhanden - Zeitstempel im Bild moeglich."
else
    echo "<WARNING> php-gd fehlt. Ohne php-gd bleibt der Zeitstempel im Bild aus."
fi
if php -r 'exit(function_exists("socket_create") ? 0 : 1);' 2>/dev/null; then
    echo "<OK> PHP-Erweiterung sockets vorhanden - MQTT moeglich."
else
    echo "<WARNING> Die PHP-Erweiterung sockets fehlt. Ohne sie sendet das Plugin"
    echo "<WARNING> nichts an das MQTT-Gateway: sudo apt-get install -y php-sockets"
fi

# Die Erstanleitung (Oberflaeche oeffnen, damit ein Zugriffstoken entsteht)
# nur, wenn data.json nach der Rueckholung keines traegt. postinstall.sh
# laeuft auch bei jedem Upgrade; danach war der Rat falsch und legte nahe,
# das Token sei verloren. Gefragt wird dasselbe wie in zweit_mit_inhalt()
# nach dem Aktionstoken - hier allein danach, denn um das Token geht die
# Anleitung.
hat_token() {
    [ -s "$1" ] || return 1
    php -r '$d = json_decode((string) @file_get_contents($argv[1]), true);
        exit(is_array($d) && isset($d["aktionstoken"]) && is_string($d["aktionstoken"])
             && $d["aktionstoken"] !== "" ? 0 : 1);' "$1" 2>/dev/null
}
if hat_token "$CF"; then
    echo "<OK> Installation abgeschlossen, Einstellungen uebernommen - das Zugriffstoken ist vorhanden."
    echo "<INFO> Der Reiter Test sagt in einer Zeile je Frage, ob die Einrichtung traegt."
else
    echo "<INFO> WICHTIG seit 1.6.0: alle Endpunkte verlangen ein Zugriffstoken."
    echo "<INFO> Bitte die Plugin-Oberflaeche einmal oeffnen - dort wird eines"
    echo "<INFO> erzeugt, und der Reiter Test sagt in einer Zeile je Frage, ob die"
    echo "<INFO> Einrichtung traegt."
fi
echo "<INFO> NEU in 2.2.0: mehrere Tuerstationen, eine Loxone-Vorlage zum"
echo "<INFO> Herunterladen, ein Aufraeumen nach Platz und ein Bildabruf, der"
echo "<INFO> das Token verlangt (bild.php)."

exit 0

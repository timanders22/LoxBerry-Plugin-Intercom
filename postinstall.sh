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

if verloren; then
    ZURUECK=""
    for kandidat in "$BASE/config/plugins/$PDIR.backup.json" \
                    "$BASE/config/plugins/$PDIR.backup.data.json"; do
        if [ -s "$kandidat" ]; then ZURUECK="$kandidat"; break; fi
    done
    if [ -n "$ZURUECK" ]; then
        if cp -p "$ZURUECK" "$CF" 2>/dev/null; then
            chmod 0600 "$CF" 2>/dev/null
            echo "<OK> Einstellungen aus der Zweitschrift wiederhergestellt ($ZURUECK)."
        else
            echo "<WARNING> Die Zweitschrift liess sich nicht zurueckspielen."
            echo "<WARNING> Sie liegt unter $ZURUECK und kann von Hand kopiert werden."
        fi
    else
        echo "<INFO> Keine Zweitschrift vorhanden - offenbar eine Erstinstallation."
    fi
else
    echo "<OK> Die Einstellungen sind vorhanden."
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

echo "<INFO> WICHTIG seit 1.6.0: alle Endpunkte verlangen ein Zugriffstoken."
echo "<INFO> Bitte die Plugin-Oberflaeche einmal oeffnen - dort wird eines"
echo "<INFO> erzeugt, und der Reiter Test sagt in einer Zeile je Frage, ob die"
echo "<INFO> Einrichtung traegt."
echo "<INFO> NEU in 2.2.0: mehrere Tuerstationen, eine Loxone-Vorlage zum"
echo "<INFO> Herunterladen, ein Aufraeumen nach Platz und ein Bildabruf, der"
echo "<INFO> das Token verlangt (bild.php)."

exit 0

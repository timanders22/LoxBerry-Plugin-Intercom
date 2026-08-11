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
LEGACY="$BASE/webfrontend/legacy/${PDIR}_data"

mkdir -p "$PCONFIG" "$BASE/log/plugins/$PDIR" "$BASE/data/plugins/$PDIR" 2>/dev/null

# Das Archiv liegt unter webfrontend/legacy/, damit die Bild- und
# Videoadressen ein Plugin-Update ueberstehen.
#
# ARCHIV AUS DER ZEIT VOR 2.0.0 UEBERNEHMEN
#
# Bis 1.6.0 hiess der Ordner "intercom22lox", seit 2.0.0 heisst er "intercom".
# Das Archiv wandert nicht von selbst mit: es liegt unter
# webfrontend/legacy/<Ordner>_data und waere nach der Umbenennung nicht mehr
# sichtbar. Deshalb wird es hier verschoben.
#
# Verschoben wird NUR, wenn der neue Ordner noch nicht existiert - ein
# vorhandenes Archiv wird unter keinen Umstaenden ueberschrieben.
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

# In data.json stehen das Zugriffstoken und die Zugangsdaten fremder
# Dienste (Webhooks, KI-Erkennung). Die Datei darf nicht fuer alle lesbar
# sein. Bis 1.5.0 wurden die Rechte nur beim Speichern aus der Oberflaeche
# gesetzt - bis dahin lag sie mit den Vorgaberechten da.
if [ -f "$PCONFIG/data.json" ]; then
    chmod 0600 "$PCONFIG/data.json" 2>/dev/null
fi

# ---------- Zweites Netz fuer die Einstellungen ----------
#
# Beim Update kopiert der Installer config/ aus dem Archiv ueber
# config/plugins/<ordner>/ - und dort liegt ein LEERES data.json. Bisher
# haing alles daran, dass preupgrade.sh gesichert und postupgrade.sh
# zurueckgestellt hat. Reisst diese Kette an einer Stelle - ein Neustart
# mittendrin, ein abgebrochener Lauf, ein anderer Ordnername -, sind
# saemtliche Einstellungen weg, und es steht nirgends ein Fehler.
#
# Die Oberflaeche schreibt deshalb bei jedem Speichern eine
# Zweitschrift NEBEN den Ordner: config/plugins/<ordner>.backup.json. Die
# wird vom Installer nicht angefasst. Ist die eigentliche Datei leer oder
# fehlt sie, wird von hier aus zurueckgespielt.
#
# Es wird NUR zurueckgespielt, wenn nichts Brauchbares dasteht. Eine
# Sicherung, die eine gueltige Konfiguration ueberschreibt, waere schlimmer
# als gar keine.
ZWEIT="$BASE/config/plugins/$PDIR.backup.json"
CF="$PCONFIG/data.json"
if [ -f "$ZWEIT" ]; then
    INHALT=$(tr -d ' \t\n\r' < "$CF" 2>/dev/null)
    if [ ! -s "$CF" ] || [ "$INHALT" = "{}" ] || [ -z "$INHALT" ]; then
        if cp -p "$ZWEIT" "$CF" 2>/dev/null; then
            chmod 0600 "$CF" 2>/dev/null
            echo "<OK> Einstellungen aus der Zweitschrift wiederhergestellt."
        else
            echo "<WARNING> Die Zweitschrift liess sich nicht zurueckspielen."
            echo "<WARNING> Sie liegt unter $ZWEIT und kann von Hand kopiert werden."
        fi
    fi
fi

if command -v ffmpeg >/dev/null 2>&1; then
    echo "<OK> ffmpeg vorhanden - Videoaufzeichnung moeglich."
else
    echo "<WARNING> ffmpeg fehlt. Ohne ffmpeg gibt es keine Videoaufzeichnung."
    echo "<WARNING> Nachinstallieren: sudo apt-get install -y ffmpeg"
fi

echo "<INFO> WICHTIG ab 1.6.0: alle Endpunkte verlangen ein Zugriffstoken."
echo "<INFO> Bitte die Plugin-Oberflaeche einmal oeffnen - dort wird eines"
echo "<INFO> erzeugt und die fertigen Adressen fuer Loxone werden angezeigt."

# ==== NETZ-EINSTELLUNGEN-UPDATE (automatisch eingefuegt, nicht doppeln) ====
# Zurueckspielen aus der Zweitschrift - aber NUR, wenn die Datei des Nutzers
# wirklich verloren ist. Erkannt wird das an dreierlei: sie fehlt, sie ist
# leer, oder sie ist zeichengenau die mitgelieferte Vorgabe (Pruefsumme
# unten). Der letzte Fall ist der eigentliche: genau so sieht die Datei nach
# dem Kopierschritt des Installers aus.
#
# Eine gueltige Konfiguration wird NIE ueberschrieben. Eine Sicherung, die
# echte Einstellungen ersetzt, waere schlimmer als gar keine.
NETZ_BASE="${5:-$LBHOMEDIR}"
NETZ_PDIR="${3:-intercom}"
NETZ_CFG="$NETZ_BASE/config/plugins/$NETZ_PDIR"
netz_zurueck() {
    datei=$1; soll=$2
    ziel="$NETZ_CFG/$datei"
    zweit="$NETZ_BASE/config/plugins/$NETZ_PDIR.backup.$datei"
    [ -f "$zweit" ] || return 0
    verloren=0
    if [ ! -f "$ziel" ] || [ ! -s "$ziel" ]; then
        verloren=1
    else
        ist=$(sha256sum "$ziel" 2>/dev/null | cut -d" " -f1)
        [ -n "$ist" ] && [ "$ist" = "$soll" ] && verloren=1
    fi
    if [ "$verloren" = "1" ]; then
        if cp -p "$zweit" "$ziel" 2>/dev/null; then
            echo "<OK> $datei aus der Zweitschrift wiederhergestellt."
        else
            echo "<WARNING> $datei liess sich nicht zurueckspielen. Die Sicherung"
            echo "<WARNING> liegt unter $zweit und kann von Hand kopiert werden."
        fi
    fi
}
netz_zurueck "data.json" "ca3d163bab055381827226140568f3bef7eaac187cebd76878e0b63e9e442356"

exit 0

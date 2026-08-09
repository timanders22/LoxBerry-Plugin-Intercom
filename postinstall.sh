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

if command -v ffmpeg >/dev/null 2>&1; then
    echo "<OK> ffmpeg vorhanden - Videoaufzeichnung moeglich."
else
    echo "<WARNING> ffmpeg fehlt. Ohne ffmpeg gibt es keine Videoaufzeichnung."
    echo "<WARNING> Nachinstallieren: sudo apt-get install -y ffmpeg"
fi

echo "<INFO> WICHTIG ab 1.6.0: alle Endpunkte verlangen ein Zugriffstoken."
echo "<INFO> Bitte die Plugin-Oberflaeche einmal oeffnen - dort wird eines"
echo "<INFO> erzeugt und die fertigen Adressen fuer Loxone werden angezeigt."
exit 0

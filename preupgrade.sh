#!/bin/bash
# Intercom - preupgrade (laeuft als Benutzer loxberry)

ARGV1=$1   # Temporaerer Ordner waehrend der Installation
ARGV3=$3   # Installationsordner des Plugins
ARGV5=$5   # Wurzelverzeichnis des LoxBerry

BASE="${ARGV5:-$LBHOMEDIR}"
PDIR="${ARGV3:-intercom}"

# Geschweifte Klammern statt Rueckstrich.
#
# Bis 1.5.0 stand hier /tmp/$ARGV1\_upgrade. Das funktioniert zwar in bash
# (der Rueckstrich beendet den Variablennamen), ist aber genau die Sorte
# Schreibweise, die beim naechsten Umbau kippt - etwa wenn jemand die Zeile
# in eine POSIX-Shell uebernimmt. ${ARGV1}_upgrade ist eindeutig.
#
# Wichtiger noch: die Sicherung liegt jetzt NICHT mehr unter /tmp.
# /tmp ist auf dem LoxBerry eine Ramdisk. Erzwingt die Installation
# zwischendurch einen Neustart oder faellt der Strom aus, ist sie leer -
# und mit ihr die gesamte Konfiguration.
SICHER="$BASE/data/plugins/$PDIR/upgrade_sicherung"
ALT_TMP="/tmp/${ARGV1}_upgrade"

echo "<INFO> Sichere die Konfiguration nach $SICHER"
rm -rf "$SICHER" 2>/dev/null
mkdir -p "$SICHER/config" 2>/dev/null
chmod 0700 "$SICHER" 2>/dev/null

if [ -d "$BASE/config/plugins/$PDIR" ]; then
    cp -a "$BASE/config/plugins/$PDIR/." "$SICHER/config/" 2>/dev/null
    # In data.json stehen Zugangsdaten fremder Dienste und das Zugriffstoken.
    chmod 0600 "$SICHER/config/data.json" 2>/dev/null
    echo "<OK> Konfiguration gesichert."
else
    echo "<INFO> Keine Konfiguration vorhanden - offenbar eine Erstinstallation."
fi

# Die Mediendateien werden NICHT nach /tmp kopiert, sondern nur an ihren
# dauerhaften Ort verschoben, falls sie noch im alten Plugin-Ordner liegen.
# Ein Bild- und Videoarchiv kann viele Gigabyte gross sein - eine Kopie
# davon in einer Ramdisk waere im besten Fall sinnlos und im schlechtesten
# der Grund, warum der LoxBerry waehrend des Updates keinen Speicher mehr
# hat.
LEGACY="$BASE/webfrontend/legacy/${PDIR}_data"
mkdir -p "$LEGACY/img_archive" "$LEGACY/video_archive" 2>/dev/null

for paar in "archive:img_archive" "videoarchive:video_archive"; do
    quelle="$BASE/webfrontend/html/plugins/$PDIR/${paar%%:*}"
    ziel="$LEGACY/${paar##*:}"
    if [ -d "$quelle" ] && [ -n "$(ls -A "$quelle" 2>/dev/null)" ]; then
        echo "<INFO> Verschiebe Altbestand aus $quelle"
        # -n: vorhandene Dateien am Ziel nicht ueberschreiben.
        cp -an "$quelle/." "$ziel/" 2>/dev/null
        echo "<OK> Altbestand uebernommen."
    fi
done

exit 0

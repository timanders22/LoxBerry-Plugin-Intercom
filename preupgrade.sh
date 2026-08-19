#!/bin/bash
# Intercom - preupgrade (laeuft als Benutzer loxberry)

ARGV1=$1   # Temporaerer Ordner waehrend der Installation
ARGV3=$3   # Installationsordner des Plugins
ARGV5=$5   # Wurzelverzeichnis des LoxBerry

BASE="${ARGV5:-$LBHOMEDIR}"
PDIR="${ARGV3:-intercom}"

# ==== EINE Zweitschrift, nicht zwei ====
#
# Der Installer kopiert config/* aus dem Archiv ueber config/plugins/<ordner>
# (plugininstall.pl, cp -r ohne -n) und ueberschreibt dabei die Datei des
# Nutzers. Die Rettung laeuft ueber eine Zweitschrift NEBEN dem Ordner - die
# fasst der Installer nicht an.
#
# Bis 2.1.13 gab es dafuer ZWEI Namen: die Oberflaeche schrieb
# <ordner>.backup.json, dieses Skript <ordner>.backup.data.json. Zwei
# Sicherungsverfahren sind eines zu viel - und die Deinstallation raeumte nur
# das eine weg, sodass Token und Zugangsdaten liegen blieben. Seit 2.2.0
# schreiben beide Stellen denselben Namen.
ZWEIT="$BASE/config/plugins/$PDIR.backup.json"
CF="$BASE/config/plugins/$PDIR/data.json"

if [ -s "$CF" ]; then
    if cp -p "$CF" "$ZWEIT" 2>/dev/null; then
        chmod 0600 "$ZWEIT" 2>/dev/null
        echo "<OK> Zweitschrift der Einstellungen angelegt: $ZWEIT"
    else
        echo "<WARNING> Die Zweitschrift liess sich nicht anlegen: $ZWEIT"
        echo "<WARNING> Bitte die Einstellungen nach dem Update pruefen."
    fi
else
    echo "<INFO> Keine Konfiguration vorhanden - offenbar eine Erstinstallation."
fi

# ==== KEINE Sicherung mehr unter data/plugins/<ordner> ====
#
# Bis 2.1.13 legte dieses Skript dort ein upgrade_sicherung/ an und meldete
# "<OK> Konfiguration gesichert." - postupgrade.sh beschreibt selbst, dass der
# Installer genau dieses Verzeichnis Sekunden spaeter abraeumt ("Removing old
# installation" vor "Deleting plugin folders", belegt im Installationsprotokoll
# vom 12.08.2026). Die Kette war konstruktionsbedingt tot, und im Protokoll
# stand trotzdem eine Erfolgsmeldung. Eine Meldung, die wie ein Schutz
# aussieht und keiner ist, ist schlimmer als gar keine.

# Altbestaende aus der Zeit vor 1.5.0 an ihren dauerhaften Ort holen. Kopiert
# wird NICHT nach /tmp: das ist auf dem LoxBerry eine Ramdisk, und ein Archiv
# kann viele Gigabyte gross sein.
LEGACY="$BASE/webfrontend/legacy/${PDIR}_data"
mkdir -p "$LEGACY/img_archive" "$LEGACY/video_archive" "$LEGACY/timelapse" 2>/dev/null

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

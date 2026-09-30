#!/bin/bash
# Intercom - preinstall
# command <TEMPFOLDER> <NAME> <FOLDER> <VERSION> <BASEFOLDER>
#
# Neu in 2.2.13 (I1, Entscheidung 1 vom 29.09.2026), Bauform AudiConnect
# 0.9.22 (audi_bau/preinstall.sh). Der Installer ruft dieses Skript bei JEDEM
# Einbau auf, nach dem Aufraeumen der alten Fassung und VOR dem Kopieren von
# Konfiguration, Cron-Dateien und Oberflaeche (sbin/plugininstall.pl:
# preupgrade :846, purge :874, preinstall :877 -
# Geraet/2026-09-28/08_plugininstall.pl).
#
# Eine Aktualisierung erkennt es allein an der Marke
# data/plugins/<ordner>.upgrade_laeuft, die preupgrade.sh als Erstes anlegt
# (kein Altersvergleich; Entscheidung 8, Frage 17). Dann tut es nichts: die
# Zweitschrift braucht postinstall.sh, die Bildlinks sollen das Update
# ueberstehen.
#
# Ohne Marke ist es eine NEUINSTALLATION. Liegengebliebene Zweitschriften
# (config/plugins/<ordner>.backup.json und der alte Name .backup.data.json),
# eine beiseitegelegte unlesbare Konfiguration (.data.json.kaputt) und die
# befristeten Bildlinks samt Bildkopien (data/plugins/<ordner>.bildlinks.json
# und .bildlinks/) einer frueheren Installation gehen nach <name>.alt,
# gemeldet mit genau einer <WARNING>. Bis 2.2.12 spielte postinstall.sh sie
# ungefragt zurueck - Zugriffstoken, Stationsadressen und das Kennwort der
# Tuerstation einer frueheren Anlage, gemeldet als "wiederhergestellt", und
# die Bibliothek heilte sogar schon vor postinstall.sh daraus (in WSL
# gemessen, Installer-Pruefer Faelle N2, N2b, N4). Die Bibliothek liest .alt
# nie; die Deinstallation raeumt es ab.
#
# Das Bild- und Videoarchiv (webfrontend/legacy/<ordner>_data) bleibt wie
# zugesagt liegen: das sind die Aufnahmen des Anwenders. Die Bereinigung
# loescht darin erst, wenn eine Konfiguration mit Inhalt steht (I3).
ARGV3=$3
ARGV5=$5
PFOLDER="${ARGV3:-intercom}"
BASE="${ARGV5:-$LBHOMEDIR}"
# Wurzelsuche wie in den uebrigen Hakenskripten: ohne config/plugins,
# data/plugins UND config/system/general.json wird nichts angefasst.
if [ -z "$BASE" ] || [ ! -d "$BASE/config/plugins" ] || [ ! -d "$BASE/data/plugins" ] \
   || [ ! -f "$BASE/config/system/general.json" ]; then
    echo "<WARNING> Kein LoxBerry-Wurzelverzeichnis erkannt ('$BASE') - nichts beiseitegelegt."
    exit 0
fi
case "$PFOLDER" in
    ''|*/*|*..*) echo "<WARNING> Unzulaessiger Ordnername '$PFOLDER' - nichts beiseitegelegt."; exit 0 ;;
esac
[ -f "$BASE/data/plugins/$PFOLDER.upgrade_laeuft" ] && exit 0

BEISEITE=""
FEST=""
for ZIEL in "$BASE/config/plugins/$PFOLDER.backup.json" \
            "$BASE/config/plugins/$PFOLDER.backup.data.json" \
            "$BASE/config/plugins/$PFOLDER.data.json.kaputt" \
            "$BASE/data/plugins/$PFOLDER.bildlinks.json" \
            "$BASE/data/plugins/$PFOLDER.bildlinks"; do
    if [ -e "$ZIEL" ] || [ -L "$ZIEL" ]; then
        rm -rf "${ZIEL:?}.alt" 2>/dev/null
        if mv -f "$ZIEL" "$ZIEL.alt" 2>/dev/null; then
            BEISEITE="$BEISEITE $ZIEL.alt"
        else
            FEST="$FEST $ZIEL"
        fi
    fi
done
# Die beiseitegelegten Staende tragen Zugriffstoken, Stationsadressen, das
# Kennwort der Tuerstation und Bilder von der Haustuer: Rechte eng.
for A in "$BASE/config/plugins/$PFOLDER.backup.json.alt" \
         "$BASE/config/plugins/$PFOLDER.backup.data.json.alt" \
         "$BASE/config/plugins/$PFOLDER.data.json.kaputt.alt" \
         "$BASE/data/plugins/$PFOLDER.bildlinks.json.alt"; do
    [ -f "$A" ] && [ ! -L "$A" ] && chmod 600 "$A" 2>/dev/null
done
D="$BASE/data/plugins/$PFOLDER.bildlinks.alt"
if [ -d "$D" ] && [ ! -L "$D" ]; then
    chmod 700 "$D" 2>/dev/null
    for A in "$D"/*; do
        [ -f "$A" ] && [ ! -L "$A" ] && chmod 600 "$A" 2>/dev/null
    done
fi
if [ -n "$BEISEITE" ] || [ -n "$FEST" ]; then
    T="<WARNING> Neuinstallation: Einstellungen, Zugangsdaten und Bildlinks einer frueheren Installation werden NICHT eingespielt."
    [ -n "$BEISEITE" ] && T="$T Beiseitegelegt:$BEISEITE (die Deinstallation raeumt sie ab)."
    [ -n "$FEST" ] && T="$T Nicht zu verschieben, bitte von Hand entfernen:$FEST"
    echo "$T"
fi
exit 0

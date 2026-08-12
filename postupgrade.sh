#!/bin/bash
# Intercom - postupgrade (laeuft als Benutzer loxberry)

ARGV1=$1
ARGV3=$3
ARGV5=$5

BASE="${ARGV5:-$LBHOMEDIR}"
PDIR="${ARGV3:-intercom}"
SICHER="$BASE/data/plugins/$PDIR/upgrade_sicherung"

mkdir -p "$BASE/config/plugins/$PDIR" 2>/dev/null

# Wer von 1.5.0 oder frueher kommt, hat die Sicherung noch in der Ramdisk.
if [ ! -d "$SICHER/config" ]; then
    for k in "/tmp/${ARGV1}_upgrade" "/tmp/uploads/${ARGV1}_upgrade"; do
        if [ -d "$k/config" ]; then
            SICHER="$k"
            echo "<INFO> Sicherung am alten Ort gefunden ($k)."
            break
        fi
    done
fi

# Existenz PRUEFEN, bevor kopiert wird: das alte
#   cp -p -v -r /tmp/..._upgrade/config/$ARGV3/* ...
# brach mit "No such file or directory" ab, wenn es die Quelle nicht gab.
if [ -d "$SICHER/config" ] && [ -n "$(ls -A "$SICHER/config" 2>/dev/null)" ]; then
    cp -a "$SICHER/config/." "$BASE/config/plugins/$PDIR/" 2>/dev/null
    chmod 0600 "$BASE/config/plugins/$PDIR/data.json" 2>/dev/null
    echo "<OK> Konfiguration zurueckgestellt."
else
    # KEIN blinder Alarm mehr.
    #
    # Der Installer loescht beim Upgrade nicht nur config/plugins/<ordner>,
    # sondern AUCH data/plugins/<ordner> - und damit die Sicherung, die
    # preupgrade.sh eine Minute zuvor genau dorthin geschrieben hat
    # ("Removing old installation" -> "Deleting plugin folders", belegt im
    # Installationsprotokoll vom 12.08.2026, 01:08:23). Diese Kette KANN
    # hier also gar nichts finden; sie ist konstruktionsbedingt tot.
    #
    # Gerettet wird die Konfiguration statt dessen von postinstall.sh aus
    # der Zweitschrift NEBEN dem Ordner, und postinstall laeuft VOR
    # postupgrade. Bevor hier gewarnt wird, wird deshalb nachgesehen, wie es
    # wirklich steht - eine Warnung, die bei heiler Konfiguration erscheint,
    # erschreckt den Anwender ohne Grund und verdeckt beim naechsten Mal die
    # echte.
    CF="$BASE/config/plugins/$PDIR/data.json"
    # Erst pruefen, DANN lesen: sonst meldet die Shell selbst
    # "cannot open", und im Protokoll steht ein Fehler, wo keiner ist.
    INHALT=""
    [ -f "$CF" ] && INHALT=$(tr -d ' \t\n\r' < "$CF" 2>/dev/null)
    if [ -s "$CF" ] && [ "$INHALT" != "{}" ] && [ -n "$INHALT" ]; then
        echo "<OK> Die Einstellungen sind vorhanden (aus der Zweitschrift)."
    else
        echo "<WARNING> Keine gesicherte Konfiguration gefunden."
        echo "<WARNING> IP der Tuerstation und Webhooks muessen neu eingetragen werden."
        echo "<WARNING> Die Zweitschrift liegt unter:"
        echo "<WARNING>   $BASE/config/plugins/$PDIR.backup.data.json"
    fi
fi

chown -R loxberry:loxberry "$BASE/config/plugins/$PDIR" 2>/dev/null

rm -rf "$BASE/data/plugins/$PDIR/upgrade_sicherung" 2>/dev/null
rm -rf "/tmp/${ARGV1}_upgrade" "/tmp/uploads/${ARGV1}_upgrade" 2>/dev/null

echo "<OK> Update abgeschlossen."
echo "<INFO> WICHTIG: Seit 1.6.0 verlangen alle Endpunkte ein Zugriffstoken."
echo "<INFO> Bitte die Plugin-Oberflaeche einmal oeffnen - dort steht das Token"
echo "<INFO> und die fertigen Adressen fuer die Virtuellen Ausgaenge."
exit 0

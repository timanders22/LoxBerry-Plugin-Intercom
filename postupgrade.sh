#!/bin/bash
# Intercom - postupgrade (laeuft als Benutzer loxberry)
#
# postinstall.sh laeuft VOR diesem Skript und hat die Einstellungen dort
# bereits aus der Zweitschrift zurueckgeholt, falls sie verloren waren. Hier
# wird deshalb nur noch nachgesehen, WIE es steht - und Reste weggeraeumt.
#
# Eine Warnung, die bei heiler Konfiguration erscheint, erschreckt den
# Anwender ohne Grund und verdeckt beim naechsten Mal die echte.

ARGV1=$1
ARGV3=$3
ARGV5=$5

BASE="${ARGV5:-$LBHOMEDIR}"
PDIR="${ARGV3:-intercom}"
CF="$BASE/config/plugins/$PDIR/data.json"

mkdir -p "$BASE/config/plugins/$PDIR" 2>/dev/null

# Erst pruefen, DANN lesen: sonst meldet die Shell selbst "cannot open", und
# im Protokoll steht ein Fehler, wo keiner ist.
INHALT=""
[ -f "$CF" ] && INHALT=$(tr -d ' \t\n\r' < "$CF" 2>/dev/null)

if [ -s "$CF" ] && [ "$INHALT" != "{}" ] && [ -n "$INHALT" ]; then
    echo "<OK> Die Einstellungen sind vorhanden."
else
    echo "<WARNING> Es steht keine Konfiguration da."
    echo "<WARNING> Adressen der Tuerstationen und Webhooks muessen neu"
    echo "<WARNING> eingetragen werden. Falls vorhanden, liegt eine"
    echo "<WARNING> Zweitschrift unter:"
    echo "<WARNING>   $BASE/config/plugins/$PDIR.backup.json"
fi

chown -R loxberry:loxberry "$BASE/config/plugins/$PDIR" 2>/dev/null
chmod 0600 "$CF" 2>/dev/null

# Reste aelterer Fassungen wegraeumen: die Sicherung unter data/ hat der
# Installer ohnehin schon geloescht, die unter /tmp ueberlebt keinen Neustart.
rm -rf "$BASE/data/plugins/$PDIR/upgrade_sicherung" 2>/dev/null
rm -rf "/tmp/${ARGV1}_upgrade" "/tmp/uploads/${ARGV1}_upgrade" 2>/dev/null

# Das letzte Bild lag bis 2.1.13 ausschliesslich im unangemeldeten Bereich.
# Es bleibt dort liegen, solange der Anwender die offene Kopie nicht abschaltet
# - deshalb wird hier nichts entfernt, sondern nur darauf hingewiesen.
echo "<OK> Update abgeschlossen."
echo "<INFO> NEU in 2.2.0: der Reiter Test enthaelt eine Selbstpruefung, der"
echo "<INFO> Reiter Einbindung in Loxone einen Knopf fuer die Importdatei, und"
echo "<INFO> das letzte Bild laesst sich auf Wunsch nur noch mit Token"
echo "<INFO> herausgeben (Reiter Einstellungen, Abschnitt Das letzte Bild)."
exit 0

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

# NEU 2.2.6: siehe preupgrade.sh - ohne Wurzel wird hier nichts getan.
if [ -z "$BASE" ] || [ ! -d "$BASE" ]; then
    echo "<ERROR> Das LoxBerry-Wurzelverzeichnis ist nicht bestimmbar (LBHOMEDIR leer)."
    exit 1
fi

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
# Installer ohnehin schon geloescht.
rm -rf "$BASE/data/plugins/$PDIR/upgrade_sicherung" 2>/dev/null
# ENTFERNT 04.09.2026 (2.2.6): hier stand
#     rm -rf "/tmp/${ARGV1}_upgrade" "/tmp/uploads/${ARGV1}_upgrade"
# Kein Skript dieses Plugins schreibt nach /tmp/*_upgrade - preupgrade.sh
# sagt ausdruecklich, dass nach /tmp nichts kopiert wird. Der Befehl war
# tot, und bei leerem $ARGV1 haette er auf /tmp/_upgrade gezeigt. Ein
# rm -rf mit einer ungeprueften Variablen im Pfad bleibt nicht stehen,
# auch wenn es hier folgenlos war.

# Das letzte Bild lag bis 2.1.13 ausschliesslich im unangemeldeten Bereich.
# Es bleibt dort liegen, solange der Anwender die offene Kopie nicht abschaltet
# - deshalb wird hier nichts entfernt, sondern nur darauf hingewiesen.
#
# BERICHTIGT 2.2.6: der angekuendigte Hinweis fehlte in der Ausgabe. Er
# steht jetzt darunter, zusammen mit dem neuen Archivschutz.
echo "<OK> Update abgeschlossen."
echo "<INFO> NEU in 2.2.0: der Reiter Test enthaelt eine Selbstpruefung, der"
echo "<INFO> Reiter Einbindung in Loxone einen Knopf fuer die Importdatei, und"
echo "<INFO> das letzte Bild laesst sich auf Wunsch nur noch mit Token"
echo "<INFO> herausgeben (Reiter Einstellungen, Abschnitt Das letzte Bild)."
echo "<INFO> BERICHTIGT in 2.2.9: hier stand bis 2.2.8 'die offene Kopie des"
echo "<INFO>   letzten Bildes bleibt liegen - dieses Update entfernt sie nicht'."
echo "<INFO>   Das war falsch. Der Installer entfernt BEIDE Kopien: das"
echo "<INFO>   Datenverzeichnis wird bei jedem Upgrade abgeraeumt, und den"
echo "<INFO>   html-Baum loescht er vor dem Kopieren vollstaendig."
echo "<INFO>   Seit 2.2.9 faellt der Bildabruf deshalb auf das juengste Bild"
echo "<INFO>   im Archiv zurueck - die Adresse des letzten Bildes bleibt also"
echo "<INFO>   auch unmittelbar nach einem Update bedient."
echo "<INFO> NEU in 2.2.6: das Bild- und Videoarchiv unter"
echo "<INFO>   $BASE/webfrontend/legacy/${PDIR}_data"
echo "<INFO>   ist ohne Anmeldung aus dem Netz erreichbar - der Webserver"
echo "<INFO>   liefert /legacy/ ohne Anmeldung und mit Verzeichnisauflistung"
echo "<INFO>   aus. Der neue Haken 'Archiv nur mit Anmeldung' im Reiter"
echo "<INFO>   Einstellungen legt eine .htaccess davor. Er ist AB WERK AUS,"
echo "<INFO>   weil sich nicht ohne Geraet messen laesst, ob die Galerien"
echo "<INFO>   danach noch Bilder zeigen. Der Reiter Test nennt den Zustand."
exit 0

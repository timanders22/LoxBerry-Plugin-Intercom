#!/bin/bash
# Intercom - preupgrade (laeuft als Benutzer loxberry)

ARGV1=$1   # Temporaerer Ordner waehrend der Installation
ARGV3=$3   # Installationsordner des Plugins
ARGV5=$5   # Wurzelverzeichnis des LoxBerry

BASE="${ARGV5:-$LBHOMEDIR}"
PDIR="${ARGV3:-intercom}"

# NEU 2.2.6: ohne Wurzel arbeitet dieses Skript auf /config/plugins/... und
# scheitert lautlos, weil alles nach /dev/null geht.
if [ -z "$BASE" ] || [ ! -d "$BASE" ]; then
    echo "<ERROR> Das LoxBerry-Wurzelverzeichnis ist nicht bestimmbar (LBHOMEDIR leer)."
    echo "<ERROR> Es wurde nichts gesichert."
    exit 1
fi

# ==== Zuerst die Marke "Aktualisierung laeuft" ====
#
# Zwischen den neuen Dateien und postinstall.sh liegt rund eine Minute
# (Regeln/06). data.json ist in der Zeit die leere Vorgabe aus dem Archiv.
# Bis 2.2.10 erzeugte die Plugin-Seite, in dieser Zeit geoeffnet, ein neues
# Token und ueberschrieb damit data.json UND die Zweitschrift; postinstall.sh
# fand danach "Einstellungen vorhanden" (in WSL nachgestellt,
# Pruefung-Upgradeluecke-2026-09-17, Fall F). Solange die Marke gilt,
# speichert die Seite nichts, und Zeitraffer und Bereinigung setzen aus.
# postinstall.sh entfernt sie. Sie liegt NEBEN dem Datenordner, weil
# purge_installation den Ordner selbst loescht. Aelter als eine Stunde gilt
# sie nicht mehr.
MARKE="$BASE/data/plugins/$PDIR.upgrade_laeuft"
mkdir -p "$BASE/data/plugins" 2>/dev/null
date +%s > "$MARKE" 2>/dev/null
if grep -Eq '^[0-9]+$' "$MARKE" 2>/dev/null; then
    echo "<OK> Bis zum Ende der Installation speichert die Plugin-Seite nichts."
else
    echo "<WARNING> Die Marke fuer die laufende Aktualisierung liess sich nicht anlegen: $MARKE"
    echo "<WARNING> Bitte die Plugin-Seite erst nach dem Ende der Installation oeffnen."
fi

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

# ==== Erneuert wird nach INHALT, nicht nach Form ====
#
# Bis 2.2.11 stand hier [ -s "$CF" ] - "Datei nicht leer". "{}" sind zwei
# Bytes und damit nicht leer; genau so sieht data.json aus, wenn ein
# frueheres Update vor der Rueckholung abgebrochen ist (die Vorgabe aus dem
# Archiv) und seither niemand die Plugin-Seite geoeffnet hat. Das naechste
# Update ueberschrieb damit eine Zweitschrift mit Stationen und Token - der
# einzige Rueckweg war weg, ohne eine Zeile im Protokoll. In WSL gemessen
# (Pruefung-Intercom-2.2.11, messe_preupgrade_leer.sh): "Zweitschrift
# danach: {}", in 2.2.10 und 2.2.11 gleich. Regeln/05 nennt [ -s ] in
# preupgrade ausdruecklich zu schwach.
#
# Gefragt wird deshalb dasselbe wie in ic_config_speichern() und in
# postinstall.sh: traegt die Datei ein Zugriffstoken ODER eine Tuerstation?
# Rueckgabe 0 = ja, 1 = nein, 2 = nicht pruefbar (kein php, unlesbares
# JSON). Im Zweifel bleibt die vorhandene Zweitschrift unangetastet.
cf_mit_inhalt() {
    [ -s "$CF" ] || return 1
    php -r '$d = json_decode((string) @file_get_contents($argv[1]), true);
        if (!is_array($d)) { exit(1); }
        $t = isset($d["aktionstoken"]) && is_string($d["aktionstoken"]) && $d["aktionstoken"] !== "";
        $s = (isset($d["stationen"]) && is_array($d["stationen"]) && count($d["stationen"]) > 0)
          || (isset($d["intercomip"]) && is_string($d["intercomip"]) && trim($d["intercomip"]) !== "");
        exit(($t || $s) ? 0 : 1);' "$CF" 2>/dev/null
    RC=$?
    [ "$RC" = 0 ] || [ "$RC" = 1 ] || return 2
    return "$RC"
}

cf_mit_inhalt
case "$?" in
0)
    if cp -p "$CF" "$ZWEIT" 2>/dev/null; then
        chmod 0600 "$ZWEIT" 2>/dev/null
        echo "<OK> Zweitschrift der Einstellungen angelegt: $ZWEIT"
    else
        echo "<WARNING> Die Zweitschrift liess sich nicht anlegen: $ZWEIT"
        echo "<WARNING> Bitte die Einstellungen nach dem Update pruefen."
    fi
    ;;
1)
    if [ -s "$ZWEIT" ]; then
        echo "<WARNING> data.json traegt weder Zugriffstoken noch Tuerstation."
        echo "<WARNING> Die vorhandene Zweitschrift bleibt deshalb unveraendert:"
        echo "<WARNING>   $ZWEIT"
        echo "<WARNING> Aus ihr werden die Einstellungen am Ende der"
        echo "<WARNING> Installation zurueckgeholt."
    else
        echo "<INFO> Keine Konfiguration vorhanden - offenbar eine Erstinstallation."
    fi
    ;;
*)
    echo "<WARNING> Der Inhalt von $CF liess sich nicht pruefen (fehlt php?)."
    echo "<WARNING> Die Zweitschrift bleibt unveraendert. Bitte die"
    echo "<WARNING> Einstellungen nach dem Update ansehen."
    ;;
esac

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

# BERICHTIGT 04.09.2026 (2.2.6), zwei Sachen an derselben Stelle:
#
# 1. Es hiess "Verschiebe" und war ein cp - die Quelle blieb liegen. Damit
#    stand das Archiv waehrend des Updates ZWEIMAL auf derselben Karte, und
#    dieselbe Datei begruendet elf Zeilen weiter oben, dass ein Archiv viele
#    Gigabyte gross sein kann. Jetzt mv -n: gleiches Dateisystem, kein
#    zweites Mal Platz, vorhandene Dateien am Ziel bleiben unberuehrt.
# 2. Das "<OK> Altbestand uebernommen." stand UNBEDINGT hinter einem cp,
#    dessen Rueckgabewert niemand las und dessen Fehlerausgabe nach
#    /dev/null ging. Volle Karte, fehlende Rechte, abgebrochene Kopie - im
#    Protokoll stand in jedem Fall Erfolg. Genau dieses Muster verurteilt
#    dieselbe Datei elf Zeilen weiter oben.
for paar in "archive:img_archive" "videoarchive:video_archive"; do
    quelle="$BASE/webfrontend/html/plugins/$PDIR/${paar%%:*}"
    ziel="$LEGACY/${paar##*:}"
    if [ -d "$quelle" ] && [ -n "$(ls -A "$quelle" 2>/dev/null)" ]; then
        echo "<INFO> Verschiebe Altbestand aus $quelle"
        if mv -n "$quelle"/* "$ziel"/ 2>/dev/null; then
            echo "<OK> Altbestand uebernommen."
        else
            echo "<WARNING> Der Altbestand liess sich nicht uebernehmen:"
            echo "<WARNING>   $quelle"
            echo "<WARNING> Er bleibt liegen. Von Hand:"
            echo "<WARNING>   mv -n '$quelle'/* '$ziel'/"
        fi
    fi
done

exit 0

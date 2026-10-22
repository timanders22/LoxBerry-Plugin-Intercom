#!/bin/bash
# Intercom - preupgrade (laeuft als Benutzer loxberry)

# ---- Wurzelblock (I1, seit 2.2.23; in allen Hakenskripten und uninstall woertlich gleich) ----
# Ordner aus $3 mit Schranke ('' . .. und alles mit / oder ..); Wurzel aus $5, sonst LBHOMEDIR, sonst vom Ablageort
# aufwaerts gesucht (Regeln/06 "Ein Systempfad wird nicht fest verdrahtet"). Eine Wurzel gilt nur mit
# config/plugins, data/plugins UND config/system/general.json - ein Rest alter Prueflaeufe hat die dritte nie.
# ic_wurzel setzt PDIR und BASE; Rueckgabe 0 = erkannt, 1 = Ordnername unzulaessig, 2 = keine LoxBerry-Wurzel.
# Bis 2.2.22 prueften preupgrade/postupgrade nur -z/-d, postinstall gar nicht (ohne $5 entstanden Ordner unter /,
# Pruefbericht I1), und keines suchte die Wurzel.
lb_wurzel_suchen() {
    v=$(cd "$(dirname "$(readlink -f "$0")")" 2>/dev/null && pwd)
    i=0
    while [ -n "$v" ] && [ "$v" != "/" ] && [ $i -lt 8 ]; do
        if [ -d "$v/config/plugins" ] && [ -d "$v/data/plugins" ] \
           && [ -f "$v/config/system/general.json" ]; then
            echo "$v"; return 0
        fi
        v=$(dirname "$v"); i=$((i + 1))
    done
    return 1
}
ic_wurzel() {
    PDIR="${1:-intercom}"
    case "$PDIR" in
        ''|.|..|*/*|*..*) return 1 ;;
    esac
    BASE="${2:-$LBHOMEDIR}"
    [ -n "$BASE" ] && [ -d "$BASE" ] || BASE=$(lb_wurzel_suchen)
    if [ -z "$BASE" ] || [ ! -d "$BASE/config/plugins" ] || [ ! -d "$BASE/data/plugins" ] \
       || [ ! -f "$BASE/config/system/general.json" ]; then
        return 2
    fi
    return 0
}
# ---- Ende Wurzelblock ----
# Seit 2.2.6 ohne Wurzel <ERROR>; seit 2.2.23 (I1) mit dem Wurzelblock oben (Ordner-Schranke, general.json,
# Wurzelsuche) - bis 2.2.22 nur -z/-d.
ic_wurzel "$3" "$5"
case $? in
    1) echo "<ERROR> Unzulaessiger Ordnername '$3' - es wurde nichts gesichert."; exit 1 ;;
    2) echo "<ERROR> Das LoxBerry-Wurzelverzeichnis ist nicht bestimmbar ('${5:-$LBHOMEDIR}')."
       echo "<ERROR> Es wurde nichts gesichert."; exit 1 ;;
esac

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
# purge_installation den Ordner selbst loescht.
#
# BERICHTIGT 2.2.13 (I8): hier stand "Aelter als eine Stunde gilt sie nicht
# mehr". Seit 2.2.13 gilt die Stunde nur noch fuer die Sperre der
# Oberflaeche; fuer Heilung, Bereinigung, Zeitraffer, preinstall.sh und
# postinstall.sh gilt die Marke ohne Altersgrenze (Entscheidung 1 vom
# 29.09.2026, Nr. 8 Frage 17). Wer frisch anfangen will, deinstalliert vorher.
MARKE="$BASE/data/plugins/$PDIR.upgrade_laeuft"
mkdir -p "$BASE/data/plugins" 2>/dev/null
date +%s > "$MARKE" 2>/dev/null
if grep -Eq '^[0-9]+$' "$MARKE" 2>/dev/null; then
    echo "<OK> Bis zum Ende der Installation speichert die Plugin-Seite nichts."
else
    echo "<WARNING> Die Marke fuer die laufende Aktualisierung liess sich nicht anlegen: $MARKE"
    echo "<WARNING> Bitte die Plugin-Seite erst nach dem Ende der Installation oeffnen."
fi

# ==== Befehlsbruecke anhalten (NEU 2.2.23, Entwurf 3.6) ====
# Nach der Marke: die Bruecke startet waehrend der Aktualisierung nicht neu (der Waechter achtet auf die Marke).
# Beim Umstieg von 2.2.22 gibt es sie noch nicht - dann nichts. Laufende Auftragsprozesse enden von selbst.
BBSH="$BASE/bin/plugins/$PDIR/befehlsbruecke.sh"
if [ -f "$BBSH" ]; then
    echo "<INFO> Befehlsbruecke: $(timeout 40 bash "$BBSH" stop 2>&1 | head -n 1)"
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
mit_inhalt() {
    [ -s "$1" ] || return 1
    php -r '$d = json_decode((string) @file_get_contents($argv[1]), true);
        if (!is_array($d)) { exit(1); }
        $t = isset($d["aktionstoken"]) && is_string($d["aktionstoken"]) && $d["aktionstoken"] !== "";
        $s = (isset($d["stationen"]) && is_array($d["stationen"]) && count($d["stationen"]) > 0)
          || (isset($d["intercomip"]) && is_string($d["intercomip"]) && trim($d["intercomip"]) !== "");
        exit(($t || $s) ? 0 : 1);' "$1" 2>/dev/null
    RC=$?
    [ "$RC" = 0 ] || [ "$RC" = 1 ] || return 2
    return "$RC"
}
cf_mit_inhalt() { mit_inhalt "$CF"; }

# NEU 2.2.13 (I6): ist data.json ueberhaupt lesbares JSON? 0 = ja, 1 = nein.
cf_lesbar() {
    php -r 'exit(is_array(json_decode((string) @file_get_contents($argv[1]), true)) ? 0 : 1);' "$CF" 2>/dev/null
}

cf_mit_inhalt
case "$?" in
0)
    # BERICHTIGT 2.2.13 (I2): die neue Zweitschrift entsteht unter .neu, wird
    # gegen data.json verglichen und auf Inhalt geprueft und erst dann
    # umbenannt. Bis 2.2.12 stand hier cp -p direkt ueber die einzige
    # Zweitschrift: bei voller Karte blieb eine abgeschnittene Datei
    # (4096 Byte, kein JSON) stehen, die alte war zerstoert, und postinstall.sh
    # spielte sie als "wiederhergestellt" ein (Installer-Pruefer, V1).
    # Misslingt es, bleibt die bisherige Zweitschrift unangetastet.
    NEU="$ZWEIT.neu"
    rm -f "$NEU" 2>/dev/null
    if ( umask 077; cp "$CF" "$NEU" ) 2>/dev/null && cmp -s "$CF" "$NEU" \
       && mit_inhalt "$NEU" && mv -f "$NEU" "$ZWEIT" 2>/dev/null; then
        chmod 0600 "$ZWEIT" 2>/dev/null
        echo "<OK> Zweitschrift der Einstellungen angelegt: $ZWEIT"
    else
        rm -f "$NEU" 2>/dev/null
        echo "<WARNING> Die Zweitschrift liess sich nicht anlegen: $ZWEIT"
        if [ -s "$ZWEIT" ]; then
            chmod 0600 "$ZWEIT" 2>/dev/null
            echo "<WARNING> Die bisherige Zweitschrift bleibt unveraendert stehen."
        fi
        echo "<WARNING> Bitte die Einstellungen nach dem Update pruefen."
    fi
    ;;
1)
    # NEU 2.2.13 (I6): eine abgeschnittene oder sonst unlesbare data.json wird
    # NEBEN den Ordner gesichert (0600), bevor der Installer sie ueberschreibt.
    # Bis 2.2.12 hiess es hier "offenbar eine Erstinstallation" - in einem
    # Upgrade -, und das lesbare Token war danach spurlos weg (U3b).
    KAPUTT="$BASE/config/plugins/$PDIR.data.json.kaputt"
    UNLESBAR=0
    if [ -s "$CF" ] && ! cf_lesbar; then
        UNLESBAR=1
        rm -f "$KAPUTT" 2>/dev/null
        if ( umask 077; cp "$CF" "$KAPUTT" ) 2>/dev/null && cmp -s "$CF" "$KAPUTT"; then
            chmod 0600 "$KAPUTT" 2>/dev/null
            echo "<WARNING> Upgrade, Konfiguration unlesbar: data.json ist kein lesbares JSON."
            echo "<WARNING> Der Inhalt ist gesichert unter $KAPUTT (0600)."
        else
            echo "<WARNING> Upgrade, Konfiguration unlesbar: data.json ist kein lesbares JSON,"
            echo "<WARNING> und die Sicherung nach $KAPUTT ist gescheitert."
        fi
    fi
    if [ -s "$ZWEIT" ]; then
        chmod 0600 "$ZWEIT" 2>/dev/null
        echo "<WARNING> data.json traegt weder Zugriffstoken noch Tuerstation."
        echo "<WARNING> Die vorhandene Zweitschrift bleibt deshalb unveraendert:"
        echo "<WARNING>   $ZWEIT"
        echo "<WARNING> Aus ihr werden die Einstellungen am Ende der"
        echo "<WARNING> Installation zurueckgeholt."
    elif [ "$UNLESBAR" = 0 ]; then
        echo "<INFO> Die Einstellungen sind leer (das Plugin wurde nie eingerichtet) - es gibt nichts zu sichern."
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

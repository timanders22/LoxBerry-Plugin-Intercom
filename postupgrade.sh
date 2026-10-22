#!/bin/bash
# Intercom - postupgrade (laeuft als Benutzer loxberry)
#
# postinstall.sh laeuft VOR diesem Skript und hat die Einstellungen dort
# bereits aus der Zweitschrift zurueckgeholt, falls sie verloren waren. Hier
# wird deshalb nur noch nachgesehen, WIE es steht - und Reste weggeraeumt.
#
# Eine Warnung, die bei heiler Konfiguration erscheint, erschreckt den
# Anwender ohne Grund und verdeckt beim naechsten Mal die echte.
#
# SEIT 2.2.23 (Gruppe 7):
#   I1  derselbe Wurzelblock wie in den uebrigen Hakenskripten (bis 2.2.22 nur -z/-d).
#   I2  "vorhanden" heisst: Token ODER Station (mit_inhalt(), wie postinstall.sh). Bis 2.2.22 pruefte diese Datei
#       nur die Form - fuer {"aktionstoken":""} und fuer kaputtes JSON stand hier "<OK> Die Einstellungen sind
#       vorhanden." (gemessen, Pruefbericht I2). Das chown -R (als loxberry wirkungslos bzw. stilles Scheitern) ist
#       einer Meldung ueber fremde Besitzer gewichen, und die Marke .upgrade_laeuft wird hier ein zweites Mal
#       entfernt - ein Sicherheitsnetz, falls postinstall.sh vorher abbrach.
#   E6  Uebergang MQTT: fehlt in data.json der Schluessel mqtt_enable, wird "0" eingetragen - MQTT ist seit 2.2.23 ab
#       Werk an, aber nur fuer Neuinstallationen; eine bestehende Anlage, deren Reiter MQTT nie gespeichert wurde,
#       bleibt aus. Ebenso befehle_mqtt_ein und befehle_mqtt_melden = "0" (ohnehin die Vorgabe). Atomar ueber eine
#       Nebendatei, 0600.

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
ic_wurzel "$3" "$5"
case $? in
    1) echo "<ERROR> Unzulaessiger Ordnername '$3' - es wurde nichts angefasst."; exit 1 ;;
    2) echo "<ERROR> Das LoxBerry-Wurzelverzeichnis ist nicht bestimmbar ('${5:-$LBHOMEDIR}') - es wurde nichts angefasst."; exit 1 ;;
esac

CF="$BASE/config/plugins/$PDIR/data.json"
MARKE="$BASE/data/plugins/$PDIR.upgrade_laeuft"

mkdir -p "$BASE/config/plugins/$PDIR" 2>/dev/null

# Dieselbe Frage wie in postinstall.sh und preupgrade.sh: traegt die Datei ein Zugriffstoken ODER eine Station?
mit_inhalt() {
    [ -s "$1" ] || return 1
    php -r '$d = json_decode((string) @file_get_contents($argv[1]), true);
        if (!is_array($d)) { exit(1); }
        $t = isset($d["aktionstoken"]) && is_string($d["aktionstoken"]) && $d["aktionstoken"] !== "";
        $s = (isset($d["stationen"]) && is_array($d["stationen"]) && count($d["stationen"]) > 0)
          || (isset($d["intercomip"]) && is_string($d["intercomip"]) && trim($d["intercomip"]) !== "");
        exit(($t || $s) ? 0 : 1);' "$1" 2>/dev/null
}

if mit_inhalt "$CF"; then
    echo "<OK> Die Einstellungen sind vorhanden."
elif [ -s "$CF" ]; then
    echo "<WARNING> data.json traegt weder Zugriffstoken noch Tuerstation (oder ist kein lesbares JSON)."
    echo "<WARNING> Bitte die Einstellungen pruefen. Falls vorhanden, liegt eine Zweitschrift unter:"
    echo "<WARNING>   $BASE/config/plugins/$PDIR.backup.json"
else
    echo "<WARNING> Es steht keine Konfiguration da."
    echo "<WARNING> Adressen der Tuerstationen und Webhooks muessen neu"
    echo "<WARNING> eingetragen werden. Falls vorhanden, liegt eine"
    echo "<WARNING> Zweitschrift unter:"
    echo "<WARNING>   $BASE/config/plugins/$PDIR.backup.json"
fi

# ---------- E6: MQTT bleibt bei bestehenden Anlagen, wie es war ----------
if [ -f "$CF" ] && command -v php >/dev/null 2>&1; then
    UEB=$(php -r '
        $f = $argv[1];
        $roh = (string) @file_get_contents($f);
        $d = json_decode($roh, true);
        if (!is_array($d)) { echo "unlesbar"; exit(0); }
        $neu = array();
        foreach (array("mqtt_enable", "befehle_mqtt_ein", "befehle_mqtt_melden") as $k) {
            if (!array_key_exists($k, $d)) { $d[$k] = "0"; $neu[] = $k; }
        }
        if (!$neu) { echo "nichts"; exit(0); }
        $js = json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($js === false) { echo "json"; exit(0); }
        $tmp = $f . ".neu." . getmypid();
        $alt = umask(077);
        $n = @file_put_contents($tmp, $js);
        umask($alt);
        @chmod($tmp, 0600);
        if ($n !== strlen($js) || !@rename($tmp, $f)) { @unlink($tmp); echo "schreiben"; exit(0); }
        echo "ok " . implode(",", $neu);' "$CF" 2>/dev/null)
    case "$UEB" in
        "ok "*) echo "<INFO> MQTT bleibt wie bisher: ${UEB#ok } = 0 eingetragen (ab Werk an gilt nur fuer Neuinstallationen; einschalten im Reiter MQTT)." ;;
        nichts) ;;
        unlesbar) ;;
        *) echo "<WARNING> Der Uebergang fuer MQTT liess sich nicht eintragen ($UEB) - im Reiter MQTT pruefen und speichern." ;;
    esac
fi

# I2: kein chown mehr (als loxberry wirkungslos) - gemeldet wird, was nicht loxberry gehoert.
FREMD=$(find "$BASE/config/plugins/$PDIR" ! -user loxberry 2>/dev/null | head -n 5)
if [ -n "$FREMD" ]; then
    echo "<WARNING> Diese Dateien gehoeren nicht dem Benutzer loxberry - die Plugin-Seite kann sie womoeglich nicht schreiben:"
    echo "$FREMD" | while IFS= read -r z; do echo "<WARNING>   $z"; done
fi
chmod 0600 "$CF" 2>/dev/null

# Reste aelterer Fassungen wegraeumen: die Sicherung unter data/ hat der
# Installer ohnehin schon geloescht.
rm -rf "$BASE/data/plugins/$PDIR/upgrade_sicherung" 2>/dev/null

# I2 (seit 2.2.23): die Marke ein zweites Mal entfernen - Sicherheitsnetz, falls postinstall.sh vorher abbrach;
# sonst setzten Zeitraffer und Bereinigung ohne Altersgrenze aus, und die Befehlsbruecke startete nicht.
rm -f "$MARKE" 2>/dev/null
if [ -e "$MARKE" ]; then
    echo "<WARNING> Die Marke der laufenden Aktualisierung liess sich nicht entfernen: $MARKE"
fi

echo "<OK> Update abgeschlossen."
echo "<INFO> NEU in 2.2.23: Befehle ueber MQTT (Foto, Foto ohne Meldung, Video) ueber die gemeinsame"
echo "<INFO>   Befehlsbruecke - ab Werk aus (Reiter MQTT). Die HTTP-Adressen bleiben unveraendert."
echo "<INFO>   Die Vorlage der Rueckmeldungen (Virtueller HTTP-Eingang) gibt es nicht mehr: Werte als"
echo "<INFO>   einfache Virtuelle Eingaenge, Namen im Reiter Einbindung in Loxone."
echo "<INFO> Das Bild- und Videoarchiv unter"
echo "<INFO>   $BASE/webfrontend/legacy/${PDIR}_data"
echo "<INFO>   ist ohne Anmeldung aus dem Netz erreichbar, solange der Haken 'Archiv nur mit Anmeldung'"
echo "<INFO>   (Reiter Einstellungen) aus ist. Der Reiter Test nennt den Zustand."
exit 0

#!/bin/sh

# Intercom - postroot
#
# Laeuft als ROOT und nach postinstall/postupgrade (plugininstall.pl ruft es
# ohne sudo-Praefix auf; die Reihenfolge ist Cron kopieren, postinstall,
# postupgrade, postroot).
#
# Er hat genau eine Aufgabe: die alte cron.d-Datei entfernen. (Die Befehlsbruecke
# startet NICHT hier - der Waechter in cron.01min startet sie spaetestens nach
# einer Minute, als loxberry; Entwurf 3.6, E15.)
#
# ---------------------------------------------------------------------------
# SONST LAUFEN DIE AUFTRAEGE DOPPELT.
#
# Bis 2.2.7 lieferte diese Linie ihre Auftraege als cron/crontab aus. Der
# Installateur legt daraus system/cron/cron.d/<NAME> an und entfernt sie
# ausschliesslich beim DEINSTALLIEREN (plugininstall.pl:1571, der Kommentar
# dort sagt "only on uninstall"). Seit 2.2.8 kommen dieselben Auftraege aus
# cron/cron.01min und cron/cron.daily - ohne diesen Schritt liefe der
# Zeitraffer nach dem Upgrade ZWEIMAL je Minute.
#
# WARUM NICHT IN postupgrade.sh: dort kann es nicht gelingen. Am 07.09.2026
# an der laufenden Anlage gemessen: postupgrade laeuft per
# "sudo -n -u loxberry", der Ordner system/cron/cron.d ist
# drwxrwxr-x root root, und loxberry ist nicht in der Gruppe root -
# "sudo -u loxberry touch .../cron.d/.probe" endet mit Permission denied.
#
# Der Dateiname ist der Plugin-NAME ($2), nicht der Ordner ($3).
#
# Geprueft wird die WIRKUNG, nicht der Rueckgabewert - doppelt laufende
# Cron-Auftraege bemerkt niemand von selbst.
#
# BERICHTIGT 2.2.23 (I4): fehlten $2 oder $5, wurde bis 2.2.22 ohne eine Zeile
# uebersprungen (Pruefbericht, Probe: keine Ausgabe, rc 0). Jetzt: der Name mit
# Schranke (Vorgabe intercom), die Wurzel wie in den uebrigen Hakenskripten (I1),
# und ohne Wurzel eine <WARNING> statt Schweigen.
# ---------------------------------------------------------------------------

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

if [ "$(id -u)" != "0" ]; then
	echo "<ERROR> postroot.sh muss als root laufen."
	exit 2
fi

PNAME="${2:-intercom}"
case "$PNAME" in
	''|.|..|*/*|*..*)
		echo "<WARNING> Unzulaessiger Plugin-Name '$PNAME' - die alte cron.d-Datei wurde nicht geprueft."
		exit 0 ;;
esac
[ -z "$2" ] && echo "<WARNING> Der Plugin-Name fehlt (\$2) - geprueft wird der Vorgabename intercom."
if ! ic_wurzel "$3" "$5"; then
	echo "<WARNING> Kein LoxBerry-Wurzelverzeichnis erkannt ('${5:-$LBHOMEDIR}') - die alte cron.d-Datei wurde nicht geprueft."
	exit 0
fi

ALT_CRON="$BASE/system/cron/cron.d/$PNAME"
if [ -e "$ALT_CRON" ]; then
	echo "<INFO> Entferne die alte cron.d-Datei aus der Zeit vor 2.2.8: $ALT_CRON"
	rm -f "$ALT_CRON"
	if [ -e "$ALT_CRON" ]; then
		echo "<WARNING> Die alte cron.d-Datei liess sich NICHT entfernen."
		echo "<WARNING> Bis das geschehen ist, laufen die Auftraege DOPPELT."
		echo "<WARNING> Bitte einmal von Hand ausfuehren:"
		echo "<WARNING>   sudo rm -f $ALT_CRON"
	else
		echo "<OK> Alte cron.d-Datei entfernt; die Auftraege laufen jetzt einfach."
	fi
else
	echo "<INFO> Keine alte cron.d-Datei vorhanden - nichts zu entfernen."
fi

exit 0

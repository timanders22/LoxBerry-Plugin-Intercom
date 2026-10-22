#!/bin/bash
# Intercom - Auftragsprozess der Befehlsbruecke starten (Gruppe 7 / MQTT, 2.2.23; Entwurf 3.3, E4).
#   ic_auftrag.sh foto|hand|video <station> <wert>
# Aufgerufen NUR von ic_auftrag_starten() (ic_lib.php) aus der Befehlsbruecke: /bin/bash <pfad> art nr wert, mit
# /dev/null als Ein- und Ausgabe und dem Fehlerkanal an log/plugins/<ordner>/intercom_auftrag.log.
#
# Warum ein eigener Prozess: die Bruecke ist EIN Prozess ohne Faeden; tun() laeuft im Lesezyklus, und der Waechter
# startet sie neu, wenn zustand.json aelter als 180 s ist. Ein Foto mit Klingel-Meldungen dauert im unguenstigen
# Fall ueber 60 s (Kamera, Objekterkennung, Anzeigegeraet, Signal, Ansage, vier Webhooks). Und die ffmpeg-Kette einer
# Videoaufnahme lebt bis 300 s - sie darf nichts von der Bruecke erben (das Rohr zu mosquitto_sub). Deshalb:
#   1. ZUERST alle geerbten Deskriptoren ueber 2 schliessen - der Block ist woertlich der Kopf von
#      befehlsbruecke.sh (:23-30, Stammfassung Werkzeuge/gemeinsam/).
#   2. Argumente pruefen (dieselben Formen wie die Zuordnung), nichts davon geht an eine Schale.
#   3. php ic_auftrag.php im Hintergrund starten und sofort enden - proc_close() in der Bruecke kommt in
#      Millisekunden zurueck. Die ffmpeg-Kette haengt danach am kurzlebigen Auftragsprozess, nicht an der Bruecke.
# Rueckgabe 0 = gestartet, 1 = nicht moeglich (keine LoxBerry-Lage, kein php), 2 = Aufruf falsch.
# Aufruf immer mit "bash <pfad>" (das Archiv traegt nicht ueberall das Ausfuehrungsrecht).

EIGEN=$(readlink -f "$0" 2>/dev/null)
for fd in /proc/$$/fd/*; do
    n=${fd##*/}
    case "$n" in ''|*[!0-9]*) continue ;; esac
    [ "$n" -gt 2 ] || continue
    [ "$(readlink "$fd" 2>/dev/null)" = "$EIGEN" ] && continue   # bash liest das Skript ueber diesen
    eval "exec $n>&-" 2>/dev/null
done

case "$1" in foto|hand|video) ;; *) echo "ic_auftrag.sh: Art unzulaessig (foto|hand|video)." >&2; exit 2 ;; esac
case "$2" in [1-9]|[1-9][0-9]) ;; *) echo "ic_auftrag.sh: Station unzulaessig (1-99)." >&2; exit 2 ;; esac
case "$3" in [0-9]|[0-9][0-9]|[0-9][0-9][0-9]) ;; *) echo "ic_auftrag.sh: Wert unzulaessig (0-999)." >&2; exit 2 ;; esac

SELF=$(cd "$(dirname "$0")" 2>/dev/null && pwd -P)
PNAME=$(basename "$SELF")
if [ "$(basename "$(dirname "$SELF")")" = "plugins" ] && [ "$(basename "$(dirname "$(dirname "$SELF")")")" = "bin" ]; then
    BASE=$(cd "$SELF/../../.." 2>/dev/null && pwd -P)
elif [ -n "$LBHOMEDIR" ] && [ -n "$LBPPLUGINDIR" ]; then
    BASE="$LBHOMEDIR"
    PNAME=$(basename "$LBPPLUGINDIR")
else
    echo "ic_auftrag.sh: keine LoxBerry-Installation erkannt - nichts getan." >&2
    exit 1
fi
LDIR="$BASE/log/plugins/$PNAME"
PHP=$(command -v php 2>/dev/null)
[ -n "$PHP" ] || { echo "ic_auftrag.sh: php nicht gefunden." >&2; exit 1; }
[ -f "$SELF/ic_auftrag.php" ] || { echo "ic_auftrag.sh: ic_auftrag.php fehlt neben diesem Skript." >&2; exit 1; }
mkdir -p "$LDIR" 2>/dev/null
nohup "$PHP" "$SELF/ic_auftrag.php" "$1" "$2" "$3" </dev/null >/dev/null 2>>"$LDIR/intercom_auftrag.log" &
exit 0
